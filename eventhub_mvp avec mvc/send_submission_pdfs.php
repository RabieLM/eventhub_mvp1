<?php
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/config/submission.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['confirm'] ?? '') !== 'YES') {
    echo "Envoi non confirme.\n";
    echo "Ajoutez ?confirm=YES a l'URL pour envoyer les PDFs au professeur.";
    exit;
}

try {
    $attachments = [
        __DIR__ . '/pdf/samples/ticket_example.pdf',
        __DIR__ . '/pdf/samples/report_example.pdf',
    ];

    foreach ($attachments as $file) {
        if (!is_file($file) || filesize($file) === 0) {
            throw new RuntimeException('Fichier PDF manquant ou vide : ' . basename($file));
        }
    }

    $mail = createMailer();
    $mail->addAddress(PROFESSOR_EMAIL, PROFESSOR_NAME);
    $mail->Subject = SUBMISSION_SUBJECT;
    $mail->Body = '<h1>EventHub Pro</h1><p>Bonjour Professeur,</p><p>Veuillez trouver ci-joint les PDFs generes du projet EventHub Pro : ticket exemple et rapport organisateur.</p>';
    $mail->AltBody = 'Bonjour Professeur, veuillez trouver ci-joint les PDFs generes du projet EventHub Pro : ticket exemple et rapport organisateur.';

    foreach ($attachments as $file) {
        $mail->addAttachment($file, basename($file));
    }

    $mail->send();

    echo 'Email avec PDFs envoye avec succes a ' . PROFESSOR_EMAIL;
} catch (Throwable $e) {
    error_log('[EventHub] Submission email failed: ' . $e->getMessage());
    echo "Erreur lors de l'envoi des PDFs. Consultez les logs PHP.";
}
