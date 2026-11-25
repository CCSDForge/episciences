const {
    setPlaceholder,
    createMockElement,
    setMockGlobals,
} = require('./submitFunctions');

describe('Submit Functions', () => {
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
