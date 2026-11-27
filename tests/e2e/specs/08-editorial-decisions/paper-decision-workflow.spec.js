const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    completeReview,
} = require('../../helpers/editorial-workflow');

const AUTHOR_EMAIL = 'e2e_author@example.org';

test.describe('Editorial decisions', () => {
    test('accepting a paper changes its status and notifies the author', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1412.6980' });

        await test.step('the paper is reviewed', async () => {
            await completeReview({ adminPage, reviewerPage, mailpit }, docid);
        });

        await test.step('the editorial team accepts the paper', async () => {
            await applyDecision(adminPage, docid, 'acceptance-modal');
            await expect(statusPanel(adminPage)).toContainText('Accepted');
        });

        await test.step('copy editing options replace the review decisions', async () => {
            const panel = statusPanel(adminPage);
            await panel.getByRole('button', { name: 'Change article status' }).click();
            await expect(panel.locator('a[data-target=".publication-modal"]')).toBeVisible();
            await expect(panel.locator('a[data-target=".minor-revision-modal"]')).toHaveCount(0);
        });

        await test.step('the author is notified and sees the new status', async () => {
            await mailpit.waitForEmail({ to: AUTHOR_EMAIL, subjectContains: [`#${docid} `, 'has been accepted'] });

            await authorPage.goto(`paper/view/id/${docid}`);
            await expect(statusPanel(authorPage)).toContainText('Accepted');
        });
    });

    test('rejecting a paper closes the editorial process and notifies the author', async ({
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(120000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1409.0473' });

        await test.step('the editorial team rejects the paper', async () => {
            await applyDecision(adminPage, docid, 'refusal-modal');
            await expect(statusPanel(adminPage)).toContainText('Rejected');
        });

        await test.step('no further decision is offered', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage).getByRole('button', { name: 'Change article status' })).toHaveCount(0);
        });

        await test.step('the author is notified', async () => {
            await mailpit.waitForEmail({ to: AUTHOR_EMAIL, subjectContains: [`#${docid} `, 'has been rejected'] });
        });
    });
});
