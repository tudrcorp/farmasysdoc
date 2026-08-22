<meta name="theme-color" content="{{ ($shopTheme ?? 'light') === 'dark' ? '#061215' : '#f2f9f9' }}" data-shop-theme-color>
<meta name="color-scheme" content="light dark">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<script>
    /* Aplica el tema antes del primer pintado y deja cookie para Livewire navigate. */
    (function () {
        try {
            var saved = localStorage.getItem('fd-shop-theme');
            var theme = saved === 'dark' || saved === 'light'
                ? saved
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

            var root = document.documentElement;
            root.setAttribute('data-shop-theme', theme);
            root.classList.toggle('dark', theme === 'dark');
            root.style.colorScheme = theme;

            var meta = document.querySelector('meta[data-shop-theme-color]');
            if (meta) meta.setAttribute('content', theme === 'dark' ? '#061215' : '#f2f9f9');

            document.cookie = 'fd-shop-theme=' + theme
                + '; Path=/; Max-Age=31536000; SameSite=Lax'
                + (location.protocol === 'https:' ? '; Secure' : '');
        } catch (e) {
            document.documentElement.setAttribute('data-shop-theme', 'light');
        }
    })();
</script>
