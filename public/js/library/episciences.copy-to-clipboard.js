const CopyToClipboard = (function () {
    'use strict';

    function showFeedback(el) {
        if (!el) return;
        el.style.display = 'inline-block';
        setTimeout(function () {
            el.style.display = 'none';
        }, 2000);
    }

    function fallbackCopy(text, feedbackEl) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showFeedback(feedbackEl);
        } catch (err) {
            console.error('Fallback copy failed', err);
        }
        document.body.removeChild(textarea);
    }

    function copyText(text, feedbackEl) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard
                .writeText(text)
                .then(function () {
                    showFeedback(feedbackEl);
                })
                .catch(function () {
                    fallbackCopy(text, feedbackEl);
                });
        } else {
            fallbackCopy(text, feedbackEl);
        }
    }

    function init(container) {
        if (!container) return;
        container.addEventListener('click', function (event) {
            const btn = event.target.closest('.copy-btn');
            if (!btn) return;
            const text = btn.getAttribute('data-copy-value');
            if (text === null) return;
            const scope = btn.closest('tr') || btn.parentElement;
            const feedback = scope ? scope.querySelector('.copy-feedback') : null;
            copyText(text, feedback);
        });
    }

    return { init: init, copyText: copyText };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = CopyToClipboard;
}