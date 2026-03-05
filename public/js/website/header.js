document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('.menu-sortable');
    if (list) {
        // uniq is passed from the view
        window._headerManager = new HeaderManager(list, window._headerUniq || 0);
    }
});
