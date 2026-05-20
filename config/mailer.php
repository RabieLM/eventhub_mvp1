<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  EventHub Pro — config/mailer.php                           ║
 * ║  Configuration PHPMailer                                    ║
 * ║  ENSA Marrakech — Examen PHP Avancé                         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * STATUT : ⚠️ Partiel — Variables SMTP manquantes à compléter
 *
 * PARTIE 2 — Complétez ce fichier :
 *   → Renseignez les constantes SMTP_* avec vos vraies valeurs
 *   → La fonction createMailer() est fournie et fonctionnelle
 *   → Vous devez créer les fonctions d'envoi dans mail/
 */

// ── TODO 2.0 — Complétez ces constantes avec vos paramètres SMTP ──────────
// Pour travailler en local sans publier le secret, vous pouvez definir
// SMTP_PASS dans config/mailer.local.php. Ce fichier est ignore par Git.
$localMailerConfig = __DIR__ . '/mailer.local.php';
if (is_file($localMailerConfig)) {
    require_once $localMailerConfig;
}

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'lamjidrabie@gmail.com');
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getenv('EVENTHUB_SMTP_PASS') ?: '');
}
define('SMTP_FROM_NAME',  'EventHub Pro — ENSA Marrakech');
define('SMTP_ENCRYPTION', 'tls');

// Conseil : utilisez Mailtrap (mailtrap.io) pour tester sans envoyer de vrais emails

// ── Chargement PHPMailer ───────────────────────────────────────────────────
// PHPMailer est disponible via composer dans vendor/ OU via inclusion directe
// Choisissez la méthode adaptée à votre installation :

// Option A — via Composer (recommandé)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Option B — inclusion directe (si pas de Composer)
$phpMailerBase = __DIR__ . '/../lib/PHPMailer/src';
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)
    && is_file($phpMailerBase . '/PHPMailer.php')
    && is_file($phpMailerBase . '/SMTP.php')
    && is_file($phpMailerBase . '/Exception.php')) {
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';
    require_once $phpMailerBase . '/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Crée et retourne une instance PHPMailer préconfigurée.
 *
 * Paramètres SMTP déjà appliqués. Il reste à :
 *   → addAddress() — destinataire
 *   → Subject      — sujet
 *   → Body         — corps HTML
 *   → AltBody      — version texte brut
 *   → send()       — envoi
 *
 * @return PHPMailer
 */
function createMailer(): PHPMailer
{
    if (!class_exists(PHPMailer::class)) {
        throw new RuntimeException('PHPMailer est introuvable. Installez vendor/autoload.php ou lib/PHPMailer/.');
    }

    if (SMTP_HOST === '' || SMTP_USER === '') {
        throw new RuntimeException('Configuration SMTP incomplète dans config/mailer.php.');
    }

    $mail = new PHPMailer(true); // true = exceptions activées

    // Serveur SMTP
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Expéditeur
    // Gmail impose un expéditeur cohérent avec le compte SMTP authentifié.
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->isHTML(true);

    return $mail;
}

/**
 * Enregistre une erreur d'email en base de données.
 *
 * STATUT : ✅ Fourni
 *
 * @param PDO    $pdo
 * @param string $type     Type d'email ('confirmation', 'alert', 'ticket')
 * @param string $to       Destinataire
 * @param string $error    Message d'erreur
 * @param int|null $eventId Événement concerné, si disponible
 */
function logMailError(PDO $pdo, string $type, string $to, string $error, ?int $eventId = null): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO mail_logs (type, recipient, event_id, error_message, created_at)
             VALUES (:type, :to, :event_id, :error, NOW())'
        );
        $stmt->execute([
            ':type'     => $type,
            ':to'       => $to,
            ':event_id' => $eventId,
            ':error'    => $error,
        ]);
    } catch (PDOException $e) {
        error_log('[EventHub] logMailError DB failed: ' . $e->getMessage());
    }
}
