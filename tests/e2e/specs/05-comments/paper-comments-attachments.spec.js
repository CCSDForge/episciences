const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Comment attachment deletion security', () => {
    test('enforces strict POST and CSRF validation on comment file deletion', async ({ secretaryPage }) => {
        // Direct GET request must be rejected
        await secretaryPage.goto('comments/removefilecomment?docid=8&pcid=1&file=test.pdf');

        // Should be redirected to root or show error flash message
        expect(secretaryPage.url()).not.toContain('comments/removefilecomment');
        const alert = secretaryPage.locator('.alert-danger, .alert-warning, .alert');
        if (await alert.count() > 0) {
            const alertText = await alert.first().textContent();
            expect(alertText).toMatch(/Invalid request method|invalide|expiré|erreur/i);
        }
    });

    test('rejects POST to removefilecomment with invalid or missing CSRF token', async ({ secretaryPage }) => {
        // Send a POST request directly with invalid CSRF token using Playwright's API request context
        const response = await secretaryPage.request.post('comments/removefilecomment', {
            form: {
                docid: '8',
                pcid: '1',
                file: 'test.pdf',
                csrf_token_name: 'fake_token',
                fake_token: 'invalid_value'
            }
        });

        // The endpoint should redirect or reject invalid token
        expect(response.status()).toBeLessThan(500);
        expect(response.url()).not.toContain('removefilecomment');
    });
});
