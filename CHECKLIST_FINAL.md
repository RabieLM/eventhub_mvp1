# Checklist finale EventHub Pro

## Base de données
- [ ] database/schema.sql importé sans erreur
- [x] 3 événements minimum
- [x] 5 utilisateurs minimum
- [x] DevFest Marrakech 2026 capacité 5 présent

## Emails
- [x] SMTP réel configuré
- [x] Email de test SMTP envoyé
- [x] Email de confirmation accepté par SMTP
- [x] Email alerte 80 % accepté par SMTP
- [x] PDF joint à l’email organisateur
- [ ] Réception visuelle des emails vérifiée dans Gmail
- [ ] Envoi final au professeur confirmé avec `send_submission_pdfs.php?confirm=YES`

## PDFs
- [x] ticket_example.pdf généré
- [x] report_example.pdf généré
- [x] Rapport multi-pages
- [x] Graphique en barres visible

## AJAX
- [x] Événements chargés via fetch
- [x] Inscription sans reload côté endpoint JSON
- [x] Compteur mis à jour par l’API
- [x] Bouton désactivé quand complet côté données `is_full`
- [ ] Vérification visuelle complète dans le navigateur

## Dashboard
- [x] api/stats.php retourne JSON
- [x] dashboard.php affiche les stats
- [x] Refresh automatique prévu dans `assets/js/app.js`
- [x] Toast événement complet implémenté
- [ ] Toast vérifié visuellement pendant un refresh réel

## Scénario
- [x] SCENARIO.md rempli
- [x] URLs notées
- [x] Résultats obtenus documentés
- [ ] Captures ajoutées
