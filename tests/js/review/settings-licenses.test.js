/**
 * Tests for the journal settings licenses synchronisation
 */

const {
    selectDefaultInAllowedLicenses,
    initLicensesSettings,
} = require('../../../public/js/review/settings-licenses');

function render(defaultCode, allowedCodes) {
    const codes = ['MIT', 'CC-BY-4.0', 'Apache-2.0'];
    document.body.innerHTML = `
        <select id="licenseDefault">
            <option value="">---</option>
            ${codes.map(c => `<option value="${c}"${c === defaultCode ? ' selected' : ''}>${c}</option>`).join('')}
        </select>
        <select id="licensesAllowed" multiple>
            ${codes.map(c => `<option value="${c}"${allowedCodes.includes(c) ? ' selected' : ''}>${c}</option>`).join('')}
        </select>`;

    return {
        defaultSelect: document.getElementById('licenseDefault'),
        allowedSelect: document.getElementById('licensesAllowed'),
    };
}

const selectedValues = select =>
    Array.from(select.selectedOptions).map(option => option.value);

describe('selectDefaultInAllowedLicenses', () => {
    test('adds the default license to a non-empty allowed list', () => {
        const { defaultSelect, allowedSelect } = render('Apache-2.0', ['MIT']);

        expect(
            selectDefaultInAllowedLicenses(defaultSelect, allowedSelect)
        ).toBe(true);
        expect(selectedValues(allowedSelect)).toEqual(['MIT', 'Apache-2.0']);
    });

    test('leaves an empty allowed list untouched (no restriction)', () => {
        const { defaultSelect, allowedSelect } = render('MIT', []);

        expect(
            selectDefaultInAllowedLicenses(defaultSelect, allowedSelect)
        ).toBe(false);
        expect(selectedValues(allowedSelect)).toEqual([]);
    });

    test('does nothing without default license', () => {
        const { defaultSelect, allowedSelect } = render('', ['MIT']);

        expect(
            selectDefaultInAllowedLicenses(defaultSelect, allowedSelect)
        ).toBe(false);
        expect(selectedValues(allowedSelect)).toEqual(['MIT']);
    });

    test('does nothing when the default license is already allowed', () => {
        const { defaultSelect, allowedSelect } = render('MIT', [
            'MIT',
            'CC-BY-4.0',
        ]);

        expect(
            selectDefaultInAllowedLicenses(defaultSelect, allowedSelect)
        ).toBe(false);
        expect(selectedValues(allowedSelect)).toEqual(['MIT', 'CC-BY-4.0']);
    });
});

describe('initLicensesSettings', () => {
    test('selects the default license on load', () => {
        const { allowedSelect } = render('CC-BY-4.0', ['MIT']);

        initLicensesSettings(document);

        expect(selectedValues(allowedSelect)).toEqual(['MIT', 'CC-BY-4.0']);
    });

    test('selects the new default license on change and keeps the previous one', () => {
        const { defaultSelect, allowedSelect } = render('MIT', ['MIT']);
        initLicensesSettings(document);

        defaultSelect.value = 'Apache-2.0';
        defaultSelect.dispatchEvent(new Event('change'));

        expect(selectedValues(allowedSelect)).toEqual(['MIT', 'Apache-2.0']);
    });

    test('re-selects the default license when it is deselected', () => {
        const { allowedSelect } = render('MIT', ['MIT', 'CC-BY-4.0']);
        initLicensesSettings(document);

        allowedSelect.options[0].selected = false;
        allowedSelect.dispatchEvent(new Event('change'));

        expect(selectedValues(allowedSelect)).toEqual(['MIT', 'CC-BY-4.0']);
    });

    test('allows clearing the whole list (back to no restriction)', () => {
        const { allowedSelect } = render('MIT', ['MIT']);
        initLicensesSettings(document);

        allowedSelect.options[0].selected = false;
        allowedSelect.dispatchEvent(new Event('change'));

        expect(selectedValues(allowedSelect)).toEqual([]);
    });

    test('does nothing when the fields are missing', () => {
        document.body.innerHTML = '';

        expect(() => initLicensesSettings(document)).not.toThrow();
    });
});
