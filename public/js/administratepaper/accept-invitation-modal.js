/**
 * Modal handler for "accept a review invitation on behalf of the reviewer".
 *
 * Defines the global submit() function invoked by the modal "OK" button
 * (data-callback="submit"). Posts the confirmation form, then refreshes the
 * reviewers block and the paper history on success.
 *
 * NOTE: must be a regular function (not async) because jQuery 1.x's
 * jQuery.isFunction() returns false for async functions ([object AsyncFunction]
 * is not in its class2type map).
 */
function submit() {
    var form = document.querySelector('#accept-invitation-form');
    var action = form.getAttribute('action');
    // Extract docid from the form action URL query string.
    var docid = new URL(action, window.location.href).searchParams.get('docid');

    var errorsEl = document.querySelector('#modal-box .accept-invitation-errors');
    var errorsMsg = document.querySelector('#modal-box .errors-message');

    function showError(message) {
        errorsMsg.textContent = '* ' + message;
        errorsEl.style.display = 'block';
    }

    fetch(action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(new FormData(form)),
    })
        .then(function (resp) {
            return resp.json();
        })
        .then(function (data) {
            if (data.status !== 1) {
                showError(data.message);
                return;
            }

            // Bootstrap 3 requires jQuery for its modal API.
            $('#modal-box').modal('hide');

            var reviewers = document.getElementById('reviewers');
            reviewers.innerHTML = getLoader(); // eslint-disable-line no-unsanitized/property -- static markup

            var logs = document.querySelector('#history .panel-body');
            logs.innerHTML = getLoader(); // eslint-disable-line no-unsanitized/property -- static markup

            return Promise.all([
                fetch('/administratepaper/displayinvitations', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ docid: docid, partial: 'false' }),
                }).then(function (r) {
                    return r.text();
                }),
                fetch('/administratepaper/displaylogs', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ docid: docid }),
                }).then(function (r) {
                    return r.text();
                }),
            ]).then(function (results) {
                // Server-rendered HTML from same-origin PHP views; user data is escaped via $this->escape().
                reviewers.innerHTML = results[0]; // eslint-disable-line no-unsanitized/property
                logs.innerHTML = results[1]; // eslint-disable-line no-unsanitized/property
            });
        })
        .catch(function () {
            showError(translate('Une erreur est survenue'));
        });
}
