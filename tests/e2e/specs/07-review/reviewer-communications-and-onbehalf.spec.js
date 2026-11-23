const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    inviteKnownReviewer,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
    assignMasterVolume,
    fillRatingComments,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const REVIEWER_NAME = /E2E Reviewer/i;

/**
 * Row of a paper in My Reviews (paper/ratings): matched on its document id cell, the other cells
 * (dates, versions, titles) may contain the same digits
 * @param {import('@playwright/test').Page} page reviewer page
 * @param {number} docid
 * @returns {import('@playwright/test').Locator}
 */
function myReviewsRow(page, docid) {
    return page
        .locator('table#papers tbody tr')
        .filter({ has: page.locator('td:nth-child(4)').filter({ hasText: new RegExp(`^\\s*${docid}\\s*$`) }) });
}

test.describe('Reviewer on-behalf rating, communications and dashboard', () => {
    test('an editor can complete a review on behalf of an active reviewer and reopen it', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        let docid;

        await test.step('the reviewer accepts an invitation on a paper with a volume grid', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1710.10903' });
            const vid = await createVolume(adminPage, `E2E Onbehalf rating volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E onbehalf criterion ${Date.now()}`,
                description: 'Rate on behalf of the reviewer.',
            });
            await assignMasterVolume(adminPage, docid, vid);

            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
            const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
            expect(invitationUrl).toBeTruthy();

            await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
        });

        await test.step('the editorial team fills and validates the rating grid on behalf of the reviewer', async () => {
            await openPaperManagement(adminPage, docid);
            const activeReviewer = adminPage.locator('#active-invitations .reviewer', { hasText: REVIEWER_NAME });
            await activeReviewer.locator('.popover-link').click();
            await adminPage.locator(`.popover .context-menu a[href^="/paper/rating?id=${docid}"]`).click();

            await expect(adminPage).toHaveURL(new RegExp(`paper/rating\\?id=${docid}`));
            await expect(adminPage.locator('h2 .alert-warning')).toBeVisible();
            // Direct reviewer-to-author comment form is hidden when rating on behalf
            await expect(adminPage.locator('form button[name="postComment"]')).toHaveCount(0);

            await adminPage.locator('form#rating-grid select[name^="note_item_"]').selectOption({ label: '4/5' });
            await fillRatingComments(adminPage, '<p>E2E review submitted on behalf.</p>');
            await adminPage.locator('#validateRating').click();

            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage)).toContainText('Reviewed pending editorial decision');
            // Completed review exposes the reopen action in the reviewer context menu
            await adminPage.locator('#active-invitations .reviewer', { hasText: REVIEWER_NAME }).locator('.popover-link').click();
            await expect(
                adminPage.locator(`.popover .context-menu a[href^="/administratepaper/refreshrating/id/${docid}"]`)
            ).toBeVisible();
        });
    });

    test('a reviewer tracks the paper status across pending, in progress and completed in My Reviews', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        let docid;

        await test.step('the reviewer accepts the invitation and sees the paper as pending in My Reviews', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1801.04381' });
            const vid = await createVolume(adminPage, `E2E Dashboard volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E dashboard criterion ${Date.now()}`,
                description: 'General assessment.',
            });
            await assignMasterVolume(adminPage, docid, vid);

            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
            const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
            expect(invitationUrl).toBeTruthy();

            await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));

            await reviewerPage.goto('paper/ratings');
            const row = myReviewsRow(reviewerPage, docid);
            await expect(row).toBeVisible();
            await expect(row).toContainText(/pending/i);
        });

        await test.step('saving a draft switches the My Reviews status to in progress', async () => {
            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await reviewerPage.locator('form#rating-grid select[name^="note_item_"]').selectOption({ label: '3/5' });
            await reviewerPage.locator('#submitRatingForm').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);

            const row = myReviewsRow(reviewerPage, docid);
            await expect(row).toBeVisible();
            await expect(row).toContainText(/ongoing review/i);
        });

        await test.step('validating the review switches the My Reviews status to completed', async () => {
            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await reviewerPage.locator('form#rating-grid select[name^="note_item_"]').selectOption({ label: '5/5' });
            await reviewerPage.locator('#validateRating').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);

            const row = myReviewsRow(reviewerPage, docid);
            await expect(row).toBeVisible();
            await expect(row).toContainText(/completed/i);
        });
    });
});
