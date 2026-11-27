const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { toAppPath } = require('../../helpers/app-url');
const { E2E_PASSWORD } = require('../../helpers/login');
const {
    submitArxivPaper,
    openPaperManagement,
    assignEditor,
    inviteKnownReviewer,
    inviteNewReviewer,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const EDITOR_FULLNAME = 'E2E Editor';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
// The application capitalises the names it displays ("E2e Reviewer")
const REVIEWER_NAME = /E2E Reviewer/i;
const ACCEPT_ON_BEHALF = 'Accept the invitation on behalf of the reviewer';

/**
 * Submits a paper reviewed with the grid of its own volume, and assigns the E2E editor to it.
 * @param {Object} fixtures
 * @param {import('@playwright/test').Page} fixtures.authorPage
 * @param {import('@playwright/test').Page} fixtures.adminPage
 * @param {string} arxivId one preprint can be submitted only once: each test uses its own
 * @returns {Promise<number>} docid
 */
async function submitPaperManagedByEditor({ authorPage, adminPage }, arxivId) {
    const docid = await submitArxivPaper(authorPage, { arxivId });
    // Accepting an invitation creates the rating report from the grid of the paper's volume; the
    // review form is only displayed once the grid has a criterion
    const vid = await createVolume(adminPage, `E2E Accepted by editor volume ${Date.now()}`);
    await createVolumeGrid(adminPage, vid);
    await addQuantitativeCriterion(adminPage, vid, {
        title: `E2E soundness ${Date.now()}`,
        description: 'Is the paper sound?',
    });
    await assignMasterVolume(adminPage, docid, vid);
    await assignEditor(adminPage, docid, EDITOR_FULLNAME);
    return docid;
}

/**
 * Opens, from the paper management page, the confirmation modal of the acceptance on behalf of a
 * pending invitee.
 * @param {import('@playwright/test').Page} page editor page
 * @param {number} docid
 * @param {string|RegExp} reviewerName as displayed in the reviewers block
 * @returns {Promise<import('@playwright/test').Locator>} the modal
 */
async function openAcceptOnBehalfModal(page, docid, reviewerName) {
    await openPaperManagement(page, docid);

    // The pending invitations are collapsed
    const pending = page.locator('#pending-invitations');
    await page.locator('[data-target="#pending-invitations"]').click();
    await expect(pending).toBeVisible();

    await pending.locator('.reviewer', { hasText: reviewerName }).locator('.popover-link').click();
    await page.locator('.popover .context-menu a', { hasText: ACCEPT_ON_BEHALF }).click();

    const modal = page.locator('#modal-box');
    await expect(modal.locator('#accept-invitation-form')).toBeVisible();
    return modal;
}

/**
 * Confirms the acceptance and checks the reviewers block and the history, both refreshed in place.
 * @param {import('@playwright/test').Page} page editor page
 * @param {import('@playwright/test').Locator} modal
 * @param {string|RegExp} reviewerName
 */
async function confirmAcceptance(page, modal, reviewerName) {
    const saved = page.waitForResponse(response => response.url().includes('/administratepaper/saveacceptreviewerinvitation'));
    await page.locator('#submit-modal').click();
    expect((await (await saved).json()).status).toBe(1);
    await expect(modal).toBeHidden();

    await expect(page.locator('#active-invitations .reviewer', { hasText: reviewerName })).toBeVisible();
    await expect(page.locator('#pending-invitations')).toHaveCount(0);

    // The history records who accepted on behalf of the reviewer, in the details of the entry
    await page.locator('#history .log-entry.reviewer_invitation_accepted').click();
    await expect(modal).toContainText(new RegExp(`Accepted on behalf of the reviewer by\\s*${EDITOR_FULLNAME}`, 'i'));
    await expect(modal).toContainText(reviewerName);
}

/**
 * Path of the first link of the email pointing to this route, relative to the base URL.
 * @param {import('../../helpers/mailpit-client').MailpitClient} mailpit
 * @param {Object} email
 * @param {string} route for instance "/paper/rating/"
 * @returns {string}
 */
function linkPath(mailpit, email, route) {
    const link = mailpit.extractLinks(email.Text).find(url => url.includes(route));
    expect(link).toBeTruthy();
    return toAppPath(link);
}

test.describe('Reviewer invitation accepted by the editor of the paper', () => {
    test('an editor accepts the invitation of a reviewer who has an account', async ({
        authorPage,
        adminPage,
        editorPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        let docid;

        await test.step('the paper, managed by the editor, has a pending invitation', async () => {
            docid = await submitPaperManagedByEditor({ authorPage, adminPage }, '1810.04805');
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
        });

        await test.step('the confirmation names the existing account the review is attached to', async () => {
            const modal = await openAcceptOnBehalfModal(editorPage, docid, REVIEWER_NAME);
            const summary = modal.locator('.accept-invitation-summary');
            await expect(summary).toContainText(REVIEWER_NAME);
            await expect(summary).toContainText(REVIEWER_EMAIL);
            await expect(summary).toContainText('The review will be attached to the existing account:');
            await expect(summary.locator('strong')).toHaveText(E2E_USERNAMES.reviewer);

            await confirmAcceptance(editorPage, modal, REVIEWER_NAME);
        });

        await test.step('the reviewer is told, and can review the paper with their account', async () => {
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, "You've accepted a reviewing invitation"],
            });
            await reviewerPage.goto(linkPath(mailpit, email, '/paper/rating/'));
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
            await expect(reviewerPage.locator('form#rating-grid')).toBeVisible();
        });
    });

    test('an editor accepts the invitation of a reviewer without an account, which creates it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        editorPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        // No account uses this address: accepting creates one
        const invitedEmail = `e2e-onbehalf-${suffix}@example.org`;
        const lastname = `Onbehalf${suffix}`;
        // The invitation form has no first name: the login comes from the local part of the address
        const expectedLogin = `e2eonbehalf${suffix}`;
        let docid;

        await test.step('the paper, managed by the editor, has a pending invitation sent to an address', async () => {
            docid = await submitPaperManagedByEditor({ authorPage, adminPage }, '1409.3215');
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname });
        });

        await test.step('the confirmation names the account that will be created', async () => {
            const modal = await openAcceptOnBehalfModal(editorPage, docid, lastname);
            const summary = modal.locator('.accept-invitation-summary');
            await expect(summary).toContainText(lastname);
            await expect(summary).toContainText(invitedEmail);
            await expect(summary).toContainText('A new account will be created with the username:');
            await expect(summary.locator('strong')).toHaveText(expectedLogin);

            await confirmAcceptance(editorPage, modal, lastname);
            // The reviewer now has a real account: no more temporary one
            await expect(editorPage.locator('#active-invitations .reviewer', { hasText: lastname })).not.toContainText(
                'temporary account'
            );
        });

        const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
        await context.addCookies([
            { name: 'cookieconsent_status', value: 'dismiss', domain: new URL(baseURL).hostname, path: '/' },
        ]);
        const page = await context.newPage();
        try {
            await test.step('the reviewer chooses the password of the new account from the email', async () => {
                const email = await mailpit.waitForEmail({ to: invitedEmail, subjectContains: ['Password reset request'] });
                await page.goto(linkPath(mailpit, email, '/user/resetpassword/'));
                await page.locator('input[name="PASSWORD"]').fill(E2E_PASSWORD);
                await page.locator('input[name="PASSWORD_VERIFY"]').fill(E2E_PASSWORD);
                await page.locator('form:has(input[name="PASSWORD_VERIFY"]) [type="submit"]').click();
                await expect(page.locator('.alert-success')).toBeVisible();
            });

            await test.step('the reviewer signs in with the new account and reviews the paper', async () => {
                const email = await mailpit.waitForEmail({
                    to: invitedEmail,
                    subjectContains: [`#${docid} `, "You've accepted a reviewing invitation"],
                });
                await page.goto(linkPath(mailpit, email, '/paper/rating/'));

                // The rating page displays the login form to a visitor who is not signed in
                const loginForm = page.locator('form#loginform, form#credential');
                await expect(loginForm).toBeVisible();
                await loginForm.locator('input#username, input[name="username"]').fill(expectedLogin);
                await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
                await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();

                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.locator('form#rating-grid')).toBeVisible();
            });
        } finally {
            await context.close();
        }
    });
});
