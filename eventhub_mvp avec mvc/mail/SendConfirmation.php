<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — mail/SendConfirmation.php                   ║
 * ║  Email de confirmation d'inscription                        ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 2.1
 *
 * APPELÉ DEPUIS : events/register.php après inscription réussie
 *
 * IMPLÉMENTÉ :
 *   ✅  Charger le template HTML (mail/templates/confirmation.html)
 *   ✅  Remplacer les placeholders par les vraies données
 *   ✅  Envoyer l'email avec PHPMailer
 *   ✅  Retourner true/false selon le résultat
 *   ✅  Logger les erreurs avec logMailError() si l'envoi échoue
 */

require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';

class SendConfirmation
{
    /**
     * Envoie l'email de confirmation d'inscription.
     *
     * @param  PDO    $pdo
     * @param  array  $event   Données de l'événement (depuis la BD)
     * @param  string $name    Nom du participant
     * @param  string $email   Email du participant
     * @param  string $token   Token unique de désinscription
     * @param  int|null $registrationId ID de l'inscription pour le lien ticket
     * @return bool            true si envoi réussi, false sinon
     */
    public static function send(
        PDO $pdo,
        array $event,
        string $name,
        string $email,
        string $token,
        ?int $registrationId = null
    ): bool
    {
        try {
            $templatePath = __DIR__ . '/templates/confirmation.html';
            if (!is_file($templatePath)) {
                throw new RuntimeException('Template confirmation.html introuvable.');
            }

            $html = file_get_contents($templatePath);
            if ($html === false) {
                throw new RuntimeException('Impossible de lire le template confirmation.html.');
            }

            $eventId = (int)($event['id'] ?? 0);
            $registrationId = $registrationId ?? (isset($event['registration_id']) ? (int)$event['registration_id'] : 0);

            $ticketLink = self::buildUrl('/pdf/ticket.php', [
                'registration_id' => $registrationId,
                'token'           => $token,
            ]);

            $unsubscribeLink = self::buildUrl('/events/unregister.php', [
                'token' => $token,
            ]);

            $replacements = [
                '{{PARTICIPANT_NAME}}' => self::escape($name),
                '{{EVENT_TITLE}}'      => self::escape((string)$event['title']),
                '{{EVENT_DATE}}'       => self::escape(self::formatDate((string)$event['event_date'])),
                '{{EVENT_LOCATION}}'   => self::escape((string)$event['location']),
                '{{TICKET_LINK}}'      => self::escape($ticketLink),
                '{{UNSUBSCRIBE_LINK}}' => self::escape($unsubscribeLink),
                '{{YEAR}}'             => date('Y'),
            ];

            $html = str_replace(array_keys($replacements), array_values($replacements), $html);

            $mail = createMailer();
            $mail->addAddress($email, $name);
            $mail->Subject = 'Votre inscription - ' . (string)$event['title'];
            $mail->Body    = $html;
            $mail->AltBody = self::toPlainText($html);
            $mail->send();

            return true;
        } catch (Throwable $e) {
            logMailError($pdo, 'confirmation', $email, $e->getMessage(), isset($event['id']) ? (int)$event['id'] : null);
            return false;
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function formatDate(string $date): string
    {
        try {
            $dt = new DateTime($date);
            return $dt->format('d/m/Y à H:i');
        } catch (Throwable $e) {
            return $date;
        }
    }

    private static function buildUrl(string $path, array $query = []): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $projectBase = self::projectBasePath();
        $url = $scheme . '://' . $host . $projectBase . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private static function projectBasePath(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $marker = '/events/';
        $pos = strpos($scriptName, $marker);

        if ($pos !== false) {
            return rtrim(substr($scriptName, 0, $pos), '/');
        }

        return '';
    }

    private static function toPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>/i', "\n\n", $text ?? $html);
        return trim(html_entity_decode(strip_tags($text ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
