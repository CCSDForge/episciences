const { test, expect } = require('@playwright/test');

test.describe('Authentication Guards & Access Control', () => {
    test('redirects unauthenticated users attempting to access /submit', async ({ page }) => {
        // Accessing 'submit' without active session should redirect to login
        await page.goto('submit');

        // URL should have redirected towards login or CAS authentication
        const currentUrl = page.url();
        const isAuthRedirect = currentUrl.includes('login') || currentUrl.includes('cas');

        expect(isAuthRedirect).toBe(true);
    });

    test('protects user dashboard from unauthenticated access', async ({ page }) => {
        await page.goto('user/dashboard');

        const currentUrl = page.url();
        const isAuthRedirect = currentUrl.includes('login') || currentUrl.includes('cas');

        expect(isAuthRedirect).toBe(true);
    });
});
