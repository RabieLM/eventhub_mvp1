# EventHub Pro — MVP
### Examen PHP Avancé · ENSA Marrakech · Université Cadi Ayyad

---

## 🚀 Installation rapide

### 1. Prérequis
- PHP 8.1+
- MySQL 8.0+
- XAMPP / WAMP / Laragon
- Composer (recommandé pour PHPMailer)

### 2. Base de données
```bash
mysql -u root -p < database/schema.sql
```
Puis complétez `database/schema.sql` (Partie 1.1).

### 3. Configuration
Éditez `config/db.php` :
```php
define('DB_NAME', 'eventhub_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Éditez `config/mailer.php` avec vos credentials SMTP (ex : Mailtrap).

### 4. Bibliothèques PDF
Déposez TCPDF **ou** Dompdf dans `lib/` :
```
lib/
  tcpdf/tcpdf.php          ← si vous choisissez TCPDF
  phpqrcode/qrlib.php      ← QR Code (obligatoire)
vendor/autoload.php        ← si vous utilisez Composer + Dompdf
```

### 5. Lancer
```
http://localhost/eventhub_mvp/
```

---

## 📁 Structure du projet

```
eventhub_mvp/
├── index.html                  ✅ Fourni — Interface HTML/JS
├── config/
│   ├── db.php                  ✅ Fourni — Connexion PDO
│   └── mailer.php              ⚠️ Partiel — Credentials SMTP à renseigner
├── events/
│   ├── create.php              ✅ Complété — Requêtes PDO sécurisées (Partie 1.2)
│   └── register.php            ✅ Complété — Inscription + emails (Parties 2.1 + 2.2)
├── api/
│   ├── events.php              ✅ Complété — searchEvents() + pagination (Partie 1.3)
│   └── stats.php               🔴 À créer entièrement (Partie 4.2)
├── pdf/
│   ├── ticket.php              ✅ Complété — Ticket PDF TCPDF (Partie 3.1)
│   ├── report.php              ✅ Complété — Rapport PDF TCPDF (Partie 3.2)
│   ├── helpers.php             ✅ Helpers TCPDF communs
│   └── samples/                ✅ Exemples PDF générés
├── mail/
│   ├── SendConfirmation.php    ✅ Complété — Email HTML participant
│   ├── AlertMailer.php         ✅ Complété — Alerte 80% organisateur
│   └── templates/
│       ├── confirmation.html   ⚠️ Partiel — Injecter les données
│       └── alert.html          ⚠️ Partiel — Injecter les données
├── assets/
│   └── js/app.js               ⚠️ Partiel — 4 fonctions fetch() à compléter
├── database/
│   └── schema.sql              ✅ Complété — migrations, FK, index, données test
└── CHOIX_TECHNIQUES.md         🔴 À rédiger (Partie 5.2)
```

---

## 📋 Livrables à rendre

| Fichier | Obligatoire |
|---------|------------|
| Code source complet (.zip) | ✅ |
| database/schema.sql complété | ✅ |
| SCENARIO.md (résultats des tests) | ✅ |
| CHOIX_TECHNIQUES.md | ✅ |
| pdf/samples/ticket_example.pdf | ✅ |
| pdf/samples/report_example.pdf | ✅ |

---

## 💡 Conseils

- Commencez par la **Partie 1** (base de données + PDO) — tout le reste en dépend
- Testez chaque partie **individuellement** avant de passer à la suivante
- Utilisez **Mailtrap** pour tester les emails sans envoyer de vrais messages
- Commitez régulièrement avec Git — le log sera évalué
- Le bonus MVC ne vaut que si les Parties 1–5 fonctionnent à 60% minimum
