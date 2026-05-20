<?php

namespace Core;

use PDO;

require_once __DIR__ . '/../config/db.php';

/**
 * Singleton PDO partage par toute la couche MVC.
 *
 * La configuration reste dans config/db.php pour ne pas dupliquer les
 * credentials. Les Models recuperent uniquement Database::getInstance()->pdo().
 */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $this->pdo = getDB();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public static function columnExists(PDO $pdo, string $table, string $column): bool
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
}
