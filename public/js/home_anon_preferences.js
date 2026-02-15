(function () {
    var STORAGE_KEY = "pp_anon_profile_v1";
    var COOKIE_KEY = "anon_pref_categories";
    var SESSION_TRACKED_VIEWS_KEY = "pp_anon_viewed_presentations_v1";
    var SESSION_TRACKED_CLICKS_KEY = "pp_anon_clicked_cards_v1";
    var MAX_BUCKETS = 20;
    var MAX_COOKIE_CATEGORIES = 6;
    var COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;
    var SESSION_MAX_ITEMS = 400;
    var CLICK_WEIGHT = 1;
    var VIEW_WEIGHT = 4;
    var memorySessionMaps = {};

    function safeParseProfile(raw) {
        if (!raw) {
            return { counts: {}, updatedAt: Date.now() };
        }

        try {
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") {
                return { counts: {}, updatedAt: Date.now() };
            }

            var counts = {};
            var source = parsed.counts || {};
            Object.keys(source).forEach(function (key) {
                if (!/^[a-z0-9_-]{1,40}$/.test(key)) {
                    return;
                }
                var value = Number(source[key] || 0);
                if (Number.isFinite(value) && value > 0) {
                    counts[key] = Math.min(500, Math.round(value * 1000) / 1000);
                }
            });

            return {
                counts: counts,
                updatedAt: Number(parsed.updatedAt || Date.now())
            };
        } catch (error) {
            return { counts: {}, updatedAt: Date.now() };
        }
    }

    function safeParseSessionMap(raw) {
        if (!raw) {
            return {};
        }

        try {
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") {
                return {};
            }

            var map = {};
            Object.keys(parsed).forEach(function (key) {
                if (!key || key.length > 220) {
                    return;
                }

                var timestamp = Number(parsed[key] || 0);
                if (Number.isFinite(timestamp) && timestamp > 0) {
                    map[key] = timestamp;
                }
            });

            return map;
        } catch (error) {
            return {};
        }
    }

    function readProfile() {
        try {
            return safeParseProfile(localStorage.getItem(STORAGE_KEY));
        } catch (error) {
            return { counts: {}, updatedAt: Date.now() };
        }
    }

    function saveProfile(profile) {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    counts: profile.counts,
                    updatedAt: Date.now()
                })
            );
        } catch (error) {
            // ignore storage failures
        }
    }

    function readSessionMap(storageKey) {
        try {
            return safeParseSessionMap(sessionStorage.getItem(storageKey));
        } catch (error) {
            return memorySessionMaps[storageKey] || {};
        }
    }

    function saveSessionMap(storageKey, map) {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(map));
        } catch (error) {
            memorySessionMaps[storageKey] = map;
        }
    }

    function trimSessionMap(map) {
        var keys = Object.keys(map);
        if (keys.length <= SESSION_MAX_ITEMS) {
            return map;
        }

        keys.sort(function (a, b) {
            return Number(map[b] || 0) - Number(map[a] || 0);
        });

        var keep = {};
        keys.slice(0, SESSION_MAX_ITEMS).forEach(function (key) {
            keep[key] = map[key];
        });

        return keep;
    }

    function markSessionUnique(storageKey, token) {
        var normalizedToken = String(token || "").trim().toLowerCase();
        if (!normalizedToken) {
            return false;
        }

        var map = readSessionMap(storageKey);
        if (Object.prototype.hasOwnProperty.call(map, normalizedToken)) {
            return false;
        }

        map[normalizedToken] = Date.now();
        map = trimSessionMap(map);
        saveSessionMap(storageKey, map);
        return true;
    }

    function sortedCategories(profile) {
        return Object.keys(profile.counts)
            .map(function (key) {
                return { key: key, score: Number(profile.counts[key] || 0) };
            })
            .filter(function (row) {
                return row.score > 0;
            })
            .sort(function (a, b) {
                return b.score - a.score;
            });
    }

    function trimProfile(profile) {
        var sorted = sortedCategories(profile);
        if (sorted.length <= MAX_BUCKETS) {
            return;
        }

        var keep = {};
        sorted.slice(0, MAX_BUCKETS).forEach(function (row) {
            keep[row.key] = row.score;
        });
        profile.counts = keep;
    }

    function syncCookie(profile) {
        var top = sortedCategories(profile)
            .slice(0, MAX_COOKIE_CATEGORIES)
            .map(function (row) {
                return row.key;
            });

        if (top.length === 0) {
            return;
        }

        var value = encodeURIComponent(top.join(","));
        document.cookie =
            COOKIE_KEY +
            "=" +
            value +
            "; Path=/; Max-Age=" +
            COOKIE_MAX_AGE_SECONDS +
            "; SameSite=Lax";
    }

    function applyCategories(profile, rawCategoryTokens, weight) {
        if (!rawCategoryTokens || weight <= 0) {
            return false;
        }

        var updated = false;
        rawCategoryTokens.split(",").forEach(function (token) {
            var slug = token.trim().toLowerCase();
            if (!/^[a-z0-9_-]{1,40}$/.test(slug)) {
                return;
            }

            var current = Number(profile.counts[slug] || 0);
            profile.counts[slug] = current + weight;
            updated = true;
        });

        return updated;
    }

    function persistProfile(profile) {
        trimProfile(profile);
        saveProfile(profile);
        syncCookie(profile);
    }

    function resolveLinkToken(link) {
        var rawHref = "";
        if (link && typeof link.getAttribute === "function") {
            rawHref = String(link.getAttribute("href") || "");
        }
        if (!rawHref && link && typeof link.href === "string") {
            rawHref = link.href;
        }
        rawHref = rawHref.trim();
        if (!rawHref) {
            return "";
        }
        if (rawHref === "#" || rawHref.indexOf("javascript:") === 0) {
            return "";
        }

        try {
            var url = new URL(rawHref, window.location.origin);
            return "card:" + url.pathname.toLowerCase();
        } catch (error) {
            return "card:" + rawHref.slice(0, 200).toLowerCase();
        }
    }

    function trackPresentationView(profile) {
        var trackRoot = document.querySelector("[data-pp-view-track='1'][data-pp-view-id]");
        if (!trackRoot) {
            return;
        }

        var viewId = String(trackRoot.getAttribute("data-pp-view-id") || "").trim();
        if (!viewId) {
            return;
        }

        if (!markSessionUnique(SESSION_TRACKED_VIEWS_KEY, "pp:" + viewId)) {
            return;
        }

        var categoryTokens = String(trackRoot.getAttribute("data-pp-categories") || "").trim();
        if (!applyCategories(profile, categoryTokens, VIEW_WEIGHT)) {
            return;
        }

        persistProfile(profile);
    }

    function bindTracking() {
        var profile = readProfile();
        syncCookie(profile);
        trackPresentationView(profile);

        document.addEventListener(
            "click",
            function (event) {
                var link = event.target.closest(
                    ".project-card a.stretched-link[href], .search-result-card a[href]"
                );
                if (!link) {
                    return;
                }

                var categoriesSource = link.closest("[data-pp-categories]");
                if (!categoriesSource) {
                    return;
                }

                var linkToken = resolveLinkToken(link);
                if (linkToken && !markSessionUnique(SESSION_TRACKED_CLICKS_KEY, linkToken)) {
                    return;
                }

                var rawCategories = String(categoriesSource.getAttribute("data-pp-categories") || "").trim();
                if (!applyCategories(profile, rawCategories, CLICK_WEIGHT)) {
                    return;
                }

                persistProfile(profile);
            },
            true
        );
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindTracking);
    } else {
        bindTracking();
    }
})();
