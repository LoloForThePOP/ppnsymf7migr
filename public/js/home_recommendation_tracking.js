(function () {
    var trackedImpressions = new Set();
    var trackedClicks = new Set();

    function parsePosition(raw) {
        var parsed = Number.parseInt(raw, 10);
        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    function buildKey(type, eventUrl, placement, position) {
        return [type, eventUrl, placement, String(position)].join('|');
    }

    function postEvent(eventUrl, payload, preferBeacon) {
        var body = JSON.stringify(payload);

        if (preferBeacon && typeof navigator.sendBeacon === 'function') {
            var blob = new Blob([body], { type: 'application/json' });
            navigator.sendBeacon(eventUrl, blob);
            return;
        }

        fetch(eventUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            keepalive: true,
            body: body
        }).catch(function () {
            // Tracking must stay best-effort and silent.
        });
    }

    function buildMeta(card) {
        var eventUrl = card.dataset.recEventUrl || '';
        var placement = (card.dataset.recPlacement || '').trim();
        var position = parsePosition(card.dataset.recPosition || '');

        if (!eventUrl || !placement || position === null) {
            return null;
        }

        return {
            eventUrl: eventUrl,
            placement: placement,
            position: position
        };
    }

    function trackImpression(card) {
        var meta = buildMeta(card);
        if (!meta) {
            return;
        }

        var key = buildKey('rec_impression', meta.eventUrl, meta.placement, meta.position);
        if (trackedImpressions.has(key)) {
            return;
        }
        trackedImpressions.add(key);

        postEvent(meta.eventUrl, {
            type: 'rec_impression',
            meta: {
                placement: meta.placement,
                position: meta.position
            }
        }, false);
    }

    function trackClick(card) {
        var meta = buildMeta(card);
        if (!meta) {
            return;
        }

        var key = buildKey('rec_click', meta.eventUrl, meta.placement, meta.position);
        if (trackedClicks.has(key)) {
            return;
        }
        trackedClicks.add(key);

        postEvent(meta.eventUrl, {
            type: 'rec_click',
            meta: {
                placement: meta.placement,
                position: meta.position
            }
        }, true);
    }

    function bindRecommendationTracking() {
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-rec-track="1"]'));
        if (cards.length === 0) {
            return;
        }

        cards.forEach(function (card) {
            var link = card.querySelector('a.stretched-link[href]');
            if (!link) {
                return;
            }
            link.addEventListener('click', function () {
                trackClick(card);
            }, { capture: true });
        });

        if (!('IntersectionObserver' in window)) {
            cards.forEach(trackImpression);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.45) {
                    var card = entry.target;
                    trackImpression(card);
                    observer.unobserve(card);
                }
            });
        }, {
            threshold: [0.45]
        });

        cards.forEach(function (card) {
            observer.observe(card);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindRecommendationTracking);
    } else {
        bindRecommendationTracking();
    }
})();
