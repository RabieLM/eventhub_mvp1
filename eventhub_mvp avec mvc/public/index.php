<?php
/**
 * Front Controller MVC EventHub Pro.
 *
 * Toutes les routes MVC passent par ce fichier. Les anciens scripts PHP restent
 * disponibles pour compatibilite, mais cette entree demontre le bonus MVC :
 * routeur -> controleur -> model -> vue.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Core\\' => __DIR__ . '/../core/',
        'App\\' => __DIR__ . '/../app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

use App\Controllers\ApiController;
use App\Controllers\EventController;
use App\Controllers\PdfController;
use Core\Router;

$router = new Router();

$router->get('/', [EventController::class, 'index']);
$router->get('/events', [EventController::class, 'index']);
$router->get('/events/create', [EventController::class, 'create']);
$router->get('/dashboard', [EventController::class, 'dashboard']);

$router->get('/api/events', [ApiController::class, 'events']);
$router->get('/api/stats', [ApiController::class, 'stats']);
$router->post('/events/create', [ApiController::class, 'createEvent']);
$router->post('/events/register', [ApiController::class, 'register']);
$router->get('/events/unregister', [ApiController::class, 'unregister']);

$router->get('/pdf/ticket', [PdfController::class, 'ticket']);
$router->get('/pdf/report', [PdfController::class, 'report']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET');
