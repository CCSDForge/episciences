const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { toAppPath } = require('../../helpers/app-url');
const { E2E_PASSWORD } = require('../../helpers/login');
const {
    submitArxivPaper,
    inviteKnownReviewer,
    createVolume,
    createVolumeGrid,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

/**
 * A browser context without any session, as an invitee opening a link while logged out.
 * @param {import('@playwright/test').Browser} browser
 * @param {string} baseURL
 * @returns {Promise<import('@playwright/test').BrowserContext>}
 */
async function newLoggedOutContext(browser, baseURL) {
    const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
    await context.addCookies([
        { name: 'cookieconsent_status', value: 'dismiss', domain: new URL(baseURL).hostname, path: '/' },
    ]);
    return context;
}

/**
 * Returns the path of the invitation link received at this address for this paper.
 * @param {import('../../helpers/mailpit-client').MailpitClient} mailpit
 * @param {number} docid
 * @param {string} to
 * @returns {Promise<string>}
 */
async function receivedInvitationPath(mailpit, docid, to) {
    const email = await mailpit.waitForEmail({
        to,
        subjectContains: [`#${docid} `, 'You are invited to review an article'],
    });
    const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
    expect(invitationUrl).toBeTruthy();

    return toAppPath(invitationUrl);
}

/**
 * Submits the forgotten login form displayed on the page.
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 */
async function requestLostLogin(page, email) {
    const form = page.locator('form#accountlostlogin');
    await expect(form).toBeVisible();
    await form.locator('input#EMAIL').fill(email);
    // Environment setting: the Altcha widget is only displayed when it is the configured CAPTCHA
    if (await form.locator('altcha-widget').count()) {
        await form.locator('altcha-widget label', { hasText: "I'm not a robot" }).click();
        await expect(form.locator('altcha-widget [data-state="verified"]')).toBeAttached({
            timeout: 30000,
        });
    }
    await form.locator('button[type="submit"]').click();
}

test.describe('Reviewer invitation security and login recovery', () => {
    test('a reviewer who forgot their login can retrieve it by email and sign in', async ({
        browser,
        baseURL,
        mailpit,
    }) => {
        test.setTimeout(120000);
        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();

        try {
            await test.step('the reviewer submits the forgotten login form', async () => {
                await page.goto('user/lostlogin');
                await requestLostLogin(page, REVIEWER_EMAIL);
                await expect(
                    page.getByText(
                        'If accounts are linked to this e-mail address, the list of their usernames has just been sent to it.'
                    )
                ).toBeVisible();
            });

            await test.step('the reviewer receives their username by email and signs in with it', async () => {
                const email = await mailpit.waitForEmail({
                    to: REVIEWER_EMAIL,
                    subjectContains: ['Logins list'],
                });
                expect(email.Text).toContain(E2E_USERNAMES.reviewer);

                await page.goto('user/login');
                const loginForm = page.locator('form#loginform, form#credential');
                await expect(loginForm).toBeVisible();
                await loginForm.locator('input#username, input[name="username"]').fill(E2E_USERNAMES.reviewer);
                await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
                await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();

                await expect(page).not.toHaveURL(/user\/login/);
                await expect(page.locator('#user-dropdown, a[href*="/user/logout"]').first()).toBeAttached();
            });
        } finally {
            await context.close();
        }
    });

    test('a forged invitation token is rejected and the paper author cannot review their own paper', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer on a paper submitted by the author', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1506.02640' });
            const vid = await createVolume(adminPage, `E2E Security volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            invitationPath = await receivedInvitationPath(mailpit, docid, REVIEWER_EMAIL);
        });

        await test.step('a logged-out visitor with a forged token is denied access to the invitation', async () => {
            const forgedPath = invitationPath.replace(/\/token\/[^/?]+/, '/token/invalid-forged-token');
            expect(forgedPath).not.toBe(invitationPath);

            const context = await newLoggedOutContext(browser, baseURL);
            const page = await context.newPage();
            try {
                await page.goto(forgedPath);
                await expect(page.getByText(/Invalid link|Lien non valide/i)).toBeVisible();
                await expect(page.locator('#submitaccept')).toHaveCount(0);
            } finally {
                await context.close();
            }
        });

        await test.step('the author of the paper is blocked when opening the valid invitation link', async () => {
            await authorPage.goto(invitationPath);
            await expect(
                authorPage.getByText(/cannot be reviewed by its author|ne peut pas être relu par son auteur/i)
            ).toBeVisible();
            await expect(authorPage.locator('#submitaccept')).toHaveCount(0);
        });
    });
});
