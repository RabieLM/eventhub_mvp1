<?php
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/config/submission.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $mail = createMailer();
    $mail->addAddress(PROFESSOR_EMAIL, PROFESSOR_NAME);
    $mail->Subject = 'Test SMTP Gmail - EventHub Pro';
    $mail->Body = '<h1>Test SMTP Gmail</h1><p>Ceci est un email de test envoye depuis EventHub Pro avec PHPMailer et Gmail SMTP.</p>';
    $mail->AltBody = 'Ceci est un email de test envoye depuis EventHub Pro avec PHPMailer et Gmail SMTP.';

    $mail->send();

    echo 'Email de test envoye avec succes a ' . PROFESSOR_EMAIL;
} catch (Throwable $e) {
    error_log('[EventHub] Real SMTP test failed: ' . $e->getMessage());
    echo "Erreur lors de l'envoi de l'email de test. Consultez les logs PHP.";
}
