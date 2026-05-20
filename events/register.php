<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — events/register.php                         ║
 * ║  Inscription d'un participant à un événement                ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Parties 2.1 + 2.2
 *
 * CE FICHIER REÇOIT (POST JSON) :
 *   {
 *     "event_id"  : 3,
 *     "name"      : "Yassine El Fassi",
 *     "email"     : "yassine@example.ma"
 *   }
 *
 * CE FICHIER DOIT :
 *   ✅  Vérifier que l'événement existe et n'est pas complet    (fourni)
 *   ✅  Vérifier que l'email n'est pas déjà inscrit             (fourni)
 *   ✅  Insérer l'inscription en BD avec un token unique
 *   ✅  Envoyer l'email de confirmation
 *   ✅  Détecter le seuil 80% et envoyer l'alerte organisateur
 *   ✅  Retourner la réponse JSON appropriée
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../mail/SendConfirmation.php';
require_once __DIR__ . '/../mail/AlertMailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.', 'message' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides.', 'message' => 'Données JSON invalides.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Validation basique (fournie) ──────────────────────────────────────────
$eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;
$name    = isset($data['name'])     ? trim((string)$data['name'])     : '';
$email   = isset($data['email'])    ? strtolower(trim((string)$data['email'])) : '';

if (!$eventId
    || $name === ''
    || strlen($name) > 150
    || strlen($email) > 255
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données manquantes ou invalides.', 'message' => 'Données manquantes ou invalides.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();
    $registrationsHaveStatus = tableColumnExists($pdo, 'registrations', 'status');
    $activeRegistrationFilter = $registrationsHaveStatus ? " AND r.status = 'active'" : '';
    $pdo->beginTransaction();

    // ── Récupérer et verrouiller l'événement pour éviter deux inscriptions
    // simultanées qui dépasseraient la capacité.
    $stmt = $pdo->prepare(
        'SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id' . $activeRegistrationFilter . ') AS registered_count
         FROM events e
         WHERE e.id = :id
         FOR UPDATE'
    );
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Événement introuvable.', 'message' => 'Événement introuvable.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Vérifier capacité (fourni) ────────────────────────────────────────
    if ((int)$event['registered_count'] >= (int)$event['capacity']) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Événement complet.',
            'message' => 'Événement complet.',
            'event_id' => $eventId,
            'registered_count' => (int)$event['registered_count'],
            'remaining_places' => 0,
            'available_places' => 0,
            'fill_rate' => 100,
            'capacity_pct' => 100,
            'is_full' => true,
            'full' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Vérifier doublon (fourni) ─────────────────────────────────────────
    $duplicateStatusFilter = $registrationsHaveStatus ? " AND status = 'active'" : '';
    $stmt = $pdo->prepare(
        'SELECT id FROM registrations WHERE event_id = :eid AND email = :email' . $duplicateStatusFilter
    );
    $stmt->execute([':eid' => $eventId, ':email' => $email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Vous êtes déjà inscrit(e) à cet événement.', 'message' => 'Vous êtes déjà inscrit(e) à cet événement.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = generateUniqueRegistrationToken($pdo);

    $insertFields = ['event_id', 'name', 'email', 'token', 'registered_at'];
    $insertValues = [':event_id', ':name', ':email', ':token', 'NOW()'];
    if ($registrationsHaveStatus) {
        $insertFields[] = 'status';
        $insertValues[] = "'active'";
    }

    $stmt = $pdo->prepare(
        'INSERT INTO registrations (' . implode(', ', $insertFields) . ')
         VALUES (' . implode(', ', $insertValues) . ')'
    );
    $stmt->execute([
        ':event_id' => $eventId,
        ':name'     => $name,
        ':email'    => $email,
        ':token'    => $token,
    ]);

    $registrationId = (int)$pdo->lastInsertId();
    $newCount = (int)$event['registered_count'] + 1;
    $capacity = (int)$event['capacity'];
    $pct = (int)round(($newCount / $capacity) * 100);
    $availablePlaces = max(0, $capacity - $newCount);
    $isFull = $availablePlaces === 0;

    $eventForMail = $event;
    $eventForMail['registered_count'] = $newCount;
    $eventForMail['available_places'] = $availablePlaces;
    $eventForMail['fill_pct'] = $pct;
    $eventForMail['registration_id'] = $registrationId;

    $pdo->commit();

    $confirmationSent = SendConfirmation::send(
        $pdo,
        $eventForMail,
        $name,
        $email,
        $token,
        $registrationId
    );

    $alertSent = false;
    if ($pct >= 80) {
        $alertSent = AlertMailer::sendCapacityAlert($pdo, $eventForMail);
    }

    echo json_encode([
        'success'          => true,
        'message'          => 'Inscription réussie',
        'event_id'         => $eventId,
        'registration_id'  => $registrationId,
        'token'            => $token,
        'registered_count' => $newCount,
        'remaining_places' => $availablePlaces,
        'available_places' => $availablePlaces,
        'fill_rate'        => $pct,
        'capacity_pct'     => $pct,
        'is_full'          => $isFull,
        'confirmation_sent'=> $confirmationSent,
        'alert_sent'       => $alertSent,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[EventHub] register.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.', 'message' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[EventHub] register.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.', 'message' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
}

/**
 * Verifie l'existence d'une colonne optionnelle sans supposer la version du schema.
 */
function tableColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute([
        ':schema' => DB_NAME,
        ':table' => $table,
        ':column' => $column,
    ]);

return (int)$stmt->fetchColumn() > 0;
}

/**
 * Genere un token de desinscription unique.
 */
function generateUniqueRegistrationToken(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('SELECT id FROM registrations WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);

        if (!$stmt->fetch()) {
            return $token;
        }
    }

    throw new RuntimeException('Impossible de générer un token unique.');
}
