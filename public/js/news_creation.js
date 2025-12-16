
/* Script to integrate News Form in the Covering Footer Layer (for "covering footer layer container" see base.html.twig */


$(document).ready(function(){

    const $newsForm = $(".news-form-struct");
    if ($newsForm.length) {
        $("#fullscreen-overlay").append($newsForm);
        $newsForm.hide();
    }

    $(".proxy-news-input, .js-footer-news").on('click', function (e){

        e.preventDefault();

        populateFooterNews();
        
        // If user have several presentation, we post news targeted presentation.
        var presentationId = $(this).data("pp-id");
        $("#news_presentationId").val(presentationId);

    });


    function populateFooterNews() {

        $("#fullscreen-overlay").addClass("displayFlex");
        $("#fullscreen-overlay").show();
        $newsForm.show();
        tinymce.execCommand('mceFocus',false,'news_textContent');

    }

});
