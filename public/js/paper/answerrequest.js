__initMCE('textarea');

let $hRequiredPwd = $('#h_requiredPwd');
let $paperPassword = $('#paperPassword');

let $commentOnly = $('[id^=comment_only_]');

let isEmptyComment = $commentOnly.length > 0 && $commentOnly.val() === '';

let isRequiredPaperPwd =
    $hRequiredPwd.length > 0 && $hRequiredPwd.val() === '1';
let isEmptyPaperPwd = $paperPassword.length > 0 && $paperPassword.val() === '';

checkCurrentForm();

tinyMCE.activeEditor.on('input', function () {
    isEmptyComment = $(this)[0].getContent() === '';
    checkCurrentForm();
});

$paperPassword.on('input', function () {
    isEmptyPaperPwd = $paperPassword.val() === '';
    checkCurrentForm();
});

function checkCurrentForm() {
    let isValidForm =
        !isEmptyComment &&
        (!isRequiredPaperPwd || (isRequiredPaperPwd && !isEmptyPaperPwd));
    isValidForm ? enableModalSubmitButton() : disableModalSubmitButton();
}
