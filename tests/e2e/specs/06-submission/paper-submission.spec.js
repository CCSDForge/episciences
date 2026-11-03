const { test, expect } = require('../../fixtures/e2e-fixtures');
const { submitArxivPaper, openPaperManagement, statusPanel } = require('../../helpers/editorial-workflow');

// Each workflow spec submits its own arXiv preprint: one paper cannot be submitted twice to a journal
const ARXIV_ID = '1706.03762';

test.describe('Paper submission', () => {
    test('author submits an arXiv preprint, which reaches the editorial team as a new submission', async ({
        authorPage,
        secretaryPage,
        mailpit,
    }) => {
        test.setTimeout(120000);
        let docid;

        await test.step('author submits the preprint', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: ARXIV_ID });

            await expect(authorPage.getByText('Your article has been submitted.').first()).toBeVisible();
            await expect(authorPage.locator('.panel-title').first()).toContainText('Attention Is All You Need');
        });

        await test.step('the submission appears in the author submissions', async () => {
            await authorPage.goto('paper/submitted');
            await expect(authorPage.getByRole('link', { name: 'Attention Is All You Need' }).first()).toBeVisible();
        });

        await test.step('the editorial team sees it with the "Submitted" status', async () => {
            await openPaperManagement(secretaryPage, docid);
            await expect(statusPanel(secretaryPage)).toContainText('Submitted');
            await expect(secretaryPage.locator('#history')).toContainText('New status');
        });

        await test.step('author receives the submission acknowledgement', async () => {
            const email = await mailpit.waitForEmail({
                to: 'e2e_author@example.org',
                subjectContains: [`#${docid} `, 'Your article has been submitted'],
            });
            expect(email.Text).toContain('Attention Is All You Need');
        });
    });
});
