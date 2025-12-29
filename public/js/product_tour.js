(function() {
    "use strict";

    function safeJsonParse(raw, fallback) {
        if (!raw) {
            return fallback;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function getStorage(type) {
        if (type === "local") {
            return window.localStorage;
        }
        return window.sessionStorage;
    }

    function getStorageKey(id) {
        return "propon_tour_" + id;
    }

    function resolveStorage(root) {
        var storage = root.dataset.tourStorage || "auto";
        var auth = root.dataset.tourAuth === "1";
        if (storage === "auto") {
            return auth ? "server" : "session";
        }
        return storage;
    }

    function isSeen(root, storageType, version) {
        if (storageType === "server") {
            return root.dataset.tourSeen === "1";
        }
        try {
            var storage = getStorage(storageType);
            return storage.getItem(getStorageKey(root.dataset.tourId || "")) === version;
        } catch (e) {
            return false;
        }
    }

    function markSeen(root, storageType, version) {
        if (storageType !== "server") {
            try {
                var storage = getStorage(storageType);
                storage.setItem(getStorageKey(root.dataset.tourId || ""), version);
            } catch (e) {
                // ignore storage failures
            }
            return;
        }

        var updateUrl = root.dataset.tourUpdateUrl || "";
        var csrfToken = root.dataset.tourCsrfToken || "";
        var tourId = root.dataset.tourId || "";

        if (!updateUrl || !csrfToken || !tourId) {
            return;
        }

        fetch(updateUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                _token: csrfToken,
                tour: tourId,
                version: version
            })
        }).catch(function() {
            // no-op
        });
    }

    function ProductTour(root) {
        this.root = root;
        this.steps = safeJsonParse(root.dataset.tourSteps, []);
        this.version = root.dataset.tourVersion || "v1";
        this.storageType = resolveStorage(root);
        this.currentIndex = 0;
        this.blocker = null;
        this.spotlight = null;
        this.pulseDisc = null;
        this.card = null;
        this.titleEl = null;
        this.bodyEl = null;
        this.buttonEl = null;
        this.onResize = null;
        this.onKeyDown = null;
    }

    ProductTour.prototype.start = function() {
        if (!Array.isArray(this.steps) || this.steps.length === 0) {
            return false;
        }
        if (isSeen(this.root, this.storageType, this.version)) {
            return false;
        }

        this.buildUi();
        this.showStep(0);
        this.attachListeners();
        document.documentElement.classList.add("tour-lock");
        document.body.classList.add("tour-lock");
        return true;
    };

    ProductTour.prototype.buildUi = function() {
        this.blocker = document.createElement("div");
        this.blocker.className = "tour-blocker";

        this.spotlight = document.createElement("div");
        this.spotlight.className = "tour-spotlight";

        this.pulseDisc = document.createElement("div");
        this.pulseDisc.className = "tour-pulse-disc";

        this.card = document.createElement("div");
        this.card.className = "tour-card";
        this.card.setAttribute("role", "dialog");
        this.card.setAttribute("aria-modal", "true");

        this.titleEl = document.createElement("div");
        this.titleEl.className = "tour-card__title";

        this.bodyEl = document.createElement("div");
        this.bodyEl.className = "tour-card__body";

        var actions = document.createElement("div");
        actions.className = "tour-card__actions";

        this.buttonEl = document.createElement("button");
        this.buttonEl.type = "button";
        this.buttonEl.className = "tour-ok-btn";
        actions.appendChild(this.buttonEl);

        this.card.appendChild(this.titleEl);
        this.card.appendChild(this.bodyEl);
        this.card.appendChild(actions);

        document.body.appendChild(this.blocker);
        document.body.appendChild(this.spotlight);
        document.body.appendChild(this.pulseDisc);
        document.body.appendChild(this.card);
    };

    ProductTour.prototype.attachListeners = function() {
        var self = this;

        this.buttonEl.addEventListener("click", function() {
            self.nextStep();
        });

        this.onResize = function() {
            self.positionStep();
        };
        window.addEventListener("resize", this.onResize);

        this.onKeyDown = function(e) {
            if (e.key === "Escape") {
                e.preventDefault();
                return;
            }
            if (e.key === "Tab") {
                e.preventDefault();
                self.buttonEl.focus();
            }
        };
        document.addEventListener("keydown", this.onKeyDown);
    };

    ProductTour.prototype.showStep = function(index) {
        if (index < 0 || index >= this.steps.length) {
            this.finish();
            return;
        }

        this.currentIndex = index;
        var step = this.steps[index];
        var target = step.target ? this.resolveTarget(step.target) : null;

        if (!target) {
            this.nextStep();
            return;
        }

        var title = step.title || "";
        var body = step.body || "";

        this.titleEl.textContent = title;
        this.bodyEl.textContent = body;

        var finishLabel = this.root.dataset.tourFinishLabel || "Terminer";
        var okLabel = this.root.dataset.tourOkLabel || "Ok";
        var isLast = index === this.steps.length - 1;

        this.buttonEl.textContent = isLast ? finishLabel : okLabel;

        this.positionStep();
        this.buttonEl.focus();
    };

    ProductTour.prototype.positionStep = function() {
        var step = this.steps[this.currentIndex];
        if (!step || !step.target) {
            return;
        }

        var target = this.resolveTarget(step.target);
        if (!target) {
            return;
        }

        var rect = target.getBoundingClientRect();
        var pad = 8;
        var spotlightTop = rect.top - pad;
        var spotlightLeft = rect.left - pad;
        var spotlightWidth = rect.width + pad * 2;
        var spotlightHeight = rect.height + pad * 2;
        var radius = 12;
        var computedRadius = window.getComputedStyle(target).borderRadius;
        if (computedRadius) {
            var parsed = parseFloat(computedRadius);
            if (!Number.isNaN(parsed)) {
                radius = parsed + pad;
            }
        }

        this.spotlight.style.top = spotlightTop + "px";
        this.spotlight.style.left = spotlightLeft + "px";
        this.spotlight.style.width = spotlightWidth + "px";
        this.spotlight.style.height = spotlightHeight + "px";
        this.spotlight.style.borderRadius = radius + "px";

        var cardRect = this.card.getBoundingClientRect();
        var viewportWidth = window.innerWidth;
        var viewportHeight = window.innerHeight;
        var margin = 14;

        var top = rect.bottom + margin;
        if (top + cardRect.height > viewportHeight) {
            top = rect.top - cardRect.height - margin;
        }
        top = clamp(top, margin, viewportHeight - cardRect.height - margin);

        var left = rect.left + rect.width / 2 - cardRect.width / 2;
        left = clamp(left, margin, viewportWidth - cardRect.width - margin);

        this.card.style.top = top + "px";
        this.card.style.left = left + "px";

        if (this.pulseDisc) {
            var discSize = this.pulseDisc.offsetWidth || 18;
            var targetCenterX = rect.left + rect.width / 2;
            var targetCenterY = rect.top + rect.height / 2;
            var cardCenterX = left + cardRect.width / 2;
            var cardCenterY = top + cardRect.height / 2;
            var ratio = 0.2;
            var discCenterX = targetCenterX + (cardCenterX - targetCenterX) * ratio;
            var discCenterY = targetCenterY + (cardCenterY - targetCenterY) * ratio;

            var discLeft = clamp(discCenterX - discSize / 2, 8, viewportWidth - discSize - 8);
            var discTop = clamp(discCenterY - discSize / 2, 8, viewportHeight - discSize - 8);

            this.pulseDisc.style.left = discLeft + "px";
            this.pulseDisc.style.top = discTop + "px";
        }
    };

    ProductTour.prototype.resolveTarget = function(selector) {
        var nodes = document.querySelectorAll(selector);
        if (!nodes.length) {
            return null;
        }

        for (var i = 0; i < nodes.length; i += 1) {
            var el = nodes[i];
            var style = window.getComputedStyle(el);
            if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") {
                continue;
            }
            if (el.getClientRects().length === 0) {
                continue;
            }
            return el;
        }

        return null;
    };

    ProductTour.prototype.nextStep = function() {
        var nextIndex = this.currentIndex + 1;
        if (nextIndex >= this.steps.length) {
            this.finish();
            return;
        }
        this.showStep(nextIndex);
    };

    ProductTour.prototype.finish = function() {
        markSeen(this.root, this.storageType, this.version);

        if (this.blocker) {
            this.blocker.remove();
        }
        if (this.spotlight) {
            this.spotlight.remove();
        }
        if (this.pulseDisc) {
            this.pulseDisc.remove();
        }
        if (this.card) {
            this.card.remove();
        }

        if (this.onResize) {
            window.removeEventListener("resize", this.onResize);
        }
        if (this.onKeyDown) {
            document.removeEventListener("keydown", this.onKeyDown);
        }

        document.documentElement.classList.remove("tour-lock");
        document.body.classList.remove("tour-lock");
    };

    function initTours() {
        var roots = document.querySelectorAll(".js-product-tour");
        if (!roots.length) {
            return;
        }
        for (var i = 0; i < roots.length; i += 1) {
            var root = roots[i];
            var tour = new ProductTour(root);
            root.__productTour = tour;
            if (tour.start()) {
                break;
            }
        }
    }

    window.ProponProductTour = {
        start: function(id) {
            var root = document.querySelector('.js-product-tour[data-tour-id="' + id + '"]');
            if (!root) {
                return;
            }
            var tour = root.__productTour || new ProductTour(root);
            tour.start();
        }
    };

    document.addEventListener("DOMContentLoaded", initTours);
})();
