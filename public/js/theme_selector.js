document.addEventListener('DOMContentLoaded', function () {
    const selectors = Array.from(document.querySelectorAll('.js-theme-selector'));
    if (selectors.length === 0) {
        return;
    }

    const root = document.documentElement;
    const primarySelector = selectors[0];
    const isAuthenticated = primarySelector.dataset.authenticated === '1';
    const storageKey = 'propon_theme';
    const cookieKey = 'propon_theme';
    const cookieMaxAgeSeconds = 60 * 60 * 24 * 365;
    const optionsBySelector = selectors.map(function (selector) {
        return Array.from(selector.querySelectorAll('.js-theme-option'));
    });
    const knownThemes = new Set(['classic']);
    optionsBySelector.forEach(function (options) {
        options.forEach(function (option) {
            const theme = option.dataset.theme;
            if (theme) {
                knownThemes.add(theme);
            }
        });
    });

    const normalizeTheme = function (theme) {
        if (!theme) {
            return 'classic';
        }
        theme = theme.toString().trim();
        if (theme === 'light') {
            return 'classic';
        }
        return knownThemes.has(theme) ? theme : 'classic';
    };

    const themeToneMap = new Map();
    optionsBySelector.forEach(function (options) {
        options.forEach(function (option) {
            const theme = option.dataset.theme;
            const tone = option.dataset.themeTone;
            if (theme && tone && !themeToneMap.has(theme)) {
                themeToneMap.set(theme, tone);
            }
        });
    });

    const resolveTone = function (theme) {
        const resolved = normalizeTheme(theme);
        return themeToneMap.get(resolved) || (resolved === 'dark' ? 'dark' : 'light');
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

    const updateThemeIcons = function (theme) {
        selectors.forEach(function (selector) {
            const icons = selector.querySelectorAll('.js-theme-icon');
            icons.forEach(function (icon) {
                const template = icon.dataset.iconTemplate;
                if (!template) {
                    return;
                }
                const src = template.replace('THEME', theme);
                icon.dataset.fallbackApplied = '';
                icon.src = src;

                if (!icon.dataset.fallbackBound) {
                    icon.dataset.fallbackBound = '1';
                    icon.addEventListener('error', function () {
                        if (icon.dataset.fallbackApplied === '1') {
                            return;
                        }
                        const fallback = icon.dataset.iconFallback;
                        if (!fallback) {
                            return;
                        }
                        icon.dataset.fallbackApplied = '1';
                        icon.src = fallback;
                    });
                }
            });
        });
    };

    const applyTheme = function (theme) {
        const resolved = normalizeTheme(theme);
        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-theme-tone', resolveTone(resolved));
        selectors.forEach(function (selector) {
            selector.dataset.currentTheme = resolved;
        });
        setActiveOption(resolved);
        updateThemeIcons(resolved);
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
            const resolved = normalizeTheme(theme);
            try {
                localStorage.setItem(storageKey, resolved);
            } catch (e) {}
            document.cookie = `${cookieKey}=${encodeURIComponent(resolved)}; Path=/; Max-Age=${cookieMaxAgeSeconds}; SameSite=Lax`;
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
    if (!isAuthenticated) {
        document.cookie = `${cookieKey}=${encodeURIComponent(initialTheme)}; Path=/; Max-Age=${cookieMaxAgeSeconds}; SameSite=Lax`;
    }
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
