(function () {
    var key = 'securevote-demo-theme';
    var theme = 'dark';
    try {
        var saved = localStorage.getItem(key);
        if (saved === 'light' || saved === 'dark') theme = saved;
        else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) theme = 'light';
    } catch (_) { /* Private browsing may disable storage. */ }

    function apply(next) {
        theme = next;
        document.documentElement.dataset.theme = next;
        var toggle = document.querySelector('.theme-toggle');
        if (!toggle) return;
        var light = next === 'light';
        toggle.setAttribute('aria-label', light ? 'Switch to dark mode' : 'Switch to light mode');
        toggle.setAttribute('aria-pressed', String(light));
        toggle.querySelector('.theme-toggle-icon').textContent = light ? '☾' : '☀';
        toggle.querySelector('.theme-toggle-label').textContent = light ? 'Dark mode' : 'Light mode';
    }

    apply(theme);
    document.addEventListener('DOMContentLoaded', function () {
        apply(theme);
        document.querySelector('.theme-toggle').addEventListener('click', function () {
            apply(theme === 'dark' ? 'light' : 'dark');
            try { localStorage.setItem(key, theme); } catch (_) { /* Keep current page usable. */ }
        });
    });
}());
