const VolumeSpecial = require('../../../public/js/volume/special.js');

describe('Volume Special Issue - Access Code Toggle', () => {
    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div id="special_issue-element" class="form-group row">
                <label class="col-md-3" for="special_issue">Special Issue</label>
                <div class="col-md-9">
                    <select id="special_issue" name="special_issue">
                        <option value="0">Non</option>
                        <option value="1">Oui</option>
                    </select>
                </div>
            </div>
        `;

        // Mock globals
        window.access_code = 'TEST_CODE_123';
        window.translate = jest.fn(text => text);
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.access_code;
        delete window.translate;
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should initialize without errors when DOM is ready', () => {
            expect(() => VolumeSpecial.init()).not.toThrow();
        });

        test('should attach change event listener to dropdown', () => {
            const dropdown = document.getElementById('special_issue');
            const spy = jest.spyOn(VolumeSpecial, 'toggleAccessCode');

            VolumeSpecial.init();
            dropdown.value = '1';
            dropdown.dispatchEvent(new Event('change'));

            expect(spy).toHaveBeenCalled();
            spy.mockRestore();
        });
    });

    describe('createAccessCodeElement', () => {
        test('should create valid HTML structure with access code', () => {
            const html = VolumeSpecial.createAccessCodeElement(
                'ABC123',
                text => text
            );

            expect(html).toContain('id="access_code-element"');
            expect(html).toContain('form-group row');
            expect(html).toContain('ABC123');
            expect(html).toContain("Code d'accès");
            expect(html).toContain(
                "<input id='access_code' name='access_code' type='hidden' value='ABC123'>"
            );
        });

        test('should use translate function for label text', () => {
            const mockTranslate = jest.fn(text => `TRANSLATED: ${text}`);
            const html = VolumeSpecial.createAccessCodeElement(
                'TEST',
                mockTranslate
            );

            expect(mockTranslate).toHaveBeenCalledWith("Code d'accès");
            expect(html).toContain("TRANSLATED: Code d'accès");
        });

        test('should handle missing access code gracefully', () => {
            const html = VolumeSpecial.createAccessCodeElement(
                undefined,
                text => text
            );

            expect(html).toContain('id="access_code-element"');
            expect(html).toContain("value=''");
        });

        test('should handle missing translate function gracefully', () => {
            const html = VolumeSpecial.createAccessCodeElement('TEST');

            expect(html).toContain('id="access_code-element"');
            expect(html).toContain("Code d'accès"); // Default translate returns text as-is
        });
    });

    describe('showAccessCode', () => {
        test('should insert access code element after special_issue-element', () => {
            const parentElement = document.getElementById(
                'special_issue-element'
            );
            expect(document.getElementById('access_code-element')).toBeNull();

            VolumeSpecial.showAccessCode();

            const accessCodeElement = document.getElementById(
                'access_code-element'
            );
            expect(accessCodeElement).not.toBeNull();
            expect(accessCodeElement.previousElementSibling).toBe(
                parentElement
            );
            expect(
                accessCodeElement.querySelector('input[type="hidden"]').value
            ).toBe('TEST_CODE_123');
        });

        test('should not create duplicate elements if called multiple times', () => {
            VolumeSpecial.showAccessCode();
            VolumeSpecial.showAccessCode();
            VolumeSpecial.showAccessCode();

            const elements = document.querySelectorAll('#access_code-element');
            expect(elements.length).toBe(1);
        });

        test('should do nothing if special_issue-element not found', () => {
            document.body.innerHTML = ''; // Remove all elements

            expect(() => VolumeSpecial.showAccessCode()).not.toThrow();
            expect(document.getElementById('access_code-element')).toBeNull();
        });
    });

    describe('hideAccessCode', () => {
        test('should remove access code element if it exists', () => {
            VolumeSpecial.showAccessCode();
            expect(
                document.getElementById('access_code-element')
            ).not.toBeNull();

            VolumeSpecial.hideAccessCode();

            expect(document.getElementById('access_code-element')).toBeNull();
        });

        test('should do nothing if access code element does not exist', () => {
            expect(document.getElementById('access_code-element')).toBeNull();

            expect(() => VolumeSpecial.hideAccessCode()).not.toThrow();
            expect(document.getElementById('access_code-element')).toBeNull();
        });
    });

    describe('toggleAccessCode', () => {
        test('should show access code when special_issue value is "1"', () => {
            const dropdown = document.getElementById('special_issue');
            dropdown.value = '1';

            VolumeSpecial.toggleAccessCode();

            expect(
                document.getElementById('access_code-element')
            ).not.toBeNull();
        });

        test('should hide access code when special_issue value is not "1"', () => {
            const dropdown = document.getElementById('special_issue');

            // First show it
            dropdown.value = '1';
            VolumeSpecial.toggleAccessCode();
            expect(
                document.getElementById('access_code-element')
            ).not.toBeNull();

            // Then hide it
            dropdown.value = '0';
            VolumeSpecial.toggleAccessCode();
            expect(document.getElementById('access_code-element')).toBeNull();
        });
    });

    describe('Edge Cases', () => {
        test('should handle missing special_issue element gracefully', () => {
            document.body.innerHTML = ''; // Empty DOM

            expect(() => VolumeSpecial.toggleAccessCode()).not.toThrow();
            expect(() => VolumeSpecial.init()).not.toThrow();
        });
    });
});
