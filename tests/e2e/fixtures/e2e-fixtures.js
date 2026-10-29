const base = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { MailpitClient } = require('../helpers/mailpit-client');
const { E2E_USERNAMES, loginAndSaveSession } = require('../helpers/login');

const authDir = path.resolve(__dirname, '..', '.auth');

/**
 * Each worker logs in once per role, the first time a test needs it, and keeps its own sessions.
 *
 * Sharing one session between parallel workers is not an option: flash messages and one-time form
 * tokens live in the PHP session, so a page loaded by one test would consume the confirmation message
 * or invalidate the form token of another test running at the same time with the same account.
 */
const workerSessions = base.test.extend({
    roleStorageState: [
        async ({ browser }, use, workerInfo) => {
            const baseURL = workerInfo.project.use.baseURL;
            const paths = new Map();
            fs.mkdirSync(authDir, { recursive: true });

            await use(async role => {
                if (!paths.has(role)) {
                    const storageStatePath = path.join(authDir, `${role}-worker-${workerInfo.parallelIndex}.json`);
                    await loginAndSaveSession(browser, baseURL, role, storageStatePath);
                    paths.set(role, storageStatePath);
                }
                return paths.get(role);
            });
        },
        { scope: 'worker' },
    ],
});

/**
 * @param {keyof E2E_USERNAMES} role
 */
function rolePageFixture(role) {
    return async ({ browser, baseURL, roleStorageState }, use) => {
        const context = await browser.newContext({
            storageState: await roleStorageState(role),
            baseURL,
            ignoreHTTPSErrors: true,
        });
        await context.addCookies([
            { name: 'cookieconsent_status', value: 'dismiss', domain: new URL(baseURL).hostname, path: '/' },
        ]);
        const page = await context.newPage();
        await use(page);
        await context.close();
    };
}

/**
 * Playwright test runner extended with one logged-in page per role and a Mailpit client.
 */
const test = workerSessions.extend({
    mailpit: async ({}, use) => {
        const client = new MailpitClient();
        await use(client);
        await client.dispose();
    },

    authorPage: rolePageFixture('author'),
    reviewerPage: rolePageFixture('reviewer'),
    editorPage: rolePageFixture('editor'),
    secretaryPage: rolePageFixture('secretary'),
    // Administrator and editor in chief
    adminPage: rolePageFixture('admin'),
});

module.exports = { test, expect: base.expect, E2E_USERNAMES };
