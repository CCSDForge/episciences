(function () {
    if (window.__epContactsListBound) {
        return;
    }
    window.__epContactsListBound = true;

    function ensureGetContactsCssLoaded() {
        // When getcontacts.phtml is injected via sanitizeHTML (DOMPurify),
        // <link> tags may be removed, resulting in an unstyled list (no row lines / no blue selection).
        // Load the stylesheet explicitly once.
        const id = 'ep-get-contacts-css';
        if (document.getElementById(id)) return;
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = '/css/administratemail/get-contacts.css';
        document.head.appendChild(link);
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
            const form = modalBody.querySelector('form');
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

        const form = modalBody.querySelector('form');
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
                    }
                });
            }

            try {
                const response = await fetch(
                    `/administratemail/getcontacts?target=${target}`,
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
