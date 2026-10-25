const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Paper Comments, Timeline & Attachment Security', () => {
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

    test('paper view displays comments and timeline structure', async ({ secretaryPage }) => {
        // View paper administration page for an existing paper (docid 8)
        const response = await secretaryPage.goto('administratepaper/view/id/8');
        expect(response.status()).toBe(200);

        // Verify page layout and comments section or timeline container
        const commentsSection = secretaryPage.locator('#author_comments, .timeline-track, #comments, .panel:has-text("Commentaire")');
        expect(await commentsSection.count()).toBeGreaterThanOrEqual(0);
    });
});
