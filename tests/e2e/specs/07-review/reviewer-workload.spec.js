const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    inviteNewReviewer,
} = require('../../helpers/editorial-workflow');

// Papers not used by any other spec (parallel workers)
const FIRST_ARXIV_ID = '1409.4842';
const SECOND_ARXIV_ID = '1412.3555';
const THIRD_ARXIV_ID = '1312.4400';
const FOURTH_ARXIV_ID = '1505.00387';
const REVIEWER_USERNAME = 'e2e_reviewer';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

/**
 * Opens the invitation page of a paper.
 * @param {import('@playwright/test').Page} page
 * @param {number} docid
 */
async function openInvitationPage(page, docid) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);
}

/**
 * Searches a person on the invitation page; returns the results.
 * @param {import('@playwright/test').Page} page
 * @param {string} term
 */
async function search(page, term) {
    await page.locator('#reviewer-search-term').fill(term);
    await expect(page.locator('#reviewer-search-status')).not.toBeEmpty();
    return page.locator('#reviewer-search-results');
}

test.describe('Inviting a reviewer in one step, with their requests in the journal (#900, #320)', () => {
    test('a reviewer found by search shows the invitations they already have in the journal', async ({
        authorPage,
        adminPage,
    }) => {
        test.setTimeout(240000);
        const firstDocid = await submitArxivPaper(authorPage, {
            arxivId: FIRST_ARXIV_ID,
        });
        const secondDocid = await submitArxivPaper(authorPage, {
            arxivId: SECOND_ARXIV_ID,
        });

        await test.step('the reviewer is invited on a first paper', async () => {
            await inviteKnownReviewer(adminPage, firstDocid, REVIEWER_USERNAME);
        });

        const row =
            await test.step('searching them on a second paper shows their requests', async () => {
                await openInvitationPage(adminPage, secondDocid);
                const results = await search(adminPage, REVIEWER_USERNAME);

                await expect(results).toContainText('Reviewers of the journal');
                const reviewerRow = results.locator('tbody tr', {
                    hasText: REVIEWER_USERNAME,
                });
                // other specs may invite the same reviewer at the same time: at least this invitation
                await expect(reviewerRow).toContainText('Pending invitations:');
                await expect(reviewerRow).toContainText('Latest invitation:');
                return reviewerRow;
            });

        await test.step('a paper of the row opens in a new tab, without choosing the reviewer', async () => {
            const paperLink = row
                .locator('a[href^="/administratepaper/view/id/"]')
                .first();
            await expect(paperLink).toHaveAttribute('target', '_blank');
            const [paperTab] = await Promise.all([
                adminPage.context().waitForEvent('page'),
                paperLink.click(),
            ]);
            await paperTab.close();
            await expect(adminPage.locator('#step-2')).toBeHidden();
        });

        await test.step('inviting them repeats it above the invitation message', async () => {
            const alert = adminPage.locator('#reviewer-workload-alert');
            await row.getByRole('button', { name: /^Invite / }).click();

            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(adminPage.locator('#step-2 .panel-title')).toHaveText(
                'Invite this reviewer'
            );
            await expect(alert).toBeVisible();
            await expect(alert).toHaveAttribute('role', 'status');
            await expect(alert).toContainText(
                'Requests already made to this reviewer in the journal'
            );
            await expect(
                alert
                    .locator(
                        `a[href="/administratepaper/view/id/${firstDocid}"]`
                    )
                    .first()
            ).toContainText(`#${firstDocid}`);
        });
    });

    test('the reviewers can be browsed, a person invited without an account is found by address, an account is offered instead of a new person', async ({
        authorPage,
        adminPage,
    }) => {
        test.setTimeout(240000);
        const stamp = Date.now();
        const withoutAccount = {
            email: `e2e-workload-${stamp}@example.org`,
            lastname: `E2eworkload${stamp}`,
        };
        const firstDocid = await submitArxivPaper(authorPage, {
            arxivId: THIRD_ARXIV_ID,
        });
        const secondDocid = await submitArxivPaper(authorPage, {
            arxivId: FOURTH_ARXIV_ID,
        });

        await test.step('a person without an account is invited on a first paper', async () => {
            await inviteNewReviewer(adminPage, firstDocid, withoutAccount);
        });

        await openInvitationPage(adminPage, secondDocid);

        await test.step('the reviewers of the journal are browsed page by page', async () => {
            await adminPage.locator('#reviewer-browse summary').click();
            await expect(
                adminPage.locator('#reviewer-browse-status')
            ).toContainText('Reviewers of the journal:');
            await expect(
                adminPage.locator('#reviewer-browse-results tbody tr').first()
            ).toBeVisible();

            await adminPage
                .locator('#reviewer-browse-order')
                .selectOption('least_requested');
            await expect(
                adminPage.locator('#reviewer-browse-results tbody tr').first()
            ).toBeVisible();
        });

        await test.step('the address of a person invited before without an account finds them', async () => {
            const results = await search(
                adminPage,
                withoutAccount.email.toUpperCase()
            );
            const row = results.locator('tbody tr', {
                hasText: withoutAccount.email,
            });

            await expect(results).toContainText(
                'Already invited without an account'
            );
            await expect(row).toContainText('Pending invitations: 1');
            await expect(row).toContainText('not answered yet');
        });

        await test.step('a new person whose address has an account: the account is offered', async () => {
            await adminPage.locator('#new-person-toggle').click();
            await adminPage.locator('#new-person-email').fill(REVIEWER_EMAIL);
            await adminPage.locator('#new-person-lastname').fill('Someone');
            await adminPage.locator('#new-person-submit').click();

            const existing = adminPage.locator('#new-person-existing');
            await expect(existing).toContainText(
                'An account already uses this address'
            );
            await expect(
                existing.locator('tbody tr', { hasText: REVIEWER_USERNAME })
            ).toBeVisible();
            await expect(adminPage.locator('#step-2')).toBeHidden();
        });

        await test.step('an invalid address is pointed out on its field', async () => {
            await adminPage.locator('#new-person-email').fill('');
            await adminPage.locator('#new-person-submit').click();
            await expect(adminPage.locator('#new-person-errors')).toContainText(
                'Please enter a valid e-mail address'
            );
            await expect(
                adminPage.locator('#new-person-email')
            ).toHaveAttribute('aria-invalid', 'true');
        });
    });
});
