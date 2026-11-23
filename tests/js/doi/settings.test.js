/**
 * Tests for the DOI preview of the DOI settings page (doi/settings)
 */

const {
    doiKeepOnlyIntegers,
    doiPreview,
    initDoiSettings,
} = require('../../../public/js/doi/settings');

const examples = {
    '%R%': 'jcode',
    '%V%': 'Volume 12',
    '%V_INT%': '12',
    '%V_BIB_REF%': 'vol12',
    '%VP%': '3',
    '%S%': 'Section 2',
    '%S_INT%': '2',
    '%P%': '12345',
    '%Y%': '2026',
    '%y%': '26',
    '%M%': '11',
    '%AUTOINCREMENT%': '7',
};

describe('doiKeepOnlyIntegers', () => {
    test('keeps the numbers separated with a dot by default', () => {
        expect(doiKeepOnlyIntegers('Volume 1 Issue 2')).toBe('1.2');
    });

    test('uses the given separator', () => {
        expect(doiKeepOnlyIntegers('vol 3 num 1', '-')).toBe('3-1');
    });

    test('a special character of regular expressions is used literally', () => {
        expect(doiKeepOnlyIntegers('vol 3 num 1', '$')).toBe('3$1');
    });
});

describe('doiPreview', () => {
    test('default format', () => {
        expect(doiPreview('10.12345', '%R%-%P%', examples)).toBe(
            '10.12345/jcode-12345'
        );
    });

    test('lower case, without spaces', () => {
        expect(doiPreview('10.12345', '%R%.%V%', examples)).toBe(
            '10.12345/jcode.volume12'
        );
    });

    test('numbers of the volume and section with a separator', () => {
        expect(
            doiPreview('10.12345', '%R%.%V_INT[-]%.%S_INT[_]%.%P%', examples)
        ).toBe('10.12345/jcode.12.2.12345');
    });

    test('the plain integer tags are not confused with the name tags', () => {
        expect(doiPreview('10.12345', '%V_INT%-%VP%-%V%', examples)).toBe(
            '10.12345/12-3-volume12'
        );
    });

    test('dates, auto-increment and percent sign', () => {
        expect(
            doiPreview(
                '10.12345',
                '%R%.%Y%.%y%.%M%.%AUTOINCREMENT%%%',
                examples
            )
        ).toBe('10.12345/jcode.2026.26.11.7%');
    });

    test('doubled separators are reduced', () => {
        expect(doiPreview('10.12345', '%R%..%P%', examples)).toBe(
            '10.12345/jcode.12345'
        );
    });

    test('nothing without prefix', () => {
        expect(doiPreview('', '%R%-%P%', examples)).toBe('');
    });
});

describe('initDoiSettings', () => {
    function render(prefix) {
        document.body.innerHTML = `
            <input id="doiPrefix" value="${prefix}" placeholder="10.12345">
            <input class="doi-format-input" value="%R%-%P%" data-doi-examples='${JSON.stringify(examples)}'>
            <code class="doi-format-preview-value"></code>`;
    }

    test('shows the DOI of the saved format', () => {
        render('10.46298');
        initDoiSettings(document);

        expect(
            document.querySelector('.doi-format-preview-value').textContent
        ).toBe('10.46298/jcode-12345');
    });

    test('follows the typed format', () => {
        render('10.46298');
        initDoiSettings(document);
        const format = document.querySelector('.doi-format-input');
        format.value = '%R%.%Y%';
        format.dispatchEvent(new Event('input'));

        expect(
            document.querySelector('.doi-format-preview-value').textContent
        ).toBe('10.46298/jcode.2026');
    });

    test('uses the example prefix while the prefix is empty', () => {
        render('');
        initDoiSettings(document);

        expect(
            document.querySelector('.doi-format-preview-value').textContent
        ).toBe('10.12345/jcode-12345');
    });
});
