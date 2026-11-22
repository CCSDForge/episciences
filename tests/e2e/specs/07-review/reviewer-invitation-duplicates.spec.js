const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    submitInvitation,
} = require('../../helpers/editorial-workflow');

const REFEREE_EMAIL = 'e2e_referee@example.org';

/**
 * Opens the invitation page of a paper and shows the new reviewer form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 */
async function openNewReviewerForm(page, docid) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);
    await page.locator('#new_user_button').click();
    await expect(page.locator('#email')).toBeVisible();
}

test.describe('Duplicate invitations and existing accounts', () => {
    test('the invitation page detects already invited reviewers, existing accounts and namesakes', async ({
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const newReviewerEmail = `e2e-namesake-${Date.now()}@example.org`;
        let docid;

        await test.step('an invited reviewer is no longer offered in the known reviewers', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1607.06450' });
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);

            await openPaperManagement(adminPage, docid);
            await adminPage.locator(`#invite_reviewer_${docid}`).click();
            await adminPage.locator('#known-reviewers_filter input').fill(E2E_USERNAMES.reviewer);
            await expect(adminPage.locator('#known-reviewers tbody tr', { hasText: E2E_USERNAMES.reviewer })).toHaveCount(0);
        });

        await test.step('the address of an already invited reviewer is refused', async () => {
            await openNewReviewerForm(adminPage, docid);
            await adminPage.locator('#email').fill('e2e_reviewer@example.org');
            await expect(adminPage.locator('#alert_exist_login')).toContainText(
                'An invitation to review this article has been sent to this user'
            );
            await expect(adminPage.locator('#next')).toBeHidden();
        });

        await test.step('a namesake account is suggested, and can be ignored to invite a new reviewer', async () => {
            await openNewReviewerForm(adminPage, docid);
            // The address is looked up as it is typed: its late answer would reset the form
            const lookedUp = adminPage.waitForResponse(response => response.url().includes('/user/ajaxfindusersbymail'));
            await adminPage.locator('#email').fill(newReviewerEmail);
            await lookedUp;
            await adminPage.locator('#lastname').fill('Editor');
            await adminPage.locator('#next').click();

            const namesakes = adminPage.locator('#homonym_users');
            await expect(namesakes).toContainText('We have found namesake users');
            await expect(namesakes.locator('#ccsd_user_invitation tr', { hasText: E2E_USERNAMES.editor })).toBeVisible();

            // A second click continues with the new reviewer
            await adminPage.locator('#next').click();
            await submitInvitation(adminPage, docid);
            await mailpit.waitForEmail({
                to: newReviewerEmail,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
        });

        await test.step('the accounts found by address, then the namesakes, can both be loaded on the same page', async () => {
            const scriptErrors = [];
            const onPageError = error => scriptErrors.push(error.message);
            adminPage.on('pageerror', onPageError);
            try {
                await openNewReviewerForm(adminPage, docid);
                await adminPage.locator('#email').fill(REFEREE_EMAIL);
                await expect(adminPage.locator('#alert_exist_login')).toContainText(
                    'We have found users with the same e-mail address'
                );

                const lookedUp = adminPage.waitForResponse(response => response.url().includes('/user/ajaxfindusersbymail'));
                await adminPage.locator('#email').fill(`e2e-namesake-bis-${Date.now()}@example.org`);
                await lookedUp;
                await adminPage.locator('#lastname').fill('Editor');
                await adminPage.locator('#next').click();
                const namesakes = adminPage.locator('#homonym_users');
                await expect(namesakes).toContainText('We have found namesake users');

                // The rows of the second list still select their account
                await namesakes.locator('#ccsd_user_invitation tr', { hasText: E2E_USERNAMES.editor }).click();
                await expect(adminPage.locator('#step-2')).toBeVisible();
                expect(scriptErrors).toEqual([]);
            } finally {
                adminPage.off('pageerror', onPageError);
            }
        });

        await test.step('the address of an existing account offers that account instead of a new one', async () => {
            await openNewReviewerForm(adminPage, docid);
            await adminPage.locator('#email').fill(REFEREE_EMAIL);

            const accounts = adminPage.locator('#alert_exist_login');
            await expect(accounts).toContainText('We have found users with the same e-mail address');
            await expect(adminPage.locator('#next')).toBeHidden();
            await accounts.locator('#ccsd_user_invitation tbody tr', { hasText: E2E_USERNAMES.referee }).click();

            await submitInvitation(adminPage, docid);
            await mailpit.waitForEmail({
                to: REFEREE_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
        });
    });
});
