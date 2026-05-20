<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Throwable;

final class PdfController extends Controller
{
    public function ticket(): void
    {
        try {
            $registrationId = filter_var($_GET['registration_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$registrationId) {
                throw new \InvalidArgumentException('registration_id invalide.');
            }

            require_once __DIR__ . '/../../pdf/ticket.php';

            $mode = isset($_GET['download']) ? 'D' : (isset($_GET['save']) ? 'F' : 'I');
            \generateTicketPdf(Database::getInstance()->pdo(), (int)$registrationId, $mode);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] PdfController::ticket: ' . $e->getMessage());
            http_response_code($e instanceof \InvalidArgumentException ? 400 : 500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Impossible de generer le ticket PDF.';
        }
    }

    public function report(): void
    {
        try {
            $eventId = filter_var($_GET['event_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new \InvalidArgumentException('event_id invalide.');
            }

            require_once __DIR__ . '/../../pdf/report.php';

            $mode = isset($_GET['download']) ? 'D' : (isset($_GET['save']) ? 'F' : 'I');
            \generateEventReportPdf(Database::getInstance()->pdo(), (int)$eventId, $mode);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] PdfController::report: ' . $e->getMessage());
            http_response_code($e instanceof \InvalidArgumentException ? 400 : 500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Impossible de generer le rapport PDF.';
        }
    }
}
