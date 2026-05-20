    </div>
</main>
<div id="toast-root"></div>
<script>
window.EVENTHUB_MVC_ENTRY = <?= json_encode($publicBase . '/index.php?route=', JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($legacyBase . '/assets/js/app.js', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></script>
</body>
</html>
