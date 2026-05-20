<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — events/create.php                           ║
 * ║  Création d'un événement                                    ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 1.2
 *
 * Ce fichier reçoit les données du formulaire (POST JSON)
 * et les insère en base de données.
 *
 * CORRIGÉ :
 *   ✅  Plus d'injection SQL directe dans createEvent()
 *   ✅  Validation des données entrantes
 *   ✅  Retour du vrai ID créé
 *   ✅  Gestion d'exception PDO
 *
 * IMPLÉMENTÉ :
 *   ✅  Corriger createEvent() avec requêtes préparées
 *   ✅  Valider et assainir les données reçues
 *   ✅  Retourner une vraie réponse JSON success/error
 *   ✅  Brancher l'appel fetch() depuis assets/js/app.js
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

// ── Point d'entrée ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

// Lecture du body JSON
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides.']);
    exit;
}

try {
    $pdo    = getDB();
    $result = createEvent($pdo, $data);

    echo json_encode([
        'success'  => true,
        'event_id' => $result,
        'message'  => 'Événement créé avec succès.'
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[EventHub] create.php PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur lors de la création.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[EventHub] create.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
}

// ═════════════════════════════════════════════════════════════════════════
// FONCTION PRINCIPALE — VERSION CORRIGÉE (Partie 1.2)
// ═════════════════════════════════════════════════════════════════════════

/**
 * Insère un nouvel événement en base de données.
 *
 * Les corrections PDO principales sont justifiées dans les commentaires inline.
 *
 * @param  PDO   $pdo
 * @param  array $data  Données issues du formulaire
 * @return int          ID de l'événement créé
 */
function createEvent(PDO $pdo, array $data): int
{
    $event = validateEventPayload($pdo, $data);

    // Correction 1 : requête préparée, aucune donnée utilisateur n'est concaténée dans le SQL.
    $stmt = $pdo->prepare(
        'INSERT INTO events (
            title, description, event_date, location, capacity,
            category, organizer_email, organizer_id, created_at
         ) VALUES (
            :title, :description, :event_date, :location, :capacity,
            :category, :organizer_email, :organizer_id, NOW()
         )'
    );

    // Correction 2 : execute() lie les valeurs typées et laisse PDO échapper correctement.
    $stmt->execute([
        ':title'           => $event['title'],
        ':description'     => $event['description'],
        ':event_date'      => $event['event_date'],
        ':location'        => $event['location'],
        ':capacity'        => $event['capacity'],
        ':category'        => $event['category'],
        ':organizer_email' => $event['organizer_email'],
        ':organizer_id'    => $event['organizer_id'],
    ]);

    // Correction 3 : lastInsertId() prouve l'insertion et permet au front de connaître le nouvel événement.
    return (int)$pdo->lastInsertId();
}

/**
 * Valide et normalise les données reçues avant insertion.
 *
 * @param PDO   $pdo
 * @param array $data
 * @return array
 */
function validateEventPayload(PDO $pdo, array $data): array
{
    $title          = trim((string)($data['title'] ?? ''));
    $description    = trim((string)($data['description'] ?? ''));
    $dateInput      = trim((string)($data['date'] ?? $data['event_date'] ?? ''));
    $location       = trim((string)($data['location'] ?? ''));
    $category       = strtolower(trim((string)($data['category'] ?? '')));
    $organizerEmail = strtolower(trim((string)($data['organizer_email'] ?? '')));
    $capacity       = filter_var(
        $data['capacity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );

    if ($title === '' || strlen($title) > 255) {
        throw new InvalidArgumentException('Le titre est obligatoire et doit faire moins de 255 caractères.');
    }

    if ($description === '') {
        throw new InvalidArgumentException('La description est obligatoire.');
    }

    if ($location === '' || strlen($location) > 255) {
        throw new InvalidArgumentException('Le lieu est obligatoire et doit faire moins de 255 caractères.');
    }

    if ($capacity === false) {
        throw new InvalidArgumentException('La capacité doit être un entier positif.');
    }

    if (!filter_var($organizerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("L'email organisateur est invalide.");
    }

    if (!preg_match('/^[a-z0-9_-]{1,50}$/', $category)) {
        throw new InvalidArgumentException('La catégorie est invalide.');
    }

    $eventDate = normalizeEventDate($dateInput);

    $stmt = $pdo->prepare('SELECT slug FROM categories WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $category]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('La catégorie demandée n’existe pas.');
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE email = :email AND role = 'organizer' LIMIT 1"
    );
    $stmt->execute([':email' => $organizerEmail]);
    $organizer = $stmt->fetch();

    return [
        'title'           => $title,
        'description'     => $description,
        'event_date'      => $eventDate,
        'location'        => $location,
        'capacity'        => (int)$capacity,
        'category'        => $category,
        'organizer_email' => $organizerEmail,
        'organizer_id'    => $organizer ? (int)$organizer['id'] : null,
    ];
}

/**
 * Convertit une date HTML datetime-local en DATETIME MySQL.
 *
 * @param string $value
 * @return string
 */
function normalizeEventDate(string $value): string
{
    if ($value === '') {
        throw new InvalidArgumentException('La date de l’événement est obligatoire.');
    }

    $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();

        if ($date instanceof DateTime
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    throw new InvalidArgumentException('La date de l’événement est invalide.');
}
