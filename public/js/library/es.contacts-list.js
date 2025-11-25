document.addEventListener('DOMContentLoaded', () => {
    const ccElement = document.getElementById('cc-element');
    const labels = ccElement?.querySelectorAll('label');

    labels?.forEach(label => {
        label.addEventListener('click', async e => {
            const form = e.target.closest('form');
            const contactsContainer = form?.nextElementSibling;

            if (!form || !contactsContainer) {
                return;
            }

            form.style.display = 'none';
            contactsContainer.style.display = 'block';
            contactsContainer.innerHTML = getLoader();

            try {
                const response = await fetch(
                    '/administratemail/getcontacts?target=cc',
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
                contactsContainer.innerHTML = content;
            } catch (error) {
                console.error('Error loading contacts:', error);
                contactsContainer.innerHTML =
                    '<p class="text-danger">Erreur lors du chargement des contacts</p>';
            }
        });
    });
});
