(function () {
    if (window.__ppMiscBookmarkBound) {
        return;
    }
    window.__ppMiscBookmarkBound = true;

    $(document).on('click', '.js-ajax-bookmark-pp-misc', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $tag = $(this);
        if ($tag.data('busy')) {
            return;
        }

        $tag.data('busy', true).addClass('is-loading');

        $.ajax({
            url: $tag.data('url'),
            data: { _token: $tag.data('token') },
            type: 'POST',
            success: function (data) {
                $tag.toggleClass('isBookmarked', data && data.action === 'created');

                const isBookmarked = $tag.hasClass('isBookmarked');
                const label = isBookmarked ? 'Retirer des marque-pages' : 'Ajouter aux marque-pages';
                const text = isBookmarked ? 'Marqué' : 'Marquer';
                $tag.attr('aria-label', label).attr('title', label);
                $tag.find('.text').text(text);
            },
            error: function () {
                // Keep silent.
            },
            complete: function () {
                $tag.data('busy', false).removeClass('is-loading');
            }
        });
    });
})();
