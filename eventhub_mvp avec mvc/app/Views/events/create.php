<section class="hero">
    <div>
        <p class="muted">Vue MVC : app/Views/events/create.php</p>
        <h1>Creer un evenement</h1>
        <p class="muted">Le formulaire envoie ses donnees au controleur MVC, qui delegue l'insertion au Model.</p>
    </div>
</section>

<section class="panel" style="padding:22px">
    <div class="grid-2">
        <div class="stack">
            <input id="f-title" type="text" placeholder="Titre de l'evenement">
            <textarea id="f-desc" placeholder="Description"></textarea>
            <input id="f-date" type="datetime-local">
            <input id="f-lieu" type="text" placeholder="Lieu">
        </div>
        <div class="stack">
            <input id="f-cap" type="number" min="1" placeholder="Capacite">
            <select id="f-cat">
                <option value="">Categorie</option>
                <?php foreach (($categories ?? []) as $category): ?>
                    <option value="<?= htmlspecialchars($category['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <?= htmlspecialchars($category['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input id="f-email" type="email" placeholder="Email organisateur">
            <button id="btn-create" class="btn" type="button" onclick="submitCreate()">
                <span id="lbl-create">Creer l'evenement</span>
                <span id="spn-create" class="spinner hidden"></span>
            </button>
        </div>
    </div>
</section>
