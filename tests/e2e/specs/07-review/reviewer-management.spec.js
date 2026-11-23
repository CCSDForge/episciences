const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    completeReview,
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
 * Formats a Date as YYYY-MM-DD in local time.
 * @param {Date} date
 * @returns {string}
 */
function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Opens the popover context menu of the active reviewer on the paper management page.
 * @param {import('@playwright/test').Page} page
 * @param {number} docid
 */
async function openActiveReviewerMenu(page, docid) {
    await openPaperManagement(page, docid);
    const activeReviewer = page.locator('#active-invitations .reviewer', { hasText: REVIEWER_NAME });
    await expect(activeReviewer).toBeVisible();
    await activeReviewer.locator('.popover-link').click();
    await expect(page.locator('.popover .context-menu')).toBeVisible();
}

test.describe('Reviewer management by the editorial team', () => {
    test('an editor can update the reviewing deadline and later unassign the reviewer', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        let docid;

        await test.step('the reviewer accepts an invitation on a paper with a volume grid', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1301.3781' });
            const vid = await createVolume(adminPage, `E2E Management volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E clarity ${Date.now()}`,
                description: 'Is the contribution clear?',
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
            await expect(reviewerPage.locator('form#rating-grid')).toBeVisible();
        });

        await test.step('the editorial team extends the reviewing deadline', async () => {
            await openActiveReviewerMenu(adminPage, docid);
            await adminPage.locator('.popover .context-menu a[href^="/administratepaper/updatedeadline/"]').click();

            const modal = adminPage.locator('#modal-box');
            const deadlineInput = modal.locator('#deadline-id');
            await expect(deadlineInput).toBeVisible();

            // Wait for TinyMCE inside the deadline modal to initialize before submitting
            await adminPage.waitForFunction(() => Boolean(window.tinymce?.get('body')?.initialized));

            // Pick a date safely inside the journal's [attr-mindate, attr-maxdate] bounds
            const maxDateAttr = await deadlineInput.getAttribute('attr-maxdate');
            const newDeadlineIso = maxDateAttr || toIsoDate(new Date(Date.now() + 14 * 86400 * 1000));
            const newDeadlineYear = newDeadlineIso.slice(0, 4);
            await deadlineInput.fill(newDeadlineIso);
            await deadlineInput.dispatchEvent('change');

            const saved = adminPage.waitForResponse(response =>
                response.url().includes('/administratepaper/savenewdeadline')
            );
            await adminPage.locator('#submit-modal').click();
            expect((await (await saved).json()).status).toBe(1);
            await expect(modal).toBeHidden();

            await expect(adminPage.locator('#active-invitations .rating_deadline')).not.toBeEmpty();
            await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `],
                bodyContains: [newDeadlineYear],
            });
        });

        await test.step('the editorial team unassigns the reviewer and revokes their rating access', async () => {
            await openActiveReviewerMenu(adminPage, docid);
            await adminPage.locator('.popover .context-menu a[href^="/administratepaper/removereviewer/"]').click();

            const modal = adminPage.locator('#modal-box');
            await expect(modal.locator('#reviewer-removal-form')).toBeVisible();
            await adminPage.waitForFunction(() => Boolean(window.tinymce?.get('body')?.initialized));

            const saved = adminPage.waitForResponse(response =>
                response.url().includes('/administratepaper/savereviewerremoval')
            );
            await adminPage.locator('#submit-modal').click();
            expect((await (await saved).json()).status).toBe(1);
            await expect(modal).toBeHidden();

            await expect(adminPage.locator('#active-invitations .reviewer', { hasText: REVIEWER_NAME })).toHaveCount(0);
            await expect(adminPage.locator('#history')).toContainText('Reviewer unassigned');

            await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'Your rating invitation has been cancelled'],
            });

            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await expect(reviewerPage.locator('form#rating-grid')).toHaveCount(0);
        });
    });

    test('a reviewer can be invited again on version 2 after a minor revision and sees round 1', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(300000);
        let docidV1;
        let docidV2;
        let vid;

        await test.step('version 1 is submitted, reviewed and sent back for minor revision', async () => {
            docidV1 = await submitArxivPaper(authorPage, { arxivId: '1502.05477', version: 1 });
            vid = await createVolume(adminPage, `E2E Reassign volume ${Date.now()}`);
            await completeReview({ adminPage, reviewerPage, mailpit }, docidV1, { vid });

            await applyDecision(adminPage, docidV1, 'minor-revision-modal');
            await expect(statusPanel(adminPage)).toContainText('Pending minor revision');
        });

        await test.step('the author submits version 2 from arXiv', async () => {
            await authorPage.goto(`paper/view/id/${docidV1}`);
            await authorPage.locator('#answer-request').click();
            await authorPage.locator('#new-version').click();

            const modal = authorPage.locator('.modal', { has: authorPage.locator('form#submit_doc') });
            await modal.locator('#search_doc-version').fill('2');
            await modal.locator('#search_doc-getPaper').click();
            await expect(modal.locator('#submitForm')).toBeVisible({ timeout: REPOSITORY_TIMEOUT });
            await modal.locator('#disclaimers-disclaimer1').check();
            await modal.locator('#disclaimers-disclaimer2').check();
            await modal.locator('#submitPaper').click();
            await expect(authorPage).toHaveURL(/paper\/submitted/, { timeout: REPOSITORY_TIMEOUT });

            await openPaperManagement(adminPage, docidV1);
            const version2Link = adminPage
                .locator('.panel:has(h2:has-text("Versions")) a[href^="/administratepaper/view/id/"]')
                .filter({ hasText: 'Version 2' });
            await expect(version2Link).toBeVisible();
            docidV2 = Number((await version2Link.getAttribute('href')).split('/').pop());
            expect(docidV2).toBeGreaterThan(docidV1);
        });

        await test.step('the reviewer is invited on version 2, sees round 1 report, and completes round 2', async () => {
            await inviteKnownReviewer(adminPage, docidV2, E2E_USERNAMES.reviewer);
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docidV2} `, 'You are invited to review an article'],
            });
            const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
            expect(invitationUrl).toBeTruthy();

            await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docidV2}`));

            // Previous rating report from version 1 is visible alongside the new rating form
            await expect(reviewerPage.locator('[id^="previous-rating-"]')).toHaveCount(1);
            await expect(reviewerPage.locator('form#rating-grid')).toBeVisible();

            await reviewerPage.locator('form#rating-grid select[name^="note_item_"]').selectOption({ label: '5/5' });
            await fillRatingComments(reviewerPage, '<p>E2E round 2 review: ready to accept.</p>');
            await reviewerPage.locator('#validateRating').click();

            await expect(reviewerPage).toHaveURL(/paper\/ratings/);
            await openPaperManagement(adminPage, docidV2);
            await expect(statusPanel(adminPage)).toContainText('Reviewed pending editorial decision');
        });
    });
});
