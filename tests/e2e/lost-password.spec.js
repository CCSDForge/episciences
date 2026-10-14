const { test, expect } = require('@playwright/test');

test.describe('Account Recovery (Lost Password)', () => {
    test('renders lost password form with username input and Altcha widget', async ({ page }) => {
        const response = await page.goto('user/lostpassword');
        expect(response).not.toBeNull();
        expect(response.status()).toBe(200);

        // Verify recovery form is present
        const form = page.locator('form#accountlostpassword');
        await expect(form).toBeVisible();

        // Verify username/login field exists
        const usernameInput = form.locator('input#USERNAME');
        await expect(usernameInput).toBeVisible();

        // Verify submit button exists
        const submitButton = form.locator('button[type="submit"]');
        await expect(submitButton).toBeVisible();

        // Verify Altcha anti-spam widget is integrated
        const altchaWidget = form.locator('altcha-widget');
        await expect(altchaWidget).toBeAttached();
    });

    test('validates form when submitted empty', async ({ page }) => {
        await page.goto('user/lostpassword');

        const form = page.locator('form#accountlostpassword');
        const submitButton = form.locator('button[type="submit"]');

        // Submit without filling the required login field
        await submitButton.click();

        // The form should not succeed; page should stay on recovery or display validation error
        expect(page.url()).toContain('lostpassword');
    });
});
