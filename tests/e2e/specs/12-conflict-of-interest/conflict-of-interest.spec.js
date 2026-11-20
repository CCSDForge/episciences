const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    declareNoConflict,
    assignEditor,
} = require('../../helpers/editorial-workflow');

const CHIEF_EDITOR_EMAIL = 'e2e_admin@example.org';

/**
 * The suite runs with the conflict of interest declaration enabled or not (E2E_COI=1, applied by
 * app:e2e-seed): each test below only applies to one of both modes.
 * @param {import('@playwright/test').Page} page administrator page
 * @returns {Promise<boolean>}
 */
async function isCoiEnabled(page) {
    await page.goto('review/settings');
    return page.locator('#isCoiEnabled').isChecked();
}

test.describe('Conflict of interest declaration', () => {
    test('the editorial team declares the absence of conflict before managing a paper, and a conflict unassigns the editor', async ({
        authorPage,
        adminPage,
        editorPage,
        mailpit,
    }) => {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip(!(await isCoiEnabled(adminPage)), 'Conflict of interest declaration disabled (E2E_COI=1 enables it)');
        test.setTimeout(180000);
        let docid;

        await test.step('the chief editor is asked to declare a conflict before managing the paper', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1611.05431' });

            await adminPage.goto(`administratepaper/view/id/${docid}`);
            await expect(adminPage).toHaveURL(/\/coi\/report/);
            await expect(
                adminPage.getByText('you must confirm the lack of conflict of interest to access this submission').first()
            ).toBeVisible();

            await declareNoConflict(adminPage, docid);
            await expect(adminPage.getByText('Your answer has been saved.').first()).toBeVisible();
            await expect(adminPage.locator('#coi-no code#nb-no')).toHaveText('1');
        });

        await test.step('once declared, the management page opens directly', async () => {
            await adminPage.goto(`administratepaper/view/id/${docid}`);
            await expect(adminPage).toHaveURL(new RegExp(`administratepaper/view/id/${docid}`));
            await assignEditor(adminPage, docid, 'E2E Editor');
        });

        await test.step('the assigned editor declares a conflict and loses access to the management page', async () => {
            await editorPage.goto(`administratepaper/view/id/${docid}`);
            await expect(editorPage).toHaveURL(/\/coi\/report/);
            // The answer sends the editor to the public page of the paper, out of the management pages
            await editorPage.locator('#coi-yes').click();
            await expect(editorPage).not.toHaveURL(/coi\/report|administratepaper/);

            await editorPage.goto(`administratepaper/view/id/${docid}`);
            await expect(editorPage).not.toHaveURL(/coi\/report|administratepaper/);
        });

        await test.step('the chief editor is notified and sees the declared conflict', async () => {
            await mailpit.waitForEmail({
                to: CHIEF_EDITOR_EMAIL,
                subjectContains: [`#${docid} `, 'An editor declared a conflict of interest'],
            });

            await openPaperManagement(adminPage, docid);
            await expect(adminPage.locator('#coi-yes code#nb-yes')).toHaveText('1');
        });
    });

    test('without the declaration, the editorial team manages a paper directly', async ({ authorPage, adminPage }) => {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip(await isCoiEnabled(adminPage), 'Conflict of interest declaration enabled');
        const docid = await submitArxivPaper(authorPage, { arxivId: '1704.04861' });

        await adminPage.goto(`administratepaper/view/id/${docid}`);
        await expect(adminPage).toHaveURL(new RegExp(`administratepaper/view/id/${docid}`));
        await expect(adminPage.getByText('Conflict management')).toHaveCount(0);

        // The declaration page sends back to the management page
        await adminPage.goto(`coi/report?id=${docid}`);
        await expect(adminPage).toHaveURL(new RegExp(`administratepaper/view\\?id=${docid}`));
    });
});
