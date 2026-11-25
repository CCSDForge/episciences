const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    MINIMAL_PDF,
    openPaperManagement,
    submitArxivPaper,
    statusPanel,
    applyDecision,
    completeReview,
    createVolume,
} = require('../../helpers/editorial-workflow');

const AUTHOR_EMAIL = 'e2e_author@example.org';
const PAPER_TITLE = 'U-Net: Convolutional Networks for Biomedical Image Segmentation';

test.describe('Publication', () => {
    test('an accepted paper is published in a volume, which can then no longer be deleted', async ({
        authorPage,
        adminPage,
        reviewerPage,
        page,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const volumeTitle = `E2E Publication volume ${Date.now()}`;
        let docid;
        let vid;

        await test.step('the paper is reviewed in a volume, then accepted', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1505.04597' });
            vid = await createVolume(adminPage, volumeTitle);
            await completeReview({ adminPage, reviewerPage, mailpit }, docid, { vid });
            await applyDecision(adminPage, docid, 'acceptance-modal');
            await expect(adminPage.locator('.panel:has(h2:has-text("Volumes & sections"))')).toContainText(volumeTitle);
        });

        await test.step('the editorial team publishes it', async () => {
            await applyDecision(adminPage, docid, 'publication-modal');
            await expect(statusPanel(adminPage)).toContainText('Published');
        });

        await test.step('the author is notified', async () => {
            await mailpit.waitForEmail({ to: AUTHOR_EMAIL, subjectContains: [`#${docid} `, 'published'] });
        });

        await test.step('anonymous visitors see the paper in its volume', async () => {
            // The volume page lists the papers indexed in Solr, which the indexing worker updates asynchronously
            await expect(async () => {
                await page.goto(`volume/view/id/${vid}`);
                await expect(page.getByRole('link', { name: PAPER_TITLE }).first()).toBeVisible({ timeout: 1000 });
            }).toPass({ timeout: 30000 });

            await page.goto(`paper/view/id/${docid}`);
            await expect(page.getByText(PAPER_TITLE).first()).toBeVisible();
        });

        await test.step('the volume can no longer be deleted', async () => {
            await adminPage.goto('volume/list');
            await adminPage.locator(`tr#volume_${vid} a.delete`).click();
            await adminPage.locator('.bootbox-confirm .bootbox-accept').click();

            const alert = adminPage.locator('.bootbox.bootbox-alert');
            await expect(alert).toContainText('have already been published in this volume');
            await expect(alert).toContainText(`#${docid}`);

            await adminPage.goto('volume/list');
            await expect(adminPage.locator(`tr#volume_${vid}`)).toBeVisible();
        });
    });

    test('copy editing by the author: the formatted version is uploaded, then the paper is published', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1502.03167' });
        await completeReview({ adminPage, reviewerPage, mailpit }, docid);

        await test.step('the editorial team accepts the paper and asks the author for the formatted version', async () => {
            await applyDecision(adminPage, docid, 'acceptance-modal');
            await applyDecision(adminPage, docid, 'waiting-for-author-formatting-modal');
            await expect(statusPanel(adminPage)).toContainText("Copy ed.: waiting for author's final version");
            await mailpit.waitForEmail({ to: AUTHOR_EMAIL, subjectContains: [`#${docid} `] });
        });

        await test.step('the author uploads the formatted version', async () => {
            await authorPage.goto(`paper/view/id/${docid}`);
            await authorPage.locator('button[id^="replyFormBtn_"]').click();
            const form = authorPage.locator('form[id^="copy_editing_form_"]');
            await expect(form).toBeVisible();

            const upload = authorPage.waitForResponse(response => response.url().includes('/file/upload'));
            await form.locator('input[type="file"]').setInputFiles(MINIMAL_PDF);
            // A refused upload carries its reason in "messages". When UPLOADED_FILE.HMAC_SECRET is missing from
            // pwd.json, every upload is refused: see the application log
            const uploadResult = await (await upload).json();
            expect(uploadResult, JSON.stringify(uploadResult.messages)).toMatchObject({ status: 'success' });

            await form.locator('button[id^="ce_reply_"]').click();
            await expect(authorPage).toHaveURL(new RegExp(`paper/view\\?id=${docid}`));
        });

        await test.step('the editorial team sees the final version and publishes the paper', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(statusPanel(adminPage)).toContainText('final version');
            await applyDecision(adminPage, docid, 'ready-to-publish-modal');
            await applyDecision(adminPage, docid, 'publication-modal');
            await expect(statusPanel(adminPage)).toContainText('Published');
        });
    });
});
