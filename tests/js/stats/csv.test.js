/**
 * CSV exports of the statistics page
 */

const path = require('path');

const {
    statCsvSeparator,
    statCsvCell,
    buildCsv,
    statCsvFileName,
    downloadCsv,
    createCsvButton,
} = require(path.join(__dirname, '../../../public/js/stats/csv.js'));

describe('statCsvSeparator', function () {
    it('uses ";" in French and "," otherwise', function () {
        expect(statCsvSeparator('fr')).toBe(';');
        expect(statCsvSeparator('fr_FR')).toBe(';');
        expect(statCsvSeparator('en')).toBe(',');
    });
});

describe('statCsvCell', function () {
    it('quotes the values with a separator, a quote or a new line', function () {
        expect(statCsvCell('a;b', ';')).toBe('"a;b"');
        expect(statCsvCell('say "hello"', ';')).toBe('"say ""hello"""');
        expect(statCsvCell('two\nlines', ';')).toBe('"two\nlines"');
        expect(statCsvCell('plain', ';')).toBe('plain');
    });

    it('neutralises the values starting like a formula', function () {
        expect(statCsvCell('=HYPERLINK("x")', ',')).toBe(
            '"\'=HYPERLINK(""x"")"'
        );
        expect(statCsvCell('+1+2', ',')).toBe("'+1+2");
        expect(statCsvCell('@SUM(A1)', ',')).toBe("'@SUM(A1)");
        expect(statCsvCell('-cmd', ',')).toBe("'-cmd");
    });

    it('keeps the numbers, even negative ones', function () {
        expect(statCsvCell(-12, ',')).toBe('-12');
        expect(statCsvCell('-3.5', ',')).toBe('-3.5');
        expect(statCsvCell(54.32, ',')).toBe('54.32');
    });

    it('writes the empty values as empty cells', function () {
        expect(statCsvCell(null, ',')).toBe('');
        expect(statCsvCell(undefined, ',')).toBe('');
    });
});

describe('buildCsv', function () {
    it('joins the cells and the rows', function () {
        expect(
            buildCsv(
                [
                    ['Year', 'Accepted'],
                    ['2025', 11],
                ],
                ';'
            )
        ).toBe('Year;Accepted\r\n2025;11');
    });
});

describe('statCsvFileName', function () {
    it('builds a file name without accent nor forbidden character', function () {
        expect(
            statCsvFileName(['statistiques-dev-2026', 'Délai / publication'])
        ).toBe('statistiques-dev-2026-delai-publication.csv');
        expect(statCsvFileName(['statistiques', null, ''])).toBe(
            'statistiques.csv'
        );
    });
});

describe('downloadCsv', function () {
    it('downloads a UTF-8 file with a BOM', async function () {
        let blob = null;
        global.URL.createObjectURL = jest.fn(b => {
            blob = b;
            return 'blob:test';
        });
        global.URL.revokeObjectURL = jest.fn();
        const click = jest
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {});

        downloadCsv('figures.csv', [['a', 'b']]);

        expect(click).toHaveBeenCalledTimes(1);
        expect(blob.type).toBe('text/csv;charset=utf-8');
        const bytes = await new Promise(resolve => {
            const reader = new FileReader();
            reader.onload = () => resolve(new Uint8Array(reader.result));
            reader.readAsArrayBuffer(blob);
        });
        // UTF-8 byte order mark, then the data
        expect(Array.from(bytes.slice(0, 3))).toEqual([0xef, 0xbb, 0xbf]);
        expect(String.fromCharCode(...bytes.slice(3))).toBe('a,b');
        expect(document.querySelector('a[download]')).toBeNull();

        click.mockRestore();
    });
});

describe('createCsvButton', function () {
    it('downloads the data of the moment of the click', function () {
        global.URL.createObjectURL = jest.fn(() => 'blob:test');
        global.URL.revokeObjectURL = jest.fn();
        const click = jest
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {});
        const getExport = jest.fn(() => ({
            fileName: 'x.csv',
            rows: [['a']],
        }));

        const button = createCsvButton('Download (CSV)', getExport);
        expect(button.textContent.trim()).toBe('Download (CSV)');
        expect(getExport).not.toHaveBeenCalled();

        button.click();
        expect(getExport).toHaveBeenCalledTimes(1);
        expect(click).toHaveBeenCalledTimes(1);

        click.mockRestore();
    });
});
