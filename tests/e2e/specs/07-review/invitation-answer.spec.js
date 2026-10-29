const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { E2E_PASSWORD } = require('../../helpers/login');
const { submitArxivPaper, inviteKnownReviewer } = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

/**
 * Invites the E2E reviewer and returns the path of the invitation link (with its link token).
 * @param {Object} fixtures
 * @param {import('@playwright/test').Page} fixtures.adminPage
 * @param {import('../../helpers/mailpit-client').MailpitClient} fixtures.mailpit
 * @param {number} docid
 * @returns {Promise<string>} path relative to the base URL
 */
async function inviteReviewer({ adminPage, mailpit }, docid) {
    await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);

    const email = await mailpit.waitForEmail({
        to: REVIEWER_EMAIL,
        subjectContains: [`#${docid} `, 'You are invited to review an article'],
    });
    const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
    expect(invitationUrl).toBeTruthy();

    // The link targets the configured application URL: only its path is kept (the link token is a path segment)
    return new URL(invitationUrl).pathname.replace(/^\//, '');
}

test.describe('Reviewer invitation answer', () => {
    test('only the answer form accepts an invitation, including after logging in', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1411.4038' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        await test.step('a GET request with the former acceptance parameter only displays the invitation', async () => {
            for (const path of [`${invitationPath}/is-accepted/1`, `${invitationPath}?is-accepted=1`]) {
                await reviewerPage.goto(path);
                await expect(reviewerPage).toHaveURL(/reviewer\/invitation/);
                await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
            }
        });

        await test.step('an acceptance posted without the form token is not stored', async () => {
            const response = await reviewerPage.request.post(invitationPath, {
                form: { submitaccept: '' },
                maxRedirects: 0,
            });
            // A stored acceptance redirects to the rating page; a rejected one displays the invitation again
            expect(response.status()).toBe(200);

            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
        });

        await test.step('a reviewer who accepts while logged out confirms once logged in', async () => {
            const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
            await context.addCookies([
                { name: 'cookieconsent_status', value: 'dismiss', domain: new URL(baseURL).hostname, path: '/' },
            ]);
            const page = await context.newPage();
            try {
                await page.goto(invitationPath);
                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(/user\/login/);

                const loginForm = page.locator('form#loginform, form#credential');
                await loginForm.locator('input#username, input[name="username"]').fill(E2E_USERNAMES.reviewer);
                await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
                await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();

                // Back on the invitation: logging in alone does not accept it
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toBeVisible();

                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            } finally {
                await context.close();
            }
        });
    });

    test('a reviewer declines an invitation with the refusal form', async ({ authorPage, adminPage, reviewerPage, mailpit }) => {
        test.setTimeout(180000);
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1506.01497' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        await test.step('the reviewer declines, with a reason', async () => {
            await reviewerPage.goto(invitationPath);
            await reviewerPage.locator('#refuse-button').click();
            await reviewerPage.locator('#refuse_form textarea[name="comment"]').fill('E2E: not my field.');
            await reviewerPage.locator('#refuse_form [name="submitrefuse"]').click();

            // A stored refusal leaves the invitation page for the home page
            await expect(reviewerPage).toHaveURL(url => !url.pathname.includes('reviewer/invitation'));
            await expect(reviewerPage.getByText('Your answer has been saved.').first()).toBeVisible();
        });

        await test.step('the invitation can no longer be answered', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText("You've already answered this invitation.")).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
        });
    });
});
