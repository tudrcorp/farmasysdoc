<meta name="theme-color" content="#eef8fb" data-theme-color>
<meta name="color-scheme" content="light">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script>
    (function () {
        try {
            var saved = localStorage.getItem('ep-theme');
            var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.classList.toggle('ep-theme-dark', theme === 'dark');
            document.documentElement.classList.toggle('ep-theme-light', theme !== 'dark');
            document.documentElement.style.colorScheme = theme;
            var metaColor = document.querySelector('meta[data-theme-color]');
            var metaScheme = document.querySelector('meta[name="color-scheme"]');
            var metaStatus = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
            if (metaColor) metaColor.setAttribute('content', theme === 'dark' ? '#07141c' : '#eef8fb');
            if (metaScheme) metaScheme.setAttribute('content', theme);
            if (metaStatus) metaStatus.setAttribute('content', theme === 'dark' ? 'black-translucent' : 'default');
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>
<style>
    :root,
    html[data-theme="light"] {
        --ep-page-bg: #eef8fb;
    }

    html[data-theme="dark"] {
        color-scheme: dark;
        --ep-page-bg: #07141c;
    }

    * { box-sizing: border-box; }

    html, body {
        height: 100%;
        margin: 0;
        background: var(--ep-page-bg);
    }

    body {
        background: var(--ep-page-bg);
        overflow: hidden;
        -webkit-font-smoothing: antialiased;
    }

    .ep-device {
        min-height: 100dvh;
        width: 100%;
        position: relative;
    }

    @media (max-width: 900px) {
        body { overflow: auto; }
    }
</style>
<script>
    (function () {
        function apply(theme) {
            var next = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.classList.toggle('ep-theme-dark', next === 'dark');
            document.documentElement.classList.toggle('ep-theme-light', next !== 'dark');
            document.documentElement.style.colorScheme = next;

            var metaColor = document.querySelector('meta[data-theme-color]');
            var metaScheme = document.querySelector('meta[name="color-scheme"]');
            var metaStatus = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
            if (metaColor) metaColor.setAttribute('content', next === 'dark' ? '#07141c' : '#eef8fb');
            if (metaScheme) metaScheme.setAttribute('content', next);
            if (metaStatus) metaStatus.setAttribute('content', next === 'dark' ? 'black-translucent' : 'default');

            try {
                localStorage.setItem('ep-theme', next);
            } catch (e) {}

            document.dispatchEvent(new CustomEvent('ep-theme-changed', { detail: { dark: next === 'dark' } }));
        }

        window.epTheme = {
            isDark: function () {
                return document.documentElement.getAttribute('data-theme') === 'dark';
            },
            apply: function (dark) {
                apply(dark ? 'dark' : 'light');
            },
            toggle: function () {
                apply(window.epTheme.isDark() ? 'light' : 'dark');
            },
        };

        document.addEventListener('livewire:navigated', function () {
            try {
                var saved = localStorage.getItem('ep-theme');
                apply(saved === 'dark' ? 'dark' : 'light');
            } catch (e) {}
        });
    })();
</script>
