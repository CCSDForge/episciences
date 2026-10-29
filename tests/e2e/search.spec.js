const { test, expect } = require('@playwright/test');

test.describe('Journal Search Engine', () => {
    test('renders the simple search form', async ({ page }) => {
        // Relative path 'search/index' works for both domain mode and manager mode
        const response = await page.goto('search/index');
        expect(response).not.toBeNull();
        expect(response.status()).toBe(200);

        // Verify search input is present in the main form
        const searchInput = page.locator('#search-simple-form input#q');
        await expect(searchInput).toBeVisible();
    });

    test('submits a query and displays results page', async ({ page }) => {
        await page.goto('search/index');

        const searchInput = page.locator('#search-simple-form input#q');
        await expect(searchInput).toBeVisible();

        // Fill search term and submit
        await searchInput.fill('mathematics');
        await page.locator('#search-simple-form button[type="submit"], #search-simple-form .input-group-btn button').first().click();

        // Wait for results page
        await page.waitForLoadState('domcontentloaded');

        // URL should contain query parameter 'q=mathematics'
        expect(page.url()).toContain('q=mathematics');

        // Ensure page body rendered without server crash
        const body = page.locator('body');
        await expect(body).toBeVisible();
    });
});
