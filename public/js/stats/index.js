$(function () {
    let $loadingStats = $('#loading-stats');
    $loadingStats.html(getLoader());
    $loadingStats.show();
    $(window).load(function () {
        $loadingStats.hide();
    });
});
