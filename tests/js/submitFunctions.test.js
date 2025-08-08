const {
    removeVersionFromIdentifier,
    processUrlIdentifier,
    setPlaceholder,
    createMockElement,
    setMockGlobals,
} = require('./submitFunctions');

describe('Submit Functions', () => {
    describe('removeVersionFromIdentifier', () => {
        test('should extract version from identifier ending with v+number', () => {
            const mockVersionField = createMockElement('');
            const result = removeVersionFromIdentifier('hal-05191818v1', {
                mockVersionField,
            });

            expect(result).toBe('hal-05191818');
            expect(mockVersionField.value).toBe('1');
        });

        test('should extract version from arxiv identifier with version', () => {
            const mockVersionField = createMockElement('');
            const result = removeVersionFromIdentifier('2310.02192v1', {
                mockVersionField,
            });

            expect(result).toBe('2310.02192');
            expect(mockVersionField.value).toBe('1');
        });

        test('should handle multi-digit versions', () => {
            const mockVersionField = createMockElement('');
            const result = removeVersionFromIdentifier('hal-05191818v21', {
                mockVersionField,
            });

            expect(result).toBe('hal-05191818');
            expect(mockVersionField.value).toBe('21');
        });

        test('should return original identifier when no version found', () => {
            const mockVersionField = createMockElement('previous_value');
            const result = removeVersionFromIdentifier('hal-05191818', {
                mockVersionField,
            });

            expect(result).toBe('hal-05191818');
            expect(mockVersionField.value).toBe(''); // Should clear previous value
        });

        test('should return original identifier when no version field available', () => {
            const result = removeVersionFromIdentifier('hal-05191818v1');
            expect(result).toBe('hal-05191818v1'); // No version field to set, returns as-is
        });

        test('should handle identifiers with slash prefix', () => {
            const mockVersionField = createMockElement('');
            const result = removeVersionFromIdentifier('/hal-05191818v21', {
                mockVersionField,
            });

            expect(result).toBe('/hal-05191818');
            expect(mockVersionField.value).toBe('21');
        });

        test('should not match version in middle of identifier', () => {
            const mockVersionField = createMockElement('');
            const result = removeVersionFromIdentifier('hal-v1-05191818', {
                mockVersionField,
            });

            expect(result).toBe('hal-v1-05191818');
            expect(mockVersionField.value).toBe('');
        });
    });

    describe('processUrlIdentifier', () => {
        describe('ArXiv URLs', () => {
            test('should process arxiv URL with version', () => {
                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier(
                    'https://arxiv.org/abs/2310.02192v1',
                    { mockVersionField }
                );

                // ArXiv URLs are processed as non-dataverse, pathname is "abs/2310.02192v1"
                // then removeVersionFromIdentifier strips 'v1' to get "abs/2310.02192"
                expect(result).toBe('abs/2310.02192');
                expect(mockVersionField.value).toBe('1');
            });

            test('should process arxiv URL without version', () => {
                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier(
                    'https://arxiv.org/abs/2310.02192',
                    { mockVersionField }
                );

                // For non-dataverse URLs, it takes pathname and processes it
                expect(result).toBe('abs/2310.02192'); // Full pathname without version processing
                expect(mockVersionField.value).toBe('');
            });
        });

        describe('HAL URLs', () => {
            test('should process hal URL with version', () => {
                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier(
                    'https://hal.science/hal-05191818v1',
                    { mockVersionField }
                );

                expect(result).toBe('hal-05191818');
                expect(mockVersionField.value).toBe('1');
            });

            test('should process hal URL without version', () => {
                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier(
                    'https://hal.science/hal-05191818',
                    { mockVersionField }
                );

                expect(result).toBe('hal-05191818');
                expect(mockVersionField.value).toBe('');
            });
        });

        describe('Dataverse URLs', () => {
            test('should process Darus dataverse URL with persistentId and version', () => {
                const mockVersionField = createMockElement('');
                const url =
                    'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=doi:10.18419/DARUS-2277&version=2.0';
                const result = processUrlIdentifier(url, {
                    mockVersionField,
                    isDataverseRepo: true,
                });

                expect(result).toBe('doi:10.18419/DARUS-2277');
                expect(mockVersionField.value).toBe('2.0');
            });

            test('should process French research data URL with persistentId and version', () => {
                const mockVersionField = createMockElement('');
                const url =
                    'https://entrepot.recherche.data.gouv.fr/dataset.xhtml?persistentId=doi:10.15454/XRXSHL&version=1.1';
                const result = processUrlIdentifier(url, {
                    mockVersionField,
                    isDataverseRepo: true,
                });

                expect(result).toBe('doi:10.15454/XRXSHL');
                expect(mockVersionField.value).toBe('1.1');
            });

            test('should process dataverse URL with only persistentId', () => {
                const mockVersionField = createMockElement('');
                const url =
                    'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=doi:10.18419/DARUS-2277';
                const result = processUrlIdentifier(url, {
                    mockVersionField,
                    isDataverseRepo: true,
                });

                expect(result).toBe('doi:10.18419/DARUS-2277');
                expect(mockVersionField.value).toBe('');
            });

            test('should handle dataverse URL without persistentId', () => {
                const mockVersionField = createMockElement('');
                const url =
                    'https://darus.uni-stuttgart.de/dataset.xhtml?someOtherParam=value';
                const result = processUrlIdentifier(url, {
                    mockVersionField,
                    isDataverseRepo: true,
                });

                expect(result).toBe('dataset.xhtml');
                expect(mockVersionField.value).toBe('');
            });
        });

        describe('Non-dataverse URLs with query parameters', () => {
            test('should process URL with query parameters when not dataverse', () => {
                const mockVersionField = createMockElement('');
                const url = 'https://example.com/resource?id=123&format=pdf';
                const result = processUrlIdentifier(url, {
                    mockVersionField,
                    isDataverseRepo: false,
                });

                // Should use the query handling path since url.search exists
                expect(result).toBe('resource');
                expect(mockVersionField.value).toBe('');
            });
        });

        describe('Error handling', () => {
            test('should handle invalid URLs gracefully', () => {
                // Spy on console.warn to verify it's called but suppress output
                const consoleSpy = jest
                    .spyOn(console, 'warn')
                    .mockImplementation(() => {});

                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier('not-a-valid-url', {
                    mockVersionField,
                });

                // Should fall back to removeVersionFromIdentifier
                expect(result).toBe('not-a-valid-url');
                expect(mockVersionField.value).toBe('');
                expect(consoleSpy).toHaveBeenCalled();

                consoleSpy.mockRestore();
            });

            test('should handle malformed URLs', () => {
                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier('https://.com/path', {
                    mockVersionField,
                });

                // Even malformed URLs can be parsed by URL constructor, pathname is "/path" -> "path"
                expect(result).toBe('path');
            });
        });

        describe('Direct identifiers (non-URLs)', () => {
            test('should process direct identifier with version', () => {
                // Mock console.warn since URL parsing will fail for non-URL inputs
                const consoleSpy = jest
                    .spyOn(console, 'warn')
                    .mockImplementation(() => {});

                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier('2310.02192v1', {
                    mockVersionField,
                });

                expect(result).toBe('2310.02192');
                expect(mockVersionField.value).toBe('1');

                consoleSpy.mockRestore();
            });

            test('should process direct identifier without version', () => {
                const consoleSpy = jest
                    .spyOn(console, 'warn')
                    .mockImplementation(() => {});

                const mockVersionField = createMockElement('');
                const result = processUrlIdentifier('2310.02192', {
                    mockVersionField,
                });

                expect(result).toBe('2310.02192');
                expect(mockVersionField.value).toBe('');

                consoleSpy.mockRestore();
            });
        });
    });

    describe('setPlaceholder', () => {
        beforeEach(() => {
            // Reset global mocks before each test
            setMockGlobals({
                examples: {
                    arxiv: '2310.02192',
                    hal: 'hal-05191818',
                    dataverse: 'doi:10.18419/DARUS-2277',
                },
                translate: text => `[${text}]`, // Mock translation with brackets
            });
        });

        test('should set placeholder text based on repository selection', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('arxiv');

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
            });

            expect(result).toBe('[exemple : ]2310.02192');
            expect(mockDocIdField.attributes.placeholder).toBe(
                '[exemple : ]2310.02192'
            );
            expect(mockDocIdField.attributes.size).toBe(
                '[exemple : ]2310.02192'.length
            );
        });

        test('should handle HAL repository selection', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('hal');

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
            });

            expect(result).toBe('[exemple : ]hal-05191818');
            expect(mockDocIdField.attributes.placeholder).toBe(
                '[exemple : ]hal-05191818'
            );
        });

        test('should handle dataverse repository selection', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('dataverse');

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
            });

            expect(result).toBe('[exemple : ]doi:10.18419/DARUS-2277');
        });

        test('should return null when fields are missing', () => {
            const result = setPlaceholder({
                mockDocIdField: null,
                mockRepoIdField: createMockElement('arxiv'),
            });

            expect(result).toBeNull();
        });

        test('should handle undefined repository value', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('unknown-repo');

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
            });

            expect(result).toBe('[exemple : ]undefined');
        });

        test('should use custom translate function', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('arxiv');
            const customTranslate = text => `TRANSLATED: ${text}`;

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
                translate: customTranslate,
            });

            expect(result).toBe('TRANSLATED: exemple : 2310.02192');
        });

        test('should use custom examples', () => {
            const mockDocIdField = createMockElement('');
            const mockRepoIdField = createMockElement('custom');
            const customExamples = { custom: 'custom-example-123' };

            const result = setPlaceholder({
                mockDocIdField,
                mockRepoIdField,
                examples: customExamples,
            });

            expect(result).toBe('[exemple : ]custom-example-123');
        });
    });
});
