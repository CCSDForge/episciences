const { test: setup, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const authDir = path.resolve(__dirname, '.auth');

if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
}

const USERS = [
    { name: 'author', username: 'e2e_author', password: 'password123' },
    { name: 'reviewer', username: 'e2e_reviewer', password: 'password123' },
    { name: 'editor', username: 'e2e_editor', password: 'password123' },
    { name: 'secretary', username: 'e2e_secretary', password: 'password123' },
    { name: 'admin', username: 'e2e_admin', password: 'password123' }
];

for (const user of USERS) {
    setup(`Authenticate as ${user.name} (${user.username})`, async ({ page }) => {
        const storageStatePath = path.join(authDir, `${user.name}.json`);

        // Navigate to login
        await page.goto('user/login');

        // Target login form
        const loginForm = page.locator('form#loginform, form#credential');
        await expect(loginForm).toBeVisible({ timeout: 10000 });

        // Fill credentials within the login form
        const usernameInput = loginForm.locator('input#username, input#USERNAME, input[name="username"]');
        await usernameInput.fill(user.username);

        const passwordInput = loginForm.locator('input#password, input#PASSWORD, input[name="password"]');
        await passwordInput.fill(user.password);

        // Submit form using specific form submit button
        const submitBtn = loginForm.locator('button#Connexion, button[type="submit"], input[type="submit"]').first();
        await submitBtn.click();

        // Wait for redirection back to the journal
        await page.waitForLoadState('networkidle');

        // Confirm logged in state (logout link should exist or URL should be on journal domain)
        const logoutLink = page.locator('a[href*="logout"]');
        await expect(logoutLink.first()).toBeAttached({ timeout: 10000 });

        // Save session state to disk
        await page.context().storageState({ path: storageStatePath });
    });
}
