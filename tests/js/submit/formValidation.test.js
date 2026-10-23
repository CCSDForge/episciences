// formValidation.test.js
const {isFormValid, setMockGlobals} = require('./formValidation');

// Factory to create mock
const createMock = ({val = '', isVisible = true, isChecked = false, isRequired = false, length = 1} = {}) => {
    return {
        val: jest.fn(() => val),
        is: jest.fn((selector) => {
            if (selector === ':visible') return isVisible;
            if (selector === ':checked') return isChecked;
            return false;
        }),
        find: jest.fn((selector) => {
            if (selector === 'label') {
                return {hasClass: jest.fn((c) => c === 'required' && isRequired)};
            }
            return {hasClass: () => false};
        }),
        length: length
    };
};

describe('Test the "isFormValid" function to check whether all required fields have been filled in, in order to determine whether or not to enable the submit button', () => {

    // Reset and load the mock before each test.
    beforeEach(() => {
        // We load "valid" default values to avoid the undefined error
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),
            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: '666'}),
            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),
            $isRequiredDescriptor: createMock({length: 0}), // not required
            $fileDescriptor: createMock({val: ''})
        });
    });

    // You must pass ALL variables, as "setMockGlobals" overrides everything
    // test 1: No error
    test('Returns TRUE if everything is valid', () => {
        expect(isFormValid()).toBe(true);
    });

    // test 2: Section error (required section and section value '0')
    test('Returns FALSE if the section is set to "0"', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '0'}), // Changement
            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),
            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),
            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({val: ''})
        });

        expect(isFormValid()).toBe(false);
    });

    // Test 3: Editors error (requirement to choose an editor)
    test('Returns FALSE if the editor is set to "0" and required', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),
            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: '0'}), // ERROR
            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),
            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({})
        });

        expect(isFormValid()).toBe(false);
    });

    // Test 4: Editors error (requirement to choose an editor)
    test('Returns FALSE if the editor is null and required', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),
            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: null}), // ERROR
            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),
            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({})
        });

        expect(isFormValid()).toBe(false);
    });

    // Test 5: Error: Disclaimer 1 (Unchecked)
    test('Returns FALSE if the first disclaimer is not checked', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: false}), // ERREUR
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),

            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({})
        });

        expect(isFormValid()).toBe(false);
    });

    //Test 6: Error: Disclaimer 2 (Unchecked)

    test('Returns FALSE if the second disclaimer is not checked', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: false}), // ERROR

            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({})
        });

        expect(isFormValid()).toBe(false);
    });

    // Test 7: ERROR File Descriptor (Required + Empty)
    test('Returns FALSE if the descriptor file is required but empty', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),

            $isRequiredDescriptor: createMock({length: 1, val: 'true'}), // required
            $fileDescriptor: createMock({val: ''}) // empty -> ERROR
        });

        expect(isFormValid()).toBe(false);
    });

    // Test 8: Special Case (descriptor file not required, so empty is OK)
    test('Returns TRUE if the file is required but the value is "false" (not required)', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),

            $isRequiredDescriptor: createMock({length: 1, val: 'false'}), // Non requis
            $fileDescriptor: createMock({val: ''}) // Vide OK
        });

        expect(isFormValid()).toBe(true);
    });

    // Test 9: SUCCESS File Descriptor (Required + File selected)
    test('Returns TRUE if the descriptor file is required and a file is selected', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: true, isRequired: true}),
            $sections: createMock({val: '1'}),

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),

            $isRequiredDescriptor: createMock({length: 1, val: 'true'}), // required
            $fileDescriptor: createMock({val: 'descriptor.zip'}) // file selected -> OK
        });

        expect(isFormValid()).toBe(true);
    });

    // Test 10: Invisible section (Ignored)
    test('Returns TRUE if the section is invisible even when the value is "0"', () => {
        setMockGlobals({
            $sectionsElement: createMock({isVisible: false, isRequired: true}), // Invisible
            $sections: createMock({val: '0'}), // A value of "0" normally indicates an error, but is ignored here

            $suggestEditorsElement: createMock({isVisible: true, isRequired: true}),
            $suggest_editors: createMock({val: 'id'}),

            $firstDisclaimersDisclaimer: createMock({isChecked: true}),
            $secondDisclaimersDisclaimer: createMock({isChecked: true}),

            $isRequiredDescriptor: createMock({length: 0}),
            $fileDescriptor: createMock({})
        });

        expect(isFormValid()).toBe(true);
    });


});