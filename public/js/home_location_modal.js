(() => {
  const modal = document.getElementById('home-location-modal');
  if (!modal) return;

  const pickerRoot = modal.querySelector('[data-home-location-picker]');
  if (!pickerRoot) return;

  const openButtons = Array.from(document.querySelectorAll('[data-home-location-trigger]'));
  if (openButtons.length === 0) return;

  if (!window.ProponLocationPicker || typeof window.ProponLocationPicker.create !== 'function') {
    return;
  }

  const closeButtons = Array.from(modal.querySelectorAll('[data-home-location-close]'));
  let picker = null;
  let lastFocusedTrigger = null;
  let locationBeforeOpen = null;

  const serializeLocation = (location) => {
    if (!location) return '';
    const lat = Number(location.lat);
    const lng = Number(location.lng);
    const radius = Number(location.radius);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(radius)) return '';
    return `${lat.toFixed(6)}|${lng.toFixed(6)}|${Math.round(radius)}`;
  };

  const readPersistedLocation = () => {
    if (typeof window.ProponLocationPicker.loadPersistedLocation !== 'function') {
      return null;
    }
    return window.ProponLocationPicker.loadPersistedLocation();
  };

  const closeModal = () => {
    if (modal.classList.contains('d-none')) {
      return;
    }

    modal.classList.add('d-none');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('home-location-modal-open');

    if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
      lastFocusedTrigger.focus();
    }
  };

  const refreshHomeIfChanged = (anchorId = '') => {
    const currentLocation = readPersistedLocation();
    if (serializeLocation(currentLocation) !== serializeLocation(locationBeforeOpen)) {
      const url = new URL(window.location.href);
      url.hash = anchorId ? `#${anchorId}` : '';
      window.location.assign(url.toString());
    }
  };

  const ensurePicker = () => {
    if (picker) {
      return;
    }

    picker = window.ProponLocationPicker.create(pickerRoot, {
      onApply: () => {
        closeModal();
        refreshHomeIfChanged('home-around-you-rail');
      },
      onReset: () => {
        closeModal();
        refreshHomeIfChanged();
      },
    });
  };

  const openModal = () => {
    locationBeforeOpen = readPersistedLocation();
    ensurePicker();
    modal.classList.remove('d-none');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('home-location-modal-open');
    picker?.syncUI();
    window.setTimeout(() => picker?.focusInput(), 0);
  };

  openButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      lastFocusedTrigger = button;
      openModal();
    });
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      closeModal();
    });
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.classList.contains('d-none')) {
      closeModal();
    }
  });
})();
