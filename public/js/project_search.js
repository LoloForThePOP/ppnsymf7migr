(() => {
  function debounce(fn, wait = 250) {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }

  function initSearch({ inputSelector, resultsSelector, statusSelector = null, minLength = 2, endpoint = '/search/projects' }) {
    const input = document.querySelector(inputSelector);
    const results = document.querySelector(resultsSelector);
    const status = statusSelector ? document.querySelector(statusSelector) : null;

    if (!input || !results) return;

    const showStatus = (msg) => {
      if (!status) return;
      if (msg) {
        status.textContent = msg;
        status.classList.remove('d-none');
      } else {
        status.classList.add('d-none');
      }
    };

    const render = (data) => {
      results.innerHTML = '';
      if (!data.results || data.results.length === 0) {
        results.classList.add('d-none');
        showStatus(data.message || 'Aucun résultat');
        return;
      }
      showStatus(`${data.count} résultat(s)`);
      data.results.forEach(item => {
        const a = document.createElement('a');
        a.className = 'list-group-item list-group-item-action d-flex gap-2';
        a.href = item.url || '#';
        if (item.thumbnail) {
          const img = document.createElement('img');
          img.src = item.thumbnail;
          img.alt = '';
          img.width = 48;
          img.height = 48;
          img.style.objectFit = 'cover';
          img.style.borderRadius = '4px';
          a.appendChild(img);
        }
        const text = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'fw-bold mb-1';
        title.textContent = item.title ?? '(Sans titre)';
        const goal = document.createElement('div');
        goal.className = 'small text-muted';
        goal.textContent = item.goal ?? '';
        text.appendChild(title);
        text.appendChild(goal);
        a.appendChild(text);
        results.appendChild(a);
      });
      results.classList.remove('d-none');
    };

    const fetchResults = debounce((term) => {
      if (!term || term.length < minLength) {
        results.classList.add('d-none');
        showStatus(`Tapez au moins ${minLength} caractères`);
        return;
      }
      showStatus('Recherche...');
      fetch(`${endpoint}?q=${encodeURIComponent(term)}`)
        .then(r => r.json())
        .then(render)
        .catch(() => {
          showStatus('Erreur de recherche');
          results.classList.add('d-none');
        });
    }, 250);

    input.addEventListener('input', () => {
      fetchResults(input.value.trim());
    });

    input.addEventListener('blur', () => setTimeout(() => results.classList.add('d-none'), 200));
  }

  window.ProjectSearch = { init: initSearch };
})();
