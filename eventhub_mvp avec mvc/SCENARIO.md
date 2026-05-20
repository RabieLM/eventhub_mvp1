# SCENARIO.md — Test bout-en-bout EventHub Pro

## 1. Objectif du scénario

Valider que les modules du MVP EventHub Pro fonctionnent ensemble : création/présence d'événement, inscriptions PDO, emails PHPMailer, seuil 80 %, PDF TCPDF, Fetch API, dashboard temps réel et désinscription par token.

## 2. Environnement de test

- PHP : 8.2.12 via XAMPP
- MySQL : MariaDB 10.4.32
- Serveur local : Apache XAMPP, `http://localhost/phpexam/eventhub_mvp/`
- Bibliothèque email : PHPMailer via Composer
- Bibliothèque PDF : TCPDF
- SMTP utilisé : Gmail SMTP, `smtp.gmail.com:587`, TLS

## 3. Données initiales

- Événement : `DevFest Marrakech 2026`
- ID local testé : `4`
- Capacité : `5`
- Catégorie : `tech`
- Destinataire alerte 80 % pendant les tests : `lamjidrabie@gmail.com`
- Date : `2026-09-20 09:00:00`
- Lieu : `ENSA Marrakech`
- Participants de test :
  - Participant 1 : `lamjidrabie+participant1@gmail.com`
  - Participant 2 : `lamjidrabie+participant2@gmail.com`
  - Participant 3 : `lamjidrabie+participant3@gmail.com`
  - Participant 4 : `lamjidrabie+participant4@gmail.com`
  - Participant 5 : `lamjidrabie+participant5@gmail.com`

## 4. Étapes du scénario

### Étape 1 — Création de l’événement

Action :
Vérification de l'événement `DevFest Marrakech 2026` en base. L'événement est aussi présent dans `database/schema.sql` pour pouvoir refaire le scénario après import.

Résultat attendu :
Événement créé en base, capacité 5, catégorie tech.

Résultat obtenu :
Validé. En base locale : `id = 4`, `capacity = 5`, `organizer_email = walid.bouarifi@gmail.com`.

Preuve / fichier / URL :
- `database/schema.sql`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?q=DevFest%20Marrakech%202026`

### Étape 2 — Inscription de 4 utilisateurs

Action :
Appel de `events/register.php` en POST JSON pour Participant 1 à Participant 4.

Résultat attendu :
4 inscriptions créées, 4 emails de confirmation envoyés, compteur mis à jour sans rechargement.

Résultat obtenu :
Validé côté serveur. Les réponses JSON ont retourné `success = true`, `confirmation_sent = true`, et les compteurs suivants : 1/5, 2/5, 3/5, 4/5.

Preuve / fichier / URL :
- `http://localhost/phpexam/eventhub_mvp/events/register.php`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?q=DevFest%20Marrakech%202026`
- Réception Gmail à vérifier manuellement dans la boîte `lamjidrabie@gmail.com` avec les alias `+participant`.

### Étape 3 — Déclenchement du seuil 80 %

Action :
Inscription du 4e participant.

Résultat attendu :
Email d'alerte envoyé à l'organisateur avec rapport PDF joint, et alerte envoyée une seule fois.

Résultat obtenu :
Validé. La réponse JSON du 4e inscrit a retourné `fill_rate = 80`, `remaining_places = 1`, `alert_sent = true`. En base, `events.alert_sent = 1`, ce qui empêche un nouvel envoi au 5e inscrit.

Preuve / fichier / URL :
- `mail/AlertMailer.php`
- `pdf/report.php?event_id=4`
- `SELECT alert_sent FROM events WHERE id = 4;`

### Étape 4 — 5e inscription et événement complet

Action :
Inscription du Participant 5.

Résultat attendu :
Événement complet, compteur 5/5, bouton d'inscription désactivé en temps réel.

Résultat obtenu :
Validé côté API. La réponse JSON a retourné `registered_count = 5`, `remaining_places = 0`, `fill_rate = 100`, `is_full = true`. Un 6e test d'inscription a retourné `success = false` et `message = Événement complet.`

Preuve / fichier / URL :
- `http://localhost/phpexam/eventhub_mvp/index.php`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?q=DevFest%20Marrakech%202026`

### Étape 5 — Téléchargement du rapport PDF

Action :
Ouverture et sauvegarde du rapport organisateur.

Résultat attendu :
PDF multi-pages avec résumé exécutif, liste des inscrits et graphique en barres généré côté PHP.

Résultat obtenu :
Validé. `pdf/samples/report_example.pdf` généré, réponse HTTP `application/pdf`, taille locale observée : environ `108346` octets.

Preuve / fichier / URL :
- `http://localhost/phpexam/eventhub_mvp/pdf/report.php?event_id=4`
- `http://localhost/phpexam/eventhub_mvp/pdf/report.php?event_id=4&save=1`
- `pdf/samples/report_example.pdf`

### Étape 6 — Désinscription par lien unique

Action :
Appel du lien de désinscription du Participant 5 :
`events/unregister.php?token=ff9d58da86a0bc1b9e3307e7dd215f1d7c10b39ae0ebefa7049457afe3454d92&format=json`

Résultat attendu :
Inscription annulée proprement, place libérée, compteur mis à jour.

