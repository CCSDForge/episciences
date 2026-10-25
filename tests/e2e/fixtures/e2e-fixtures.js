const base = require('@playwright/test');
const path = require('path');
const { MailpitClient } = require('../helpers/mailpit-client');

const authDir = path.resolve(__dirname, '..', '.auth');

/**
 * Creates a browser context with preloaded dismiss cookies for the cookie consent banner.
 */
async function createRoleContext(browser, role) {
    const storageState = path.join(authDir, `${role}.json`);
    const context = await browser.newContext({ storageState, ignoreHTTPSErrors: true });
    await context.addCookies([
        { name: 'cookieconsent_status', value: 'dismiss', domain: 'dev.episciences.org', path: '/' },
        { name: 'cookieconsent_status', value: 'dismiss', domain: '127.0.0.1', path: '/' },
        { name: 'cookieconsent_status', value: 'dismiss', domain: 'localhost', path: '/' }
    ]);
    return context;
}

/**
 * Extended Playwright test runner with pre-authenticated role contexts and utility helpers.
 */
const test = base.test.extend({
    // Helper to interact with Mailpit email API
    mailpit: async ({}, use) => {
        const client = new MailpitClient();
        await use(client);
    },

    // Pre-authenticated author page
    authorPage: async ({ browser }, use) => {
        const context = await createRoleContext(browser, 'author');
        const page = await context.newPage();
        await use(page);
        await context.close();
    },

    // Pre-authenticated reviewer page
    reviewerPage: async ({ browser }, use) => {
        const context = await createRoleContext(browser, 'reviewer');
        const page = await context.newPage();
        await use(page);
        await context.close();
    },

    // Pre-authenticated editor page
    editorPage: async ({ browser }, use) => {
        const context = await createRoleContext(browser, 'editor');
        const page = await context.newPage();
        await use(page);
        await context.close();
    },

    // Pre-authenticated secretary page
    secretaryPage: async ({ browser }, use) => {
        const context = await createRoleContext(browser, 'secretary');
        const page = await context.newPage();
        await use(page);
        await context.close();
    },

    // Pre-authenticated administrator / chief editor page
    adminPage: async ({ browser }, use) => {
        const context = await createRoleContext(browser, 'admin');
        const page = await context.newPage();
        await use(page);
        await context.close();
    }
});

module.exports = { test, expect: base.expect };
