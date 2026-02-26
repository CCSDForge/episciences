document.addEventListener('DOMContentLoaded', () => {
    // Handle all .show_contacts_button links across all forms
    const contactButtons = document.querySelectorAll('.show_contacts_button');

    contactButtons.forEach(button => {
        button.addEventListener('click', async e => {
            e.preventDefault();

            // Get the target (cc or bcc) from the link href
            const url = new URL(button.href, window.location.origin);
            const target = url.searchParams.get('target');

            const modalBody = e.target.closest('.modal-body');
            if (!modalBody) {
                return;
            }

            const form = modalBody.querySelector('form');
            const contactsContainer = modalBody.querySelector('.contacts-container');

            if (!form || !contactsContainer) {
                return;
            }

            // Hide the form
            form.style.display = 'none';

            // Also hide all "Required fields" decorators in the modal body
            const requiredFields = modalBody.querySelectorAll('.ccsd_form_required');
            requiredFields.forEach(el => el.style.display = 'none');

            contactsContainer.style.display = 'block';
            contactsContainer.innerHTML = getLoader(); // OK: getLoader() returns static HTML

            try {
                const response = await fetch(
                    `${JS_PREFIX_URL}/administratemail/getcontacts?target=${target}`,
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

                // Extract and execute JavaScript variables before sanitizing
                // (DOMPurify will remove <script> tags, but contact data variables are needed)
                // Create actual <script> tags and append them to execute in global scope
                const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script[^>]*>/gi;
                let match;
                const scriptsToExecute = [];
                while ((match = scriptRegex.exec(content)) !== null) {
                    scriptsToExecute.push(match[1]);
                }

                // Execute each script by creating a script element
                scriptsToExecute.forEach(scriptContent => {
                    const scriptElement = document.createElement('script');
                    scriptElement.textContent = scriptContent;
                    document.head.appendChild(scriptElement);
                    // Remove it after execution to keep DOM clean
                    document.head.removeChild(scriptElement);
                });

                // SECURITY FIX: Sanitize HTML from server before injection to prevent XSS
                contactsContainer.innerHTML = sanitizeHTML(content);

                // Load the get-contacts.js script to enable filter functionality
                // Note: DOMPurify removes <script> tags, so it must be loaded manually
                $.ajaxSetup({ cache: true });
                $.getScript('/js/administratemail/get-contacts.js')
                    .done(function() {
                        if (typeof initGetContacts === 'function') {
                            initGetContacts();
                        }
                    })
                    .fail(function() {
                        console.error('Failed to load get-contacts.js');
                    });
            } catch (error) {
                console.error('Error loading contacts:', error);
                contactsContainer.innerHTML =
                    '<p class="text-danger">Error loading contacts</p>';
            }
        });
    });
});
