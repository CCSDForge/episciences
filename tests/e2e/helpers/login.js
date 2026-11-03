const { expect } = require('@playwright/test');

/**
 * E2E accounts created by `php scripts/console.php app:e2e-seed` (scripts/E2ESeedCommand.php).
 * Requires the MySQL authentication adapter: "AUTH_ADAPTER_NAME": "MYSQL" in pwd.json.
 */
const E2E_PASSWORD = 'password123';
const E2E_USERNAMES = {
    author: 'e2e_author',
    reviewer: 'e2e_reviewer',
    editor: 'e2e_editor',
    secretary: 'e2e_secretary',
    admin: 'e2e_admin',
};

/**
 * Logs in through the login form and saves the session to storageStatePath.
 * @param {import('@playwright/test').Browser} browser
 * @param {string} baseURL
 * @param {keyof E2E_USERNAMES} role
 * @param {string} storageStatePath
 */
async function loginAndSaveSession(browser, baseURL, role, storageStatePath) {
    const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
    const page = await context.newPage();

    try {
        await page.goto('user/login');
        const loginForm = page.locator('form#loginform, form#credential');
        // The CAS adapter redirects to the CAS server, where the E2E accounts do not exist
        await expect(loginForm, 'Login form not found: is AUTH_ADAPTER_NAME set to "MYSQL" in pwd.json?').toBeVisible({
            timeout: 10000,
        });

        await loginForm.locator('input#username, input[name="username"]').fill(E2E_USERNAMES[role]);
        await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
        await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();

        await expect(page.locator('a[href*="logout"]').first()).toBeAttached({ timeout: 10000 });
        await context.storageState({ path: storageStatePath });
    } finally {
        await context.close();
    }
}

module.exports = { E2E_USERNAMES, E2E_PASSWORD, loginAndSaveSession };
