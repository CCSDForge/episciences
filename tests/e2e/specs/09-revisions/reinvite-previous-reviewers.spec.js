const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    submitInvitation,
} = require('../../helpers/editorial-workflow');

// A paper with a second arXiv version, not used by any other spec (parallel workers)
const ARXIV_ID = '1502.03044';
const REVIEWER_USERNAME = 'e2e_reviewer';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

/**
 * Asks for a minor revision, without reassigning the reviewers to the new version.
 * @param {import('@playwright/test').Page} adminPage
 * @param {number} docid
 */
async function askMinorRevisionWithoutReassignment(adminPage, docid) {
    const panel = await openPaperManagement(adminPage, docid);
    await panel.getByRole('button', { name: 'Change article status' }).click();
    await panel.locator('a[data-target=".minor-revision-modal"]').click();
    const modal = adminPage.locator('.minor-revision-modal');
    await expect(modal).toBeVisible();
    await modal.locator('#auto_reassignminor').uncheck();
    await modal.locator('button.submit-modal').click();
    await adminPage.waitForURL(
        new RegExp(`administratepaper/view(/id/|\\?id=)${docid}`)
    );
}

/**
 * The author answers the revision request with version 2 of the arXiv paper; returns its DOCID.
 * @param {import('@playwright/test').Page} authorPage
 * @param {import('@playwright/test').Page} adminPage
 * @param {number} docid
 */
async function submitVersion2(authorPage, adminPage, docid) {
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
    return Number((await version2Link.getAttribute('href')).split('/').pop());
}

test.describe('Reviewers of the previous versions (#1387)', () => {
    test('a reviewer of the first version is invited again on the second one, with the reassignment message', async ({
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(300000);
        const docid = await submitArxivPaper(authorPage, {
            arxivId: ARXIV_ID,
            version: 1,
        });

        await test.step('the reviewer is invited on version 1, then a revision is asked without reassignment', async () => {
            await inviteKnownReviewer(adminPage, docid, REVIEWER_USERNAME);
            await askMinorRevisionWithoutReassignment(adminPage, docid);
        });

        const newDocid =
            await test.step('the author submits version 2', async () =>
                submitVersion2(authorPage, adminPage, docid));

        const table = adminPage.locator('#previous-reviewers');

        await test.step('the invitation page of version 2 lists the reviewers of version 1', async () => {
            await openPaperManagement(adminPage, newDocid);
            await adminPage.locator(`#invite_reviewer_${newDocid}`).click();
            await expect(adminPage).toHaveURL(
                /administratepaper\/invitereviewer/
            );

            await expect(table.locator('h2')).toHaveText(
                'Reviewers of the previous versions'
            );
            const row = table.locator('tbody tr', { hasText: REVIEWER_EMAIL });
            await expect(row).toContainText('Version 1');
            await expect(
                row.getByRole('button', { name: /Invite .* again/ })
            ).toBeVisible();
        });

        await test.step('inviting them again uses the reassignment message, linked to version 1', async () => {
            await table
                .locator('tbody tr', { hasText: REVIEWER_EMAIL })
                .getByRole('button', { name: /Invite .* again/ })
                .click();

            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(adminPage.locator('#step-2 .panel-title')).toHaveText(
                'Re-invite this reviewer'
            );
            await expect(adminPage.locator('#subject')).toHaveValue(
                /new version of an article/
            );
            await submitInvitation(adminPage, newDocid);

            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${newDocid} `, 'new version of an article'],
            });
            expect(email.HTML).toContain(`/paper/view/id/${docid}`);
            expect(email.HTML).toContain('You are invited again');
            expect(email.HTML).not.toContain('automatically re-invited');
        });

        await test.step('once invited again, they cannot be invited a second time', async () => {
            await openPaperManagement(adminPage, newDocid);
            await adminPage.locator(`#invite_reviewer_${newDocid}`).click();
            const row = table.locator('tbody tr', { hasText: REVIEWER_EMAIL });
            await expect(row).toContainText('Already invited on this version');
            await expect(
                row.getByRole('button', { name: /Invite .* again/ })
            ).toHaveCount(0);
        });
    });
});
