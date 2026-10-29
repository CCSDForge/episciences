const { test, expect } = require('@playwright/test');

test.describe('Public Journal Homepage', () => {
    test('loads the journal homepage successfully', async ({ page }) => {
        // Relative path '' respects baseURL in both domain mode ('/') and manager mode ('/dev/')
        const response = await page.goto('');

        // Verify successful response
        expect(response).not.toBeNull();
        expect(response.status()).toBe(200);

        // Check page title is present and meaningful
        await expect(page).toHaveTitle(/Discrete Mathematics|Episciences|Home/i);

        // Verify key structural elements exist
        const body = page.locator('body');
        await expect(body).toBeVisible();

        // Check for presence of header / navigation banner
        const headerOrNavbar = page.locator('header, nav, #header, .navbar, .menu').first();
        await expect(headerOrNavbar).toBeVisible();
    });

    test('renders primary navigation links', async ({ page }) => {
        await page.goto('');

        // Ensure at least one navigation link is clickable
        const navLinks = page.locator('a[href]');
        const count = await navLinks.count();
        expect(count).toBeGreaterThan(0);
    });
});
