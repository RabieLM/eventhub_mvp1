<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — mail/AlertMailer.php                        ║
 * ║  Email d'alerte organisateur (seuil 80%)                    ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ✅ Complété — Partie 2.2
 *
 * APPELÉ DEPUIS : events/register.php quand taux >= 80%
 *
 * QUESTION DE CONCEPTION (à répondre dans CHOIX_TECHNIQUES.md) :
 *   Comment éviter d'envoyer cet email plusieurs fois pour le même
 *   événement quand plusieurs personnes s'inscrivent rapidement ?
 *   Implémentez votre solution dans sendCapacityAlert() et commentez-la.
 */

require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/submission.php';
require_once __DIR__ . '/../pdf/report.php';

class AlertMailer
{
    /**
     * Envoie l'email d'alerte de capacité à l'organisateur.
     *
     * @param  PDO   $pdo
     * @param  array $event   Données complètes de l'événement
     * @return bool
     */
    public static function sendCapacityAlert(PDO $pdo, array $event): bool
    {
        $eventId = (int)($event['id'] ?? 0);
        // Pour la remise, les alertes de seuil sont envoyées au professeur.
        // L'email organisateur de l'événement reste disponible en base pour un usage réel.
        $organizerEmail = defined('PROFESSOR_EMAIL')
            ? PROFESSOR_EMAIL
            : trim((string)($event['organizer_email'] ?? ''));

        if ($eventId <= 0 || !filter_var($organizerEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $registered = (int)($event['registered_count'] ?? $event['registered'] ?? 0);
        $capacity = max(1, (int)($event['capacity'] ?? 1));
        $fillPct = (int)round(($registered / $capacity) * 100);

        if ($fillPct < 80) {
            return false;
        }

        // Solution anti-doublon : on "réserve" l'alerte avec un UPDATE atomique.
        // Si deux inscriptions arrivent en même temps, une seule requête peut passer
        // alert_sent de 0 à 1; les autres auront rowCount() = 0 et n'enverront rien.
        $claim = $pdo->prepare(
            'UPDATE events SET alert_sent = 1 WHERE id = :id AND alert_sent = 0'
        );
        $claim->execute([':id' => $eventId]);

        if ($claim->rowCount() === 0) {
            return false;
        }

        $tempPdf = '';

        try {
            $tempPdf = self::generateReportAttachment($pdo, $eventId);
            $html = self::renderAlertTemplate($pdo, $event, $registered, $capacity, $fillPct);

            $mail = createMailer();
            $mail->addAddress($organizerEmail, self::organizerName($pdo, $event));
            $mail->Subject = 'Alerte capacite - ' . (string)$event['title'];
            $mail->Body    = $html;
            $mail->AltBody = self::toPlainText($html);
            $mail->addAttachment($tempPdf, 'rapport_event_' . $eventId . '.pdf');
            $mail->send();

            return true;
        } catch (Throwable $e) {
            // Si l'envoi échoue, on libère alert_sent pour permettre une nouvelle tentative
            // après correction SMTP/PDF. Un envoi réussi reste marqué définitivement.
            $reset = $pdo->prepare('UPDATE events SET alert_sent = 0 WHERE id = :id');
            $reset->execute([':id' => $eventId]);
            logMailError($pdo, 'capacity_alert', $organizerEmail, $e->getMessage(), $eventId);
            return false;
        } finally {
            if ($tempPdf !== '' && is_file($tempPdf)) {
                @unlink($tempPdf);
            }
        }
    }

    private static function generateReportAttachment(PDO $pdo, int $eventId): string
    {
        if (!function_exists('generateReportPDF')) {
            throw new RuntimeException('La fonction generateReportPDF() est introuvable.');
        }

        $tempPdf = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'eventhub_report_' . $eventId . '_' . bin2hex(random_bytes(4)) . '.pdf';

        generateReportPDF($pdo, $eventId, 'F', $tempPdf);

        if (!is_file($tempPdf) || filesize($tempPdf) === 0) {
            throw new RuntimeException('Le rapport PDF n’a pas été généré correctement.');
        }

        return $tempPdf;
    }

    private static function renderAlertTemplate(PDO $pdo, array $event, int $registered, int $capacity, int $fillPct): string
    {
        $templatePath = __DIR__ . '/templates/alert.html';
        if (!is_file($templatePath)) {
            throw new RuntimeException('Template alert.html introuvable.');
        }

        $html = file_get_contents($templatePath);
        if ($html === false) {
            throw new RuntimeException('Impossible de lire le template alert.html.');
        }

        $available = max(0, $capacity - $registered);
        $dashboardLink = self::buildUrl('/index.html', ['section' => 'dashboard']);

        $replacements = [
            '{{ORGANIZER_NAME}}' => self::escape(self::organizerName($pdo, $event)),
            '{{EVENT_TITLE}}'    => self::escape((string)$event['title']),
            '{{FILL_PCT}}'       => (string)$fillPct,
            '{{REGISTERED}}'     => (string)$registered,
            '{{CAPACITY}}'       => (string)$capacity,
            '{{AVAILABLE}}'      => (string)$available,
            '{{DASHBOARD_LINK}}' => self::escape($dashboardLink),
            '{{YEAR}}'           => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private static function organizerName(PDO $pdo, array $event): string
    {
        if (!empty($event['organizer_name'])) {
            return (string)$event['organizer_name'];
        }

        if (!empty($event['organizer_id'])) {
            $stmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$event['organizer_id']]);
            $name = $stmt->fetchColumn();
            if ($name) {
                return (string)$name;
            }
        }

        return 'Organisateur';
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

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function toPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>/i', "\n\n", $text ?? $html);
        return trim(html_entity_decode(strip_tags($text ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
