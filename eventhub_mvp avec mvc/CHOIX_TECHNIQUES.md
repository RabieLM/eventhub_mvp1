# CHOIX_TECHNIQUES.md - EventHub Pro

## Bonus MVC

Le projet garde les anciens fichiers PHP natifs pour compatibilite avec les parties 1 a 5, mais ajoute une couche MVC coherent dans `app/`, `core/` et `public/`.

## Structure retenue

- `public/index.php` est le point d'entree unique MVC. Il charge l'autoloader, declare les routes et delegue au routeur.
- `core/Router.php` contient un routeur minimaliste sans framework.
- `core/Controller.php` factorise le rendu des vues et les reponses JSON.
- `core/Database.php` fournit un singleton PDO base sur `config/db.php`.
- `app/Models/` contient uniquement les requetes PDO et la validation de donnees metier.
- `app/Controllers/` orchestre les Models, les emails, les PDF et les reponses HTTP.
- `app/Views/` contient uniquement le HTML des pages, sans requete SQL.

## Separation des responsabilites

Les Models `EventModel`, `RegistrationModel` et `UserModel` concentrent les acces base de donnees. Les controleurs ne construisent pas de SQL : ils appellent les Models, gerent les erreurs et choisissent entre vue HTML ou JSON. Les vues ne connaissent ni PDO ni les tables SQL.

Cette organisation respecte le bonus attendu : Models = donnees, Controllers = logique metier / orchestration, Views = affichage.

## Routes principales MVC

- `public/index.php?route=/events` : liste des evenements.
- `public/index.php?route=/events/create` : formulaire de creation.
- `public/index.php?route=/dashboard` : dashboard organisateur.
- `public/index.php?route=/api/events` : endpoint JSON evenements.
- `public/index.php?route=/api/stats` : endpoint JSON statistiques.
- `public/index.php?route=/events/register` : inscription AJAX en POST.
- `public/index.php?route=/events/unregister&token=TOKEN` : desinscription.
- `public/index.php?route=/pdf/ticket&registration_id=ID` : ticket PDF.
- `public/index.php?route=/pdf/report&event_id=ID` : rapport PDF.

## Compatibilite avec l'existant

Les fichiers historiques `api/events.php`, `api/stats.php`, `events/register.php`, `events/create.php`, `pdf/ticket.php` et `pdf/report.php` restent disponibles. Cela evite de casser les tests deja faits pour les parties precedentes.

Le JavaScript `assets/js/app.js` detecte la variable `window.EVENTHUB_MVC_ENTRY` injectee par les vues MVC. Si elle existe, les appels Fetch sont rediriges vers le front controller MVC. Sinon, le meme JS continue d'utiliser les anciens endpoints.

## Singleton PDO et heritage

`core/Database.php` applique le patron Singleton pour reutiliser la meme connexion PDO. Tous les controleurs heritent de `core/Controller.php`, ce qui factorise `render()`, `json()` et la lecture du body JSON.

## Emails d'alerte 80 %

Pendant les tests, l'alerte de seuil 80 % est redirigee vers `ALERT_EMAIL` dans `config/submission.php`, actuellement `lamjidrabie@gmail.com`. La logique anti-doublon reste basee sur `events.alert_sent` avec un `UPDATE` atomique.

## PDF

`PdfController` reutilise les fonctions existantes `generateTicketPdf()` et `generateEventReportPdf()`. Le choix TCPDF reste documente dans les fichiers `pdf/ticket.php` et `pdf/report.php`.

## Limite volontaire

Le MVC a ete ajoute sans framework pour respecter l'enonce. Les anciennes pages restent accessibles afin que le projet soit testable rapidement, mais la demonstration du bonus doit se faire via `public/index.php`.
