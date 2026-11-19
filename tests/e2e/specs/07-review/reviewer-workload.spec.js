const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
} = require('../../helpers/editorial-workflow');

// Papers not used by any other spec (parallel workers)
const FIRST_ARXIV_ID = '1409.4842';
const SECOND_ARXIV_ID = '1412.3555';
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
        });
    });
});
