<?php
/**
 * EventHub Pro - api/stats.php
 *
 * Endpoint JSON du dashboard temps reel. Dans une vraie version organisateur,
 * l'acces doit etre protege par une session PHP (role organizer). Pour l'examen
 * et la demonstration du MVP, l'endpoint reste accessible.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDB();
    $registrationsHaveStatus = tableColumnExists($pdo, 'registrations', 'status');
    $activeJoinFilter = $registrationsHaveStatus ? " AND r.status = 'active'" : '';
    $activeWhereFilter = $registrationsHaveStatus ? " AND status = 'active'" : '';
    $recentStatusFilter = $registrationsHaveStatus ? " AND r.status = 'active'" : '';

    $events = fetchEventStats($pdo, $activeJoinFilter);
    $summary = buildSummary($pdo, $events, $activeWhereFilter);
    $recentRegistrations = fetchRecentRegistrations($pdo, $recentStatusFilter);
    $topEvents = array_slice($events, 0, 3);

    echo json_encode([
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => $summary,
        'events' => $events,
        'top_events' => $topEvents,
        'recent_registrations' => $recentRegistrations,

        // Alias gardes pour les scripts fournis dans le MVP.
        'top3' => $topEvents,
        'per_event' => $events,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[EventHub] api/stats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur lors du chargement des statistiques.',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * @return array<int,array<string,mixed>>
 */
function fetchEventStats(PDO $pdo, string $activeJoinFilter): array
{
    $sql = "SELECT
            e.id,
            e.title,
            e.capacity,
            COUNT(r.id) AS registered_count,
            GREATEST(e.capacity - COUNT(r.id), 0) AS remaining_places,
            ROUND((COUNT(r.id) / NULLIF(e.capacity, 0)) * 100) AS fill_rate,
            CASE WHEN COUNT(r.id) >= e.capacity THEN 1 ELSE 0 END AS is_full
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id{$activeJoinFilter}
        GROUP BY e.id, e.title, e.capacity
        ORDER BY fill_rate DESC, registered_count DESC, e.event_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['capacity'] = (int)$row['capacity'];
        $row['registered_count'] = (int)$row['registered_count'];
        $row['remaining_places'] = (int)$row['remaining_places'];
        $row['fill_rate'] = (int)$row['fill_rate'];
        $row['is_full'] = (bool)$row['is_full'];

        // Alias utiles pour l'ancien code fourni.
        $row['registered'] = $row['registered_count'];
        $row['fill_pct'] = $row['fill_rate'];

        return $row;
    }, $stmt->fetchAll());
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<string,int>
 */
function buildSummary(PDO $pdo, array $events, string $activeWhereFilter): array
{
    $totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE 1=1' . $activeWhereFilter);
    $stmt->execute();
    $totalRegistrations = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM registrations
         WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)' . $activeWhereFilter
    );
    $stmt->execute();
    $newRegistrations24h = (int)$stmt->fetchColumn();

    $fullEvents = 0;
    $fillTotal = 0;
    foreach ($events as $event) {
        if ($event['is_full']) {
            $fullEvents++;
        }
        $fillTotal += (int)$event['fill_rate'];
    }

    $avgFillRate = count($events) > 0 ? (int)round($fillTotal / count($events)) : 0;

    return [
        'total_events' => $totalEvents,
        'total_registrations' => $totalRegistrations,
        'full_events' => $fullEvents,
        'new_registrations_24h' => $newRegistrations24h,
        'avg_fill_rate' => $avgFillRate,

        // Alias compatibles avec le squelette initial.
        'total_registered' => $totalRegistrations,
        'new_last_24h' => $newRegistrations24h,
        'avg_fill_pct' => $avgFillRate,
        'alert_count' => count(array_filter($events, static fn(array $event): bool => (int)$event['fill_rate'] >= 80)),
    ];
}

/**
 * @return array<int,array<string,string>>
 */
function fetchRecentRegistrations(PDO $pdo, string $recentStatusFilter): array
{
    $sql = "SELECT
            r.name,
            r.email,
            e.title AS event_title,
            DATE_FORMAT(r.registered_at, '%Y-%m-%d %H:%i:%s') AS registered_at
        FROM registrations r
        INNER JOIN events e ON e.id = r.event_id
        WHERE r.registered_at >= DATE_SUB(NOW(), INTERVAL 1 DAY){$recentStatusFilter}
        ORDER BY r.registered_at DESC
        LIMIT 8";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll();
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
