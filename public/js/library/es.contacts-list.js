$(function () {

    $('#cc-element').find('label').click(function() {

        var $form = $(this).closest('form');
        var $contacts_container = $form.next('.contacts_container');

        $form.hide();
        $contacts_container.show();
        $contacts_container.html(getLoader());

        $.ajax({
            url: '/administratemail/getcontacts?target=cc',
            type: 'POST',
            data: {ajax: true},
            success: function (content) {
                $contacts_container.html(content);
            }
        });
    });

});
