<?php

namespace App\Models;

use Core\Database;
use DateTime;
use InvalidArgumentException;
use PDO;
use PDOStatement;

final class EventModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->pdo();
    }

    /**
     * @return array{events: array<int,array<string,mixed>>, total: int}
     */
    public function search(array $filters = []): array
    {
        $keyword = trim((string)($filters['q'] ?? $filters['keyword'] ?? $filters['search'] ?? ''));
        $category = strtolower(trim((string)($filters['category'] ?? '')));
        $dateFrom = $this->normalizeDateFilter(trim((string)($filters['date_from'] ?? '')), false);
        $dateTo = $this->normalizeDateFilter(trim((string)($filters['date_to'] ?? '')), true);
        $tab = strtolower(trim((string)($filters['tab'] ?? 'all')));
        $hasPlaces = $this->parseBoolean($filters['has_places'] ?? $filters['places'] ?? false);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(30, (int)($filters['per_page'] ?? 30)));

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            throw new InvalidArgumentException('La date de debut doit preceder la date de fin.');
        }

        $activeJoinFilter = $this->activeJoinFilter('r');
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
            if (!preg_match('/^[a-z0-9_-]{1,50}$/', $category)) {
                throw new InvalidArgumentException('Filtre categorie invalide.');
            }
            $conditions[] = 'e.category = :category';
            $bindings[':category'] = $category;
        }

        if ($dateFrom !== '') {
            $conditions[] = 'e.event_date >= :date_from';
            $bindings[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $conditions[] = 'e.event_date <= :date_to';
            $bindings[':date_to'] = $dateTo;
        }

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
            LEFT JOIN registrations r ON r.event_id = e.id{$activeJoinFilter}";

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

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $bindings);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $events = array_map([$this, 'normalizeEventRow'], $stmt->fetchAll());

        $countSql = 'SELECT COUNT(*) FROM (SELECT e.id'
            . $fromSql
            . $whereSql
            . $groupSql
            . $havingSql
            . ') filtered_events';
        $countStmt = $this->pdo->prepare($countSql);
        $this->bindValues($countStmt, $bindings);
        $countStmt->execute();

        return [
            'events' => $events,
            'total' => (int)$countStmt->fetchColumn(),
        ];
    }

    public function find(int $id): ?array
    {
        $activeJoinFilter = $this->activeJoinFilter('r');
        $stmt = $this->pdo->prepare(
            "SELECT
                e.*,
                COALESCE(c.label, e.category) AS category_label,
                COALESCE(c.color_primary, '#2563EB') AS color_primary,
                COALESCE(c.color_light, '#DBEAFE') AS color_light,
                COUNT(r.id) AS registered_count,
                GREATEST(e.capacity - COUNT(r.id), 0) AS remaining_places,
                ROUND((COUNT(r.id) / NULLIF(e.capacity, 0)) * 100) AS fill_rate,
                CASE WHEN COUNT(r.id) >= e.capacity THEN 1 ELSE 0 END AS is_full
             FROM events e
             LEFT JOIN categories c ON c.slug = e.category
             LEFT JOIN registrations r ON r.event_id = e.id{$activeJoinFilter}
             WHERE e.id = :id
             GROUP BY
                e.id, e.title, e.description, e.event_date, e.location, e.capacity,
                e.category, e.organizer_email, e.organizer_id, e.alert_sent,
                e.created_at, e.updated_at, c.label, c.color_primary, c.color_light
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $event = $stmt->fetch();

        return $event ? $this->normalizeEventRow($event) : null;
    }

    public function create(array $data): int
    {
        $event = $this->validateEventPayload($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO events (
                title, description, event_date, location, capacity,
                category, organizer_email, organizer_id, created_at
             ) VALUES (
                :title, :description, :event_date, :location, :capacity,
                :category, :organizer_email, :organizer_id, NOW()
             )'
        );
        $stmt->execute([
            ':title' => $event['title'],
            ':description' => $event['description'],
            ':event_date' => $event['event_date'],
            ':location' => $event['location'],
            ':capacity' => $event['capacity'],
            ':category' => $event['category'],
            ':organizer_email' => $event['organizer_email'],
            ':organizer_id' => $event['organizer_id'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function categories(): array
    {
        $stmt = $this->pdo->prepare('SELECT slug, label, color_primary, color_light FROM categories ORDER BY label');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboardStats(): array
    {
        $events = $this->fetchEventStats();
        $summary = $this->buildSummary($events);
        $topEvents = array_slice($events, 0, 3);

        return [
            'success' => true,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'events' => $events,
            'top_events' => $topEvents,
            'recent_registrations' => $this->fetchRecentRegistrations(),
            'top3' => $topEvents,
            'per_event' => $events,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchEventStats(): array
    {
        $activeJoinFilter = $this->activeJoinFilter('r');
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
            GROUP BY e.id, e.title, e.capacity, e.event_date
            ORDER BY fill_rate DESC, registered_count DESC, e.event_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return array_map(static function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['capacity'] = (int)$row['capacity'];
            $row['registered_count'] = (int)$row['registered_count'];
            $row['remaining_places'] = (int)$row['remaining_places'];
            $row['fill_rate'] = (int)$row['fill_rate'];
            $row['is_full'] = (bool)$row['is_full'];
            $row['registered'] = $row['registered_count'];
            $row['fill_pct'] = $row['fill_rate'];
            return $row;
        }, $stmt->fetchAll());
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<string,int>
     */
    private function buildSummary(array $events): array
    {
        $totalEvents = (int)$this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM registrations WHERE 1=1' . $this->activeWhereFilter());
        $stmt->execute();
        $totalRegistrations = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM registrations
             WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)' . $this->activeWhereFilter()
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
            'total_registered' => $totalRegistrations,
            'new_last_24h' => $newRegistrations24h,
            'avg_fill_pct' => $avgFillRate,
            'alert_count' => count(array_filter($events, static fn(array $event): bool => (int)$event['fill_rate'] >= 80)),
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function fetchRecentRegistrations(): array
    {
        $statusFilter = $this->activeWhereFilter('r');
        $sql = "SELECT
                r.name,
                r.email,
                e.title AS event_title,
                DATE_FORMAT(r.registered_at, '%Y-%m-%d %H:%i:%s') AS registered_at
            FROM registrations r
            INNER JOIN events e ON e.id = r.event_id
            WHERE r.registered_at >= DATE_SUB(NOW(), INTERVAL 1 DAY){$statusFilter}
            ORDER BY r.registered_at DESC
            LIMIT 8";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function validateEventPayload(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $dateInput = trim((string)($data['date'] ?? $data['event_date'] ?? ''));
        $location = trim((string)($data['location'] ?? ''));
        $category = strtolower(trim((string)($data['category'] ?? '')));
        $organizerEmail = strtolower(trim((string)($data['organizer_email'] ?? '')));
        $capacity = filter_var(
            $data['capacity'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );

        if ($title === '' || strlen($title) > 255) {
            throw new InvalidArgumentException('Le titre est obligatoire et doit faire moins de 255 caracteres.');
        }
        if ($description === '') {
            throw new InvalidArgumentException('La description est obligatoire.');
        }
        if ($location === '' || strlen($location) > 255) {
            throw new InvalidArgumentException('Le lieu est obligatoire et doit faire moins de 255 caracteres.');
        }
        if ($capacity === false) {
            throw new InvalidArgumentException('La capacite doit etre un entier positif.');
        }
        if (!filter_var($organizerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("L'email organisateur est invalide.");
        }
        if (!preg_match('/^[a-z0-9_-]{1,50}$/', $category)) {
            throw new InvalidArgumentException('La categorie est invalide.');
        }

        $stmt = $this->pdo->prepare('SELECT slug FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $category]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('La categorie demandee n existe pas.');
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email AND role = 'organizer' LIMIT 1");
        $stmt->execute([':email' => $organizerEmail]);
        $organizer = $stmt->fetch();

        return [
            'title' => $title,
            'description' => $description,
            'event_date' => $this->normalizeEventDate($dateInput),
            'location' => $location,
            'capacity' => (int)$capacity,
            'category' => $category,
            'organizer_email' => $organizerEmail,
            'organizer_id' => $organizer ? (int)$organizer['id'] : null,
        ];
    }

    private function normalizeEventDate(string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('La date de l evenement est obligatoire.');
        }

        foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            $errors = DateTime::getLastErrors();
            if ($date instanceof DateTime
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidArgumentException('La date de l evenement est invalide.');
    }

    private function normalizeDateFilter(string $value, bool $endOfDay): string
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

        return '';
    }

    /**
     * @param array<string,mixed> $bindings
     */
    private function bindValues(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeEventRow(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['capacity'] = (int)$row['capacity'];
        $row['registered_count'] = (int)($row['registered_count'] ?? 0);
        $row['remaining_places'] = (int)($row['remaining_places'] ?? 0);
        $row['available_places'] = (int)($row['available_places'] ?? $row['remaining_places']);
        $row['fill_rate'] = (int)($row['fill_rate'] ?? 0);
        $row['fill_percentage'] = (int)($row['fill_percentage'] ?? $row['fill_rate']);
        $row['is_full'] = (bool)($row['is_full'] ?? false);

        return $row;
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }

    private function activeJoinFilter(string $alias): string
    {
        return Database::columnExists($this->pdo, 'registrations', 'status')
            ? " AND {$alias}.status = 'active'"
            : '';
    }

    private function activeWhereFilter(string $alias = ''): string
    {
        if (!Database::columnExists($this->pdo, 'registrations', 'status')) {
            return '';
        }

        $prefix = $alias !== '' ? $alias . '.' : '';
        return " AND {$prefix}status = 'active'";
    }
}
