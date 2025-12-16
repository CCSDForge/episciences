$(function () {
    $('button[id^="cancel"]').click(function () {
        $('#submitNewVersion').hide();
        $('#submitTmpVersion').hide();
        $('#comment_only').fadeIn();
    });

    $('#displayRevisionForm').click(function () {
        $('#comment_only').hide();
        $('#submitTmpVersion').hide();
        $('#submitNewVersion').fadeIn();
        // window.location.hash = 'answer';
    });

    $('#displayTmpVersionForm').click(function () {
        $('#comment_only').hide();
        $('#submitNewVersion').hide();
        $('#submitTmpVersion').fadeIn();
    });

    $('.replyButton').each(function () {
        $(this).click(function () {
            var form = $(this).next('.replyForm');
            $(form).parent().find('.replyForm').hide();
            $(form).parent().find('.replyButton').show();
            $(this).hide();
            $(form).fadeIn(400, function() {
                // Scroll automatiquement vers le formulaire après l'animation
                form[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            });
        });
    });

    $('button[id^="cancel"]').each(function () {
        $(this).click(function () {
            $(this).closest('.replyForm').hide();
            $(this).closest('.replyForm').prev().find('.replyButton').fadeIn();
        });
    });

    if (isFromZSubmit) {
        if ($('#answer-request').length > 0) {
            $('#answer-request').click();
        }

        setTimeout(function () {
            if ($('#new-version').length > 0) {
                $('#new-version').click();
            }
        }, 0.1);
    }

    // show and hide citations to avoid big listing page
    $('button[id^="btn-show-citations"]').click(function () {
        $('div#list-citations').show();
        $('#btn-hide-citations').show();
        $('#btn-show-citations').hide();
    });
    $('button[id^="btn-hide-citations"]').click(function () {
        $('div#list-citations').hide();
        $('#btn-hide-citations').hide();
        $('#btn-show-citations').show();
    });
});
