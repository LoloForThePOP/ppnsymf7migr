(() => {
  const overlay = document.getElementById('search-overlay');
  if (!overlay) return;

  const input = overlay.querySelector('#search-overlay-input');
  const resultsEl = overlay.querySelector('#search-overlay-results');
  const statusEl = overlay.querySelector('#search-overlay-status');
  const countEl = overlay.querySelector('#search-overlay-count');
  const emptyEl = overlay.querySelector('#search-overlay-empty');
  const categoriesEl = overlay.querySelector('#search-overlay-categories');
  const clearInputBtn = overlay.querySelector('[data-search-clear]');
  const clearFiltersBtn = overlay.querySelector('[data-search-clear-filters]');
  const closeBtn = overlay.querySelector('[data-search-close]');
  const moreBtn = overlay.querySelector('[data-search-more]');
  const pageEl = overlay.querySelector('#search-overlay-page');
  const paginationEl = overlay.querySelector('.search-overlay__pagination');
  const triggers = Array.from(document.querySelectorAll('[data-search-trigger]'));
  const triggerInputs = triggers.filter((el) => el.tagName === 'INPUT');

  const minLength = 2;
  const limit = 16;
  let currentResults = [];
  let currentQuery = '';
  let currentPage = 1;
  let totalPages = 0;
  let totalCount = 0;
  let activeCategories = new Set();
  let activeController = null;
  let isLoading = false;

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

  const setCount = (message) => {
    if (!countEl) return;
    countEl.textContent = message || '';
  };

  const setEmpty = (show, message) => {
    if (!emptyEl) return;
    if (message) emptyEl.textContent = message;
    emptyEl.classList.toggle('d-none', !show);
  };

  const setLoading = (loading) => {
    isLoading = loading;
    if (!moreBtn) return;
    moreBtn.disabled = loading;
    moreBtn.textContent = loading ? 'Chargement...' : 'Afficher plus';
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
    return `/search/projects?${params.toString()}`;
  };

  const openOverlay = (initialValue = '') => {
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
    if (input.value.length >= minLength) {
      debouncedSearch(input.value.trim());
    } else {
      currentQuery = input.value.trim();
      currentResults = [];
      totalPages = 0;
      totalCount = 0;
      currentPage = 1;
      setStatus('Tapez au moins 2 caracteres.');
      setCount('');
      renderCategories([]);
      renderResults([]);
      updatePagination();
    }
  };

  const closeOverlay = () => {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('search-overlay-open');
  };

  const clearSearch = () => {
    input.value = '';
    syncTriggerInputs('');
    clearInputBtn?.classList.add('d-none');
    activeCategories.clear();
    currentQuery = '';
    currentResults = [];
    totalPages = 0;
    totalCount = 0;
    currentPage = 1;
    renderCategories([]);
    renderResults([]);
    setStatus('Tapez au moins 2 caracteres.');
    setCount('');
    setEmpty(false);
    updatePagination();
  };

  const clearFilters = () => {
    activeCategories.clear();
    if (currentQuery.length >= minLength) {
      performSearch(currentQuery, { page: 1 });
    } else {
      renderCategories(currentResults);
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

  const renderCategories = (items) => {
    if (!categoriesEl) return;
    const map = buildCategoryMap(items);
    const categories = Array.from(map.values()).sort((a, b) => {
      if (b.count !== a.count) return b.count - a.count;
      return a.label.localeCompare(b.label);
    });
    if (categories.length > 0) {
      const available = new Set(categories.map((cat) => cat.key));
      activeCategories = new Set(Array.from(activeCategories).filter((key) => available.has(key)));
    }

    categoriesEl.innerHTML = '';
    if (categories.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'search-overlay__categories-empty';
      empty.textContent = 'Aucune categorie trouvee.';
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
        if (currentQuery.length >= minLength) {
          performSearch(currentQuery, { page: 1 });
        } else {
          renderCategories(currentResults);
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

      const thumb = document.createElement('div');
      thumb.className = 'search-result-card__thumb';
      if (item.thumbnail) {
        const img = document.createElement('img');
        img.src = item.thumbnail;
        img.alt = '';
        img.loading = 'lazy';
        thumb.appendChild(img);
      } else {
        thumb.textContent = 'Aucune image';
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
      setCount('');
      return;
    }
    if (totalCount > currentResults.length) {
      setCount(`Affichage ${currentResults.length} / ${totalCount} resultats`);
    } else {
      setCount(`${totalCount} resultat(s)`);
    }
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
        totalPages = typeof data.pages === 'number' ? data.pages : 1;
        currentPage = typeof data.page === 'number' ? data.page : page;
        if (append) {
          currentResults = currentResults.concat(items);
        } else {
          currentResults = items;
        }
        renderCategories(currentResults);
        renderResults(currentResults);
        if (currentResults.length === 0) {
          setStatus(data.message || 'Aucun resultat');
          setEmpty(true, 'Aucun resultat pour cette recherche.');
        } else {
          setStatus(`Resultats pour "${term}"`);
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
        totalPages = 0;
        currentPage = 1;
        renderCategories([]);
        renderResults([]);
        setCount('');
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
    if (term.length < minLength) {
      currentQuery = term;
      currentResults = [];
      totalPages = 0;
      totalCount = 0;
      currentPage = 1;
      renderCategories([]);
      renderResults([]);
      setStatus('Tapez au moins 2 caracteres.');
      setCount('');
      setEmpty(false);
      updatePagination();
      return;
    }
    performSearch(term, { page: 1 });
  }, 250);

  input.addEventListener('input', () => {
    syncTriggerInputs(input.value);
    clearInputBtn?.classList.toggle('d-none', input.value.length === 0);
    debouncedSearch(input.value);
  });

  clearInputBtn?.addEventListener('click', clearSearch);
  clearFiltersBtn?.addEventListener('click', clearFilters);
  closeBtn?.addEventListener('click', closeOverlay);
  moreBtn?.addEventListener('click', () => {
    if (isLoading) return;
    if (currentQuery.length < minLength) return;
    if (currentPage >= totalPages) return;
    performSearch(currentQuery, { page: currentPage + 1, append: true });
  });

  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) {
      closeOverlay();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
      closeOverlay();
    }
  });

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
})();
