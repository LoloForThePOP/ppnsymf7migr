(() => {
  if (window.ProponLocationPicker) {
    return;
  }

  const DEFAULT_OPTIONS = {
    defaultRadius: 10,
    minRadius: 1,
    maxRadius: 200,
    storageKey: 'searchOverlayLocationV1',
    cookieKey: 'search_pref_location',
    cookieMaxAge: 60 * 60 * 24 * 30,
    loadPersisted: true,
    persistOnApply: true,
  };

  const CURRENT_LOCATION_ICON = '<svg class="search-overlay__btn-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M12 2c.55 0 1 .45 1 1v1.84c3.19.44 5.72 2.97 6.16 6.16H21c.55 0 1 .45 1 1s-.45 1-1 1h-1.84c-.44 3.19-2.97 5.72-6.16 6.16V21c0 .55-.45 1-1 1s-1-.45-1-1v-1.84c-3.19-.44-5.72-2.97-6.16-6.16H3c-.55 0-1-.45-1-1s.45-1 1-1h1.84c.44-3.19 2.97-5.72 6.16-6.16V3c0-.55.45-1 1-1Zm0 4.77A5.23 5.23 0 0 0 6.77 12 5.23 5.23 0 0 0 12 17.23 5.23 5.23 0 0 0 17.23 12 5.23 5.23 0 0 0 12 6.77Zm0 3.26a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/></svg>';

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const escapeRegExp = (value) => value.replace(/[-[\]/{}()*+?.\\^$|]/g, '\\$&');

  const formatLocationLabel = (location) => {
    if (!location) return '';
    if (location.source === 'me') {
      return 'Autour de moi';
    }
    const label = typeof location.label === 'string' ? location.label.trim() : '';
    return label || 'Lieu sélectionné';
  };

  const truncateText = (value, max = 42) => {
    if (typeof value !== 'string') return '';
    const clean = value.trim();
    if (clean.length <= max) return clean;
    return `${clean.slice(0, max - 3).trimEnd()}...`;
  };

  const normalizeLocation = (location, options = DEFAULT_OPTIONS) => {
    if (!location || typeof location !== 'object') {
      return null;
    }

    const lat = Number(location.lat);
    const lng = Number(location.lng);
    const radiusRaw = location.radius ?? options.defaultRadius;
    const radius = Number(radiusRaw);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return null;
    }

    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      return null;
    }

    const normalized = {
      lat,
      lng,
      radius: Number.isFinite(radius)
        ? clamp(radius, options.minRadius, options.maxRadius)
        : options.defaultRadius,
      label: '',
      source: location.source === 'me' ? 'me' : 'place',
    };

    const label = typeof location.label === 'string' ? location.label.trim() : '';
    if (label) {
      normalized.label = label.slice(0, 120);
    }

    return normalized;
  };

  const writeLocationCookie = (location, options) => {
    const value = `${location.lat.toFixed(6)}|${location.lng.toFixed(6)}|${Math.round(location.radius)}`;
    document.cookie = `${options.cookieKey}=${encodeURIComponent(value)}; Path=/; Max-Age=${options.cookieMaxAge}; SameSite=Lax`;
  };

  const clearLocationCookie = (options) => {
    document.cookie = `${options.cookieKey}=; Path=/; Max-Age=0; SameSite=Lax`;
  };

  const parseLocationCookie = (options) => {
    const escapedKey = escapeRegExp(options.cookieKey);
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${escapedKey}=([^;]*)`));
    if (!match || !match[1]) {
      return null;
    }

    const decoded = decodeURIComponent(match[1]);
    const parts = decoded.split(/[|,]/).map((part) => part.trim());
    if (parts.length < 2) {
      return null;
    }

    return normalizeLocation(
      {
        lat: parts[0],
        lng: parts[1],
        radius: parts[2],
        source: 'place',
      },
      options
    );
  };

  const persistLocation = (location, options = DEFAULT_OPTIONS) => {
    const normalized = normalizeLocation(location, options);
    if (!normalized) {
      return null;
    }

    try {
      window.localStorage.setItem(options.storageKey, JSON.stringify(normalized));
    } catch {
      // Ignore storage failures.
    }

    writeLocationCookie(normalized, options);
    return normalized;
  };

  const loadPersistedLocation = (options = DEFAULT_OPTIONS) => {
    try {
      const raw = window.localStorage.getItem(options.storageKey);
      if (raw) {
        const parsed = JSON.parse(raw);
        const normalized = normalizeLocation(parsed, options);
        if (normalized) {
          return normalized;
        }
      }
    } catch {
      // Ignore storage failures.
    }

    return parseLocationCookie(options);
  };

  const clearPersistedLocation = (options = DEFAULT_OPTIONS) => {
    try {
      window.localStorage.removeItem(options.storageKey);
    } catch {
      // Ignore storage failures.
    }

    clearLocationCookie(options);
  };

  const cloneLocation = (location) => (location ? { ...location } : null);

  const create = (root, userOptions = {}) => {
    if (!root) {
      return null;
    }

    const options = {
      ...DEFAULT_OPTIONS,
      ...userOptions,
    };
    options.defaultRadius = clamp(Number(options.defaultRadius) || 10, options.minRadius, options.maxRadius);

    const scope = root.closest('[data-location-picker-scope]') || root;
    const input = root.querySelector('[data-location-input]');
    const inputWrap = root.querySelector('[data-location-input-wrap]') || input?.closest('.search-overlay__location-input');
    const applyBtn = root.querySelector('[data-location-apply]');
    const resetBtn = scope.querySelector('[data-location-reset]');
    const useMeBtn = root.querySelector('[data-location-use-me]');
    const statusEl = root.querySelector('[data-location-status]');
    const inputClearBtn = root.querySelector('[data-location-clear-input]');
    const noteEl = root.querySelector('[data-location-note]');
    const actionsEl = root.querySelector('[data-location-actions]') || root.querySelector('.search-overlay__location-actions');
    const radiusWrap = root.querySelector('[data-location-radius-wrap]');
    const radiusInput = root.querySelector('[data-location-radius-input]');
    const radiusValueEl = root.querySelector('[data-location-radius-value]');

    const detachers = [];

    const state = {
      pendingLocation: null,
      activeLocation: null,
      lastRadius: options.defaultRadius,
      hasPlacesInit: false,
    };

    const emitState = () => {
      if (typeof options.onStateChange !== 'function') {
        return;
      }

      options.onStateChange({
        activeLocation: cloneLocation(state.activeLocation),
        pendingLocation: cloneLocation(state.pendingLocation),
      });
    };

    const setStatus = (message, tone = '') => {
      if (!statusEl) return;
      const text = typeof message === 'string' ? message.trim() : '';
      statusEl.textContent = text;

      if (!text) {
        delete statusEl.dataset.state;
        return;
      }

      if (tone) {
        statusEl.dataset.state = tone;
      } else {
        delete statusEl.dataset.state;
      }
    };

    const updateRadiusLabel = (value) => {
      const number = Number(value);
      if (!Number.isFinite(number)) {
        return;
      }
      const normalized = clamp(number, options.minRadius, options.maxRadius);
      state.lastRadius = normalized;
      if (radiusValueEl) {
        radiusValueEl.textContent = String(normalized);
      }
    };

    const syncUi = () => {
      const hasPending = !!state.pendingLocation;
      const activeLocation = state.activeLocation;
      const pendingLocation = state.pendingLocation;

      if (radiusWrap) {
        radiusWrap.classList.toggle('is-hidden', !(hasPending || !!activeLocation));
      }

      if (radiusInput) {
        radiusInput.disabled = !(hasPending || !!activeLocation);
      }

      if (applyBtn) {
        applyBtn.disabled = !hasPending;
        applyBtn.classList.toggle('is-hidden', !hasPending);
        if (hasPending) {
          const pendingLabel = truncateText(formatLocationLabel(pendingLocation), 26);
          applyBtn.textContent = `Appliquer: ${pendingLabel}`;
          applyBtn.setAttribute('aria-label', `Appliquer la localisation (${formatLocationLabel(pendingLocation)})`);
        } else {
          applyBtn.textContent = 'Appliquer la localisation';
          applyBtn.setAttribute('aria-label', 'Appliquer la localisation');
        }
      }

      if (noteEl) {
        noteEl.classList.toggle('d-none', !activeLocation);
      }

      if (inputWrap) {
        const hideInput = pendingLocation?.source === 'me' || activeLocation?.source === 'me';
        inputWrap.classList.toggle('is-hidden', !!hideInput);
      }

      if (actionsEl) {
        const hasStatus = !!(statusEl?.textContent || '').trim();
        const isMeState = pendingLocation?.source === 'me' || activeLocation?.source === 'me';
        const showActions = !inputWrap?.classList.contains('is-hidden') || hasStatus || isMeState;
        actionsEl.classList.toggle('is-hidden', !showActions);
      }

      if (useMeBtn) {
        const hideMe = (!actionsEl || actionsEl.classList.contains('is-hidden'))
          || pendingLocation?.source === 'place'
          || activeLocation?.source === 'place';
        useMeBtn.classList.toggle('d-none', !!hideMe);
        const meActive = activeLocation?.source === 'me' || pendingLocation?.source === 'me';
        useMeBtn.innerHTML = `${CURRENT_LOCATION_ICON}${meActive ? 'Autour de moi (activé)' : 'Autour de moi'}`;
      }

      if (inputClearBtn) {
        const hasValue = !!input?.value.trim();
        const hideClear = !hasValue || inputWrap?.classList.contains('is-hidden');
        inputClearBtn.classList.toggle('d-none', !!hideClear);
      }

      emitState();
    };

    const setPendingLocation = (location, { dirty = true } = {}) => {
      if (!location) {
        state.pendingLocation = null;
        if (!dirty) {
          syncUi();
          return;
        }
        syncUi();
        return;
      }

      const normalized = normalizeLocation(location, {
        ...options,
        defaultRadius: state.lastRadius,
      });

      if (!normalized) {
        return;
      }

      state.pendingLocation = {
        ...normalized,
        radius: normalized.radius ?? state.lastRadius,
      };

      if (input && state.pendingLocation.source !== 'me') {
        input.value = state.pendingLocation.label || input.value || '';
      }

      if (radiusInput) {
        radiusInput.value = String(state.pendingLocation.radius);
        updateRadiusLabel(state.pendingLocation.radius);
      }

      syncUi();
    };

    const applyPending = () => {
      if (state.pendingLocation) {
        state.activeLocation = {
          ...state.pendingLocation,
        };
        state.pendingLocation = null;

        if (options.persistOnApply !== false) {
          persistLocation(state.activeLocation, options);
        }
      }

      syncUi();

      if (typeof options.onApply === 'function') {
        options.onApply(cloneLocation(state.activeLocation));
      }

      return cloneLocation(state.activeLocation);
    };

    const clearLocation = ({ keepRadius = false, clearPersisted = true, emitReset = true } = {}) => {
      state.pendingLocation = null;
      state.activeLocation = null;

      if (input) {
        input.value = '';
      }

      if (radiusInput) {
        const targetRadius = keepRadius ? state.lastRadius : options.defaultRadius;
        radiusInput.value = String(targetRadius);
        updateRadiusLabel(targetRadius);
        if (!keepRadius) {
          state.lastRadius = options.defaultRadius;
        }
      }

      setStatus('');

      if (clearPersisted) {
        clearPersistedLocation(options);
      }

      syncUi();

      if (emitReset && typeof options.onReset === 'function') {
        options.onReset();
      }
    };

    const useCurrentLocation = () => {
      if (!navigator.geolocation) {
        setStatus('Géolocalisation indisponible.', 'error');
        return;
      }

      setStatus('Localisation en cours...', 'progress');
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setStatus('');
          setPendingLocation({
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            label: 'Autour de moi',
            source: 'me',
            radius: state.lastRadius,
          });
        },
        () => {
          setStatus('Autorisation refusée.', 'error');
        },
        { enableHighAccuracy: false, timeout: 8000 }
      );
    };

    const initPlacesAutocomplete = () => {
      if (state.hasPlacesInit || !input) {
        return;
      }

      if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
        window.setTimeout(initPlacesAutocomplete, 250);
        return;
      }

      state.hasPlacesInit = true;
      const autocomplete = new google.maps.places.Autocomplete(input, {
        types: ['(regions)'],
      });

      autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (!place || !place.geometry || !place.geometry.location) {
          setStatus('Lieu non reconnu.', 'error');
          return;
        }

        setStatus('');
        setPendingLocation({
          lat: place.geometry.location.lat(),
          lng: place.geometry.location.lng(),
          label: place.name || place.formatted_address || 'Lieu sélectionné',
          source: 'place',
          radius: state.lastRadius,
        });
      });
    };

    const bind = (element, eventName, handler) => {
      if (!element) {
        return;
      }
      element.addEventListener(eventName, handler);
      detachers.push(() => element.removeEventListener(eventName, handler));
    };

    bind(input, 'input', () => {
      setStatus('');
      state.pendingLocation = null;
      syncUi();
    });

    bind(radiusInput, 'input', () => {
      const value = Number.parseInt(radiusInput.value, 10);
      if (!Number.isFinite(value)) {
        return;
      }

      updateRadiusLabel(value);

      if (state.pendingLocation) {
        state.pendingLocation.radius = clamp(value, options.minRadius, options.maxRadius);
        syncUi();
      } else if (state.activeLocation) {
        setPendingLocation({
          ...state.activeLocation,
          radius: value,
        });
      }
    });

    bind(applyBtn, 'click', (event) => {
      event.preventDefault();
      applyPending();
    });

    bind(resetBtn, 'click', (event) => {
      event.preventDefault();
      clearLocation({ keepRadius: false, clearPersisted: true, emitReset: true });
    });

    bind(useMeBtn, 'click', (event) => {
      event.preventDefault();
      useCurrentLocation();
    });

    bind(inputClearBtn, 'click', (event) => {
      event.preventDefault();
      clearLocation({ keepRadius: true, clearPersisted: true, emitReset: true });
    });

    if (options.loadPersisted !== false) {
      const persisted = loadPersistedLocation(options);
      if (persisted) {
        state.activeLocation = persisted;
        if (input && persisted.source !== 'me' && persisted.label) {
          input.value = persisted.label;
        }
        if (radiusInput) {
          radiusInput.value = String(persisted.radius);
        }
      }
    }

    if (radiusInput) {
      updateRadiusLabel(radiusInput.value || options.defaultRadius);
    }

    syncUi();
    initPlacesAutocomplete();

    return {
      getActiveLocation: () => cloneLocation(state.activeLocation),
      getPendingLocation: () => cloneLocation(state.pendingLocation),
      setPendingLocation,
      applyPending,
      clearLocation,
      setStatus,
      syncUI: syncUi,
      focusInput: () => {
        input?.focus();
      },
      setActiveLocation: (location, { persist = false } = {}) => {
        const normalized = normalizeLocation(location, options);
        if (!normalized) {
          state.activeLocation = null;
          syncUi();
          return;
        }
        state.activeLocation = normalized;
        state.pendingLocation = null;
        if (persist) {
          persistLocation(state.activeLocation, options);
        }
        syncUi();
      },
      destroy: () => {
        detachers.forEach((remove) => remove());
      },
    };
  };

  window.ProponLocationPicker = {
    create,
    normalizeLocation,
    loadPersistedLocation,
    persistLocation,
    clearPersistedLocation,
    formatLocationLabel,
  };
})();
