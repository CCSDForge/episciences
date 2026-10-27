const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Multi-Role Interface Access & Permissions', () => {
    test('author can access their submissions and cannot access journal administration', async ({ authorPage }) => {
        // Author dashboard should be accessible
        const response = await authorPage.goto('user/dashboard');
        expect(response.status()).toBe(200);

        // Verification of author elements
        await expect(authorPage.locator('body')).toContainText(/Articles|Mes articles|Dashboard|Espace auteur/i);

        // Author should be rejected when trying to access admin paper list
        const adminResponse = await authorPage.goto('administratepaper/list');
        // Either redirected to 403 or denied page
        const isAccessDenied = adminResponse.status() === 403 ||
            authorPage.url().includes('forbidden') ||
            authorPage.url().includes('login') ||
            (await authorPage.locator('.alert-danger, .alert-warning, #error').count()) > 0;

        expect(isAccessDenied).toBe(true);
    });

    test('reviewer can access review dashboard', async ({ reviewerPage }) => {
        const response = await reviewerPage.goto('user/dashboard');
        expect(response.status()).toBe(200);

        // Reviewer ratings space should be accessible
        const reviewResponse = await reviewerPage.goto('paper/ratings');
        expect(reviewResponse.status()).toBeLessThan(400);
    });

    test('editor can access assigned papers in editorial space', async ({ editorPage }) => {
        const response = await editorPage.goto('administratepaper/list');
        expect(response.status()).toBe(200);

        // DataTables container should be present and visible
        const table = editorPage.locator('#papers_wrapper, table#papers');
        await expect(table.first()).toBeVisible();
    });

    test('secretary has access to full paper administration and user management', async ({ secretaryPage }) => {
        // Secretary paper list
        const papersResponse = await secretaryPage.goto('administratepaper/list');
        expect(papersResponse.status()).toBe(200);
        await expect(secretaryPage.locator('#papers_wrapper, table#papers').first()).toBeVisible();

        // Secretary user list
        const usersResponse = await secretaryPage.goto('user/list');
        expect(usersResponse.status()).toBe(200);
        expect(usersResponse.url()).toContain('user/list');
    });

    test('administrator / chief editor can access review settings', async ({ adminPage }) => {
        const response = await adminPage.goto('review/settings');
        expect(response.status()).toBe(200);

        // Settings heading should be present
        const heading = adminPage.getByRole('heading', { level: 1 });
        await expect(heading.first()).toBeVisible();
    });
});
