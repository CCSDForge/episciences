const ConferenceProceeding = require('../../../public/js/volume/confproceeding.js');

describe('Conference Proceeding Module', () => {
    beforeEach(() => {
        // Setup DOM with all required elements
        document.body.innerHTML = `
            <div id="title-element">
                <label>Original Title Label</label>
            </div>
            <input id="translate_text" type="hidden" value="Conference Proceedings Title" />
            <input id="is_proceeding" type="checkbox" />
            <div id="conference_name-element" class="conference-field">
                <label for="conference_name" class="optional">Conference Name</label>
                <input id="conference_name" type="text" />
            </div>
            <div id="conference_start-element" class="conference-field">
                <label for="conference_start-id" class="optional">Start Date</label>
                <input id="conference_start-id" type="text" />
            </div>
            <div id="conference_end-element" class="conference-field">
                <label for="conference_end-id" class="optional">End Date</label>
                <input id="conference_end-id" type="text" />
            </div>
            <input id="journalprefixDoi" type="hidden" value="10.1234/" />
            <div id="conference_proceedings_doi-element">
                <label for="conference_proceedings_doi">DOI</label>
                <input id="conference_proceedings_doi" type="text" value="" />
            </div>
            <input id="doi_proceedings_prefix_input" type="hidden" value="10.1234/" />
            <input id="translate_text_doi_request" type="hidden" value="DOI Request" />
            <input id="btn-request-proceedings" type="button" value="Request DOI" />
            <input id="btn-cancel-request-proceedings" type="button" value="Cancel" style="display: none;" />
            <input id="doi_status" type="hidden" value="not-assigned" />
        `;

        // Reset module state
        ConferenceProceeding.originalTitleLabel = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    describe('setRequiredInput', () => {
        test('should set field as required and update label', () => {
            const field = document.getElementById('conference_name');
            const label = document.querySelector(
                "label[for='conference_name']"
            );

            ConferenceProceeding.setRequiredInput('conference_name');

            expect(field.hasAttribute('required')).toBe(true);
            expect(label.classList.contains('required')).toBe(true);
            expect(label.classList.contains('optional')).toBe(false);
        });

        test('should handle missing field gracefully', () => {
            expect(() => {
                ConferenceProceeding.setRequiredInput('non-existent-field');
            }).not.toThrow();
        });
    });

    describe('unsetRequiredInput', () => {
        test('should remove required attribute and update label', () => {
            const field = document.getElementById('conference_name');
            const label = document.querySelector(
                "label[for='conference_name']"
            );

            // First set as required
            field.setAttribute('required', 'required');
            label.classList.add('required');

            // Then unset
            ConferenceProceeding.unsetRequiredInput('conference_name');

            expect(field.hasAttribute('required')).toBe(false);
            expect(label.classList.contains('required')).toBe(false);
            expect(label.classList.contains('optional')).toBe(true);
        });

        test('should handle missing field gracefully', () => {
            expect(() => {
                ConferenceProceeding.unsetRequiredInput('non-existent-field');
            }).not.toThrow();
        });
    });

    describe('addDoiPrefix', () => {
        test('should add DOI prefix to placeholder and label', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const doiLabel = document.querySelector(
                "label[for='conference_proceedings_doi']"
            );
            const originalLabelText = doiLabel.textContent;

            ConferenceProceeding.addDoiPrefix();

            expect(doiField.getAttribute('placeholder')).toBe('10.1234/');
            expect(doiLabel.textContent).toBe(`${originalLabelText} 10.1234/`);
        });

        test('should handle missing elements gracefully', () => {
            document.getElementById('journalprefixDoi').remove();

            expect(() => {
                ConferenceProceeding.addDoiPrefix();
            }).not.toThrow();
        });
    });

    describe('showConferenceFields', () => {
        test('should show all conference fields', () => {
            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );

            // Hide them first
            conferenceFields.forEach(field => {
                field.style.display = 'none';
            });

            ConferenceProceeding.showConferenceFields();

            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('');
            });
        });
    });

    describe('hideConferenceFields', () => {
        test('should hide all conference fields', () => {
            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );

            ConferenceProceeding.hideConferenceFields();

            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('none');
            });
        });
    });

    describe('updateTitleLabel', () => {
        test('should update title label to proceeding title when isProceeding is true', () => {
            ConferenceProceeding.originalTitleLabel = 'Original Title Label';
            const titleLabel = document.querySelector(
                'div#title-element > label'
            );

            ConferenceProceeding.updateTitleLabel(true);

            expect(titleLabel.textContent).toBe('Conference Proceedings Title');
        });

        test('should restore original title label when isProceeding is false', () => {
            ConferenceProceeding.originalTitleLabel = 'Original Title Label';
            const titleLabel = document.querySelector(
                'div#title-element > label'
            );

            ConferenceProceeding.updateTitleLabel(false);

            expect(titleLabel.textContent).toBe('Original Title Label');
        });

        test('should handle missing title label gracefully', () => {
            document.querySelector('div#title-element > label').remove();

            expect(() => {
                ConferenceProceeding.updateTitleLabel(true);
            }).not.toThrow();
        });
    });

    describe('setConferenceFieldsRequired', () => {
        test('should set all conference fields as required', () => {
            ConferenceProceeding.setConferenceFieldsRequired();

            expect(
                document
                    .getElementById('conference_name')
                    .hasAttribute('required')
            ).toBe(true);
            expect(
                document
                    .getElementById('conference_start-id')
                    .hasAttribute('required')
            ).toBe(true);
            expect(
                document
                    .getElementById('conference_end-id')
                    .hasAttribute('required')
            ).toBe(true);
        });
    });

    describe('setConferenceFieldsOptional', () => {
        test('should set all conference fields as optional', () => {
            // First set as required
            ConferenceProceeding.setConferenceFieldsRequired();

            // Then set as optional
            ConferenceProceeding.setConferenceFieldsOptional();

            expect(
                document
                    .getElementById('conference_name')
                    .hasAttribute('required')
            ).toBe(false);
        });
    });

    describe('handleProceedingChange', () => {
        test('should show fields and set required when checkbox is checked', () => {
            const checkbox = document.getElementById('is_proceeding');
            ConferenceProceeding.originalTitleLabel = 'Original Title Label';

            checkbox.checked = true;
            ConferenceProceeding.handleProceedingChange();

            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );
            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('');
            });

            expect(
                document
                    .getElementById('conference_name')
                    .hasAttribute('required')
            ).toBe(true);
        });

        test('should hide fields and set optional when checkbox is unchecked', () => {
            const checkbox = document.getElementById('is_proceeding');
            ConferenceProceeding.originalTitleLabel = 'Original Title Label';

            checkbox.checked = false;
            ConferenceProceeding.handleProceedingChange();

            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );
            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('none');
            });
        });
    });

    describe('updateRequestButtonState', () => {
        test('should disable button when DOI field is empty', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );

            doiField.value = '';
            ConferenceProceeding.updateRequestButtonState();

            expect(requestButton.disabled).toBe(true);
        });

        test('should enable button when DOI field has value', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );

            doiField.value = 'test-doi-123';
            ConferenceProceeding.updateRequestButtonState();

            expect(requestButton.disabled).toBe(false);
        });

        test('should clear display DOI text when field is empty', () => {
            // Create display element
            const displayDoi = document.createElement('em');
            displayDoi.id = 'display-doi-proceeding';
            displayDoi.textContent = 'Previous DOI';
            document.body.appendChild(displayDoi);

            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            doiField.value = '';

            ConferenceProceeding.updateRequestButtonState();

            expect(displayDoi.textContent).toBe('');
        });
    });

    describe('displayDoiRequest', () => {
        test('should create and display DOI request element', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            doiField.value = 'test-123';

            ConferenceProceeding.displayDoiRequest();

            const displayDoi = document.getElementById(
                'display-doi-proceeding'
            );
            expect(displayDoi).not.toBeNull();
            expect(displayDoi.textContent).toBe(
                'DOI Request -> 10.1234/test-123'
            );
        });

        test('should update existing display DOI element', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            doiField.value = 'test-123';

            // First request
            ConferenceProceeding.displayDoiRequest();

            // Second request with different value
            doiField.value = 'test-456';
            ConferenceProceeding.displayDoiRequest();

            const displayElements = document.querySelectorAll(
                '#display-doi-proceeding'
            );
            expect(displayElements.length).toBe(1);
            expect(displayElements[0].textContent).toBe(
                'DOI Request -> 10.1234/test-456'
            );
        });

        test('should set DOI field as readonly and update buttons', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );
            const cancelButton = document.getElementById(
                'btn-cancel-request-proceedings'
            );

            doiField.value = 'test-123';

            ConferenceProceeding.displayDoiRequest();

            expect(doiField.readOnly).toBe(true);
            expect(requestButton.style.display).toBe('none');
            expect(cancelButton.style.display).toBe('');
        });
    });

    describe('cancelDoiRequest', () => {
        test('should remove display element and restore buttons', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            doiField.value = 'test-123';

            // First create a DOI request
            ConferenceProceeding.displayDoiRequest();

            // Then cancel it
            ConferenceProceeding.cancelDoiRequest();

            expect(
                document.getElementById('display-doi-proceeding')
            ).toBeNull();
            expect(
                document.getElementById('btn-request-proceedings').style.display
            ).toBe('');
            expect(
                document.getElementById('btn-cancel-request-proceedings').style
                    .display
            ).toBe('none');
            expect(doiField.readOnly).toBe(false);
        });

        test('should handle missing display element gracefully', () => {
            expect(() => {
                ConferenceProceeding.cancelDoiRequest();
            }).not.toThrow();
        });
    });

    describe('handleExistingDoi', () => {
        test('should not modify DOM when status is assigned', () => {
            const doiStatus = document.getElementById('doi_status');
            doiStatus.value = 'assigned';

            const originalHTML = document.body.innerHTML;
            ConferenceProceeding.handleExistingDoi();

            expect(document.body.innerHTML).toBe(originalHTML);
        });

        test('should not modify DOM when status is not-assigned', () => {
            const doiStatus = document.getElementById('doi_status');
            doiStatus.value = 'not-assigned';

            const originalHTML = document.body.innerHTML;
            ConferenceProceeding.handleExistingDoi();

            expect(document.body.innerHTML).toBe(originalHTML);
        });

        test('should display DOI and remove button for other statuses', () => {
            const doiStatus = document.getElementById('doi_status');
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );

            doiStatus.value = 'pending';
            doiField.value = 'existing-doi-123';

            ConferenceProceeding.handleExistingDoi();

            expect(
                document.getElementById('conference_proceedings_doi')
            ).toBeNull();
            expect(
                document.getElementById('btn-request-proceedings')
            ).toBeNull();

            const displayDoi = document.getElementById(
                'display-doi-proceeding'
            );
            expect(displayDoi).not.toBeNull();
            expect(displayDoi.textContent).toBe('10.1234/existing-doi-123');
        });
    });

    describe('initializeState', () => {
        test('should store original title label', () => {
            ConferenceProceeding.initializeState();

            expect(ConferenceProceeding.originalTitleLabel).toBe(
                'Original Title Label'
            );
        });

        test('should show conference fields when proceeding checkbox is checked', () => {
            const checkbox = document.getElementById('is_proceeding');
            checkbox.checked = true;

            ConferenceProceeding.initializeState();

            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );
            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('');
            });
        });

        test('should hide conference fields when proceeding checkbox is unchecked', () => {
            const checkbox = document.getElementById('is_proceeding');
            checkbox.checked = false;

            ConferenceProceeding.initializeState();

            const conferenceFields = document.querySelectorAll(
                'div[id^="conference_"]'
            );
            conferenceFields.forEach(field => {
                expect(field.style.display).toBe('none');
            });
        });
    });

    describe('initializeButtons', () => {
        test('should disable request button when DOI field is empty', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );

            doiField.value = '';

            ConferenceProceeding.initializeButtons();

            expect(requestButton.disabled).toBe(true);
        });

        test('should hide cancel button when DOI field is empty', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const cancelButton = document.getElementById(
                'btn-cancel-request-proceedings'
            );

            doiField.value = '';

            ConferenceProceeding.initializeButtons();

            expect(cancelButton.style.display).toBe('none');
        });
    });

    describe('attachEventListeners', () => {
        test('should attach click listener to proceeding checkbox', () => {
            const checkbox = document.getElementById('is_proceeding');
            const spy = jest.spyOn(
                ConferenceProceeding,
                'handleProceedingChange'
            );

            ConferenceProceeding.attachEventListeners();

            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('click'));

            expect(spy).toHaveBeenCalled();
            spy.mockRestore();
        });

        test('should attach keyup listener to DOI field', () => {
            const doiField = document.getElementById(
                'conference_proceedings_doi'
            );
            const spy = jest.spyOn(
                ConferenceProceeding,
                'updateRequestButtonState'
            );

            ConferenceProceeding.attachEventListeners();

            doiField.dispatchEvent(new Event('keyup'));

            expect(spy).toHaveBeenCalled();
            spy.mockRestore();
        });

        test('should attach click listener to request button', () => {
            const requestButton = document.getElementById(
                'btn-request-proceedings'
            );
            const spy = jest.spyOn(ConferenceProceeding, 'displayDoiRequest');

            ConferenceProceeding.attachEventListeners();

            requestButton.dispatchEvent(new Event('click'));

            expect(spy).toHaveBeenCalled();
            spy.mockRestore();
        });

        test('should attach click listener to cancel button', () => {
            const cancelButton = document.getElementById(
                'btn-cancel-request-proceedings'
            );
            const spy = jest.spyOn(ConferenceProceeding, 'cancelDoiRequest');

            ConferenceProceeding.attachEventListeners();

            cancelButton.dispatchEvent(new Event('click'));

            expect(spy).toHaveBeenCalled();
            spy.mockRestore();
        });
    });

    describe('init', () => {
        test('should initialize without errors', () => {
            expect(() => {
                ConferenceProceeding.init();
            }).not.toThrow();
        });

        test('should call all initialization methods', () => {
            const spyInitState = jest.spyOn(
                ConferenceProceeding,
                'initializeState'
            );
            const spyAddDoi = jest.spyOn(ConferenceProceeding, 'addDoiPrefix');
            const spyInitButtons = jest.spyOn(
                ConferenceProceeding,
                'initializeButtons'
            );
            const spyHandleExisting = jest.spyOn(
                ConferenceProceeding,
                'handleExistingDoi'
            );
            const spyAttachEvents = jest.spyOn(
                ConferenceProceeding,
                'attachEventListeners'
            );

            ConferenceProceeding.init();

            expect(spyInitState).toHaveBeenCalled();
            expect(spyAddDoi).toHaveBeenCalled();
            expect(spyInitButtons).toHaveBeenCalled();
            expect(spyHandleExisting).toHaveBeenCalled();
            expect(spyAttachEvents).toHaveBeenCalled();

            spyInitState.mockRestore();
            spyAddDoi.mockRestore();
            spyInitButtons.mockRestore();
            spyHandleExisting.mockRestore();
            spyAttachEvents.mockRestore();
        });
    });

    describe('Edge Cases', () => {
        test('should handle completely empty DOM', () => {
            document.body.innerHTML = '';

            expect(() => {
                ConferenceProceeding.init();
            }).not.toThrow();
        });

        test('should handle missing translate text input', () => {
            document.getElementById('translate_text').remove();

            expect(() => {
                ConferenceProceeding.updateTitleLabel(true);
            }).not.toThrow();
        });
    });
});
