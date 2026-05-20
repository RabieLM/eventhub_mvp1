<?php

namespace App\Models;

use Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class RegistrationModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->pdo();
    }

    /**
     * Cree une inscription et retourne les donnees necessaires aux emails/API.
     *
     * @return array<string,mixed>
     */
    public function register(array $data): array
    {
        $eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));

        if (!$eventId || $name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Donnees inscription invalides.');
        }

        $hasStatus = Database::columnExists($this->pdo, 'registrations', 'status');
        $activeRegistrationFilter = $hasStatus ? " AND r.status = 'active'" : '';

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT e.*,
                        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id' . $activeRegistrationFilter . ') AS registered_count
                 FROM events e
                 WHERE e.id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $eventId]);
            $event = $stmt->fetch();

            if (!$event) {
                throw new InvalidArgumentException('Evenement introuvable.');
            }

            $registeredCount = (int)$event['registered_count'];
            $capacity = max(1, (int)$event['capacity']);
            if ($registeredCount >= $capacity) {
                throw new InvalidArgumentException('Evenement complet.');
            }

            $duplicateStatusFilter = $hasStatus ? " AND status = 'active'" : '';
            $stmt = $this->pdo->prepare(
                'SELECT id FROM registrations WHERE event_id = :event_id AND email = :email' . $duplicateStatusFilter
            );
            $stmt->execute([':event_id' => $eventId, ':email' => $email]);
            if ($stmt->fetch()) {
                throw new InvalidArgumentException('Vous etes deja inscrit(e) a cet evenement.');
            }

            $token = $this->generateUniqueToken();
            $insertFields = ['event_id', 'name', 'email', 'token', 'registered_at'];
            $insertValues = [':event_id', ':name', ':email', ':token', 'NOW()'];
            if ($hasStatus) {
                $insertFields[] = 'status';
                $insertValues[] = "'active'";
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO registrations (' . implode(', ', $insertFields) . ')
                 VALUES (' . implode(', ', $insertValues) . ')'
            );
            $stmt->execute([
                ':event_id' => $eventId,
                ':name' => $name,
                ':email' => $email,
                ':token' => $token,
            ]);

            $registrationId = (int)$this->pdo->lastInsertId();
            $newCount = $registeredCount + 1;
            $remainingPlaces = max(0, $capacity - $newCount);
            $fillRate = (int)round(($newCount / $capacity) * 100);
            $isFull = $remainingPlaces === 0;

            $eventForMail = $event;
            $eventForMail['registered_count'] = $newCount;
            $eventForMail['available_places'] = $remainingPlaces;
            $eventForMail['fill_pct'] = $fillRate;
            $eventForMail['registration_id'] = $registrationId;

            $this->pdo->commit();

            return [
                'event' => $eventForMail,
                'name' => $name,
                'email' => $email,
                'token' => $token,
                'registration_id' => $registrationId,
                'event_id' => $eventId,
                'registered_count' => $newCount,
                'remaining_places' => $remainingPlaces,
                'available_places' => $remainingPlaces,
                'fill_rate' => $fillRate,
                'capacity_pct' => $fillRate,
                'is_full' => $isFull,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function unregisterByToken(string $token): array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
            throw new InvalidArgumentException('Lien de desinscription invalide.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT r.*, e.title AS event_title, e.id AS event_id
                 FROM registrations r
                 INNER JOIN events e ON e.id = r.event_id
                 WHERE r.token = :token
                 LIMIT 1'
            );
            $stmt->execute([':token' => $token]);
            $registration = $stmt->fetch();

            if (!$registration) {
                throw new InvalidArgumentException('Inscription introuvable ou deja supprimee.');
            }

            $hasStatus = Database::columnExists($this->pdo, 'registrations', 'status');
            $hasCancelledAt = Database::columnExists($this->pdo, 'registrations', 'cancelled_at');

            if ($hasStatus) {
                if (($registration['status'] ?? 'active') === 'cancelled') {
                    $this->pdo->commit();
                    return [
                        'success' => true,
                        'message' => 'Cette inscription est deja annulee.',
                        'event_id' => (int)$registration['event_id'],
                        'event_title' => (string)$registration['event_title'],
                    ];
                }

                $sql = 'UPDATE registrations SET status = :status';
                if ($hasCancelledAt) {
                    $sql .= ', cancelled_at = NOW()';
                }
                $sql .= ' WHERE token = :token';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':status' => 'cancelled', ':token' => $token]);
            } else {
                $stmt = $this->pdo->prepare('DELETE FROM registrations WHERE token = :token');
                $stmt->execute([':token' => $token]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Desinscription effectuee. Une place a ete liberee.',
                'event_id' => (int)$registration['event_id'],
                'event_title' => (string)$registration['event_title'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function generateUniqueToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare('SELECT id FROM registrations WHERE token = :token LIMIT 1');
            $stmt->execute([':token' => $token]);

            if (!$stmt->fetch()) {
                return $token;
            }
        }

        throw new RuntimeException('Impossible de generer un token unique.');
    }
}
