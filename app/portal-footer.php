    </div>
</main>
<footer class="portal-footer"><div class="portal-container"><span>سامانه داخلی <?= e(site_setting('site_name')) ?></span><a href="<?= e($portalRoot ?? '') ?>index.php">مشاهده وب‌سایت عمومی <span aria-hidden="true">↖</span></a></div></footer>
<?php if (!empty($extraScripts) && is_array($extraScripts)): foreach ($extraScripts as $script): ?>
<script src="<?= e($portalRoot ?? '') ?><?= e($script) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
