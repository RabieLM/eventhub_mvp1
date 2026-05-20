<?php
/**
 * Helpers communs aux PDF EventHub Pro.
 *
 * Ce fichier centralise le chargement de TCPDF, les petites fonctions
 * d'introspection SQL et les éléments visuels communs aux tickets/rapports.
 */

require_once __DIR__ . '/../config/db.php';

function eventhubLoadTcpdf(): void
{
    if (class_exists('TCPDF')) {
        return;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    if (class_exists('TCPDF')) {
        return;
    }

    $candidates = [
        __DIR__ . '/../lib/tcpdf/tcpdf.php',
        __DIR__ . '/../lib/TCPDF/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        'C:/xampp/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            require_once $candidate;
            break;
        }
    }

    if (!class_exists('TCPDF')) {
        throw new RuntimeException(
            'TCPDF est introuvable. Copiez TCPDF dans lib/tcpdf/ ou installez tecnickcom/tcpdf.'
        );
    }
}

eventhubLoadTcpdf();

class EventHubPdf extends TCPDF
{
    private string $generatedAt;
    private string $logoPath;

    public function __construct(string $orientation = 'P', string $format = 'A4')
    {
        parent::__construct($orientation, 'mm', $format, true, 'UTF-8', false);
        $this->generatedAt = date('d/m/Y H:i');
        $this->logoPath = eventhubLogoPath();
        $this->SetCreator('EventHub Pro');
        $this->SetAuthor('EventHub Pro');
        $this->SetMargins(15, 24, 15);
        $this->SetHeaderMargin(8);
        $this->SetFooterMargin(12);
        $this->SetAutoPageBreak(true, 18);
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
    }

    public function Header(): void
    {
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            $this->Image($this->logoPath, 15, 7, 16, 0, '', '', '', false, 300);
            $this->SetXY(34, 9);
        } else {
            $this->SetXY(15, 8);
        }

        $this->SetFont('dejavusans', 'B', 11);
        $this->SetTextColor(15, 31, 61);
        $this->Cell(0, 6, 'EventHub Pro', 0, 1, 'L');
        $this->SetDrawColor(226, 232, 240);
        $this->Line(15, 21, $this->getPageWidth() - 15, 21);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(100, 116, 139);
        $text = 'Généré le ' . $this->generatedAt . ' · Page ' . $this->getAliasNumPage()
            . ' / ' . $this->getAliasNbPages();
        $this->Cell(0, 8, $text, 0, 0, 'C');
    }
}

function eventhubLogoPath(): string
{
    $paths = [
        __DIR__ . '/../assets/img/logo.png',
        __DIR__ . '/../assets/images/logo.png',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function eventhubColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name'  => $table,
        ':column_name' => $column,
    ]);

    $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function eventhubTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);

    $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$table];
}

function eventhubFormatDate(?string $date): string
{
    if (!$date) {
        return 'Non renseignée';
    }

    try {
        return (new DateTime($date))->format('d/m/Y à H:i');
    } catch (Throwable $e) {
        return $date;
    }
}

function eventhubShortText(?string $text, int $length = 170): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return 'Aucune description disponible.';
    }

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 3, 'UTF-8') . '...';
}

function eventhubCategoryMeta(PDO $pdo, array $event): array
{
    $default = [
        'slug'    => (string)($event['category'] ?? 'general'),
        'label'   => ucfirst((string)($event['category'] ?? 'Général')),
        'primary' => '#2563EB',
        'light'   => '#DBEAFE',
    ];

    if (!eventhubTableExists($pdo, 'categories')) {
        return $default;
    }

    $hasSlug = eventhubColumnExists($pdo, 'categories', 'slug');
    $hasLabel = eventhubColumnExists($pdo, 'categories', 'label');
    $hasName = eventhubColumnExists($pdo, 'categories', 'name');
    $hasPrimary = eventhubColumnExists($pdo, 'categories', 'color_primary');
    $hasLight = eventhubColumnExists($pdo, 'categories', 'color_light');

    $select = [
        $hasSlug ? 'slug' : "'' AS slug",
        $hasLabel ? 'label' : ($hasName ? 'name AS label' : "'' AS label"),
        $hasPrimary ? 'color_primary' : "'#2563EB' AS color_primary",
        $hasLight ? 'color_light' : "'#DBEAFE' AS color_light",
    ];

    if (isset($event['category_id']) && eventhubColumnExists($pdo, 'categories', 'id')) {
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$event['category_id']]);
    } elseif (isset($event['category']) && $hasSlug) {
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => (string)$event['category']]);
    } else {
        return $default;
    }

    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }

    return [
        'slug'    => (string)($row['slug'] ?: $default['slug']),
        'label'   => (string)($row['label'] ?: $default['label']),
        'primary' => (string)($row['color_primary'] ?: $default['primary']),
        'light'   => (string)($row['color_light'] ?: $default['light']),
    ];
}

function eventhubHexToRgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return [37, 99, 235];
    }

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function eventhubEnsureSamplesDir(): string
{
    $dir = __DIR__ . '/samples';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function eventhubEnsureFileDirectory(string $filePath): void
{
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function eventhubPdfError(string $message, int $status = 400): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur PDF</title>';
    echo '<body style="font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:32px">';
    echo '<h1 style="color:#0f1f3d">EventHub Pro</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    echo '</body></html>';
}

function eventhubStreamSavedPdf(string $path, string $filename): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Le fichier PDF sauvegardé est introuvable.');
    }

    if (!headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
    }

    readfile($path);
}

