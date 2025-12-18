document.addEventListener('DOMContentLoaded', function () {
    // Cache DOM selectors
    const submitNewVersion = document.getElementById('submitNewVersion');
    const submitTmpVersion = document.getElementById('submitTmpVersion');
    const commentOnly = document.getElementById('comment_only');

    // Utility functions for fade animations
    function hide(element) {
        if (element) {
            element.style.display = 'none';
        }
    }

    function show(element) {
        if (element) {
            element.style.display = '';
        }
    }

    function fadeIn(element, duration = 400, callback = null) {
        if (!element) return;

        element.style.opacity = '0';
        element.style.display = '';

        let start = null;
        function animate(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            const percentage = Math.min(progress / duration, 1);

            element.style.opacity = percentage.toString();

            if (progress < duration) {
                requestAnimationFrame(animate);
            } else if (callback) {
                callback();
            }
        }

        requestAnimationFrame(animate);
    }

    // Cancel buttons - hide forms and show comment_only
    document.querySelectorAll('button[id^="cancel"]').forEach(function (button) {
        button.addEventListener('click', function () {
            hide(submitNewVersion);
            hide(submitTmpVersion);
            fadeIn(commentOnly);
        });
    });

    // Display revision form
    const displayRevisionForm = document.getElementById('displayRevisionForm');
    if (displayRevisionForm) {
        displayRevisionForm.addEventListener('click', function () {
            hide(commentOnly);
            hide(submitTmpVersion);
            fadeIn(submitNewVersion);
            // window.location.hash = 'answer';
        });
    }

    // Display temporary version form
    const displayTmpVersionForm = document.getElementById('displayTmpVersionForm');
    if (displayTmpVersionForm) {
        displayTmpVersionForm.addEventListener('click', function () {
            hide(commentOnly);
            hide(submitNewVersion);
            fadeIn(submitTmpVersion);
        });
    }

    // Reply buttons
    document.querySelectorAll('.replyButton').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.nextElementSibling;
            if (!form || !form.classList.contains('replyForm')) return;

            const parent = form.parentElement;

            // Hide all reply forms and show all reply buttons in parent
            parent.querySelectorAll('.replyForm').forEach(hide);
            parent.querySelectorAll('.replyButton').forEach(show);

            hide(button);
            fadeIn(form, 400, function () {
                // Automatically scroll to the form after animation
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        });
    });

    // Cancel buttons in reply forms
    document.querySelectorAll('button[id^="cancel"]').forEach(function (button) {
        button.addEventListener('click', function () {
            const replyForm = button.closest('.replyForm');
            if (!replyForm) return;

            hide(replyForm);
            const prevElement = replyForm.previousElementSibling;
            if (prevElement) {
                const replyButton = prevElement.querySelector('.replyButton');
                if (replyButton) {
                    fadeIn(replyButton);
                }
            }
        });
    });

    // Handle isFromZSubmit flag
    if (typeof isFromZSubmit !== 'undefined' && isFromZSubmit) {
        const answerRequest = document.getElementById('answer-request');
        if (answerRequest) {
            answerRequest.click();
        }

        setTimeout(function () {
            const newVersion = document.getElementById('new-version');
            if (newVersion) {
                newVersion.click();
            }
        }, 0.1);
    }

    // Show and hide citations to avoid big listing page
    const listCitations = document.getElementById('list-citations');
    const btnHideCitations = document.getElementById('btn-hide-citations');
    const btnShowCitations = document.getElementById('btn-show-citations');

    document.querySelectorAll('button[id^="btn-show-citations"]').forEach(function (button) {
        button.addEventListener('click', function () {
            show(listCitations);
            show(btnHideCitations);
            hide(btnShowCitations);
        });
    });

    document.querySelectorAll('button[id^="btn-hide-citations"]').forEach(function (button) {
        button.addEventListener('click', function () {
            hide(listCitations);
            hide(btnHideCitations);
            show(btnShowCitations);
        });
    });
});