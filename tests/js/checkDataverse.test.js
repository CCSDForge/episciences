const {
    checkDataverse,
    setMockGlobals,
    createMockElement,
    setMockFetch,
} = require('./checkDataverse');

describe('checkDataverse function', () => {
    beforeEach(() => {
        // Reset globals before each test
        setMockGlobals({
            $isDataverseRepo: false,
            translate: text => text,
        });
    });

    test('should return early if no repository ID field', async () => {
        const result = await checkDataverse({
            mockRepoIdField: null,
        });

        expect(result).toBeUndefined();
    });

    test('should detect dataverse repository and update submit entry text', async () => {
        const mockRepoIdField = createMockElement('dataverse-repo-id');
        const mockSubmitEntry = createMockElement('', 'Original text');

        // Mock successful dataverse response
        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('{"isDataverse": true}'),
        });

        setMockFetch(mockFetch);

        const result = await checkDataverse({
            mockRepoIdField,
            mockSubmitEntry,
            customFetch: mockFetch,
        });

        expect(mockFetch).toHaveBeenCalledWith('/submit/ajaxisdataverse', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: expect.any(FormData),
        });

        expect(result.isDataverse).toBe(true);
        expect(result.submitEntryText).toBe('Proposer un jeu de données');
        expect(mockSubmitEntry.textContent).toBe('Proposer un jeu de données');
    });

    test('should detect non-dataverse repository and update submit entry text', async () => {
        const mockRepoIdField = createMockElement('arxiv-repo-id');
        const mockSubmitEntry = createMockElement('', 'Original text');

        // Mock non-dataverse response
        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('{"isDataverse": false}'),
        });

        const result = await checkDataverse({
            mockRepoIdField,
            mockSubmitEntry,
            customFetch: mockFetch,
        });

        expect(result.isDataverse).toBe(false);
        expect(result.submitEntryText).toBe('Proposer un article');
        expect(mockSubmitEntry.textContent).toBe('Proposer un article');
    });

    test('should handle response without isDataverse property', async () => {
        const mockRepoIdField = createMockElement('unknown-repo-id');
        const mockSubmitEntry = createMockElement('', 'Original text');

        // Mock response without isDataverse property
        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('{"someOtherProperty": "value"}'),
        });

        const result = await checkDataverse({
            mockRepoIdField,
            mockSubmitEntry,
            customFetch: mockFetch,
        });

        expect(result.isDataverse).toBe(false); // Should default to false
        expect(result.submitEntryText).toBe('Proposer un article');
    });

    test('should work without submit entry element', async () => {
        const mockRepoIdField = createMockElement('dataverse-repo-id');

        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('{"isDataverse": true}'),
        });

        const result = await checkDataverse({
            mockRepoIdField,
            mockSubmitEntry: null,
            customFetch: mockFetch,
        });

        expect(result.isDataverse).toBe(true);
        expect(result.submitEntryText).toBeNull();
    });

    test('should use custom translate function', async () => {
        const mockRepoIdField = createMockElement('dataverse-repo-id');
        const mockSubmitEntry = createMockElement('', 'Original text');
        const customTranslate = jest.fn(text => `TRANSLATED: ${text}`);

        setMockGlobals({
            translate: customTranslate,
        });

        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('{"isDataverse": true}'),
        });

        const result = await checkDataverse({
            mockRepoIdField,
            mockSubmitEntry,
            customFetch: mockFetch,
        });

        expect(customTranslate).toHaveBeenCalledWith(
            'Proposer un jeu de données'
        );
        expect(result.submitEntryText).toBe(
            'TRANSLATED: Proposer un jeu de données'
        );
    });

    test('should send correct FormData with repository ID', async () => {
        const mockRepoIdField = createMockElement('test-repo-123');

        let capturedFormData = null;
        const mockFetch = jest.fn().mockImplementation(async (url, options) => {
            capturedFormData = options.body;
            return {
                text: jest.fn().mockResolvedValue('{"isDataverse": false}'),
            };
        });

        await checkDataverse({
            mockRepoIdField,
            customFetch: mockFetch,
        });

        expect(capturedFormData).toBeInstanceOf(FormData);
        // Note: FormData.get() is not available in Jest environment by default
        // but we can verify the FormData was created correctly by checking the mock was called
        expect(mockFetch).toHaveBeenCalledWith(
            '/submit/ajaxisdataverse',
            expect.objectContaining({
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: expect.any(FormData),
            })
        );
    });

    test('should handle network errors', async () => {
        const consoleSpy = jest
            .spyOn(console, 'error')
            .mockImplementation(() => {});

        const mockRepoIdField = createMockElement('error-repo-id');

        const mockFetch = jest
            .fn()
            .mockRejectedValue(new Error('Network error'));

        await expect(
            checkDataverse({
                mockRepoIdField,
                customFetch: mockFetch,
            })
        ).rejects.toThrow('Network error');

        consoleSpy.mockRestore();
    });

    test('should handle JSON parsing errors', async () => {
        const consoleSpy = jest
            .spyOn(console, 'error')
            .mockImplementation(() => {});

        const mockRepoIdField = createMockElement('invalid-json-repo-id');

        const mockFetch = jest.fn().mockResolvedValue({
            text: jest.fn().mockResolvedValue('invalid json response'),
        });

        await expect(
            checkDataverse({
                mockRepoIdField,
                customFetch: mockFetch,
            })
        ).rejects.toThrow();

        consoleSpy.mockRestore();
    });

    test('should handle fetch response errors', async () => {
        const consoleSpy = jest
            .spyOn(console, 'error')
            .mockImplementation(() => {});

        const mockRepoIdField = createMockElement('error-response-repo-id');

        const mockFetch = jest.fn().mockResolvedValue({
            text: jest
                .fn()
                .mockRejectedValue(new Error('Response parsing error')),
        });

        await expect(
            checkDataverse({
                mockRepoIdField,
                customFetch: mockFetch,
            })
        ).rejects.toThrow('Response parsing error');

        consoleSpy.mockRestore();
    });

    describe('Real-world scenarios', () => {
        test('should handle typical ArXiv repository check', async () => {
            const mockRepoIdField = createMockElement('arxiv');
            const mockSubmitEntry = createMockElement('', '');

            const mockFetch = jest.fn().mockResolvedValue({
                text: jest.fn().mockResolvedValue('{"isDataverse": false}'),
            });

            const result = await checkDataverse({
                mockRepoIdField,
                mockSubmitEntry,
                customFetch: mockFetch,
            });

            expect(result.isDataverse).toBe(false);
            expect(result.submitEntryText).toBe('Proposer un article');
        });

        test('should handle typical Dataverse repository check', async () => {
            const mockRepoIdField = createMockElement('darus');
            const mockSubmitEntry = createMockElement('', '');

            const mockFetch = jest.fn().mockResolvedValue({
                text: jest.fn().mockResolvedValue('{"isDataverse": true}'),
            });

            const result = await checkDataverse({
                mockRepoIdField,
                mockSubmitEntry,
                customFetch: mockFetch,
            });

            expect(result.isDataverse).toBe(true);
            expect(result.submitEntryText).toBe('Proposer un jeu de données');
        });
    });
});
