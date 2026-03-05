/**
 * Toggle Reply Form Component
 * Shows/hides editor reply forms in author-editor communication timeline
 */

(function() {
    'use strict';

    /**
     * Show reply form and hide button
     * @param {string} formId - ID of the form to show
     */
    function showReplyForm(formId) {
        var form = document.getElementById(formId);
        var button = document.querySelector('.toggle-reply-form[data-reply-form-id="' + formId + '"]');

        if (form && button) {
            // Hide the button
            button.style.display = 'none';

            // Show the form
            form.style.display = 'block';

            // Focus on the textarea
            var textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
        }
    }

    /**
     * Hide reply form and show button
     * @param {string} formId - ID of the form to hide
     */
    function hideReplyForm(formId) {
        var form = document.getElementById(formId);
        var button = document.querySelector('.toggle-reply-form[data-reply-form-id="' + formId + '"]');

        if (form && button) {
            // Hide the form
            form.style.display = 'none';

            // Show the reply button again
            button.style.display = 'inline-block';

            // Clear the textarea
            var textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.value = '';
            }
        }
    }

    /**
     * Initialize toggle reply form behavior
     */
    function initToggleReplyForm() {
        // Toggle reply form visibility when clicking the reply button
        var toggleButtons = document.querySelectorAll('.toggle-reply-form');
        for (var i = 0; i < toggleButtons.length; i++) {
            var button = toggleButtons[i];

            // Remove any existing event listeners
            var newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            // Add event listener
            (function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var formId = btn.getAttribute('data-reply-form-id');
                    showReplyForm(formId);
                });
            })(newButton);
        }

        // Cancel reply form - hide form (fold) and show button again
        // For reply forms: clicking Cancel will fold/hide the form and show the Reply button
        var cancelButtons = document.querySelectorAll('.cancel-reply-form');
        for (var j = 0; j < cancelButtons.length; j++) {
            var cancelBtn = cancelButtons[j];

            // Remove any existing event listeners
            var newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Add event listener
            (function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var formId = btn.getAttribute('data-reply-form-id');
                    hideReplyForm(formId); // This hides the form and shows the Reply button
                });
            })(newCancelBtn);
        }

        // Cancel main author form - clear form fields (delete message content)
        // For the main form: clicking Cancel will clear/delete the message content but keep the form visible
        var cancelAuthorFormButtons = document.querySelectorAll('.cancel-author-form');
        for (var k = 0; k < cancelAuthorFormButtons.length; k++) {
            var cancelAuthorBtn = cancelAuthorFormButtons[k];

            // Remove any existing event listeners
            var newCancelAuthorBtn = cancelAuthorBtn.cloneNode(true);
            cancelAuthorBtn.parentNode.replaceChild(newCancelAuthorBtn, cancelAuthorBtn);

            // Add event listener
            (function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var form = btn.closest('form');
                    if (form) {
                        // Clear textarea (delete message content)
                        var textarea = form.querySelector('textarea[name="comment"]');
                        if (textarea) {
                            textarea.value = '';
                        }
                        // Clear file input (delete attached file)
                        var fileInput = form.querySelector('input[type="file"]');
                        if (fileInput) {
                            fileInput.value = '';
                        }
                    }
                    return false;
                });
            })(newCancelAuthorBtn);
        }
    }

    /**
     * Initialize on DOM ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initToggleReplyForm);
        } else {
            // DOM is already loaded
            initToggleReplyForm();
        }

        // Re-initialize when new content is added (e.g., after AJAX)
        document.addEventListener('contentLoaded', initToggleReplyForm);
    }

    // Start initialization
    init();

})();