const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    chooseReviewReport,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const REQUEST_TYPE_OPTIONS = '#invitation-form select[name="request_type_id"] option';

/**
 * Opens the invitation page of a paper and chooses a person found by the search (git #900).
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {string} username
 */
async function chooseFromSearch(page, docid, username) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);
    await page.locator('#reviewer-search-term').fill(username);
    await page.locator('#reviewer-search-results tbody tr', { hasText: username }).locator('button').click();
    await expect(page.locator('#step-2')).toBeVisible();
}

test.describe('Duplicate invitations', () => {
    test('a reviewer is not invited twice for the same request type, and stays offered for another one', async ({
        authorPage,
        adminPage,
        secretaryPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let docid;

        await test.step('a reviewer invited meanwhile from another page is not invited twice for the same type', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1607.06450' });

            // The secretary chooses the reviewer, but sends the invitation only after the administrator did
            await chooseFromSearch(secretaryPage, docid, E2E_USERNAMES.reviewer);
            await chooseReviewReport(secretaryPage);

            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);

            await secretaryPage.waitForFunction(() => Boolean(window.tinymce?.get('body')?.initialized));
            await secretaryPage.locator('#submit-invitation').click();
            await expect(secretaryPage.locator('body')).toContainText(
                'This reviewer already has a request of this type in progress for this article.'
            );

            await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
            const invited = adminPage.locator('#pending-invitations .reviewer, #invitations .reviewer', {
                hasText: /E2E Reviewer/i,
            });
            await openPaperManagement(adminPage, docid);
            await expect(invited).toHaveCount(1);
        });

        await test.step('the invited reviewer is still offered, for the types not in progress only (#319)', async () => {
            await chooseFromSearch(adminPage, docid, E2E_USERNAMES.reviewer);

            await expect(adminPage.locator(REQUEST_TYPE_OPTIONS, { hasText: 'Review report' })).toBeDisabled();
            await expect(adminPage.locator(REQUEST_TYPE_OPTIONS, { hasText: 'Quick opinion' })).toBeEnabled();
        });
    });
});
