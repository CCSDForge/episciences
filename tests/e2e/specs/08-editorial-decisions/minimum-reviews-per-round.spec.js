const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    assignEditor,
    completeReview,
} = require('../../helpers/editorial-workflow');

// A paper with a second arXiv version, not used by any other spec (parallel workers)
const ARXIV_ID = '1301.3781';
const EDITOR_FULLNAME = 'E2E Editor';
const NOT_ENOUGH_REVIEWS =
    'You cannot accept this article, because the required number of reviews (1) has not been reached.';

/**
 * Opens the "Change article status" menu of a paper.
 * @param {import('@playwright/test').Page} page
 * @param {number} docid
 */
async function openStatusMenu(page, docid) {
    const panel = await openPaperManagement(page, docid);
    await panel.getByRole('button', { name: 'Change article status' }).click();
    return panel;
}

/**
 * The journal settings come from the E2E seed: 1 review in the first round, none in the later rounds,
 * editors may accept papers (see tests/e2e/README.md).
 */
test.describe('Minimum number of reviews per review round', () => {
    test('an editor may accept the first version once it is reviewed, and a new version without review', async ({
        authorPage,
        adminPage,
        editorPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(300000);
        const docid = await submitArxivPaper(authorPage, {
            arxivId: ARXIV_ID,
            version: 1,
        });
        await assignEditor(adminPage, docid, EDITOR_FULLNAME);

        await test.step('first round, not reviewed: the editor cannot accept the paper', async () => {
            const panel = await openStatusMenu(editorPage, docid);
            await expect(
                panel.locator('a[data-target=".acceptance-modal"]')
            ).toHaveCount(0);
            await expect(
                panel.locator('li[data-toggle="tooltip"] a.disabled').first()
            ).toBeVisible();
        });

        await test.step('the acceptance sent anyway is refused and leaves the paper unchanged', async () => {
            // The form is in the page even when the menu entry is disabled
            await editorPage.evaluate(() =>
                window.jQuery('.acceptance-modal').modal('show')
            );
            const modal = editorPage.locator('.acceptance-modal');
            await expect(modal).toBeVisible();
            await modal.locator('button.submit-modal').click();

            await editorPage.waitForURL(
                new RegExp(`administratepaper/view/id/${docid}`)
            );
            await expect(
                editorPage.getByText(NOT_ENOUGH_REVIEWS).first()
            ).toBeAttached();
            await expect(statusPanel(editorPage)).not.toContainText('Accepted');
        });

        await test.step('once reviewed, the editor can accept it', async () => {
            await completeReview({ adminPage, reviewerPage, mailpit }, docid);

            const panel = await openStatusMenu(editorPage, docid);
            await expect(
                panel.locator('a[data-target=".acceptance-modal"]')
            ).toBeVisible();
        });

        const newDocid =
            await test.step('the author answers a minor revision with version 2', async () => {
                await applyDecision(adminPage, docid, 'minor-revision-modal');

                await authorPage.goto(`paper/view/id/${docid}`);
                await authorPage.locator('#answer-request').click();
                await authorPage.locator('#new-version').click();
                const modal = authorPage.locator('.modal', {
                    has: authorPage.locator('form#submit_doc'),
                });
                await modal.locator('#search_doc-version').fill('2');
                await modal.locator('#search_doc-getPaper').click();
                await expect(modal.locator('#submitForm')).toBeVisible({
                    timeout: REPOSITORY_TIMEOUT,
                });
                await modal.locator('#disclaimers-disclaimer1').check();
                await modal.locator('#disclaimers-disclaimer2').check();
                await modal.locator('#submitPaper').click();
                await expect(authorPage).toHaveURL(/paper\/submitted/, {
                    timeout: REPOSITORY_TIMEOUT,
                });

                await openPaperManagement(adminPage, docid);
                const version2Link = adminPage
                    .locator(
                        '.panel:has(h2:has-text("Versions")) a[href^="/administratepaper/view/id/"]'
                    )
                    .filter({ hasText: 'Version 2' });
                return Number(
                    (await version2Link.getAttribute('href')).split('/').pop()
                );
            });

        await test.step('second round: the editor can accept version 2 without any review', async () => {
            // the editor assigned to the paper is kept on the new version
            const panel = await openStatusMenu(editorPage, newDocid);
            await expect(
                panel.locator('a[data-target=".acceptance-modal"]')
            ).toBeVisible();

            await panel.locator('a[data-target=".acceptance-modal"]').click();
            const modal = editorPage.locator('.acceptance-modal');
            await expect(modal).toBeVisible();
            await modal.locator('button.submit-modal').click();
            await editorPage.waitForURL(
                new RegExp(`administratepaper/view(/id/|\\?id=)${newDocid}`)
            );
            await expect(statusPanel(editorPage)).toContainText('Accepted');
        });
    });
});
