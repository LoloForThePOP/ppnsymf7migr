
/* Script to integrate News Form in the Covering Footer Layer (for "covering footer layer container" see base.html.twig */


$(document).ready(function(){

    const $newsForm = $(".news-form-struct");
    const $overlay = $("#fullscreen-overlay");
    if (!$newsForm.length || !$overlay.length) {
        return;
    }

    const defaultPresentationId = $newsForm.data("pp-id") || null;

    $overlay.append($newsForm);
    $newsForm.hide();

    const setPresentationId = function (presentationId) {
        const targetId = presentationId || defaultPresentationId;
        if (targetId && $("#news_presentationId").length) {
            $("#news_presentationId").val(targetId);
        }
    };

    $(".proxy-news-input, .js-footer-news").on('click', function (e){

        e.preventDefault();

        setPresentationId($(this).data("pp-id"));
        populateFooterNews();

    });

    $overlay.find('.close-button').on('click', function() {
        $newsForm.hide();
    });

    function populateFooterNews() {

        $overlay.addClass("displayFlex");
        $overlay.show();
        $newsForm.show();

        if (window.tinymce) {
            tinymce.execCommand('mceFocus',false,'news_textContent');
        }

    }

});
