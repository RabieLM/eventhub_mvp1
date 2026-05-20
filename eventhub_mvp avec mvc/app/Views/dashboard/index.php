<section class="hero">
    <div>
        <p class="muted">Acces organisateur a proteger par session dans une version reelle.</p>
        <h1>Dashboard temps reel</h1>
        <p class="muted">Actualisation automatique toutes les 30 secondes avec retry en cas d'erreur.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <span class="status-pill" id="dashboard-status">Connecte a l'API</span>
        <span class="spinner hidden" id="dashboard-spinner"></span>
        <button class="btn" id="refresh-dashboard" type="button">Actualiser maintenant</button>
    </div>
</section>

<section class="stats-grid">
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

<section class="panel" style="padding:0;overflow:hidden;margin-bottom:18px">
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
        <tbody id="dashboard-events-body"></tbody>
    </table>
</section>

<section class="grid-2">
    <article class="panel" style="padding:18px">
        <h2 style="margin-top:0">Top 3 remplissage</h2>
        <div class="stack" id="top-events-list"></div>
    </article>
    <article class="panel" style="padding:18px">
        <h2 style="margin-top:0">Nouveaux inscrits 24h</h2>
        <div class="stack" id="recent-registrations-list"></div>
    </article>
</section>
