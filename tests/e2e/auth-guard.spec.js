const { test, expect } = require('@playwright/test');

test.describe('Authentication Guards & Access Control', () => {
    test('redirects unauthenticated users attempting to access /submit', async ({ page }) => {
        // Accessing 'submit' without active session should redirect to login
        await page.goto('submit');

        // URL redirected to login/cas, or page renders login challenge
        const currentUrl = page.url();
        const isAuthRedirect = currentUrl.includes('login') || currentUrl.includes('cas');
        const showsLoginForm = await page.locator('form input#password, form input#PASSWORD, form#loginform').first().isVisible().catch(() => false);

        expect(isAuthRedirect || showsLoginForm).toBe(true);
    });

    test('protects user dashboard from unauthenticated access', async ({ page }) => {
        await page.goto('user/dashboard');

        const currentUrl = page.url();
        const isAuthRedirect = currentUrl.includes('login') || currentUrl.includes('cas');
        const showsLoginForm = await page.locator('form input#password, form input#PASSWORD, form#loginform').first().isVisible().catch(() => false);

        expect(isAuthRedirect || showsLoginForm).toBe(true);
    });
});
