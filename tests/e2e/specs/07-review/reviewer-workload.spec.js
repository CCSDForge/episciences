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

test.describe('Requests already made to a reviewer in the journal (#320)', () => {
    test('inviting a reviewer shows the invitations they already have in the journal', async ({
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
            await test.step('the known reviewers table shows it when inviting on a second paper', async () => {
                await openPaperManagement(adminPage, secondDocid);
                await adminPage
                    .locator(`#invite_reviewer_${secondDocid}`)
                    .click();
                await expect(adminPage).toHaveURL(
                    /administratepaper\/invitereviewer/
                );

                await expect(
                    adminPage.locator('#known-reviewers thead')
                ).toContainText('Requests in the journal');
                await adminPage
                    .locator('#known-reviewers_filter input')
                    .fill(REVIEWER_USERNAME);
                const reviewerRow = adminPage.locator(
                    '#known-reviewers tbody tr',
                    {
                        hasText: REVIEWER_USERNAME,
                    }
                );
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

        await test.step('choosing the reviewer repeats it above the invitation message', async () => {
            const alert = adminPage.locator('#reviewer-workload-alert');
            await expect(alert).toBeHidden();

            await row.click();
            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(alert).toBeVisible();
            await expect(alert).toHaveAttribute('role', 'status');
            await expect(alert).toContainText(
                'Requests already made to this reviewer in the journal'
            );
            await expect(alert).toContainText('Pending invitations:');
            // the alert lists every paper the user may manage, the first one of this test included
            await expect(
                alert
                    .locator(
                        `a[href="/administratepaper/view/id/${firstDocid}"]`
                    )
                    .first()
            ).toContainText(`#${firstDocid}`);
        });
    });

    test('the new reviewer panel shows it too, for an account found by search and for an address invited before', async ({
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

        await test.step('a person without an account and the reviewer are invited on a first paper', async () => {
            await inviteNewReviewer(adminPage, firstDocid, withoutAccount);
            await inviteKnownReviewer(adminPage, firstDocid, REVIEWER_USERNAME);
        });

        const alert = adminPage.locator('#reviewer-workload-alert');
        const openNewReviewerPanel = async () => {
            await openPaperManagement(adminPage, secondDocid);
            await adminPage.locator(`#invite_reviewer_${secondDocid}`).click();
            await expect(adminPage).toHaveURL(
                /administratepaper\/invitereviewer/
            );
            await adminPage.locator('#new_user_button').click();
        };

        await test.step('an account found by search', async () => {
            await openNewReviewerPanel();
            await adminPage.locator('#autocomplete').fill(REVIEWER_USERNAME);
            await adminPage
                .locator('.modern-autocomplete-item', {
                    hasText: 'e2e_reviewer@example.org',
                })
                .first()
                .click();

            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(alert).toBeVisible();
            await expect(alert).toContainText('Pending invitations:');
        });

        await test.step('the address of an account: the accounts found show their requests too', async () => {
            await openNewReviewerPanel();
            await adminPage.locator('#email').fill('e2e_reviewer@example.org');
            // the address is looked up when the field changes
            await adminPage.locator('#lastname').click();

            const accounts = adminPage.locator('#ccsd_user_invitation');
            await expect(accounts.locator('thead')).toContainText(
                'Requests in the journal'
            );
            await expect(accounts).not.toContainText('Invitation(s)');
            const account = accounts.locator('tbody tr', {
                hasText: 'e2e_reviewer',
            });
            await expect(account).toContainText('Pending invitations:');

            await account.locator('td').first().click();
            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(alert).toContainText('Pending invitations:');
        });

        await test.step('the address of a person invited before without an account', async () => {
            await openNewReviewerPanel();
            await adminPage
                .locator('#email')
                .fill(withoutAccount.email.toUpperCase());
            await adminPage.locator('#lastname').fill(withoutAccount.lastname);
            await adminPage.locator('#next').click();

            await expect(adminPage.locator('#step-2')).toBeVisible();
            await expect(alert).toBeVisible();
            await expect(alert).toContainText('Pending invitations: 1');
            await expect(alert).toContainText('not answered yet');
        });
    });
});
