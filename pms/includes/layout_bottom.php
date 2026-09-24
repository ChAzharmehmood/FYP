<?php declare(strict_types=1); ?>
<?php if (!empty($layoutPublic)): ?>
</main>
<?php else: ?>
        <footer class="app-footer text-muted small">
            <?= e((string) config('app_name')) ?> · <?= date('Y') ?> · Times shown in Pakistan Standard Time
        </footer>
    </main>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(app_url('assets/js/app.js')) ?>?v=2"></script>
<?php if (!empty($pageScripts)) { echo $pageScripts; } ?>
</body>
</html>
