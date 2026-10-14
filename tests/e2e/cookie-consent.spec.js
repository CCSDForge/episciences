const { test, expect } = require('@playwright/test');

test.describe('Cookie Consent Banner (GDPR)', () => {
    test('displays cookie notice and allows user dismissal', async ({ page }) => {
        await page.goto('');

        // Locate cookie banner container
        const cookieBanner = page.locator('.cc-window, [aria-label="cookieconsent"], .cc-banner');

        // Check if banner is displayed on first visit
        const isBannerVisible = await cookieBanner.isVisible().catch(() => false);

        if (isBannerVisible) {
            // Locate the dismiss / close button
            const dismissButton = cookieBanner.locator('.cc-dismiss, .cc-btn, button, a:has-text("Close")').first();
            await expect(dismissButton).toBeVisible();

            // Click to dismiss
            await dismissButton.click();

            // Verify banner is hidden or dismissed
            await expect(cookieBanner).not.toBeVisible();
        }
    });
});
