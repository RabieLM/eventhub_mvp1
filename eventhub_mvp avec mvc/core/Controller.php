<?php

namespace Core;

/**
 * Controleur de base : rend les vues et les reponses JSON.
 */
abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        $root = dirname(__DIR__);
        $viewFile = $root . '/app/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Vue introuvable.';
            return;
        }

        extract($data, EXTR_SKIP);

        require $root . '/app/Views/layouts/header.php';
        require $viewFile;
        require $root . '/app/Views/layouts/footer.php';
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    protected function requestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') === false) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \InvalidArgumentException('Donnees JSON invalides.');
        }

        return $data;
    }

    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return (($_GET['format'] ?? '') === 'json') || stripos($accept, 'application/json') !== false;
    }
}
