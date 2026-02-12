(() => {
  const overlay = document.getElementById('search-overlay');
  if (!overlay) return;

  const input = overlay.querySelector('#search-overlay-input');
  const resultsEl = overlay.querySelector('#search-overlay-results');
  const statusEl = overlay.querySelector('#search-overlay-status');
  const countEl = overlay.querySelector('#search-overlay-count');
  const countTotalEl = overlay.querySelector('#search-overlay-count-total');
  const countLocationEl = overlay.querySelector('#search-overlay-count-location');
  const emptyEl = overlay.querySelector('#search-overlay-empty');
  const categoriesEl = overlay.querySelector('#search-overlay-categories');
  const suggestBoxEl = overlay.querySelector('[data-search-suggest-box]');
  const recentEl = overlay.querySelector('[data-search-recent]');
  const recentListEl = overlay.querySelector('[data-search-recent-list]');
  const suggestEl = overlay.querySelector('[data-search-suggest]');
  const suggestListEl = overlay.querySelector('[data-search-suggest-list]');
  const inputWrap = overlay.querySelector('.search-overlay__input-wrap');
  const mapModal = overlay.querySelector('#search-overlay-map');
  const mapFrame = overlay.querySelector('#search-overlay-map-frame');
  const mapTitle = overlay.querySelector('#search-overlay-map-title');
  const mapLink = overlay.querySelector('#search-overlay-map-link');
  const mapCloseButtons = Array.from(overlay.querySelectorAll('[data-search-map-close]'));
  const clearInputBtn = overlay.querySelector('[data-search-clear]');
  const clearFiltersBtn = overlay.querySelector('[data-search-clear-filters]');
  const closeBtn = overlay.querySelector('[data-search-close]');
  const filtersToggleBtn = overlay.querySelector('[data-search-filters-toggle]');
  const filtersCloseTargets = Array.from(overlay.querySelectorAll('[data-search-filters-close]'));
  const moreBtn = overlay.querySelector('[data-search-more]');
  const pageEl = overlay.querySelector('#search-overlay-page');
  const paginationEl = overlay.querySelector('.search-overlay__pagination');
  const triggers = Array.from(document.querySelectorAll('[data-search-trigger]'));
  const triggerInputs = triggers.filter((el) => el.tagName === 'INPUT');
  const locationInput = overlay.querySelector('#search-location-input');
  const locationApplyBtn = overlay.querySelector('[data-search-location-apply]');
  const locationResetBtn = overlay.querySelector('[data-search-location-reset]');
  const locationMeBtn = overlay.querySelector('[data-search-location-me]');
  const locationStatusEl = overlay.querySelector('#search-location-status');
  const locationInputClearBtn = overlay.querySelector('[data-search-location-clear-input]');
  const locationNoteEl = overlay.querySelector('[data-search-location-note]');
  const locationActionsEl = overlay.querySelector('.search-overlay__location-actions');
  const locationSummaryEl = null;
  const locationSummaryLabelEl = null;
  const locationSummaryClearBtn = null;
  const locationSummaryEditBtns = [];
  const locationFieldsEl = overlay.querySelector('[data-search-location-fields]');
  const locationInputWrap = locationInput?.closest('.search-overlay__location-input');
  const radiusInput = overlay.querySelector('#search-radius-input');
  const radiusValueEl = overlay.querySelector('#search-radius-value');
  const radiusWrap = overlay.querySelector('[data-search-radius-wrap]');
  const historyEnabled = overlay.dataset.searchHistoryEnabled === '1';
  const historyUrl = overlay.dataset.searchHistoryUrl;
  const historyToken = overlay.dataset.searchHistoryCsrf;

  const minLength = 2;
  const limit = 16;
  let currentResults = [];
  let currentQuery = '';
  let currentPage = 1;
  let totalPages = 0;
  let totalCount = 0;
  let totalBase = 0;
  let categoryCounts = [];
  let activeCategories = new Set();
  let activeController = null;
  let isLoading = false;
  let pendingLocation = null;
  let activeLocation = null;
  let isEditingLocation = false;
  let pendingDirty = false;
  const defaultRadius = 10;
  let lastRadius = defaultRadius;
  const RECENT_KEY = 'searchRecentQueries';
  const RECENT_LIMIT = 5;
  const SUGGEST_LIMIT = 8;
  let suggestController = null;
  let suggestionsLocked = false;
  let hasPlacesInit = false;
  let remoteHistory = null;
  let historyLoaded = false;
  let activeSuggestionIndex = -1;
  let suggestionInputSeed = '';

  const debounce = (fn, wait = 250) => {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  };

  const setStatus = (message) => {
    if (!statusEl) return;
    if (message) {
      statusEl.textContent = message;
      statusEl.classList.remove('d-none');
    } else {
      statusEl.textContent = '';
      statusEl.classList.add('d-none');
    }
  };

  const setCountLines = (totalLabel, locationLabel) => {
    if (countTotalEl) {
      countTotalEl.textContent = totalLabel || '';
    }
    if (countLocationEl) {
      countLocationEl.textContent = locationLabel || '';
    }
    if (countEl) {
      const hasContent = (totalLabel && totalLabel.length > 0) || (locationLabel && locationLabel.length > 0);
      countEl.classList.toggle('d-none', !hasContent);
    }
  };

  const loadLocalRecentQueries = () => {
    try {
      const raw = window.localStorage.getItem(RECENT_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed.filter((value) => typeof value === 'string' && value.trim() !== '');
    } catch {
      return [];
    }
  };

  const saveLocalRecentQueries = (queries) => {
    try {
      window.localStorage.setItem(RECENT_KEY, JSON.stringify(queries));
    } catch {
      // ignore
    }
  };

  const normalizeRecentQueries = (queries) => {
    const normalized = [];
    const seen = new Set();
    queries.forEach((value) => {
      if (typeof value !== 'string') return;
      const term = value.trim();
      if (!term || term.length < minLength) return;
      const key = term.toLowerCase();
      if (seen.has(key)) return;
      seen.add(key);
      normalized.push(term);
    });
    return normalized.slice(0, RECENT_LIMIT);
  };

  const loadRecentQueries = () => {
    if (historyEnabled) {
      return Array.isArray(remoteHistory) ? remoteHistory : [];
    }
    return loadLocalRecentQueries();
  };

  const persistRemoteHistory = (action, payload = {}) => {
    if (!historyEnabled || !historyUrl || !historyToken) return;
    fetch(historyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        _token: historyToken,
        action,
        ...payload,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (Array.isArray(data.history)) {
          remoteHistory = normalizeRecentQueries(data.history);
          renderRecentQueries();
        }
      })
      .catch(() => {});
  };

  const ensureRecentQueriesLoaded = () => {
    if (!historyEnabled || historyLoaded || !historyUrl) return;
    historyLoaded = true;
    fetch(historyUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((res) => res.json())
      .then((data) => {
        const serverHistory = Array.isArray(data.history) ? data.history : [];
        const localHistory = loadLocalRecentQueries();
        const merged = normalizeRecentQueries([...localHistory, ...serverHistory]);
        remoteHistory = merged;
        if (localHistory.length > 0) {
          persistRemoteHistory('replace', { items: merged });
          try {
            window.localStorage.removeItem(RECENT_KEY);
          } catch {}
        }
        renderRecentQueries();
      })
      .catch(() => {
        remoteHistory = normalizeRecentQueries(loadLocalRecentQueries());
        renderRecentQueries();
      });
  };

  const removeRecentQuery = (term) => {
    const current = loadRecentQueries();
    const lower = term.toLowerCase();
    const next = current.filter((item) => item.toLowerCase() !== lower);
    if (historyEnabled) {
      remoteHistory = normalizeRecentQueries(next);
      renderRecentQueries();
      persistRemoteHistory('remove', { term });
    } else {
      saveLocalRecentQueries(next);
    }
  };

  const updateRecentQueries = (query) => {
    const term = query.trim();
    if (term.length < minLength) return;
    const current = loadRecentQueries();
    const lower = term.toLowerCase();
    const filtered = current.filter((item) => item.toLowerCase() !== lower);
    filtered.unshift(term);
    const next = normalizeRecentQueries(filtered);
    if (historyEnabled) {
      remoteHistory = next;
      renderRecentQueries();
      persistRemoteHistory('add', { term });
    } else {
      saveLocalRecentQueries(next);
    }
  };

  const getSuggestionMainButtons = () => {
    if (!suggestBoxEl) {
      return [];
    }
    return Array.from(suggestBoxEl.querySelectorAll('.search-overlay__suggestions-item-main'));
  };

  const updateInputFromSuggestion = (value) => {
    input.value = value;
    syncTriggerInputs(value);
    clearInputBtn?.classList.toggle('d-none', value.length === 0);
  };

  const resetSuggestionNavigation = ({ restoreInput = false } = {}) => {
    const options = getSuggestionMainButtons();
    options.forEach((btn) => {
      btn.setAttribute('aria-selected', 'false');
      btn.closest('.search-overlay__suggestions-item')?.classList.remove('is-active');
    });
    if (restoreInput && suggestionInputSeed) {
      updateInputFromSuggestion(suggestionInputSeed);
    }
    activeSuggestionIndex = -1;
    suggestionInputSeed = '';
  };

  const setSuggestionActiveIndex = (index) => {
    const options = getSuggestionMainButtons();
    if (options.length === 0) {
      resetSuggestionNavigation();
      return false;
    }

    options.forEach((btn) => {
      btn.setAttribute('aria-selected', 'false');
      btn.closest('.search-overlay__suggestions-item')?.classList.remove('is-active');
    });

    const safeIndex = Math.max(0, Math.min(index, options.length - 1));
    activeSuggestionIndex = safeIndex;
    const activeBtn = options[safeIndex];
    const activeItem = activeBtn.closest('.search-overlay__suggestions-item');
    activeBtn.setAttribute('aria-selected', 'true');
    activeItem?.classList.add('is-active');
    activeItem?.scrollIntoView({ block: 'nearest' });

    const term = (activeBtn.dataset.suggestionTerm || activeBtn.textContent || '').trim();
    if (term) {
      updateInputFromSuggestion(term);
    }

    return true;
  };

  const navigateSuggestions = (direction) => {
    const options = getSuggestionMainButtons();
    if (options.length === 0) {
      return false;
    }
    if (suggestBoxEl?.classList.contains('d-none')) {
      suggestBoxEl.classList.remove('d-none');
    }

    if (activeSuggestionIndex === -1) {
      suggestionInputSeed = input.value;
      return setSuggestionActiveIndex(direction > 0 ? 0 : options.length - 1);
    }

    const nextIndex = activeSuggestionIndex + direction;
    if (nextIndex < 0) {
      resetSuggestionNavigation({ restoreInput: true });
      return true;
    }

    return setSuggestionActiveIndex(nextIndex);
  };

  const selectActiveSuggestion = () => {
    const options = getSuggestionMainButtons();
    if (activeSuggestionIndex < 0 || activeSuggestionIndex >= options.length) {
      return false;
    }
    options[activeSuggestionIndex].click();
    return true;
  };

  const renderRecentQueries = () => {
    if (!recentEl || !recentListEl || !suggestBoxEl) return;
    resetSuggestionNavigation();
    if (suggestionsLocked) {
      recentEl.classList.add('d-none');
      if (suggestEl?.classList.contains('d-none')) {
        suggestBoxEl.classList.add('d-none');
      }
      return;
    }
    if (historyEnabled && !historyLoaded) {
      ensureRecentQueriesLoaded();
    }
    const items = loadRecentQueries();
    recentListEl.innerHTML = '';
    if (items.length === 0) {
      recentEl.classList.add('d-none');
      if (suggestEl?.classList.contains('d-none')) {
        suggestBoxEl.classList.add('d-none');
      }
      return;
    }
    items.forEach((term) => {
      const item = document.createElement('div');
      item.className = 'search-overlay__suggestions-item search-overlay__suggestions-item--history';

      const mainBtn = document.createElement('button');
      mainBtn.type = 'button';
      mainBtn.className = 'search-overlay__suggestions-item-main';
      mainBtn.textContent = term;
      mainBtn.dataset.suggestionTerm = term;
      mainBtn.setAttribute('aria-selected', 'false');
      mainBtn.addEventListener('click', () => {
        input.value = term;
        syncTriggerInputs(term);
        clearInputBtn?.classList.remove('d-none');
        performSearch(term, { page: 1 });
        hideSuggestions();
      });

      const actions = document.createElement('div');
      actions.className = 'search-overlay__suggestions-actions';

      const appendBtn = document.createElement('button');
      appendBtn.type = 'button';
      appendBtn.className = 'search-overlay__suggestions-item-append';
      appendBtn.setAttribute('aria-label', 'Insérer la recherche');
      appendBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 12h12.17l-3.58-3.59L14 7l6 6-6 6-1.41-1.41L16.17 13H4z"/></svg>';
      appendBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const nextValue = `${term} `;
        input.value = nextValue;
        syncTriggerInputs(nextValue);
        clearInputBtn?.classList.remove('d-none');
        debouncedSearch(nextValue);
        debouncedSuggest(nextValue);
        input.focus();
        if (input.setSelectionRange) {
          const pos = nextValue.length;
          input.setSelectionRange(pos, pos);
        }
      });

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'search-overlay__suggestions-item-remove';
      removeBtn.setAttribute('aria-label', 'Supprimer cette recherche');
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        removeRecentQuery(term);
        renderRecentQueries();
      });

      let holdTimer = null;
      const clearHold = () => {
        if (holdTimer) {
          clearTimeout(holdTimer);
          holdTimer = null;
        }
      };
      item.addEventListener('touchstart', () => {
        clearHold();
        holdTimer = setTimeout(() => {
          item.classList.add('is-reveal-delete');
        }, 450);
      }, { passive: true });
      item.addEventListener('touchend', clearHold);
      item.addEventListener('touchcancel', clearHold);

      actions.appendChild(appendBtn);
      actions.appendChild(removeBtn);
      item.appendChild(mainBtn);
      item.appendChild(actions);
      recentListEl.appendChild(item);
    });
    recentEl.classList.remove('d-none');
    suggestBoxEl.classList.remove('d-none');
  };

  const hideSuggestions = ({ restoreInput = false } = {}) => {
    if (!suggestBoxEl) return;
    suggestBoxEl.classList.add('d-none');
    resetSuggestionNavigation({ restoreInput });
  };

  const renderSuggestions = (items, term) => {
    if (!suggestEl || !suggestListEl || !suggestBoxEl) return;
    resetSuggestionNavigation();
    if (suggestionsLocked) {
      suggestEl.classList.add('d-none');
      if (recentEl?.classList.contains('d-none')) {
        suggestBoxEl.classList.add('d-none');
      }
      return;
    }
    suggestListEl.innerHTML = '';
    if (!term || items.length === 0) {
      suggestEl.classList.add('d-none');
      if (recentEl?.classList.contains('d-none')) {
        suggestBoxEl.classList.add('d-none');
      }
      return;
    }
    items.forEach((item) => {
      const term = item.label || item;
      const row = document.createElement('div');
      row.className = 'search-overlay__suggestions-item';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'search-overlay__suggestions-item-main';
      btn.textContent = term;
      btn.dataset.suggestionTerm = term;
      btn.setAttribute('aria-selected', 'false');
      btn.addEventListener('click', () => {
        input.value = term;
        syncTriggerInputs(term);
        clearInputBtn?.classList.remove('d-none');
        performSearch(term, { page: 1 });
        hideSuggestions();
      });

      const actions = document.createElement('div');
      actions.className = 'search-overlay__suggestions-actions';

      const appendBtn = document.createElement('button');
      appendBtn.type = 'button';
      appendBtn.className = 'search-overlay__suggestions-item-append';
      appendBtn.setAttribute('aria-label', 'Insérer la suggestion');
      appendBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 12h12.17l-3.58-3.59L14 7l6 6-6 6-1.41-1.41L16.17 13H4z"/></svg>';
      appendBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const nextValue = `${term} `;
        input.value = nextValue;
        syncTriggerInputs(nextValue);
        clearInputBtn?.classList.remove('d-none');
        debouncedSearch(nextValue);
        debouncedSuggest(nextValue);
        input.focus();
        if (input.setSelectionRange) {
          const pos = nextValue.length;
          input.setSelectionRange(pos, pos);
        }
      });

      actions.appendChild(appendBtn);
      row.appendChild(btn);
      row.appendChild(actions);
      suggestListEl.appendChild(row);
    });
    suggestEl.classList.remove('d-none');
    suggestBoxEl.classList.remove('d-none');
  };

  const fetchSuggestions = (term) => {
    if (!term || term.length < minLength) {
      renderSuggestions([], '');
      return;
    }
    if (suggestController) {
      suggestController.abort();
    }
    suggestController = new AbortController();
    fetch(`/search/suggest?q=${encodeURIComponent(term)}&limit=${SUGGEST_LIMIT}`, { signal: suggestController.signal })
      .then((res) => res.json())
      .then((data) => {
        if (suggestController?.signal.aborted) return;
        const items = Array.isArray(data.suggestions) ? data.suggestions : [];
        renderSuggestions(items, term);
      })
      .catch((err) => {
        if (err.name === 'AbortError') return;
        renderSuggestions([], term);
      });
  };

  const setEmpty = (show, message) => {
    if (!emptyEl) return;
    if (message) emptyEl.textContent = message;
    emptyEl.classList.toggle('d-none', !show);
  };

  const openMapModal = (item) => {
    if (!mapModal || !mapFrame || !item?.location) return;
    const { lat, lng, label } = item.location;
    if (typeof lat !== 'number' || typeof lng !== 'number') return;
    const title = label || item.title || 'Localisation';
    if (mapTitle) mapTitle.textContent = title;
    const mapUrl = `https://maps.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&z=12&output=embed`;
    mapFrame.src = mapUrl;
    if (mapLink) {
      mapLink.href = `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}`;
    }
    mapModal.classList.remove('d-none');
    mapModal.setAttribute('aria-hidden', 'false');
  };

  const closeMapModal = () => {
    if (!mapModal) return;
    mapModal.classList.add('d-none');
    mapModal.setAttribute('aria-hidden', 'true');
    if (mapFrame) {
      mapFrame.src = '';
    }
  };

  const setLoading = (loading) => {
    isLoading = loading;
    if (!moreBtn) return;
    moreBtn.disabled = loading;
    moreBtn.textContent = loading ? 'Chargement...' : 'Afficher plus';
  };

  const setLocationStatus = (message) => {
    if (!locationStatusEl) return;
    locationStatusEl.textContent = message || '';
  };

  const canSearch = (term) => term.length >= minLength || !!activeLocation;

  const updateLocationSummary = () => {
    return;
  };

  const updateRadiusLabel = (value) => {
    if (!radiusValueEl) return;
    radiusValueEl.textContent = String(value);
    if (Number.isFinite(Number(value))) {
      lastRadius = Number(value);
    }
  };

  const updateLocationUI = () => {
    const hasPending = !!pendingLocation;
    const summaryLocation = activeLocation?.source === 'me' || pendingLocation?.source === 'me';
    if (radiusWrap) {
      const shouldShowRadius = hasPending || !!activeLocation;
      radiusWrap.classList.toggle('is-hidden', !shouldShowRadius);
    }
    if (radiusInput) {
      radiusInput.disabled = !(hasPending || !!activeLocation);
    }
    if (locationApplyBtn) {
      const shouldEnable = hasPending || !!activeLocation;
      locationApplyBtn.disabled = !shouldEnable;
      locationApplyBtn.classList.remove('is-hidden');
    }
    if (locationNoteEl) {
      const showNote = !!activeLocation;
      locationNoteEl.classList.toggle('d-none', !showNote);
    }
    if (locationInputWrap) {
      const hideInput = pendingLocation?.source === 'me' || activeLocation?.source === 'me';
      locationInputWrap.classList.toggle('is-hidden', !!hideInput);
    }
    if (locationActionsEl) {
      const hasStatus = !!locationStatusEl?.textContent;
      const isMeState = pendingLocation?.source === 'me' || activeLocation?.source === 'me';
      const showActions = !locationInputWrap?.classList.contains('is-hidden') || hasStatus || isMeState;
      locationActionsEl.classList.toggle('is-hidden', !showActions);
    }
    if (locationMeBtn) {
      const hideMe = (!locationActionsEl || locationActionsEl.classList.contains('is-hidden'))
        || pendingLocation?.source === 'place'
        || activeLocation?.source === 'place';
      locationMeBtn.classList.toggle('d-none', !!hideMe);
      locationMeBtn.innerHTML = `<svg class="search-overlay__btn-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M12 2c.55 0 1 .45 1 1v1.84c3.19.44 5.72 2.97 6.16 6.16H21c.55 0 1 .45 1 1s-.45 1-1 1h-1.84c-.44 3.19-2.97 5.72-6.16 6.16V21c0 .55-.45 1-1 1s-1-.45-1-1v-1.84c-3.19-.44-5.72-2.97-6.16-6.16H3c-.55 0-1-.45-1-1s.45-1 1-1h1.84c.44-3.19 2.97-5.72 6.16-6.16V3c0-.55.45-1 1-1Zm0 4.77A5.23 5.23 0 0 0 6.77 12 5.23 5.23 0 0 0 12 17.23 5.23 5.23 0 0 0 17.23 12 5.23 5.23 0 0 0 12 6.77Zm0 3.26a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/></svg>${summaryLocation ? 'Autour de moi (activé)' : 'Autour de moi'}`;
    }
    if (locationInputClearBtn) {
      const hasValue = !!locationInput?.value.trim();
      const hideClear = !hasValue || locationInputWrap?.classList.contains('is-hidden');
      locationInputClearBtn.classList.toggle('d-none', !!hideClear);
    }
  };

  const syncLocationUI = () => {
    updateLocationUI();
  };

  const setPendingLocation = (location, dirty = true) => {
    pendingLocation = location
      ? {
          ...location,
          radius: location.radius ?? pendingLocation?.radius ?? lastRadius,
        }
      : null;
    pendingDirty = !!pendingLocation && dirty;
    if (pendingLocation && locationInput && pendingLocation.label && pendingLocation.source !== 'me') {
      locationInput.value = pendingLocation.label;
    }
    if (pendingLocation && radiusInput) {
      radiusInput.value = String(pendingLocation.radius);
      updateRadiusLabel(pendingLocation.radius);
    }
    syncLocationUI();
  };

  const applyLocation = () => {
    if (pendingLocation) {
      activeLocation = { ...pendingLocation };
      pendingLocation = null;
      pendingDirty = false;
      isEditingLocation = false;
    }
    syncLocationUI();
    const term = input.value.trim();
    if (!canSearch(term)) {
      setStatus('Tapez au moins 2 caracteres ou choisissez une localisation.');
      return;
    }
    performSearch(term, { page: 1 });
  };

  const resetLocation = (keepRadius = false) => {
    pendingLocation = null;
    activeLocation = null;
    pendingDirty = false;
    isEditingLocation = false;
    if (locationInput) {
      locationInput.value = '';
    }
    if (radiusInput) {
      const targetRadius = keepRadius ? lastRadius : defaultRadius;
      radiusInput.value = String(targetRadius);
      updateRadiusLabel(targetRadius);
      if (!keepRadius) {
        lastRadius = defaultRadius;
      }
    }
    setLocationStatus('');
    syncLocationUI();
    const term = input.value.trim();
    if (term.length >= minLength) {
      performSearch(term, { page: 1 });
    } else {
      setStatus('Tapez au moins 2 caracteres.');
      renderCategories([], []);
      renderResults([]);
      setCountLines('', '');
      updatePagination();
    }
  };

  const initPlacesAutocomplete = () => {
    if (hasPlacesInit) return;
    if (!locationInput) return;
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
      setTimeout(initPlacesAutocomplete, 250);
      return;
    }
    hasPlacesInit = true;
    const autocomplete = new google.maps.places.Autocomplete(locationInput, {
      types: ['(regions)'],
    });
    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place || !place.geometry || !place.geometry.location) {
        setLocationStatus('Lieu non reconnu.');
        return;
      }
      setLocationStatus('');
      setPendingLocation({
        lat: place.geometry.location.lat(),
        lng: place.geometry.location.lng(),
        label: place.name || place.formatted_address || 'Lieu sélectionné',
        source: 'place',
      });
        isEditingLocation = true;
        syncLocationUI();
    });
  };

  const useCurrentLocation = () => {
    if (!navigator.geolocation) {
      setLocationStatus('Géolocalisation indisponible.');
      return;
    }
    setLocationStatus('Localisation en cours...');
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocationStatus('');
        setPendingLocation({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          label: 'Autour de moi',
          source: 'me',
        });
        isEditingLocation = true;
        syncLocationUI();
      },
      () => {
        setLocationStatus('Autorisation refusée.');
      },
      { enableHighAccuracy: false, timeout: 8000 }
    );
  };

  const updatePagination = () => {
    if (!paginationEl || !moreBtn || !pageEl) return;
    if (currentResults.length === 0) {
      paginationEl.classList.add('d-none');
      pageEl.textContent = '';
      return;
    }
    paginationEl.classList.toggle('d-none', totalPages <= 1);
    if (totalPages > 1) {
      pageEl.textContent = `Page ${currentPage}/${totalPages}`;
    } else {
      pageEl.textContent = '';
    }
    moreBtn.classList.toggle('d-none', currentPage >= totalPages);
  };

  const syncTriggerInputs = (value) => {
    triggerInputs.forEach((el) => {
      el.value = value;
    });
  };

  const buildSearchUrl = (term, page) => {
    const params = new URLSearchParams({
      q: term,
      limit: String(limit),
      page: String(page),
    });
    if (activeCategories.size > 0) {
      params.set('categories', Array.from(activeCategories).join(','));
    }
    if (activeLocation) {
      params.set('lat', String(activeLocation.lat));
      params.set('lng', String(activeLocation.lng));
      params.set('radius', String(activeLocation.radius));
    }
    return `/search/projects?${params.toString()}`;
  };

  const openOverlay = (initialValue = '') => {
    suggestionsLocked = false;
    if (!overlay.classList.contains('is-open')) {
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.classList.add('search-overlay-open');
    }
    if (typeof initialValue === 'string') {
      input.value = initialValue;
      syncTriggerInputs(initialValue);
    }
    clearInputBtn?.classList.toggle('d-none', input.value.length === 0);
    input.focus();
    const term = input.value.trim();
    if (canSearch(term)) {
      performSearch(term, { page: 1 });
    } else {
      currentQuery = term;
      currentResults = [];
      totalPages = 0;
      totalCount = 0;
      totalBase = 0;
      categoryCounts = [];
      currentPage = 1;
      setStatus('Tapez au moins 2 caracteres.');
      setCountLines('', '');
      renderCategories([], []);
      renderResults([]);
      updatePagination();
    }
    if (!term) {
      ensureRecentQueriesLoaded();
      renderRecentQueries();
      renderSuggestions([], '');
    } else if (recentEl) {
      recentEl.classList.add('d-none');
    }
    syncLocationUI();
  };

  const closeOverlay = () => {
    overlay.classList.remove('is-open');
    overlay.classList.remove('is-filters-open');
    if (filtersToggleBtn) {
      filtersToggleBtn.setAttribute('aria-expanded', 'false');
    }
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('search-overlay-open');
  };

  const openFilters = () => {
    overlay.classList.add('is-filters-open');
    if (filtersToggleBtn) {
      filtersToggleBtn.setAttribute('aria-expanded', 'true');
    }
  };

  const closeFilters = () => {
    overlay.classList.remove('is-filters-open');
    if (filtersToggleBtn) {
      filtersToggleBtn.setAttribute('aria-expanded', 'false');
    }
  };

  let touchStartX = null;
  let touchStartY = null;

  const onTouchStart = (event) => {
    if (!event.touches || event.touches.length !== 1) return;
    touchStartX = event.touches[0].clientX;
    touchStartY = event.touches[0].clientY;
  };

  const onTouchMove = (event) => {
    if (touchStartX === null || touchStartY === null) return;
    const touch = event.touches[0];
    const deltaX = touch.clientX - touchStartX;
    const deltaY = touch.clientY - touchStartY;
    if (Math.abs(deltaX) < 12 || Math.abs(deltaX) < Math.abs(deltaY)) return;

    if (deltaX > 0 && !overlay.classList.contains('is-filters-open') && touchStartX < 24) {
      openFilters();
      touchStartX = null;
      touchStartY = null;
    } else if (deltaX < 0 && overlay.classList.contains('is-filters-open')) {
      closeFilters();
      touchStartX = null;
      touchStartY = null;
    }
  };

  const onTouchEnd = () => {
    touchStartX = null;
    touchStartY = null;
  };

  const clearSearch = () => {
    suggestionsLocked = false;
    input.value = '';
    syncTriggerInputs('');
    clearInputBtn?.classList.add('d-none');
    activeCategories.clear();
    currentQuery = '';
    currentResults = [];
    totalPages = 0;
    totalCount = 0;
    totalBase = 0;
    categoryCounts = [];
    currentPage = 1;
    renderCategories([], []);
    renderResults([]);
    setStatus('Tapez au moins 2 caracteres.');
    setCountLines('', '');
    setEmpty(false);
    updatePagination();
    renderRecentQueries();
    renderSuggestions([], '');
  };

  const clearFilters = () => {
    activeCategories.clear();
    if (canSearch(currentQuery)) {
      performSearch(currentQuery, { page: 1 });
    } else {
      renderCategories(currentResults, categoryCounts);
      updatePagination();
    }
  };

  const buildCategoryMap = (items) => {
    const map = new Map();
    items.forEach((item) => {
      (item.categories || []).forEach((cat) => {
        const key = cat.uniqueName || cat.label;
        if (!key) return;
        const label = cat.label || cat.uniqueName || key;
        if (!map.has(key)) {
          map.set(key, { key, label, count: 0 });
        }
        map.get(key).count += 1;
      });
    });
    return map;
  };

  const normalizeCategoryCounts = (counts) => {
    if (!Array.isArray(counts)) return [];
    return counts
      .map((cat) => {
        const key = cat.key || cat.uniqueName || '';
        if (!key) return null;
        const label = cat.label || cat.uniqueName || key;
        const count = Number(cat.count || 0);
        return { key, label, count };
      })
      .filter((cat) => cat && cat.key);
  };

  const renderCategories = (items, counts = null) => {
    if (!categoriesEl) return;
    let categories = normalizeCategoryCounts(counts);
    if (categories.length === 0) {
      const map = buildCategoryMap(items);
      categories = Array.from(map.values());
    }
    categories.sort((a, b) => {
      if (b.count !== a.count) return b.count - a.count;
      return a.label.localeCompare(b.label);
    });

    if (activeCategories.size > 0) {
      const known = new Set(categories.map((cat) => cat.key));
      Array.from(activeCategories).forEach((key) => {
        if (!known.has(key)) {
          categories.push({ key, label: key, count: 0 });
        }
      });
    }
    if (categories.length > 0) {
      const available = new Set(categories.map((cat) => cat.key));
      activeCategories = new Set(Array.from(activeCategories).filter((key) => available.has(key)));
    }

    categoriesEl.innerHTML = '';
    if (categories.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'search-overlay__categories-empty';
      empty.textContent = 'Aucune catégorie trouvée.';
      categoriesEl.appendChild(empty);
      if (activeCategories.size === 0) {
        clearFiltersBtn?.setAttribute('disabled', 'disabled');
      } else {
        clearFiltersBtn?.removeAttribute('disabled');
      }
      return;
    }

    categories.forEach((cat) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'search-overlay__category' + (activeCategories.has(cat.key) ? ' is-active' : '');
      button.dataset.category = cat.key;

      const name = document.createElement('span');
      name.className = 'search-overlay__category-name';
      name.textContent = cat.label;

      const count = document.createElement('span');
      count.className = 'search-overlay__category-count';
      count.textContent = String(cat.count);

      button.appendChild(name);
      button.appendChild(count);
      button.addEventListener('click', () => {
        if (activeCategories.has(cat.key)) {
          activeCategories.delete(cat.key);
        } else {
          activeCategories.add(cat.key);
        }
        if (canSearch(currentQuery)) {
          performSearch(currentQuery, { page: 1 });
        } else {
          renderCategories(currentResults, categoryCounts);
        }
      });
      categoriesEl.appendChild(button);
    });

    if (activeCategories.size === 0) {
      clearFiltersBtn?.setAttribute('disabled', 'disabled');
    } else {
      clearFiltersBtn?.removeAttribute('disabled');
    }
  };

  const renderResults = (items) => {
    if (!resultsEl) return;
    resultsEl.innerHTML = '';
    if (items.length === 0) {
      setEmpty(false);
      return;
    }
    setEmpty(false);

    items.forEach((item) => {
      const card = document.createElement('article');
      card.className = 'search-result-card';

      const link = document.createElement('a');
      link.href = item.url || '#';
      link.addEventListener('click', () => {
        if (currentQuery) {
          updateRecentQueries(currentQuery);
        }
      });

      const thumb = document.createElement('div');
      thumb.className = 'search-result-card__thumb';
      if (item.thumbnail) {
        const img = document.createElement('img');
        img.src = item.thumbnail;
        img.alt = '';
        img.loading = 'lazy';
        thumb.appendChild(img);
      } else {
        const fallback = document.createElement('div');
        fallback.className = 'search-result-card__thumb-fallback';
        const source = (item.title || item.goal || '').trim();
        const letter = source ? source.slice(0, 1).toUpperCase() : '';
        if (letter) {
          const span = document.createElement('span');
          span.className = 'search-result-card__thumb-fallback-letter';
          span.textContent = letter;
          fallback.appendChild(span);
        } else {
          fallback.textContent = 'Aucune image';
        }
        thumb.appendChild(fallback);
      }

      if (item.location && typeof item.location.lat === 'number' && typeof item.location.lng === 'number') {
        const mapBtn = document.createElement('button');
        mapBtn.type = 'button';
        mapBtn.className = 'search-result-card__map-btn';
        mapBtn.setAttribute('aria-label', 'Voir la localisation');
        mapBtn.innerHTML = `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M12 2c-4.418 0-8 3.582-8 8 0 6 8 12 8 12s8-6 8-12c0-4.418-3.582-8-8-8Zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
          </svg>
        `;
        mapBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          openMapModal(item);
        });
        thumb.appendChild(mapBtn);
      }

      const body = document.createElement('div');
      body.className = 'search-result-card__body';

      const title = document.createElement('div');
      title.className = 'search-result-card__title';
      title.textContent = item.title || 'Projet sans titre';

      const goal = document.createElement('div');
      goal.className = 'search-result-card__goal';
      goal.textContent = item.goal || '';

      body.appendChild(title);
      body.appendChild(goal);

      link.appendChild(thumb);
      link.appendChild(body);
      card.appendChild(link);
      resultsEl.appendChild(card);
    });
  };

  const applyCountLabel = () => {
    if (totalCount === 0) {
      setCountLines('', '');
      return;
    }
    const plural = totalCount > 1 ? 's' : '';
    let totalLabel = `${totalCount} résultat${plural}`;
    if (currentQuery) {
      totalLabel += ` pour "${currentQuery}"`;
    }
    if (activeLocation) {
      totalLabel += ` dans un rayon de ${activeLocation.radius} km`;
    }
    if (activeCategories.size > 0) {
      const catPlural = activeCategories.size > 1 ? 's' : '';
      const selectedText = `${activeCategories.size} catégorie${catPlural} sélectionnée${catPlural}`;
      totalLabel += ` (${selectedText})`;
    }

    setCountLines(totalLabel, '');
  };

  const performSearch = (term, { page = 1, append = false } = {}) => {
    currentQuery = term;
    if (activeController) {
      activeController.abort();
    }
    const controller = new AbortController();
    activeController = controller;
    setLoading(true);
    if (!append) {
      setStatus('Recherche...');
    } else {
      setStatus('Chargement...');
    }

    fetch(buildSearchUrl(term, page), { signal: controller.signal })
      .then((res) => res.json())
      .then((data) => {
        if (controller !== activeController) return;
        const items = Array.isArray(data.results) ? data.results : [];
        totalCount = typeof data.total === 'number' ? data.total : items.length;
        totalBase = typeof data.totalBase === 'number' ? data.totalBase : totalCount;
        categoryCounts = Array.isArray(data.categoryCounts) ? data.categoryCounts : [];
        totalPages = typeof data.pages === 'number' ? data.pages : 1;
        currentPage = typeof data.page === 'number' ? data.page : page;
        if (append) {
          currentResults = currentResults.concat(items);
        } else {
          currentResults = items;
        }
        renderCategories(currentResults, categoryCounts);
        renderResults(currentResults);
        if (currentResults.length === 0) {
          setStatus(data.message || 'Aucun résultat');
          const hints = [];
          if (activeCategories.size > 0) {
            hints.push('Essayez sans catégories');
          }
          if (activeLocation) {
            hints.push('Augmenter le rayon');
          }
          const hintText = hints.length > 0 ? `\n\n${hints.join(' · ')}` : '';
          setEmpty(true, `Aucun résultat pour cette recherche.${hintText}`);
        } else {
          setStatus('');
          setEmpty(false);
        }
        applyCountLabel();
        updatePagination();
      })
      .catch((err) => {
        if (err.name === 'AbortError') return;
        setStatus('Erreur de recherche');
        currentResults = [];
        totalCount = 0;
        totalBase = 0;
        categoryCounts = [];
        totalPages = 0;
        currentPage = 1;
        renderCategories([], []);
        renderResults([]);
        setCountLines('', '');
        setEmpty(true, 'Une erreur est survenue.');
        updatePagination();
      })
      .finally(() => {
        if (controller === activeController) {
          setLoading(false);
        }
      });
  };

  const debouncedSearch = debounce((raw) => {
    const term = raw.trim();
    if (!canSearch(term)) {
      currentQuery = term;
      currentResults = [];
      totalPages = 0;
      totalCount = 0;
      totalBase = 0;
      categoryCounts = [];
      currentPage = 1;
      renderCategories([], []);
      renderResults([]);
      setStatus('Tapez au moins 2 caracteres ou choisissez une localisation.');
      setCountLines('', '');
      setEmpty(false);
      updatePagination();
      renderRecentQueries();
      renderSuggestions([], '');
      return;
    }
    performSearch(term, { page: 1 });
  }, 250);

  const debouncedSuggest = debounce((raw) => {
    const term = raw.trim();
    fetchSuggestions(term);
  }, 200);

  input.addEventListener('input', () => {
    suggestionsLocked = false;
    resetSuggestionNavigation();
    syncTriggerInputs(input.value);
    clearInputBtn?.classList.toggle('d-none', input.value.length === 0);
    debouncedSearch(input.value);
    debouncedSuggest(input.value);
  });

  document.addEventListener('click', (event) => {
    if (!suggestBoxEl || suggestBoxEl.classList.contains('d-none')) return;
    const target = event.target;
    if (inputWrap?.contains(target) || suggestBoxEl.contains(target)) {
      return;
    }
    hideSuggestions({ restoreInput: true });
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      if (navigateSuggestions(1)) {
        event.preventDefault();
      }
      return;
    }
    if (event.key === 'ArrowUp') {
      if (navigateSuggestions(-1)) {
        event.preventDefault();
      }
      return;
    }
    if (event.key === 'Escape') {
      if (suggestBoxEl && !suggestBoxEl.classList.contains('d-none')) {
        event.preventDefault();
        hideSuggestions({ restoreInput: true });
      }
      return;
    }
    if (event.key === 'Enter') {
      if (selectActiveSuggestion()) {
        event.preventDefault();
        return;
      }
      suggestionsLocked = true;
      const term = input.value.trim();
      if (term.length >= minLength) {
        updateRecentQueries(term);
      }
      if (suggestController) {
        suggestController.abort();
      }
      renderSuggestions([], '');
      hideSuggestions();
    }
  });

  locationInput?.addEventListener('input', () => {
    setLocationStatus('');
    pendingLocation = null;
    pendingDirty = false;
    syncLocationUI();
  });

  radiusInput?.addEventListener('input', () => {
    const value = parseInt(radiusInput.value, 10);
    updateRadiusLabel(value);
    if (pendingLocation) {
      pendingLocation.radius = value;
      pendingDirty = true;
    } else if (activeLocation) {
      setPendingLocation({ ...activeLocation, radius: value }, true);
    }
  });

  locationApplyBtn?.addEventListener('click', applyLocation);
  locationResetBtn?.addEventListener('click', resetLocation);
  locationMeBtn?.addEventListener('click', useCurrentLocation);

  if (radiusInput) {
    updateRadiusLabel(radiusInput.value || defaultRadius);
  }
  syncLocationUI();
  initPlacesAutocomplete();

  clearInputBtn?.addEventListener('click', clearSearch);
  clearFiltersBtn?.addEventListener('click', clearFilters);
  closeBtn?.addEventListener('click', closeOverlay);
  filtersToggleBtn?.addEventListener('click', () => {
    if (overlay.classList.contains('is-filters-open')) {
      closeFilters();
    } else {
      openFilters();
    }
  });
  filtersCloseTargets.forEach((el) => el.addEventListener('click', closeFilters));
  overlay.addEventListener('touchstart', onTouchStart, { passive: true });
  overlay.addEventListener('touchmove', onTouchMove, { passive: true });
  overlay.addEventListener('touchend', onTouchEnd);
  moreBtn?.addEventListener('click', () => {
    if (isLoading) return;
    if (!canSearch(currentQuery)) return;
    if (currentPage >= totalPages) return;
    performSearch(currentQuery, { page: currentPage + 1, append: true });
  });

  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) {
      closeOverlay();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mapModal && !mapModal.classList.contains('d-none')) {
      closeMapModal();
      return;
    }
    if (event.key === 'Escape' && overlay.classList.contains('is-filters-open')) {
      closeFilters();
      return;
    }
    if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
      closeOverlay();
    }
  });

  mapCloseButtons.forEach((btn) => btn.addEventListener('click', closeMapModal));

  triggers.forEach((trigger) => {
    if (trigger.tagName === 'INPUT') {
      trigger.addEventListener('focus', () => openOverlay(trigger.value || ''));
      trigger.addEventListener('input', () => {
        if (!overlay.classList.contains('is-open')) {
          openOverlay(trigger.value || '');
        } else {
          input.value = trigger.value || '';
          debouncedSearch(input.value);
        }
      });
    } else {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        openOverlay('');
      });
    }
  });
  locationInputClearBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    resetLocation(true);
  });
})();
