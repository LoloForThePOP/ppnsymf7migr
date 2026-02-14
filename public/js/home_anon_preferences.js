(function () {
    var STORAGE_KEY = "pp_anon_profile_v1";
    var COOKIE_KEY = "anon_pref_categories";
    var MAX_BUCKETS = 20;
    var MAX_COOKIE_CATEGORIES = 6;
    var COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

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
                    counts[key] = Math.min(500, Math.round(value));
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

    function readProfile() {
        return safeParseProfile(localStorage.getItem(STORAGE_KEY));
    }

    function saveProfile(profile) {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                counts: profile.counts,
                updatedAt: Date.now()
            })
        );
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

    function applyCardCategories(profile, cardElement) {
        if (!cardElement) {
            return false;
        }

        var raw = (cardElement.getAttribute("data-pp-categories") || "").trim();
        if (!raw) {
            return false;
        }

        var updated = false;
        raw.split(",").forEach(function (token) {
            var slug = token.trim().toLowerCase();
            if (!/^[a-z0-9_-]{1,40}$/.test(slug)) {
                return;
            }

            var current = Number(profile.counts[slug] || 0);
            profile.counts[slug] = current + 1;
            updated = true;
        });

        return updated;
    }

    function bindTracking() {
        var profile = readProfile();
        syncCookie(profile);

        document.addEventListener(
            "click",
            function (event) {
                var link = event.target.closest(".project-card a.stretched-link[href]");
                if (!link) {
                    return;
                }

                var card = link.closest(".project-card");
                if (!applyCardCategories(profile, card)) {
                    return;
                }

                trimProfile(profile);
                saveProfile(profile);
                syncCookie(profile);
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

