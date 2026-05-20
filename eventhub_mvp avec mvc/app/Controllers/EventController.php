<?php

namespace App\Controllers;

use App\Models\EventModel;
use Core\Controller;

final class EventController extends Controller
{
    private EventModel $events;

    public function __construct()
    {
        $this->events = new EventModel();
    }

    public function index(): void
    {
        $this->render('events/index', [
            'title' => 'Evenements - EventHub Pro',
            'active' => 'events',
            'categories' => $this->events->categories(),
            'bodyPage' => 'events',
        ]);
    }

    public function create(): void
    {
        $this->render('events/create', [
            'title' => 'Creer un evenement - EventHub Pro',
            'active' => 'create',
            'categories' => $this->events->categories(),
            'bodyPage' => 'create',
        ]);
    }

    public function dashboard(): void
    {
        // Dans une vraie application, verifier ici la session et le role organizer.
        $this->render('dashboard/index', [
            'title' => 'Dashboard - EventHub Pro',
            'active' => 'dashboard',
            'bodyPage' => 'dashboard',
        ]);
    }
}
