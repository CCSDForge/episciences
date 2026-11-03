const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    inviteKnownReviewer,
    createVolume,
    assignMasterVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
} = require('../../helpers/editorial-workflow');

const ARXIV_ID = '1512.03385';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

test.describe('Reviewer invitation and review', () => {
    test('a reviewer invited by email accepts the invitation and rates the paper with the volume grid', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        const criterionTitle = `E2E originality ${Date.now()}`;
        let docid;

        await test.step('the paper is submitted in a volume that has its own rating grid', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: ARXIV_ID });

            const vid = await createVolume(adminPage, `E2E Review volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: criterionTitle,
                description: 'How original is the contribution?',
            });
            await assignMasterVolume(adminPage, docid, vid);
        });

        await test.step('the editorial team invites a reviewer known to the journal', async () => {
            await inviteKnownReviewer(adminPage, docid, 'e2e_reviewer');
            await expect(adminPage.locator('#reviewers')).toContainText('E2e Reviewer');
        });

        let invitationUrl;

        await test.step('the reviewer receives the invitation by email', async () => {
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
            invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
            expect(invitationUrl).toBeTruthy();
        });

        await test.step('the reviewer accepts the invitation', async () => {
            // The link targets the configured application URL: only its path is kept
            const { pathname } = new URL(invitationUrl);
            await reviewerPage.goto(pathname.replace(/^\//, ''));
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
        });

        await test.step('the rating form is built from the volume grid', async () => {
            const form = reviewerPage.locator('form#rating-grid');
            await expect(form).toContainText(criterionTitle);
            // The only criterion of the grid: graded on a 0-5 scale
            await expect(form.locator('select[name^="note_item_"]')).toHaveCount(1);
        });

        await test.step('the reviewer submits the review', async () => {
            const form = reviewerPage.locator('form#rating-grid');
            await form.locator('select[name^="note_item_"]').selectOption({ label: '4/5' });
            // Comment fields are TinyMCE editors
            await reviewerPage.evaluate(() => {
                window.tinymce.get().forEach(editor => editor.setContent('<p>E2E review: sound and original.</p>'));
            });
            await reviewerPage.locator('#validateRating').click();

            await expect(reviewerPage).toHaveURL(/paper\/ratings/);
            await expect(reviewerPage.getByText('Your review has been saved.').first()).toBeVisible();
        });

        await test.step('the editorial team sees the completed review and the new status', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage)).toContainText('Reviewed pending editorial decision');
            await expect(adminPage.locator('#history')).toContainText('Review completed');
        });
    });
});
