(() => {
  if (window.__ppProjectCardBookmarkBound) {
    return;
  }
  if (typeof window.jQuery === 'undefined') {
    return;
  }
  window.__ppProjectCardBookmarkBound = true;

  const $ = window.jQuery;
  const selector = '.js-ajax-bookmark-pp-card';

  const setButtonLabel = ($button, isBookmarked) => {
    const addLabel = $button.data('labelAdd') || 'Ajouter aux marque-pages';
    const activeLabel = $button.data('labelActive') || 'Déjà dans vos marque-pages';
    const label = isBookmarked ? activeLabel : addLabel;
    $button.attr('aria-label', label).attr('title', label);
  };

  const isAddOnlyMode = ($button) => ($button.data('bookmarkMode') || 'add-only') !== 'toggle';

  $(document).on('click', selector, function (event) {
    event.preventDefault();
    event.stopPropagation();

    const $button = $(this);
    if ($button.prop('disabled') || $button.data('loading')) {
      return;
    }

    const addOnlyMode = isAddOnlyMode($button);
    if (addOnlyMode && $button.hasClass('isBookmarked')) {
      return;
    }

    $button.data('loading', true).prop('disabled', true).addClass('is-loading');

    $.ajax({
      url: $button.data('url'),
      data: { _token: $button.data('token') },
      type: 'POST',
      success(data) {
        if (!data || typeof data.action !== 'string') {
          return;
        }

        if (!addOnlyMode) {
          const isBookmarked = data.action === 'created';
          $button.toggleClass('isBookmarked', isBookmarked);
          setButtonLabel($button, isBookmarked);
          return;
        }

        if (data.action === 'created' || data.action === 'already_exists') {
          $button.addClass('isBookmarked');
          setButtonLabel($button, true);
        }
      },
      error() {
        // Keep silent to avoid UI noise in card grids.
      },
      complete() {
        $button.data('loading', false).removeClass('is-loading');
        if (addOnlyMode && $button.hasClass('isBookmarked')) {
          return;
        }
        $button.prop('disabled', false);
      },
    });
  });
})();
