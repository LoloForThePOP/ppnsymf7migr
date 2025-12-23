document.addEventListener('DOMContentLoaded', function () {
    const selectors = Array.from(document.querySelectorAll('.js-theme-selector'));
    if (selectors.length === 0) {
        return;
    }

    const root = document.documentElement;
    const primarySelector = selectors[0];
    const isAuthenticated = primarySelector.dataset.authenticated === '1';
    const storageKey = 'propon_theme';
    const optionsBySelector = selectors.map(function (selector) {
        return Array.from(selector.querySelectorAll('.js-theme-option'));
    });

    const normalizeTheme = function (theme) {
        if (!theme) {
            return 'classic';
        }
        theme = theme.toString().trim();
        return theme === 'light' ? 'classic' : theme;
    };

    const setActiveOption = function (theme) {
        optionsBySelector.forEach(function (options) {
            options.forEach(function (option) {
                const isActive = option.dataset.theme === theme;
                option.classList.toggle('active', isActive);
                const badge = option.querySelector('.js-theme-active');
                if (badge) {
                    badge.classList.toggle('d-none', !isActive);
                }
            });
        });
    };

    const applyTheme = function (theme) {
        const resolved = normalizeTheme(theme);
        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-theme-variant', resolved === 'classic' ? 'classic' : 'custom');
        selectors.forEach(function (selector) {
            selector.dataset.currentTheme = resolved;
        });
        setActiveOption(resolved);
        if (window.ProponThemeFonts && typeof window.ProponThemeFonts.load === 'function') {
            window.ProponThemeFonts.load(resolved);
        }
    };

    const initThemePreviews = function () {
        selectors.forEach(function (selector) {
            const previewShells = selector.querySelectorAll('.theme-preview-shell');
            previewShells.forEach(function (shell) {
                const img = shell.querySelector('.theme-preview-img');
                const src = shell.dataset.thumbnailSrc;
                if (!img || !src) {
                    return;
                }

                img.addEventListener('load', function () {
                    shell.classList.add('is-image');
                });
                img.addEventListener('error', function () {
                    shell.classList.remove('is-image');
                });

                img.src = src;

                if (img.complete && img.naturalWidth > 0) {
                    shell.classList.add('is-image');
                }
            });
        });
    };

    const persistTheme = function (theme) {
        if (!isAuthenticated) {
            try {
                localStorage.setItem(storageKey, theme);
            } catch (e) {}
            return;
        }

        const url = primarySelector.dataset.syncUrl;
        const token = primarySelector.dataset.csrfToken;
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
            return normalizeTheme(primarySelector.dataset.currentTheme || 'classic');
        }
        try {
            const stored = localStorage.getItem(storageKey);
            return normalizeTheme(stored || primarySelector.dataset.currentTheme || 'classic');
        } catch (e) {
            return normalizeTheme(primarySelector.dataset.currentTheme || 'classic');
        }
    })();

    applyTheme(initialTheme);
    initThemePreviews();

    selectors.forEach(function (selector) {
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
});
