<?php
/**
 * EventHub Pro - api/events.php
 *
 * Endpoint AJAX pour la liste des evenements. Les filtres (q, category,
 * date_from, date_to, has_places, tab) sont tous optionnels et combinables.
 * Les inscriptions sont comptees avec status = 'active' si la colonne existe,
 * sinon toutes les lignes registrations sont prises en compte.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $params = readRequestParams();

    $keyword   = trim((string)($params['q'] ?? $params['keyword'] ?? $params['search'] ?? ''));
    $category  = trim((string)($params['category'] ?? ''));
    $dateFrom  = trim((string)($params['date_from'] ?? ''));
    $dateTo    = trim((string)($params['date_to'] ?? ''));
    $tab       = trim((string)($params['tab'] ?? 'all'));
    $hasPlaces = parseBoolean($params['has_places'] ?? $params['places'] ?? false);
    $page      = max(1, (int)($params['page'] ?? 1));
    $perPage   = max(1, min(30, (int)($params['per_page'] ?? 30)));

    $pdo = getDB();
    $result = searchEvents($pdo, $keyword, $category, $dateFrom, $dateTo, $hasPlaces, $tab, $page, $perPage);

    echo json_encode([
        'success' => true,
        'events'  => $result['events'],
        'data'    => $result['events'], // compatibilite avec l'ancien app.js
        'meta'    => [
            'total'    => $result['total'],
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($result['total'] / $perPage),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[EventHub] api/events.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.'], JSON_UNESCAPED_UNICODE);
}

/**
 * @return array<string,mixed>
 */
function readRequestParams(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $_GET;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }

    $params = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($params)) {
        throw new InvalidArgumentException('Donnees JSON invalides.');
    }

    return $params;
}

/**
 * Recherche les evenements avec requete preparee et filtres dynamiques.
 *
 * @return array{events: array<int,array<string,mixed>>, total: int}
 */
function searchEvents(
    PDO $pdo,
    string $keyword = '',
    string $category = '',
    string $dateFrom = '',
    string $dateTo = '',
    bool $hasPlaces = false,
    string $tab = 'all',
    int $page = 1,
    int $perPage = 30
): array {
    $registrationsHaveStatus = tableColumnExists($pdo, 'registrations', 'status');
    $registrationJoinFilter = $registrationsHaveStatus ? " AND r.status = 'active'" : '';

    $conditions = [];
    $bindings = [];
    $having = [];

    if ($keyword !== '') {
        $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 100) : substr($keyword, 0, 100);
        $conditions[] = '(e.title LIKE :keyword_title OR e.description LIKE :keyword_description OR e.location LIKE :keyword_location)';
        $bindings[':keyword_title'] = '%' . $keyword . '%';
        $bindings[':keyword_description'] = '%' . $keyword . '%';
        $bindings[':keyword_location'] = '%' . $keyword . '%';
    }

    if ($category !== '') {
        $category = strtolower($category);
        if (!preg_match('/^[a-z0-9_-]{1,50}$/', $category)) {
            throw new InvalidArgumentException('Filtre categorie invalide.');
        }

        $conditions[] = 'e.category = :category';
        $bindings[':category'] = $category;
    }

    $dateFrom = normalizeDateFilter($dateFrom, false);
    if ($dateFrom !== '') {
        $conditions[] = 'e.event_date >= :date_from';
        $bindings[':date_from'] = $dateFrom;
    }

    $dateTo = normalizeDateFilter($dateTo, true);
    if ($dateTo !== '') {
        $conditions[] = 'e.event_date <= :date_to';
        $bindings[':date_to'] = $dateTo;
    }

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        throw new InvalidArgumentException('La date de debut doit preceder la date de fin.');
    }

    $tab = strtolower($tab);
    if ($tab === 'full') {
        $having[] = 'COUNT(r.id) >= e.capacity';
    } elseif ($tab === 'upcoming') {
        $conditions[] = 'e.event_date >= NOW()';
    }

    if ($hasPlaces) {
        $having[] = 'COUNT(r.id) < e.capacity';
    }

    $selectSql = "SELECT
            e.id,
            e.title,
            e.description,
            DATE_FORMAT(e.event_date, '%Y-%m-%dT%H:%i:%s') AS event_date,
            e.location,
            e.capacity,
            e.category,
            COALESCE(c.label, e.category) AS category_label,
            COALESCE(c.color_primary, '#2563EB') AS color_primary,
            COALESCE(c.color_light, '#DBEAFE') AS color_light,
            e.organizer_email,
            COUNT(r.id) AS registered_count,
            GREATEST(e.capacity - COUNT(r.id), 0) AS remaining_places,
            GREATEST(e.capacity - COUNT(r.id), 0) AS available_places,
            ROUND((COUNT(r.id) / NULLIF(e.capacity, 0)) * 100) AS fill_rate,
            ROUND((COUNT(r.id) / NULLIF(e.capacity, 0)) * 100) AS fill_percentage,
            CASE WHEN COUNT(r.id) >= e.capacity THEN 1 ELSE 0 END AS is_full";

    $fromSql = " FROM events e
        LEFT JOIN categories c ON c.slug = e.category
        LEFT JOIN registrations r ON r.event_id = e.id{$registrationJoinFilter}";

    $whereSql = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);

    $groupSql = ' GROUP BY
            e.id, e.title, e.description, e.event_date, e.location, e.capacity,
            e.category, c.label, c.color_primary, c.color_light, e.organizer_email';

    $havingSql = empty($having) ? '' : ' HAVING ' . implode(' AND ', $having);
    $offset = ($page - 1) * $perPage;

    $sql = $selectSql
        . $fromSql
        . $whereSql
        . $groupSql
        . $havingSql
        . ' ORDER BY e.event_date ASC, e.id ASC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    bindSearchValues($stmt, $bindings);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $events = array_map('normalizeEventRow', $stmt->fetchAll());

    $countSql = 'SELECT COUNT(*) FROM (SELECT e.id'
        . $fromSql
        . $whereSql
        . $groupSql
        . $havingSql
        . ') filtered_events';

    $countStmt = $pdo->prepare($countSql);
    bindSearchValues($countStmt, $bindings);
    $countStmt->execute();

    return [
        'events' => $events,
        'total' => (int)$countStmt->fetchColumn(),
    ];
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalizeEventRow(array $row): array
{
    $row['id'] = (int)$row['id'];
    $row['capacity'] = (int)$row['capacity'];
    $row['registered_count'] = (int)$row['registered_count'];
    $row['remaining_places'] = (int)$row['remaining_places'];
    $row['available_places'] = (int)$row['available_places'];
    $row['fill_rate'] = (int)$row['fill_rate'];
    $row['fill_percentage'] = (int)$row['fill_percentage'];
    $row['is_full'] = (bool)$row['is_full'];

    return $row;
}

/**
 * @param array<string,mixed> $bindings
 */
function bindSearchValues(PDOStatement $stmt, array $bindings): void
{
    foreach ($bindings as $name => $value) {
        $stmt->bindValue($name, $value);
    }
}

function normalizeDateFilter(string $value, bool $endOfDay): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if ($date instanceof DateTime
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d') . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }
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

    throw new InvalidArgumentException('Filtre de date invalide.');
}

function parseBoolean($value): bool
{
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? false;
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
