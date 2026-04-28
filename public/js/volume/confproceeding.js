/**
 * Conference Proceeding Module
 *
 * Manages the display and behavior of conference-related fields in volume forms.
 * Handles the "is proceeding" checkbox toggle, DOI request functionality,
 * and dynamic field requirements.
 *
 * @module ConferenceProceeding
 */
const ConferenceProceeding = {
    // Store original title label text
    originalTitleLabel: '',

    /**
     * Sets a field as required and updates its label styling
     *
     * @param {string} fieldName - The name/id of the field to make required
     */
    setRequiredInput(fieldName) {
        const field = document.getElementById(fieldName);
        const label = document.querySelector(
            `label[for='${CSS.escape(fieldName)}']`
        );

        if (field) {
            field.setAttribute('required', 'required');
        }
        if (label) {
            label.classList.remove('optional');
            label.classList.add('required');
        }
    },

    /**
     * Removes required status from a field and updates its label styling
     *
     * @param {string} fieldName - The name/id of the field to make optional
     */
    unsetRequiredInput(fieldName) {
        const field = document.getElementById(fieldName);
        const label = document.querySelector(
            `label[for='${CSS.escape(fieldName)}']`
        );

        if (field) {
            field.removeAttribute('required');
        }
        if (label) {
            label.classList.remove('required');
            label.classList.add('optional');
        }
    },

    /**
     * Adds DOI prefix to the conference proceedings DOI field placeholder and label
     */
    addDoiPrefix() {
        const journalPrefixDoi = document.getElementById('journalprefixDoi');
        const proceedingsDoiField = document.getElementById(
            'conference_proceedings_doi'
        );
        const proceedingsDoiLabel = document.querySelector(
            "label[for='conference_proceedings_doi']"
        );

        if (journalPrefixDoi && proceedingsDoiField) {
            const prefix = journalPrefixDoi.value;
            proceedingsDoiField.setAttribute('placeholder', prefix);

            if (proceedingsDoiLabel) {
                proceedingsDoiLabel.textContent = `${proceedingsDoiLabel.textContent} ${prefix}`;
            }
        }
    },

    /**
     * Shows all conference-related fields
     */
    showConferenceFields() {
        const conferenceFields = document.querySelectorAll(
            'div[id^="conference_"]'
        );
        conferenceFields.forEach(field => {
            field.style.display = '';
        });
    },

    /**
     * Hides all conference-related fields
     */
    hideConferenceFields() {
        const conferenceFields = document.querySelectorAll(
            'div[id^="conference_"]'
        );
        conferenceFields.forEach(field => {
            field.style.display = 'none';
        });
    },

    /**
     * Updates the title label based on proceeding status
     *
     * @param {boolean} isProceeding - Whether the volume is a proceeding
     */
    updateTitleLabel(isProceeding) {
        const titleLabel = document.querySelector('div#title-element > label');
        const translateTextInput = document.getElementById('translate_text');

        if (!titleLabel) return;

        if (isProceeding && translateTextInput) {
            titleLabel.textContent = translateTextInput.value;
        } else {
            titleLabel.textContent = this.originalTitleLabel;
        }
    },

    /**
     * Sets conference fields as required
     */
    setConferenceFieldsRequired() {
        this.setRequiredInput('conference_name');
        this.setRequiredInput('conference_start-id');
        this.setRequiredInput('conference_end-id');
    },

    /**
     * Sets conference fields as optional
     */
    setConferenceFieldsOptional() {
        this.unsetRequiredInput('conference_name');
        this.unsetRequiredInput('conference_start');
        this.unsetRequiredInput('conference_end');
    },

    /**
     * Handles the proceeding checkbox change event
     */
    handleProceedingChange() {
        const isProceedingCheckbox = document.getElementById('is_proceeding');
        if (!isProceedingCheckbox) return;

        const isChecked = isProceedingCheckbox.checked;

        if (isChecked) {
            this.showConferenceFields();
            this.updateTitleLabel(true);
            this.setConferenceFieldsRequired();
        } else {
            this.hideConferenceFields();
            this.updateTitleLabel(false);
            this.setConferenceFieldsOptional();
        }
    },

    /**
     * Updates the request DOI button state based on DOI field value
     */
    updateRequestButtonState() {
        const doiField = document.getElementById('conference_proceedings_doi');
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );
        const displayDoi = document.getElementById('display-doi-proceeding');

        if (!doiField || !requestButton) return;

        if (doiField.value.length === 0) {
            requestButton.disabled = true;
            if (displayDoi) {
                displayDoi.textContent = '';
            }
        } else {
            requestButton.disabled = false;
        }
    },

    /**
     * Displays the DOI request confirmation
     */
    displayDoiRequest() {
        const prefixInput = document.getElementById(
            'doi_proceedings_prefix_input'
        );
        const doiField = document.getElementById('conference_proceedings_doi');
        const translateTextDoi = document.getElementById(
            'translate_text_doi_request'
        );
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );
        const cancelButton = document.getElementById(
            'btn-cancel-request-proceedings'
        );

        if (!prefixInput || !doiField) return;

        const prefix = prefixInput.value;
        const doiSuffix = doiField.value;
        const requestText = translateTextDoi
            ? translateTextDoi.value
            : 'DOI request';

        let displayDoi = document.getElementById('display-doi-proceeding');

        if (!displayDoi) {
            // Create display elements
            const container = document.createElement('div');
            container.className = 'col-sm-3 d-inline-block';

            displayDoi = document.createElement('em');
            displayDoi.id = 'display-doi-proceeding';
            displayDoi.style.paddingTop = '2%';
            displayDoi.style.display = 'inline-block';
            displayDoi.style.verticalAlign = 'middle';

            container.appendChild(displayDoi);
            requestButton?.insertAdjacentElement('afterend', container);
        }

        displayDoi.textContent = `${requestText} -> ${prefix}${doiSuffix}`;

        // Update button states and field
        if (doiField) doiField.readOnly = true;
        if (requestButton) requestButton.style.display = 'none';
        if (cancelButton) cancelButton.style.display = '';
    },

    /**
     * Cancels the DOI request
     */
    cancelDoiRequest() {
        const displayDoi = document.getElementById('display-doi-proceeding');
        const doiField = document.getElementById('conference_proceedings_doi');
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );
        const cancelButton = document.getElementById(
            'btn-cancel-request-proceedings'
        );

        if (displayDoi?.parentElement) {
            displayDoi.parentElement.remove();
        }

        if (requestButton) requestButton.style.display = '';
        if (cancelButton) cancelButton.style.display = 'none';
        if (doiField) doiField.readOnly = false;
    },

    /**
     * Handles DOI status display for already assigned/pending DOIs
     */
    handleExistingDoi() {
        const doiStatus = document.getElementById('doi_status');
        if (!doiStatus) return;

        const status = doiStatus.value;
        if (status === 'assigned' || status === 'not-assigned') {
            return;
        }

        const prefixInput = document.getElementById(
            'doi_proceedings_prefix_input'
        );
        const doiField = document.getElementById('conference_proceedings_doi');
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );

        if (!prefixInput || !doiField) return;

        const prefix = prefixInput.value;
        const doiSuffix = doiField.value;

        // Remove the input field
        doiField.remove();

        // Create display element
        const container = document.createElement('div');
        const displayDoi = document.createElement('em');
        displayDoi.id = 'display-doi-proceeding';
        displayDoi.textContent = `${prefix}${doiSuffix}`;

        container.appendChild(displayDoi);
        prefixInput.replaceWith(container);

        // Remove request button
        if (requestButton) {
            requestButton.remove();
        }
    },

    /**
     * Initializes the initial state based on proceeding checkbox
     */
    initializeState() {
        const titleLabel = document.querySelector('div#title-element > label');
        if (titleLabel) {
            this.originalTitleLabel = titleLabel.textContent;
        }

        const isProceedingCheckbox = document.getElementById('is_proceeding');
        if (!isProceedingCheckbox) return;

        if (isProceedingCheckbox.checked) {
            this.updateTitleLabel(true);
            this.showConferenceFields();
            this.setConferenceFieldsRequired();
        } else {
            this.hideConferenceFields();
        }
    },

    /**
     * Initializes button states
     */
    initializeButtons() {
        const doiField = document.getElementById('conference_proceedings_doi');
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );
        const cancelButton = document.getElementById(
            'btn-cancel-request-proceedings'
        );

        if (doiField && requestButton) {
            if (doiField.value === '') {
                requestButton.disabled = true;
            }
        }

        if (cancelButton && doiField?.value === '') {
            cancelButton.style.display = 'none';
        }
    },

    /**
     * Attaches all event listeners
     */
    attachEventListeners() {
        // Proceeding checkbox change
        const isProceedingCheckbox = document.getElementById('is_proceeding');
        if (isProceedingCheckbox) {
            isProceedingCheckbox.addEventListener('click', () => {
                this.handleProceedingChange();
            });
        }

        // DOI field input change
        const doiField = document.getElementById('conference_proceedings_doi');
        if (doiField) {
            doiField.addEventListener('keyup', () => {
                this.updateRequestButtonState();
            });
            doiField.addEventListener('change', () => {
                this.updateRequestButtonState();
            });
        }

        // Request DOI button
        const requestButton = document.getElementById(
            'btn-request-proceedings'
        );
        if (requestButton) {
            requestButton.addEventListener('click', () => {
                this.displayDoiRequest();
            });
        }

        // Cancel DOI request button
        const cancelButton = document.getElementById(
            'btn-cancel-request-proceedings'
        );
        if (cancelButton) {
            cancelButton.addEventListener('click', () => {
                this.cancelDoiRequest();
            });
        }
    },

    /**
     * Initializes the module
     */
    init() {
        console.log('ConferenceProceeding: Initialized');

        this.initializeState();
        this.addDoiPrefix();
        this.initializeButtons();
        this.handleExistingDoi();
        this.attachEventListeners();
    },
};

// Auto-initialize when DOM is ready (only in browser, not in tests)
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            ConferenceProceeding.init();
        });
    } else {
        ConferenceProceeding.init();
    }
}

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ConferenceProceeding;
}
