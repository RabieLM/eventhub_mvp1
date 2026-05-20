<?php

namespace App\Models;

use Core\Database;
use PDO;

final class UserModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->pdo();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findOrganizerByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, role FROM users WHERE email = :email AND role = 'organizer' LIMIT 1"
        );
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}
