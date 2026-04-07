(function () {
    if (window.__epContactsListBound) {
        return;
    }
    window.__epContactsListBound = true;

    function ensureGetContactsCssLoaded() {
        // DOMPurify removes <link> tags, so load the stylesheet manually
        const id = 'ep-get-contacts-css';
        if (document.getElementById(id)) return;
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = '/css/administratemail/get-contacts.css';
        document.head.appendChild(link);
    }

    // Sync footer submit disabled state with revision-deadline rules (see view.js).
    function refreshRevisionSubmitAfterContactsClosed(modalBody) {
        if (!modalBody || typeof jQuery === 'undefined') {
            return;
        }
        const $deadline = jQuery(modalBody).find("[id$='-revision-deadline']");
        if ($deadline.length) {
            $deadline.trigger('change');
        }
    }

    // Clean up contacts state when modal closes to prevent issues when opening another modal
    function cleanupContactsState(modal) {
        const modalBody = modal.querySelector('.modal-body');
        if (!modalBody) return;

        const contactsContainer = modalBody.querySelector('.contacts-container');
        const form = modalBody.querySelector('form');

        // Clear __epContactsForm if it belongs to this modal
        if (
            window.__epContactsForm instanceof HTMLElement &&
            modalBody.contains(window.__epContactsForm)
        ) {
            window.__epContactsForm = null;
        }

        // Reset contacts container
        if (contactsContainer) {
            contactsContainer.innerHTML = '';
            contactsContainer.style.display = 'none';
        }

        // Ensure form is visible again
        if (form) {
            form.style.display = '';
        }

        // Reset submit button state
        const modalContent = modal.querySelector('.modal-content');
        const submitButton = modalContent
            ? modalContent.querySelector('.submit-modal')
            : null;
        if (submitButton && submitButton.dataset.epContactsBound) {
            const originalText = submitButton.dataset.epContactsOriginalText;
            if (originalText) {
                submitButton.textContent = originalText;
            }
            delete submitButton.dataset.epContactsBound;
            delete submitButton.dataset.epContactsOriginalText;
        }

        // Clear global contact variables to prevent stale data
        if (typeof window.all_contacts !== 'undefined') {
            window.all_contacts = null;
        }

        // Clear added contacts hidden input and tags
        const hiddenAddedContacts = document.getElementById('hidden_added_contacts');
        if (hiddenAddedContacts) {
            hiddenAddedContacts.value = '[]';
        }
        const addedContactsTags = document.getElementById('added_contacts_tags');
        if (addedContactsTags) {
            addedContactsTags.innerHTML = '';
        }
    }

    // Listen for modal hide events to clean up contacts state
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('hide.bs.modal', '.modal', function () {
            cleanupContactsState(this);
        });
    }

    // Intercept .submit-modal clicks when contacts list is open (capture phase)
    document.addEventListener(
        'click',
        evt => {
            const submitBtn = evt.target.closest
                ? evt.target.closest('.submit-modal')
                : null;
            if (!submitBtn) return;

            const modalBody = submitBtn
                .closest('.modal-content')
                ?.querySelector('.modal-body');
            if (!modalBody) return;

            const contactsContainer = modalBody.querySelector(
                '.contacts-container'
            );
            const cf =
                typeof window !== 'undefined' &&
                window.__epContactsForm instanceof HTMLElement &&
                modalBody.contains(window.__epContactsForm)
                    ? window.__epContactsForm
                    : null;
            const form = cf || modalBody.querySelector('form');
            if (!contactsContainer || !form) return;

            const contactsOpen =
                contactsContainer.style.display !== 'none' &&
                form.style.display === 'none';

            if (contactsOpen) {
                evt.preventDefault();
                evt.stopImmediatePropagation();
                if (typeof addContacts === 'function') {
                    addContacts(true);
                }
                contactsContainer.style.display = 'none';
                form.style.display = 'block';
                const requiredFields = modalBody.querySelectorAll(
                    '.ccsd_form_required'
                );
                requiredFields.forEach(el => (el.style.display = ''));
                refreshRevisionSubmitAfterContactsClosed(modalBody);
            }
        },
        true
    );

    // Delegated handler: works when DOMContentLoaded already fired before this script,
    // and uses the anchor as root for .closest (avoids Text nodes without .closest).
    document.addEventListener('click', ev => {
        const button =
            ev.target && ev.target.closest
                ? ev.target.closest('a.show_contacts_button')
                : null;
        if (!button) return;

        const modalBody = button.closest('.modal-body');
        if (!modalBody) return;

        const form = button.closest('form') || modalBody.querySelector('form');
        const contactsContainer = modalBody.querySelector(
            '.contacts-container'
        );
        if (!form || !contactsContainer) return;

        ev.preventDefault();

        void (async () => {
            const url = new URL(button.href, window.location.origin);
            const target = url.searchParams.get('target');

            // Store form reference for addRecipient() to find the correct tags container
            window.__epContactsForm = form;

            form.style.display = 'none';

            const requiredFields = modalBody.querySelectorAll(
                '.ccsd_form_required'
            );
            requiredFields.forEach(el => (el.style.display = 'none'));

            contactsContainer.style.display = 'block';
            contactsContainer.innerHTML = getLoader();
            ensureGetContactsCssLoaded();

            const modalContent = modalBody.closest('.modal-content');
            const submitButton = modalContent
                ? modalContent.querySelector('.submit-modal')
                : null;
            if (submitButton) {
                submitButton.disabled = false;
                if (submitButton.dataset.epContactsBound) {
                    submitButton.textContent =
                        submitButton.getAttribute('data-contacts-text') ||
                        'Submit';
                }
            }
            if (submitButton && !submitButton.dataset.epContactsBound) {
                submitButton.dataset.epContactsBound = 'true';
                submitButton.dataset.epContactsOriginalText =
                    submitButton.textContent || '';
                submitButton.textContent =
                    submitButton.getAttribute('data-contacts-text') || 'Submit';
                submitButton.addEventListener('click', evt => {
                    if (
                        contactsContainer.style.display !== 'none' &&
                        form.style.display === 'none'
                    ) {
                        evt.preventDefault();
                        if (typeof addContacts === 'function') {
                            addContacts(true);
                        }
                        contactsContainer.style.display = 'none';
                        form.style.display = 'block';
                        requiredFields.forEach(el => (el.style.display = ''));
                        const originalText =
                            submitButton.dataset.epContactsOriginalText;
                        if (originalText) {
                            submitButton.textContent = originalText;
                        }
                        refreshRevisionSubmitAfterContactsClosed(modalBody);
                    }
                });
            }

            try {
                const response = await fetch(
                    `${JS_PREFIX_URL}administratemail/getcontacts?target=${target}`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: 'ajax=true',
                    }
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const content = await response.text();

                // Extract and execute scripts before sanitizing HTML
                // (DOMPurify will remove <script> tags, but contact data variables are needed)
                const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script[^>]*>/gi;
                let match;
                const scriptsToExecute = [];
                while ((match = scriptRegex.exec(content)) !== null) {
                    scriptsToExecute.push(match[1]);
                }

                scriptsToExecute.forEach(scriptContent => {
                    const scriptElement = document.createElement('script');
                    scriptElement.textContent = scriptContent;
                    document.head.appendChild(scriptElement);
                    // Remove after execution to keep DOM clean
                    document.head.removeChild(scriptElement);
                });

                // Sanitize HTML before injection to prevent XSS
                contactsContainer.innerHTML = sanitizeHTML(content);

                // Load get-contacts.js for filter functionality
                // (DOMPurify removes <script> tags, so it must be loaded manually)
                $.ajaxSetup({ cache: true });
                $.getScript('/js/administratemail/get-contacts.js')
                    .done(function () {
                        if (typeof initGetContacts === 'function') {
                            initGetContacts();
                        }
                    })
                    .fail(function () {
                        console.error('Failed to load get-contacts.js');
                    });
            } catch (error) {
                console.error('Error loading contacts:', error);
                contactsContainer.innerHTML =
                    '<p class="text-danger">Error loading contacts</p>';
            }
        })();
    });
})();
