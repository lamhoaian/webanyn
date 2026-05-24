<script>
(function () {
    try {
        var t = localStorage.getItem('anyn_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();
</script>
<style id="theme-early">
    html { background: #faf8f9; }
    html[data-theme="dark"] { background: #131015; color-scheme: dark; }
    html[data-theme="light"] { color-scheme: light; }
</style>