Résultat obtenu :
Validé. La réponse JSON a retourné `success = true`. En base, l'inscription du Participant 5 est passée à `status = cancelled` avec `cancelled_at` rempli. L'API événements retourne maintenant `registered_count = 4`, `remaining_places = 1`, `fill_rate = 80`, `is_full = false`.

Preuve / fichier / URL :
- `http://localhost/phpexam/eventhub_mvp/events/unregister.php?token=TOKEN`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?q=DevFest%20Marrakech%202026`

## 5. URLs utilisées pendant le test

- `http://localhost/phpexam/eventhub_mvp/index.php`
- `http://localhost/phpexam/eventhub_mvp/index.html`
- `http://localhost/phpexam/eventhub_mvp/dashboard.php`
- `http://localhost/phpexam/eventhub_mvp/api/events.php`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?category=tech`
- `http://localhost/phpexam/eventhub_mvp/api/events.php?q=DevFest`
- `http://localhost/phpexam/eventhub_mvp/api/stats.php`
- `http://localhost/phpexam/eventhub_mvp/pdf/ticket.php?registration_id=19`
- `http://localhost/phpexam/eventhub_mvp/pdf/ticket.php?registration_id=19&save=1`
- `http://localhost/phpexam/eventhub_mvp/pdf/report.php?event_id=4`
- `http://localhost/phpexam/eventhub_mvp/pdf/report.php?event_id=4&save=1`
- `http://localhost/phpexam/eventhub_mvp/events/unregister.php?token=ff9d58da86a0bc1b9e3307e7dd215f1d7c10b39ae0ebefa7049457afe3454d92`
- `http://localhost/phpexam/eventhub_mvp/test_real_smtp.php`
- `http://localhost/phpexam/eventhub_mvp/send_submission_pdfs.php?confirm=YES`

## 6. Requêtes SQL utiles de vérification

```sql
SELECT * FROM events WHERE title = 'DevFest Marrakech 2026';

SELECT COUNT(*) AS total
FROM registrations
WHERE event_id = 4;

SELECT COUNT(*) AS total_active
FROM registrations
WHERE event_id = 4
AND status = 'active';

SELECT alert_sent
FROM events
WHERE id = 4;

SELECT id, name, email, status, cancelled_at
FROM registrations
WHERE event_id = 4
ORDER BY id;

SELECT *
FROM mail_logs
ORDER BY created_at DESC;
```

Si la colonne `status` n'existe pas après un ancien import, utiliser :

```sql
SELECT COUNT(*) AS total
FROM registrations
WHERE event_id = 4;
```

## 7. Emails envoyés

- Test SMTP réel vers `walid.bouarifi@gmail.com` : validé par `test_real_smtp.php`.
- Emails de confirmation du scénario : SMTP accepté (`confirmation_sent = true`) pour les 5 alias Gmail.
- Email d'alerte 80 % : SMTP accepté (`alert_sent = true`) au 4e inscrit.
- Réception visuelle dans la boîte Gmail : à vérifier manuellement.
- Envoi des PDFs au professeur via `send_submission_pdfs.php?confirm=YES` : prêt, non lancé automatiquement dans cette documentation.

## 8. PDFs générés

- `pdf/samples/ticket_example.pdf` : généré avec `registration_id = 19`.
- `pdf/samples/report_example.pdf` : généré avec `event_id = 4`.
- Rapport : multi-pages, résumé, liste des inscrits, graphique TCPDF côté PHP.
- Ticket : informations événement/participant, QR code, token unique.

## 9. Captures recommandées

- Page d'accueil avec `DevFest Marrakech 2026`.
- Dashboard avec les statistiques.
- Réponse JSON de `api/events.php?q=DevFest%20Marrakech%202026`.
- Réponse JSON de `api/stats.php`.
- Ticket PDF ouvert dans le navigateur.
- Rapport PDF ouvert dans le navigateur.
- Email de confirmation reçu dans Gmail.
- Email d'alerte 80 % reçu dans Gmail avec pièce jointe PDF.
- Désinscription réussie et compteur revenu à 4/5.

## 10. Problèmes rencontrés et corrections

- `register.php` mélangeait du HTML PDF au JSON quand `pdf/report.php` était inclus indirectement. Correction : les scripts PDF ne s'exécutent en mode direct que si le fichier appelé est le fichier courant.
- Mailtrap Sandbox refusait certains emails avec pièces jointes. Correction finale : configuration Gmail SMTP réelle dans `config/mailer.php`.
- Le lien d'email pointait vers `unsubscribe.php`. Correction : lien vers `events/unregister.php`.
- La désinscription n'existait pas. Ajout de `events/unregister.php`, avec annulation par `status = cancelled` et `cancelled_at`.
- Les données de test ne contenaient pas `DevFest Marrakech 2026`. Ajout dans `database/schema.sql`.
- Pour rendre les emails testables, les participants utilisent des alias Gmail du compte configuré.

## 11. Conclusion

Le scénario bout-en-bout est fonctionnel côté application : événements, inscriptions, JSON AJAX, seuil 80 %, anti-doublon d'alerte, PDFs TCPDF, dashboard et désinscription. Les emails sont acceptés par Gmail SMTP ; la réception visuelle dans Gmail et l'envoi final au professeur restent les vérifications manuelles à faire avant remise.
