<?php

namespace App\Controllers;

use Core\Controller;
use PDO;

final class MailController extends Controller
{
    public function sendConfirmation(PDO $pdo, array $registration): bool
    {
        require_once __DIR__ . '/../../mail/SendConfirmation.php';

        return \SendConfirmation::send(
            $pdo,
            $registration['event'],
            (string)$registration['name'],
            (string)$registration['email'],
            (string)$registration['token'],
            (int)$registration['registration_id']
        );
    }

    public function sendCapacityAlert(PDO $pdo, array $event): bool
    {
        require_once __DIR__ . '/../../mail/AlertMailer.php';

        return \AlertMailer::sendCapacityAlert($pdo, $event);
    }
}
