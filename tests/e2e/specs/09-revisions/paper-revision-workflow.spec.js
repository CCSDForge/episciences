const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    completeReview,
} = require('../../helpers/editorial-workflow');

const AUTHOR_EMAIL = 'e2e_author@example.org';

/**
 * Opens the author's answer menu of a paper waiting for a revision.
 * @param {import('@playwright/test').Page} authorPage
 * @param {number} docid
 */
async function openAnswerMenu(authorPage, docid) {
    await authorPage.goto(`paper/view/id/${docid}`);
    await authorPage.locator('#answer-request').click();
}

test.describe('Revisions', () => {
    test('a minor revision answered with a new version starts a new round on the new version', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1312.6114', version: 1 });
        await completeReview({ adminPage, reviewerPage, mailpit }, docid);

        await test.step('the editorial team asks for a minor revision', async () => {
            await applyDecision(adminPage, docid, 'minor-revision-modal');
            await expect(statusPanel(adminPage)).toContainText('Pending minor revision');
            await mailpit.waitForEmail({ to: AUTHOR_EMAIL, subjectContains: [`#${docid} `, 'Minor revision request'] });
        });

        await test.step('the author submits version 2 from arXiv', async () => {
            await openAnswerMenu(authorPage, docid);
            await authorPage.locator('#new-version').click();

            const modal = authorPage.locator('.modal', { has: authorPage.locator('form#submit_doc') });
            await modal.locator('#search_doc-version').fill('2');
            await modal.locator('#search_doc-getPaper').click();
            await expect(modal.locator('#submitForm')).toBeVisible({ timeout: REPOSITORY_TIMEOUT });
            await modal.locator('#disclaimers-disclaimer1').check();
            await modal.locator('#disclaimers-disclaimer2').check();
            await modal.locator('#submitPaper').click();

            await expect(authorPage).toHaveURL(/paper\/submitted/, { timeout: REPOSITORY_TIMEOUT });
        });

        await test.step('version 1 becomes obsolete and version 2 is a new submission', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage)).toContainText('Obsolete');

            const version2Link = adminPage
                .locator('.panel:has(h2:has-text("Versions")) a[href^="/administratepaper/view/id/"]')
                .filter({ hasText: 'Version 2' });
            await expect(version2Link).toBeVisible();
            const newDocid = Number((await version2Link.getAttribute('href')).split('/').pop());
            expect(newDocid).toBeGreaterThan(docid);

            await openPaperManagement(adminPage, newDocid);
            await expect(statusPanel(adminPage)).toContainText('Submitted');
            await mailpit.waitForEmail({
                to: AUTHOR_EMAIL,
                subjectContains: [`#${newDocid} `, 'New version has been submitted'],
            });
        });
    });

    test('a major revision can be answered without modifying the paper', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1406.2661' });
        await completeReview({ adminPage, reviewerPage, mailpit }, docid);

        await test.step('the editorial team asks for a major revision', async () => {
            await applyDecision(adminPage, docid, 'major-revision-modal');
            await expect(statusPanel(adminPage)).toContainText('Pending major revision');
        });

        await test.step('the author answers without any modification', async () => {
            await openAnswerMenu(authorPage, docid);
            await authorPage.locator('a[href^="/paper/answerrequest"]').click();

            const form = authorPage.locator('form[action^="/paper/saveanswer"]');
            await expect(form).toBeVisible();
            // The submit button is only enabled by an input event of the TinyMCE editor
            const editorBody = authorPage.frameLocator('#comment_only_answerRequest_ifr').locator('body');
            await editorBody.click();
            await editorBody.pressSequentially('E2E: the paper is kept as it is.');
            await authorPage.locator('.modal:visible .modal-footer').getByRole('button', { name: 'Submit' }).click();
            await expect(authorPage.locator('.modal:visible')).toHaveCount(0);
        });

        await test.step('the editorial team sees the answer and can decide again', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage)).toContainText('Revision request answer: without any modifications');
            await expect(adminPage.locator('#history')).toContainText('Reply to a revision request');
            await expect(statusPanel(adminPage).getByRole('button', { name: 'Change article status' })).toBeVisible();
        });
    });
});
