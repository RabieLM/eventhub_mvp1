<?php

namespace App\Controllers;

use App\Models\EventModel;
use App\Models\RegistrationModel;
use Core\Controller;
use Core\Database;
use InvalidArgumentException;
use Throwable;

final class ApiController extends Controller
{
    private EventModel $events;
    private RegistrationModel $registrations;

    public function __construct()
    {
        $this->events = new EventModel();
        $this->registrations = new RegistrationModel();
    }

    public function events(): void
    {
        try {
            $result = $this->events->search($_GET);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(30, (int)($_GET['per_page'] ?? 30)));

            $this->json([
                'success' => true,
                'events' => $result['events'],
                'data' => $result['events'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $page,
                    'per_page' => $perPage,
                    'pages' => (int)ceil($result['total'] / $perPage),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::events: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur.'], 500);
        }
    }

    public function stats(): void
    {
        try {
            $this->json($this->events->dashboardStats());
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::stats: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Erreur serveur lors du chargement des statistiques.',
            ], 500);
        }
    }

    public function createEvent(): void
    {
        try {
            $eventId = $this->events->create($this->requestData());
            $this->json([
                'success' => true,
                'event_id' => $eventId,
                'message' => 'Evenement cree avec succes.',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::createEvent: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur.', 'message' => 'Erreur serveur.'], 500);
        }
    }

    public function register(): void
    {
        try {
            $result = $this->registrations->register($this->requestData());
            $pdo = Database::getInstance()->pdo();
            $mailer = new MailController();

            $confirmationSent = $mailer->sendConfirmation($pdo, $result);
            $alertSent = false;
            if ((int)$result['fill_rate'] >= 80) {
                $alertSent = $mailer->sendCapacityAlert($pdo, $result['event']);
            }

            $this->json([
                'success' => true,
                'message' => 'Inscription reussie',
                'event_id' => $result['event_id'],
                'registration_id' => $result['registration_id'],
                'token' => $result['token'],
                'registered_count' => $result['registered_count'],
                'remaining_places' => $result['remaining_places'],
                'available_places' => $result['available_places'],
                'fill_rate' => $result['fill_rate'],
                'capacity_pct' => $result['capacity_pct'],
                'is_full' => $result['is_full'],
                'confirmation_sent' => $confirmationSent,
                'alert_sent' => $alertSent,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::register: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur.', 'message' => 'Erreur serveur.'], 500);
        }
    }

    public function unregister(): void
    {
        try {
            $result = $this->registrations->unregisterByToken((string)($_GET['token'] ?? ''));

            if ($this->wantsJson()) {
                $this->json($result);
                return;
            }

            $this->render('events/unregister', [
                'title' => 'Desinscription - EventHub Pro',
                'active' => 'events',
                'bodyPage' => 'unregister',
                'result' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 400);
                return;
            }

            http_response_code(400);
            $this->render('events/unregister', [
                'title' => 'Desinscription impossible - EventHub Pro',
                'active' => 'events',
                'bodyPage' => 'unregister',
                'result' => ['success' => false, 'message' => $e->getMessage(), 'event_title' => 'EventHub Pro'],
            ]);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::unregister: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }
}
