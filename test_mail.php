<?php
require_once __DIR__ . '/config/mailer.php';

try {
    $mail = createMailer();
    $mail->addAddress('test@example.com', 'Test User');
    $mail->Subject = 'Test Mailtrap - EventHub Pro';
    $mail->Body = '<h1>Test EventHub Pro</h1><p>Ceci est un email de test envoyé via Mailtrap.</p>';
    $mail->AltBody = 'Ceci est un email de test envoyé via Mailtrap.';

    $mail->send();

    echo 'Email envoyé avec succès';
} catch (Throwable $e) {
    error_log('[EventHub] Test email failed: ' . $e->getMessage());
    echo 'Erreur lors de l’envoi de l’email. Consultez les logs PHP.';
}
