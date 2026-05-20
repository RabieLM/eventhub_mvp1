<?php $ok = !empty($result['success']); ?>
<section class="panel" style="padding:28px;max-width:680px;margin:0 auto">
    <h1 style="font-size:32px"><?= $ok ? 'Desinscription confirmee' : 'Desinscription impossible' ?></h1>
    <p><strong>Evenement :</strong>
        <?= htmlspecialchars((string)($result['event_title'] ?? 'EventHub Pro'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
    <p class="muted"><?= htmlspecialchars((string)($result['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <a class="btn" href="<?= htmlspecialchars($routeUrl('/events'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Retour aux evenements</a>
</section>
