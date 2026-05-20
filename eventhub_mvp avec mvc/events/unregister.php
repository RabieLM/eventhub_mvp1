<?php
/**
 * EventHub Pro - desinscription par token unique.
 *
 * URL de test :
 * events/unregister.php?token=TOKEN
 *
 * Si les colonnes status/cancelled_at existent, l'inscription est annulee
 * proprement. Sinon, elle est supprimee pour liberer la place.
 */

require_once __DIR__ . '/../config/db.php';

try {
    $token = trim((string)($_GET['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
        throw new InvalidArgumentException('Lien de desinscription invalide.');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT r.*, e.title AS event_title, e.id AS event_id
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         WHERE r.token = :token
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $registration = $stmt->fetch();

    if (!$registration) {
        $pdo->rollBack();
        respondUnregister(false, 'Inscription introuvable ou deja supprimee.', null, 404);
        exit;
    }

    $hasStatus = tableColumnExists($pdo, 'registrations', 'status');
    $hasCancelledAt = tableColumnExists($pdo, 'registrations', 'cancelled_at');

    if ($hasStatus) {
        if (($registration['status'] ?? 'active') === 'cancelled') {
            $pdo->commit();
            respondUnregister(true, 'Cette inscription est deja annulee.', $registration);
            exit;
        }

        $sql = 'UPDATE registrations SET status = :status';
        if ($hasCancelledAt) {
            $sql .= ', cancelled_at = NOW()';
        }
        $sql .= ' WHERE token = :token';

        $update = $pdo->prepare($sql);
        $update->execute([
            ':status' => 'cancelled',
            ':token' => $token,
        ]);
    } else {
        $delete = $pdo->prepare('DELETE FROM registrations WHERE token = :token');
        $delete->execute([':token' => $token]);
    }

    $pdo->commit();
    respondUnregister(true, 'Desinscription effectuee. Une place a ete liberee.', $registration);
} catch (InvalidArgumentException $e) {
    respondUnregister(false, $e->getMessage(), null, 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[EventHub] unregister.php: ' . $e->getMessage());
    respondUnregister(false, 'Erreur serveur pendant la desinscription.', null, 500);
}

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

function respondUnregister(bool $success, string $message, ?array $registration, int $status = 200): void
{
    http_response_code($status);

    $eventId = $registration ? (int)$registration['event_id'] : null;
    $payload = [
        'success' => $success,
        'message' => $message,
        'event_id' => $eventId,
    ];

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (isset($_GET['format']) && $_GET['format'] === 'json' || stripos($accept, 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    $title = $success ? 'Desinscription confirmee' : 'Desinscription impossible';
    $eventTitle = $registration ? htmlspecialchars((string)$registration['event_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'EventHub Pro';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $title . ' - EventHub Pro</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0;display:grid;place-items:center;min-height:100vh}.box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;max-width:560px;padding:28px;box-shadow:0 18px 42px rgba(15,23,42,.12)}h1{margin-top:0}.ok{color:#15803d}.err{color:#dc2626}a{display:inline-block;margin-top:16px;background:#2563eb;color:#fff;text-decoration:none;padding:11px 14px;border-radius:8px;font-weight:700}</style>';
    echo '</head><body><main class="box">';
    echo '<h1 class="' . ($success ? 'ok' : 'err') . '">' . $title . '</h1>';
    echo '<p><strong>Evenement :</strong> ' . $eventTitle . '</p>';
    echo '<p>' . $safeMessage . '</p>';
    echo '<a href="../index.html">Retour aux evenements</a>';
    echo '</main></body></html>';
}
