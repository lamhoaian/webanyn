</div> <!-- Đóng thẻ div class="container" ở file header -->
<?php if (!defined('ANYN_DISCORD_URL')) { require_once __DIR__ . '/site_links.php'; } ?>

<footer class="site-footer">
    <span style="color:var(--text-3);font-size:13px;">© <?= date('Y') ?> ANYN</span>
    <a href="<?= ANYN_DISCORD_URL ?>" class="discord-pill" target="_blank" rel="noopener noreferrer">
        <i class="fa-brands fa-discord" style="font-size:16px;color:#5865F2;"></i>
        Join our Discord
    </a>
</footer>

</body>
</html>
