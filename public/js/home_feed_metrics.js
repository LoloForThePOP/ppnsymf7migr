(function () {
    var SESSION_METRICS_KEY = "pp_home_feed_metrics_v1";
    var SESSION_MAX_ITEMS = 800;
    var IMPRESSION_TYPE = "home_feed_impression";
    var CLICK_TYPE = "home_feed_click";

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
                if (!key || key.length > 260) {
                    return;
                }

                var value = Number(parsed[key] || 0);
                if (Number.isFinite(value) && value > 0) {
                    map[key] = value;
                }
            });

            return map;
        } catch (error) {
            return {};
        }
    }

    function readSessionMap() {
        try {
            return safeParseSessionMap(sessionStorage.getItem(SESSION_METRICS_KEY));
        } catch (error) {
            return {};
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

    function saveSessionMap(map) {
        try {
            sessionStorage.setItem(SESSION_METRICS_KEY, JSON.stringify(trimSessionMap(map)));
        } catch (error) {
            // ignore storage failures
        }
    }

    function markSessionUnique(token) {
        var normalizedToken = String(token || "").trim().toLowerCase();
        if (!normalizedToken) {
            return false;
        }

        var map = readSessionMap();
        if (Object.prototype.hasOwnProperty.call(map, normalizedToken)) {
            return false;
        }

        map[normalizedToken] = Date.now();
        saveSessionMap(map);
        return true;
    }

    function toBoundedInt(value, fallback, min, max) {
        var parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return fallback;
        }

        var rounded = Math.round(parsed);
        if (rounded < min || rounded > max) {
            return fallback;
        }

        return rounded;
    }

    function resolveEventUrl(card) {
        var direct = String(card.getAttribute("data-pp-event-url") || "").trim();
        if (direct) {
            return direct;
        }

        var stringId = String(card.getAttribute("data-pp-string-id") || "").trim();
        if (!stringId) {
            return "";
        }

        return "/pp/" + encodeURIComponent(stringId) + "/event";
    }

    function sendEvent(eventUrl, payload) {
        if (!eventUrl || !payload) {
            return;
        }

        var body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([body], { type: "application/json" });
                if (navigator.sendBeacon(eventUrl, blob)) {
                    return;
                }
            } catch (error) {
                // fallback to fetch below
            }
        }

        fetch(eventUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: body,
            keepalive: true
        }).catch(function () {
            // telemetry failures are non-blocking
        });
    }

    function buildPayload(type, meta) {
        return {
            type: type,
            meta: {
                placement: "homepage",
                block: meta.block,
                block_position: meta.blockPosition,
                card_position: meta.cardPosition
            }
        };
    }

    function trackImpression(meta) {
        var token = [
            "imp",
            meta.block,
            meta.blockPosition,
            meta.cardPosition,
            meta.eventUrl
        ].join("|");

        if (!markSessionUnique(token)) {
            return;
        }

        sendEvent(meta.eventUrl, buildPayload(IMPRESSION_TYPE, meta));
    }

    function trackClick(meta) {
        trackImpression(meta);

        var token = [
            "clk",
            meta.block,
            meta.blockPosition,
            meta.cardPosition,
            meta.eventUrl
        ].join("|");

        if (!markSessionUnique(token)) {
            return;
        }

        sendEvent(meta.eventUrl, buildPayload(CLICK_TYPE, meta));
    }

    function resolveMeta(card, blockKey, blockPosition, cardPosition) {
        var normalizedBlockKey = String(blockKey || "").trim().toLowerCase();
        if (!/^[a-z0-9_-]{2,80}$/.test(normalizedBlockKey)) {
            return null;
        }

        var eventUrl = resolveEventUrl(card);
        if (!eventUrl) {
            return null;
        }

        return {
            block: normalizedBlockKey,
            blockPosition: toBoundedInt(blockPosition, 1, 1, 20),
            cardPosition: toBoundedInt(cardPosition, 1, 1, 50),
            eventUrl: eventUrl
        };
    }

    function bindTracking() {
        var blockRoots = document.querySelectorAll("[data-home-feed-block-key]");
        if (!blockRoots.length) {
            return;
        }

        var observer = null;
        var metaByCard = new WeakMap();

        if (typeof IntersectionObserver === "function") {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting || entry.intersectionRatio < 0.45) {
                        return;
                    }

                    var meta = metaByCard.get(entry.target);
                    if (!meta) {
                        return;
                    }

                    trackImpression(meta);
                    observer.unobserve(entry.target);
                });
            }, {
                threshold: [0.45]
            });
        }

        blockRoots.forEach(function (blockRoot) {
            var blockKey = blockRoot.getAttribute("data-home-feed-block-key");
            var blockPosition = blockRoot.getAttribute("data-home-feed-block-position");
            var cards = blockRoot.querySelectorAll(".project-card");

            cards.forEach(function (card, index) {
                var meta = resolveMeta(card, blockKey, blockPosition, index + 1);
                if (!meta) {
                    return;
                }

                var link = card.querySelector("a.stretched-link[href]");
                if (link) {
                    link.addEventListener("click", function () {
                        trackClick(meta);
                    }, {
                        capture: true,
                        passive: true
                    });
                }

                if (observer) {
                    metaByCard.set(card, meta);
                    observer.observe(card);
                } else {
                    trackImpression(meta);
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindTracking);
    } else {
        bindTracking();
    }
})();
