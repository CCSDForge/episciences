const SubmitIdentifier = require('../../public/js/submit/identifier');

const parse = (input, options) => SubmitIdentifier.parse(input, options);

describe('SubmitIdentifier.parse', () => {
    describe('direct identifiers', () => {
        test.each([
            ['hal-05191818v1', 'hal-05191818', 1],
            ['hal-05191818v21', 'hal-05191818', 21],
            ['hal-05191818', 'hal-05191818', null],
            ['2310.02192v1', '2310.02192', 1],
            ['2310.02192', '2310.02192', null],
            ['hep-th/9704188v2', 'hep-th/9704188', 2],
            ['arXiv:2310.02192v2', '2310.02192', 2],
            ['  hal-05191818v3  ', 'hal-05191818', 3],
            ['10.1101/2023.01.01.522345v2', '10.1101/2023.01.01.522345', 2],
        ])('%s', (input, identifier, version) => {
            expect(parse(input)).toEqual({ identifier, version });
        });

        test('does not match a version in the middle of the identifier', () => {
            expect(parse('hal-v1-05191818')).toEqual({
                identifier: 'hal-v1-05191818',
                version: null,
            });
        });

        test('does not treat v0 as a version', () => {
            expect(parse('hal-05191818v0')).toEqual({
                identifier: 'hal-05191818v0',
                version: null,
            });
        });

        test('keeps a trailing vN when the version is not required (BAOBAB-style slug)', () => {
            expect(parse('s9g8r-v1011', { isRequiredVersion: false })).toEqual({
                identifier: 's9g8r-v1011',
                version: null,
            });
        });
    });

    describe('HAL URLs', () => {
        test.each([
            ['https://hal.science/hal-05191818v1', 'hal-05191818', 1],
            ['https://hal.science/hal-05191818', 'hal-05191818', 1],
            ['https://hal.science/hal-01234567v2/document', 'hal-01234567', 2],
            ['https://hal.science/hal-01234567v2/document/', 'hal-01234567', 2],
            [
                'https://hal.science/hal-01234567/file/paper.pdf',
                'hal-01234567',
                1,
            ],
            ['https://inria.hal.science/hal-01234567v3', 'hal-01234567', 3],
            [
                'https://theses.hal.science/tel-01234567v1/document',
                'tel-01234567',
                1,
            ],
            ['https://hal.science/hal-01234567v2?lang=en', 'hal-01234567', 2],
        ])('%s', (input, identifier, version) => {
            expect(parse(input)).toEqual({ identifier, version });
        });
    });

    describe('arXiv URLs', () => {
        test.each([
            ['https://arxiv.org/abs/2310.02192v1', '2310.02192', 1],
            ['https://arxiv.org/abs/2310.02192', '2310.02192', 1],
            ['https://arxiv.org/pdf/2310.02192v2', '2310.02192', 2],
            ['https://arxiv.org/pdf/2310.02192v2.pdf', '2310.02192', 2],
            ['https://arxiv.org/html/2310.02192v2', '2310.02192', 2],
            ['https://www.arxiv.org/abs/2310.02192v2', '2310.02192', 2],
            ['https://export.arxiv.org/abs/2310.02192v2', '2310.02192', 2],
            ['https://arxiv.org/abs/2310.02192v2?context=cs', '2310.02192', 2],
            ['https://arxiv.org/abs/hep-th/9704188v2', 'hep-th/9704188', 2],
            ['https://arxiv.org/abs/math.GT/0309136', 'math.GT/0309136', 1],
        ])('%s', (input, identifier, version) => {
            expect(parse(input)).toEqual({ identifier, version });
        });
    });

    describe('version of a URL', () => {
        test('is a number', () => {
            expect(
                typeof parse('https://hal.science/hal-01234567v2').version
            ).toBe('number');
        });

        test('defaults to 1 when the URL gives none', () => {
            expect(parse('https://hal.science/hal-01234567')).toEqual({
                identifier: 'hal-01234567',
                version: 1,
            });
        });

        test('keeps a decimal version', () => {
            expect(
                parse(
                    'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.70122/FK2/ABC&version=1.1',
                    { isDataverseRepo: true }
                ).version
            ).toBe(1.1);
        });

        test('defaults to 1 when the version parameter is not a number', () => {
            expect(
                parse(
                    'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.70122/FK2/ABC&version=DRAFT',
                    { isDataverseRepo: true }
                ).version
            ).toBe(1);
        });

        test('is not set when the repository does not require one', () => {
            expect(
                parse('https://zenodo.org/records/123456', {
                    isRequiredVersion: false,
                }).version
            ).toBeNull();
        });
    });

    describe('bioRxiv / medRxiv URLs', () => {
        test.each([
            [
                'https://www.biorxiv.org/content/10.64898/2025.12.15.694470v2',
                '10.64898/2025.12.15.694470',
                2,
            ],
            [
                'https://www.biorxiv.org/content/10.1101/2023.01.01.522345v2',
                '10.1101/2023.01.01.522345',
                2,
            ],
            [
                'https://www.biorxiv.org/content/10.1101/2023.01.01.522345v2.full',
                '10.1101/2023.01.01.522345',
                2,
            ],
            [
                'https://www.biorxiv.org/content/10.1101/2023.01.01.522345v2.full.pdf',
                '10.1101/2023.01.01.522345',
                2,
            ],
            [
                'https://www.medrxiv.org/content/10.1101/2021.03.04.21252931v1.abstract',
                '10.1101/2021.03.04.21252931',
                1,
            ],
            [
                'https://www.biorxiv.org/content/10.1101/339747',
                '10.1101/339747',
                1,
            ],
        ])('%s', (input, identifier, version) => {
            expect(parse(input)).toEqual({ identifier, version });
        });
    });

    describe('Zenodo URLs', () => {
        test.each([
            ['https://zenodo.org/records/123456', '123456'],
            ['https://zenodo.org/record/123456', '123456'],
            ['https://zenodo.org/records/123456/files/paper.pdf', '123456'],
            ['https://zenodo.org/doi/10.5281/zenodo.123456', '123456'],
            ['https://doi.org/10.5281/zenodo.123456', '10.5281/zenodo.123456'],
        ])('%s', (input, identifier) => {
            expect(parse(input, { isRequiredVersion: false })).toEqual({
                identifier,
                version: null,
            });
        });
    });

    describe('IACR Cryptology ePrint URLs', () => {
        test.each([
            ['https://eprint.iacr.org/2026/1234', '2026/1234'],
            ['https://eprint.iacr.org/2026/1234.pdf', '2026/1234'],
            ['https://ia.cr/2026/1234', '2026/1234'],
        ])('%s', (input, identifier) => {
            expect(parse(input, { isRequiredVersion: false })).toEqual({
                identifier,
                version: null,
            });
        });
    });

    describe('handle URLs (DSpace, ARCHE)', () => {
        test('keeps the handle', () => {
            expect(
                parse('https://hdl.handle.net/21.11115/0000-000B-C715-D', {
                    isRequiredVersion: false,
                })
            ).toEqual({
                identifier: '21.11115/0000-000B-C715-D',
                version: null,
            });
        });
    });

    describe('BAOBAB URLs', () => {
        test('does not truncate a slug that coincidentally ends in vN', () => {
            // The leftover "records/" prefix is stripped server-side by
            // Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers().
            expect(
                parse('https://baobab.wacren.net/records/s9g8r-v1011', {
                    isRequiredVersion: false,
                })
            ).toEqual({ identifier: 'records/s9g8r-v1011', version: null });
        });
    });

    describe('Dataverse URLs', () => {
        test.each([
            [
                'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=doi:10.18419/DARUS-2277&version=2.0',
                'doi:10.18419/DARUS-2277',
                2,
            ],
            [
                'https://entrepot.recherche.data.gouv.fr/dataset.xhtml?persistentId=doi:10.15454/XRXSHL&version=1.1',
                'doi:10.15454/XRXSHL',
                1.1,
            ],
            [
                'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=doi:10.18419/DARUS-2277',
                'doi:10.18419/DARUS-2277',
                1,
            ],
            [
                'https://darus.uni-stuttgart.de/dataset.xhtml?someOtherParam=value',
                'dataset.xhtml',
                1,
            ],
        ])('%s', (input, identifier, version) => {
            expect(parse(input, { isDataverseRepo: true })).toEqual({
                identifier,
                version,
            });
        });
    });

    describe('other URLs', () => {
        test('uses the path of a URL with query parameters', () => {
            expect(
                parse('https://example.com/resource?id=123&format=pdf')
            ).toEqual({ identifier: 'resource', version: 1 });
        });

        test('uses the path of a malformed host', () => {
            expect(parse('https://.com/path')).toEqual({
                identifier: 'path',
                version: 1,
            });
        });

        test('treats a non-URL input as a direct identifier', () => {
            expect(parse('not-a-valid-url')).toEqual({
                identifier: 'not-a-valid-url',
                version: null,
            });
        });
    });
});
