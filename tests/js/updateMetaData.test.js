/**
 * Tests for updateMetaData function
 */

// Import the function to test
const fs = require('fs');
const path = require('path');

// Read the source file
const updateMetaDataSource = fs.readFileSync(
    path.join(__dirname, '../../public/js/common/updateMetaData.js'),
    'utf8'
);

// Evaluate the source to make the function available
eval(updateMetaDataSource);

describe('updateMetaData', function() {
    let mockRecordLoading;
    let mockButton;
    let mockParentNode;
    let mockNewButton;

    beforeEach(() => {
        // Mock DOM elements
        mockRecordLoading = {
            innerHTML: '',
            style: { display: 'none' }
        };

        mockNewButton = {
            cloneNode: jest.fn().mockReturnThis()
        };

        mockButton = {
            cloneNode: jest.fn().mockReturnValue(mockNewButton)
        };

        mockParentNode = {
            replaceChild: jest.fn()
        };
        mockButton.parentNode = mockParentNode;

        // Mock document.getElementById
        document.getElementById = jest.fn().mockReturnValue(mockRecordLoading);

        // Mock getLoader function
        global.getLoader = jest.fn().mockReturnValue('<div class="loader">Loading...</div>');

        // Mock window methods
        global.alert = jest.fn();

        // Clear fetch mock
        fetch.mockClear();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    describe('DOM manipulation', function() {
        it('should show loading indicator', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'Success', affectedRows: 1 };
            let loadingStateWhenFetchCalled = null;
            
            fetch.mockImplementationOnce(() => {
                // Capture the loading state when fetch is called
                loadingStateWhenFetchCalled = mockRecordLoading.style.display;
                return Promise.resolve({
                    text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
                });
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(document.getElementById).toHaveBeenCalledWith('record-loading');
            expect(getLoader).toHaveBeenCalled();
            expect(mockRecordLoading.innerHTML).toBe('<div class="loader">Loading...</div>');
            expect(loadingStateWhenFetchCalled).toBe('block');
        });

        it('should remove event listeners from button', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'Success', affectedRows: 0 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(mockButton.cloneNode).toHaveBeenCalledWith(true);
            expect(mockParentNode.replaceChild).toHaveBeenCalledWith(mockNewButton, mockButton);
        });

        it('should hide loading indicator after completion', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'Success', affectedRows: 0 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(mockRecordLoading.style.display).toBe('none');
        });
    });

    describe('HTTP requests', function() {
        it('should make POST request with correct parameters', async function() {
            // Arrange
            const docId = 456;
            const responseData = { message: 'Updated', affectedRows: 1 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(fetch).toHaveBeenCalledWith('/paper/updaterecorddata', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: expect.any(URLSearchParams)
            });

            // Check URLSearchParams content
            const [, options] = fetch.mock.calls[0];
            const formData = options.body;
            expect(formData.get('docid')).toBe('456');
        });
    });

    describe('successful response handling', function() {
        it('should display success message', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'Metadata updated successfully', affectedRows: 1 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(alert).toHaveBeenCalledWith('Metadata updated successfully');
        });

        it('should handle response with affectedRows > 0', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'Success', affectedRows: 1 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(alert).toHaveBeenCalledWith('Success');
            // Note: location.reload testing skipped due to JSDOM limitations
        });

        it('should handle response with affectedRows = 0', async function() {
            // Arrange
            const docId = 123;
            const responseData = { message: 'No changes needed', affectedRows: 0 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(alert).toHaveBeenCalledWith('No changes needed');
        });

        it('should handle error response', async function() {
            // Arrange
            const docId = 123;
            const responseData = { 
                message: 'Error occurred', 
                affectedRows: 1, 
                error: 'Database error' 
            };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(alert).toHaveBeenCalledWith('Error occurred');
        });
    });

    describe('error handling', function() {
        it('should handle fetch errors gracefully', async function() {
            // Arrange
            const docId = 123;
            const fetchError = new Error('Network error');
            
            fetch.mockRejectedValueOnce(fetchError);

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(console.error).toHaveBeenCalledWith('Fetch error:', fetchError);
            expect(alert).toHaveBeenCalledWith('An error occurred while updating metadata');
            expect(mockRecordLoading.style.display).toBe('none');
        });

        it('should handle invalid JSON response', async function() {
            // Arrange
            const docId = 123;
            const invalidJson = 'not valid json';
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(invalidJson)
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(console.log).toHaveBeenCalledWith(expect.any(SyntaxError));
            expect(mockRecordLoading.style.display).toBe('none');
        });

        it('should ensure loading indicator is hidden even when errors occur', async function() {
            // Arrange
            const docId = 123;
            fetch.mockRejectedValueOnce(new Error('Network error'));

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(mockRecordLoading.style.display).toBe('none');
        });
    });

    describe('edge cases', function() {
        it('should handle empty response', async function() {
            // Arrange
            const docId = 123;
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue('')
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            expect(console.log).toHaveBeenCalledWith(expect.any(SyntaxError));
            expect(mockRecordLoading.style.display).toBe('none');
        });

        it('should handle docId as string', async function() {
            // Arrange
            const docId = '789';
            const responseData = { message: 'Success', affectedRows: 0 };
            
            fetch.mockResolvedValueOnce({
                text: jest.fn().mockResolvedValue(JSON.stringify(responseData))
            });

            // Act
            await updateMetaData(mockButton, docId);

            // Assert
            const [, options] = fetch.mock.calls[0];
            const formData = options.body;
            expect(formData.get('docid')).toBe('789');
        });

        it('should handle missing record-loading element', async function() {
            // Arrange
            const docId = 123;
            document.getElementById.mockReturnValue(null);

            // Act & Assert
            await expect(updateMetaData(mockButton, docId)).rejects.toThrow();
        });
    });
});