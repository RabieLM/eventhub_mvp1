<?php
/**
 * EventHub Pro - Dashboard organisateur.
 *
 * Dans une vraie application, cette page doit verifier la session PHP et le role
 * organizer avant affichage. Pour le MVP d'examen, elle reste accessible afin de
 * demontrer le dashboard AJAX temps reel.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EventHub Pro</title>
    <style>
        :root {
            --navy: #0f1f3d;
            --blue: #2563eb;
            --amber: #f59e0b;
            --green: #16a34a;
            --red: #dc2626;
            --slate: #64748b;
            --line: #e2e8f0;
            --bg: #f1f5f9;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: #1e293b;
        }

        a { color: inherit; text-decoration: none; }

        .topbar {
            background: var(--navy);
            color: #fff;
            padding: 18px 24px;
            box-shadow: 0 10px 24px rgba(15, 31, 61, .18);
        }

        .topbar-inner, .page {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            font-weight: 800;
            letter-spacing: .02em;
            font-size: 20px;
        }

        .brand span { color: var(--amber); }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 9px 13px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 8px;
            font-size: 13px;
            color: rgba(255,255,255,.88);
        }

        .page { padding: 32px 0 48px; }

        .hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            color: #0f172a;
        }

        .muted { color: var(--slate); }

        .dashboard-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .pill, .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 10px 13px;
            color: #475569;
            font-size: 13px;
        }

        .status-pill.error {
            border-color: #fecaca;
            background: #fff1f2;
            color: #b91c1c;
        }

        .btn {
            border: 0;
            border-radius: 8px;
            padding: 11px 14px;
            background: var(--blue);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:disabled { opacity: .6; cursor: wait; }

        .spinner {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid #dbeafe;
            border-top-color: var(--blue);
            animation: spin .75s linear infinite;
        }

        .hidden { display: none !important; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .card, .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        }

        .stat-card { padding: 20px; }

        .stat-label {
            margin: 0 0 10px;
            font-size: 12px;
            color: var(--slate);
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }

        .stat-value {
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
            transition: transform .22s ease, background .22s ease, color .22s ease;
            display: inline-block;
            border-radius: 8px;
            padding: 2px 6px;
            margin-left: -6px;
        }

        .stat-value.value-changed {
            transform: scale(1.08);
            background: #dcfce7;
            color: #15803d;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .card-body { padding: 20px; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        th {
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .progress {
            height: 7px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            min-width: 110px;
        }

        .progress span {
            display: block;
            height: 100%;
            background: var(--blue);
            border-radius: 999px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge.full { background: #fee2e2; color: #dc2626; }
        .badge.open { background: #dcfce7; color: #15803d; }

        .stack { display: grid; gap: 12px; }
        .list-item {
            border: 1px solid var(--line);
            border-radius: 9px;
            padding: 13px;
            background: #f8fafc;
        }

        .list-item strong { display: block; color: #0f172a; margin-bottom: 5px; }

        #toast-container {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 100;
            display: grid;
            gap: 10px;
        }

        .toast {
            min-width: 280px;
            max-width: min(420px, calc(100vw - 40px));
            color: #fff;
            background: var(--blue);
            padding: 14px 16px;
            border-radius: 10px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .24);
            animation: slideIn .22s ease;
        }

        .toast.success { background: var(--green); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 840px) {
            .hero { flex-direction: column; }
            .dashboard-tools { justify-content: flex-start; }
            .stats-grid, .grid-2 { grid-template-columns: 1fr; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body data-page="dashboard">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.html">EventHub <span>Pro</span></a>
            <nav class="nav-actions">
                <a class="nav-link" href="index.html">Evenements</a>
                <a class="nav-link" href="events/create.php">Creer</a>
            </nav>
        </div>
    </header>

    <main class="page">
        <section class="hero">
            <div>
                <h1>Dashboard organisateur</h1>
                <p class="muted">Statistiques chargees via Fetch API, sans rechargement complet de page.</p>
            </div>
            <div class="dashboard-tools">
                <span class="pill" id="last-update">Derniere mise a jour : en attente</span>
                <span class="status-pill" id="dashboard-status">Connecte a l'API</span>
                <span class="spinner hidden" id="dashboard-spinner" aria-label="Chargement"></span>
                <button class="btn" id="refresh-dashboard" type="button">Actualiser maintenant</button>
            </div>
        </section>

        <section class="stats-grid" aria-label="Statistiques principales">
            <article class="stat-card">
                <p class="stat-label">Total evenements</p>
                <div class="stat-value" id="stat-total-events">0</div>
            </article>
            <article class="stat-card">
                <p class="stat-label">Total inscriptions</p>
                <div class="stat-value" id="stat-total-registrations">0</div>
            </article>
            <article class="stat-card">
                <p class="stat-label">Nouveaux inscrits 24h</p>
                <div class="stat-value" id="stat-new-registrations-24h">0</div>
            </article>
            <article class="stat-card">
                <p class="stat-label">Evenements complets</p>
                <div class="stat-value" id="stat-full-events">0</div>
            </article>
        </section>

        <section class="grid-2">
            <article class="card">
                <div class="card-header">
                    <h2>Evenements</h2>
                    <span class="muted">Capacite et remplissage</span>
                </div>
                <div class="card-body table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Evenement</th>
                                <th>Capacite</th>
                                <th>Inscrits</th>
                                <th>Restantes</th>
                                <th>Taux</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody id="dashboard-events-body">
                            <tr><td colspan="6">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="stack">
                <article class="card">
                    <div class="card-header">
                        <h2>Top 3</h2>
                    </div>
                    <div class="card-body stack" id="top-events-list">
                        <div class="list-item">Chargement...</div>
                    </div>
                </article>

                <article class="card">
                    <div class="card-header">
                        <h2>Nouveaux inscrits 24h</h2>
                    </div>
                    <div class="card-body stack" id="recent-registrations-list">
                        <div class="list-item">Chargement...</div>
                    </div>
                </article>
            </aside>
        </section>
    </main>

    <div id="toast-container"></div>
    <script src="assets/js/app.js"></script>
</body>
</html>
