const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    MINIMAL_PDF,
    submitArxivPaper,
    openPaperManagement,
    applyDecision,
    inviteKnownReviewer,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

test.describe('Reviewer rating draft, attachment and anonymity', () => {
    test('a reviewer saves a draft with an attachment, deletes it, validates the report and stays anonymous to the author', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const criterionTitle = `E2E draft criterion ${Date.now()}`;
        let docid;

        await test.step('a paper is assigned to a volume grid allowing file attachments and the reviewer accepts', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1406.1078' });
            const vid = await createVolume(adminPage, `E2E Draft volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: criterionTitle,
                description: 'Provide a score, a comment and an optional PDF report.',
                visibility: 'public',
                upload: true,
            });
            await assignMasterVolume(adminPage, docid, vid);

            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            const email = await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'You are invited to review an article'],
            });
            const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
            expect(invitationUrl).toBeTruthy();

            await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
        });

        await test.step('the reviewer saves a draft with an attached PDF and can still edit it', async () => {
            const form = reviewerPage.locator('form#rating-grid');
            await expect(form).toBeVisible();
            await form.locator('select[name^="note_item_"]').selectOption({ label: '3/5' });
            await reviewerPage.waitForFunction(() => {
                const editors = window.tinymce?.get() ?? [];
                return editors.length > 0 && editors.every(editor => editor.initialized);
            });
            await reviewerPage.evaluate(() => {
                window.tinymce.get().forEach(editor => editor.setContent('<p>E2E draft comment.</p>'));
            });
            await form.locator('input[type="file"]').setInputFiles(MINIMAL_PDF);

            await reviewerPage.locator('#submitRatingForm').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);

            // Reopening the rating page shows the saved draft and the uploaded attachment
            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await expect(reviewerPage.locator('form#rating-grid')).toBeVisible();
            await expect(reviewerPage.locator('form#rating-grid select[name^="note_item_"]')).toHaveValue('3');
            await expect(reviewerPage.locator('a[onclick*="deleteattachmentreport"]')).toBeVisible();
        });

        await test.step('the reviewer deletes the draft attachment, then validates the final review', async () => {
            reviewerPage.once('dialog', dialog => dialog.accept());
            await reviewerPage.locator('a[onclick*="deleteattachmentreport"]').click();
            await expect(reviewerPage.locator('a[onclick*="deleteattachmentreport"]')).toHaveCount(0);

            const form = reviewerPage.locator('form#rating-grid');
            await form.locator('select[name^="note_item_"]').selectOption({ label: '5/5' });
            await reviewerPage.waitForFunction(() => {
                const editors = window.tinymce?.get() ?? [];
                return editors.length > 0 && editors.every(editor => editor.initialized);
            });
            await reviewerPage.evaluate(() => {
                window.tinymce.get().forEach(editor => editor.setContent('<p>E2E final anonymous review.</p>'));
            });
            await reviewerPage.locator('#validateRating').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);
        });

        await test.step('the validated report is locked for editing', async () => {
            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await expect(reviewerPage.locator('form#rating-grid')).toHaveCount(0);
            await expect(reviewerPage.locator('#current-rating')).toBeAttached();
        });

        await test.step('the author sees the completed review under an alias without the reviewer real name', async () => {
            await openPaperManagement(adminPage, docid);
            await applyDecision(adminPage, docid, 'minor-revision-modal');

            await authorPage.goto(`paper/view/id/${docid}`);
            await expect(authorPage.locator('body')).toContainText('E2E final anonymous review.');
            await expect(authorPage.locator('body')).toContainText(/Reviewer\s+1/i);
            await expect(authorPage.locator('body')).not.toContainText(/E2E Reviewer/i);
            await expect(authorPage.locator('body')).not.toContainText(E2E_USERNAMES.reviewer);
        });
    });
});
