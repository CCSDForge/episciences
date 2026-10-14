const { test, expect } = require('@playwright/test');

test.describe('Browse Issues and Publications', () => {
    test('renders latest publications / issues page', async ({ page }) => {
        const response = await page.goto('browse/latest');
        expect(response).not.toBeNull();
        expect(response.status()).toBe(200);

        // Page should display main header
        const heading = page.locator('h1').first();
        await expect(heading).toBeVisible();

        // Main content area should be present
        const mainContent = page.locator('#main, #content, .container, body');
        await expect(mainContent.first()).toBeVisible();
    });

    test('renders regular issues archive', async ({ page }) => {
        const response = await page.goto('browse/regularissues');
        expect(response).not.toBeNull();
        expect(response.status()).toBe(200);

        const heading = page.locator('h1').first();
        await expect(heading).toBeVisible();
    });
});
