document.addEventListener('DOMContentLoaded', function () {
    const selector = document.querySelector('.js-theme-selector');
    if (!selector) {
        return;
    }

    const root = document.documentElement;
    const isAuthenticated = selector.dataset.authenticated === '1';
    const storageKey = 'propon_theme';
    const options = selector.querySelectorAll('.js-theme-option');

    const normalizeTheme = function (theme) {
        if (!theme) {
            return 'classic';
        }
        theme = theme.toString().trim();
        return theme === 'light' ? 'classic' : theme;
    };

    const setActiveOption = function (theme) {
        options.forEach(function (option) {
            const isActive = option.dataset.theme === theme;
            option.classList.toggle('active', isActive);
            const badge = option.querySelector('.js-theme-active');
            if (badge) {
                badge.classList.toggle('d-none', !isActive);
            }
        });
    };

    const applyTheme = function (theme) {
        const resolved = normalizeTheme(theme);
        root.setAttribute('data-theme', resolved);
        selector.dataset.currentTheme = resolved;
        setActiveOption(resolved);
    };

    const persistTheme = function (theme) {
        if (!isAuthenticated) {
            try {
                localStorage.setItem(storageKey, theme);
            } catch (e) {}
            return;
        }

        const url = selector.dataset.syncUrl;
        const token = selector.dataset.csrfToken;
        if (!url || !token) {
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                theme: theme,
                _token: token,
            }),
        }).catch(function () {
            // Theme is already applied locally; persistence can be retried later.
        });
    };

    const initialTheme = (function () {
        if (isAuthenticated) {
            return normalizeTheme(selector.dataset.currentTheme || 'classic');
        }
        try {
            const stored = localStorage.getItem(storageKey);
            return normalizeTheme(stored || selector.dataset.currentTheme || 'classic');
        } catch (e) {
            return normalizeTheme(selector.dataset.currentTheme || 'classic');
        }
    })();

    applyTheme(initialTheme);

    selector.addEventListener('click', function (event) {
        const target = event.target.closest('.js-theme-option');
        if (!target) {
            return;
        }

        event.preventDefault();
        const theme = normalizeTheme(target.dataset.theme);
        applyTheme(theme);
        persistTheme(theme);
    });
});
