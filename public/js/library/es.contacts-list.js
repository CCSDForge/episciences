$(function () {

    $('#cc-element').find('label').click(function() {


        let $form = $(this).closest('form');
        let $contacts_container = $form.next('.contacts_container');

        $form.hide();
        $contacts_container.show();
        $contacts_container.html(getLoader());

        $.ajax({
            url: JS_PREFIX_URL + 'administratemail/getcontacts?target=cc',
            type: 'POST',
            data: {ajax: true},
            success: function (content) {
                $contacts_container.html(content);
            }
        });
    });

});
