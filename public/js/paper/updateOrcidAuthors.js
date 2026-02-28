/**
 * OrcidAuthorsManager
 * Refactored without jQuery, modern ES6+ vanilla JS
 * Compatible with Chrome, Firefox, Edge modern versions
 */

class OrcidAuthorsManager {
    static ORCID_PATTERN = /\d{4}-\d{4}-\d{4}-\d{3}(?:\d|X)/;

    constructor() {
        if (typeof document !== 'undefined') {
            this.init();
        }
    }

    init() {
        this.generateSelectAuthors();
        this.setupFormSubmission();
    }

    /**
     * Extracts and sanitizes ORCID from a string
     * @param {string} input
     * @returns {string}
     */
    static sanitizeOrcid(input) {
        if (!input) return '';
        const match = input.match(OrcidAuthorsManager.ORCID_PATTERN);
        return match ? match[0] : '';
    }

    /**
     * Updates the ORCID authors in the modal
     */
    updateOrcidAuthors() {
        const authorsListEl = document.querySelector('div#authors-list');
        const orcidExistingEl = document.querySelector('div#orcid-author-existing');
        const modalCalledEl = document.querySelector('input#modal-called');
        const modalBodyEl = document.querySelector('#modal-body-authors');

        if (!authorsListEl || !orcidExistingEl || !modalCalledEl || !modalBodyEl) {
            return;
        }

        // Avoid re-running if already called
        if (modalCalledEl.value !== '0') {
            return;
        }

        const authors = authorsListEl.textContent
            .split(';')
            .map(author => author.trim())
            .filter(author => author.length > 0);

        const orcidExisting = orcidExistingEl.textContent.split('##');

        authors.forEach((fullname, index) => {
            let orcid = orcidExisting[index] || '';
            if (orcid === 'NULL') {
                orcid = '';
            }

            const row = document.createElement('div');
            row.style.marginBottom = '15px';
            // Accessibility & Layout logic: Use flexbox for perfect alignment between label and input
            row.style.display = 'flex';
            row.style.justifyContent = 'space-between';
            row.style.alignItems = 'center';

            // Accessibility logic: Changed from span to label and added 'htmlFor' to link it with the input
            const label = document.createElement('label');
            label.id = `fullname__${index}`;
            label.htmlFor = `ORCIDauthor__${index}`;
            label.style.flex = '1';
            label.style.marginRight = '10px';
            label.style.marginBottom = '0'; // Reset label margin for better alignment
            label.style.fontWeight = 'normal'; 
            label.textContent = fullname;

            const input = document.createElement('input');
            input.id = `ORCIDauthor__${index}`;
            input.className = 'form-control'; // Adding bootstrap class if available for better look
            input.pattern = OrcidAuthorsManager.ORCID_PATTERN.source;
            input.placeholder = '1111-2222-3333-4444';
            input.style.width = '200px'; // Set a consistent width for ORCID inputs
            input.style.overflow = 'hidden';
            input.value = orcid;

            // Immediate visual cleanup on blur
            input.addEventListener('blur', () => {
                input.value = OrcidAuthorsManager.sanitizeOrcid(input.value);
            });

            row.appendChild(label);
            row.appendChild(input);
            modalBodyEl.appendChild(row);
        });

        modalCalledEl.value = '1';
    }

    /**
     * Generates the select list for authors
     */
    generateSelectAuthors() {
        const authorsListEl = document.querySelector('div#authors-list');
        const affiliationsLabelEl = document.querySelector('label#affiliations-label');

        if (!authorsListEl || !affiliationsLabelEl) {
            return;
        }

        const authors = authorsListEl.textContent
            .split(';')
            .map(author => author.trim())
            .filter(author => author.length > 0);

        const select = document.createElement('select');
        select.id = 'select-author-affi';
        select.className = 'form-control select-author-affi';
        select.style.width = 'auto';
        // Accessibility logic: Added an aria-label since it has no explicit <label> attached
        select.setAttribute('aria-label', 'Sélectionner un auteur');

        // Add empty option
        select.appendChild(document.createElement('option'));

        authors.forEach((author, index) => {
            const option = document.createElement('option');
            option.id = index.toString();
            option.value = author;
            option.textContent = author;
            select.appendChild(option);
        });

        affiliationsLabelEl.insertAdjacentElement('beforebegin', select);
    }

    /**
     * Sets up the form submission handler
     */
    setupFormSubmission() {
        const form = document.querySelector('form#post-orcid-author');
        if (!form) return;

        form.addEventListener('submit', async event => {
            event.preventDefault();

            // .action property returns the absolute URL, safer than getAttribute('action')
            const url = form.action;
            const fullnameLabels = document.querySelectorAll("label[id^='fullname__']");
            const orcidInputs = document.querySelectorAll("input[id^='ORCIDauthor__']");

            const arrayMerge = Array.from(fullnameLabels).map((labelEl, index) => {
                const fullname = labelEl.textContent.trim();
                const orcidInput = orcidInputs[index];
                const orcid = orcidInput ? OrcidAuthorsManager.sanitizeOrcid(orcidInput.value) : '';
                return [fullname, orcid];
            });

            // Validation: non-empty ORCIDs must be unique
            const nonEmptyOrcids = arrayMerge.map(item => item[1]).filter(orcid => orcid !== '');
            const uniqueOrcids = new Set(nonEmptyOrcids);
            if (uniqueOrcids.size !== nonEmptyOrcids.length) {
                alert(translate('orcid-duplicate'));
                return;
            }

            const docidEl = document.querySelector('div#docid-for-author');
            const paperidEl = document.querySelector('div#paperid-for-author');
            const rightOrcidEl = document.querySelector('div#rightOrcid');

            const payload = {
                docid: docidEl ? docidEl.textContent.trim() : '',
                paperid: paperidEl ? paperidEl.textContent.trim() : '',
                authors: arrayMerge,
                rightOrcid: rightOrcidEl ? rightOrcidEl.textContent.trim() : '',
            };

            try {
                // PHP frameworks often need X-Requested-With to correctly route AJAX requests.
                // Content-Type is set to match jQuery's default behavior.
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: JSON.stringify(payload)
                });

                if (response.ok) {
                    this.reloadPage();
                } else {
                    console.error('Failed to update ORCID authors', response.statusText);
                }
            } catch (error) {
                console.error('Error submitting ORCID authors:', error);
            }
        });
    }

    /**
     * Reloads the current page
     */
    reloadPage() {
        window.location.reload();
    }
}

// Export for Node.js/Jest
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
    module.exports = { OrcidAuthorsManager };
}

// Browser initialization
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        window.orcidAuthorsManager = new OrcidAuthorsManager();
    });

    // Expose updateOrcidAuthors globally
    window.updateOrcidAuthors = () => {
        if (window.orcidAuthorsManager) {
            window.orcidAuthorsManager.updateOrcidAuthors();
        }
    };
}
