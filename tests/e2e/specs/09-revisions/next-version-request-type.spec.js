const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    completeReview,
} = require('../../helpers/editorial-workflow');

// A paper with a second arXiv version, not used by any other spec (parallel workers)
const ARXIV_ID = '1406.1078';
const QUICK_OPINION = 'Quick opinion';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

/**
 * Id of a request type, read on the grids page.
 * @param {import('@playwright/test').Page} page administrator page
 * @param {string} label
 */
async function requestTypeId(page, label) {
    await page.goto('grid/list');
    const block = page.locator('[id^="rating-grid-t"]', {
        has: page.locator('.panel-title', { hasText: label }),
    });
    const id = await block.first().getAttribute('id');
    return Number(id.replace('rating-grid-t', ''));
}

/**
 * The author answers the revision request with version 2 of the arXiv paper.
 * @param {import('@playwright/test').Page} authorPage
 * @param {number} docid
 */
async function submitVersion2(authorPage, docid) {
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
}

test.describe('Request type of the next version (#319)', () => {
    test('the type chosen in the revision request is the one of the reviewers on the new version', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(300000);
        const quickOpinion = await requestTypeId(adminPage, QUICK_OPINION);
        const docid = await submitArxivPaper(authorPage, {
            arxivId: ARXIV_ID,
            version: 1,
        });
        // the reviewer is asked for a review report
        await completeReview({ adminPage, reviewerPage, mailpit }, docid);

        await test.step('the minor revision request asks for a quick opinion on the next version', async () => {
            const panel = await openPaperManagement(adminPage, docid);
            await panel
                .getByRole('button', { name: 'Change article status' })
                .click();
            await panel
                .locator('a[data-target=".minor-revision-modal"]')
                .click();

            const modal = adminPage.locator('.minor-revision-modal');
            await expect(modal).toBeVisible();
            const nextType = modal.locator('#next_request_type_id_minor');
            await expect(nextType).toHaveValue('');
            await nextType.selectOption(String(quickOpinion));
            await modal.locator('#auto_reassignminor').check();
            await modal.locator('button.submit-modal').click();
            await adminPage.waitForURL(
                new RegExp(`administratepaper/view(/id/|\\?id=)${docid}`)
            );
            await expect(statusPanel(adminPage)).toContainText(
                'Pending minor revision'
            );
        });

        const newDocid =
            await test.step('the author submits version 2', async () => {
                await submitVersion2(authorPage, docid);

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

        await test.step('the reviewer is invited again, for a quick opinion', async () => {
            await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [
                    `#${newDocid} `,
                    'Reviewer assignment to a new version',
                ],
            });

            await openPaperManagement(adminPage, newDocid);
            const reviewers = adminPage.locator('.panel', {
                has: adminPage.locator('h2', { hasText: 'Reviewers' }),
            });
            await expect(
                reviewers.locator('.label-info', { hasText: QUICK_OPINION })
            ).toHaveCount(1);
        });

        await test.step('inviting another reviewer on version 2 offers the quick opinion first', async () => {
            await adminPage.locator(`#invite_reviewer_${newDocid}`).click();
            await expect(adminPage).toHaveURL(
                /administratepaper\/invitereviewer/
            );
            await expect(
                adminPage.locator(
                    '#invitation-form select[name="request_type_id"]'
                )
            ).toHaveValue(String(quickOpinion));
        });
    });
});
