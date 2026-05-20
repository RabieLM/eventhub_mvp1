<section class="hero">
    <div>
        <p class="muted">Architecture MVC - Front controller public/index.php</p>
        <h1>Gerez vos evenements intelligemment</h1>
        <p class="muted">Les cartes ci-dessous sont chargees via Fetch API depuis le controleur MVC.</p>
    </div>
    <div class="stats-grid" style="min-width:min(520px,100%);margin:0">
        <article class="stat-card">
            <p class="stat-label">Evenements</p>
            <div class="stat-value" id="h-total">0</div>
        </article>
        <article class="stat-card">
            <p class="stat-label">Inscrits</p>
            <div class="stat-value" id="h-inscrits">0</div>
        </article>
        <article class="stat-card">
            <p class="stat-label">Complets</p>
            <div class="stat-value" id="h-complets">0</div>
        </article>
        <article class="stat-card">
            <p class="stat-label">Nouveaux 24h</p>
            <div class="stat-value" id="h-new24">0</div>
        </article>
    </div>
</section>

<section class="toolbar" aria-label="Filtres evenements">
    <input id="search-input" type="search" placeholder="Rechercher un evenement...">
    <select id="filter-cat">
        <option value="">Toutes categories</option>
        <?php foreach (($categories ?? []) as $category): ?>
            <option value="<?= htmlspecialchars($category['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?= htmlspecialchars($category['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select id="filter-places">
        <option value="">Toutes places</option>
        <option value="1">Places disponibles</option>
    </select>
</section>

<div class="tabs" aria-label="Etat des evenements">
    <button class="tab-btn active" type="button" onclick="filterTab('all', this)">Tous</button>
    <button class="tab-btn" type="button" onclick="filterTab('upcoming', this)">A venir</button>
    <button class="tab-btn" type="button" onclick="filterTab('full', this)">Complets</button>
</div>

<section id="events-grid" class="events-grid" aria-live="polite"></section>

<div id="modal-reg" class="modal hidden">
    <div class="modal-box">
        <h2 id="m-title" style="margin-top:0">Inscription</h2>
        <p class="muted" id="m-info"></p>
        <div class="cap-bar" style="margin:12px 0"><div id="m-bar" class="cap-bar-fill"></div></div>
        <p class="muted" id="m-places"></p>
        <div class="stack">
            <input id="r-name" type="text" placeholder="Nom complet">
            <input id="r-email" type="email" placeholder="Email">
            <button id="btn-reg" class="btn" type="button" onclick="submitReg()">
                <span id="lbl-reg">S'inscrire & recevoir le ticket PDF</span>
                <span id="spn-reg" class="spinner hidden"></span>
            </button>
            <button class="btn secondary" type="button" onclick="closeReg()">Annuler</button>
        </div>
    </div>
</div>
