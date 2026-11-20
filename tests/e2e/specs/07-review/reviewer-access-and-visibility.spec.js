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
const REVIEWER_NAME = /E2E Reviewer/i;

test.describe('Reviewer report visibility and access control', () => {
    test('an editors-only criterion and its attachment stay hidden and inaccessible to the author', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const publicComment = `E2E public feedback ${Date.now()}`;
        const confidentialComment = `E2E editors-only confidential note ${Date.now()}`;
        let docid;
        let confidentialAttachmentHref;

        await test.step('a paper is reviewed with both a public criterion and an editors-only criterion with upload', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1512.02325' });
            const vid = await createVolume(adminPage, `E2E Visibility volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E public criterion ${Date.now()}`,
                description: 'Visible to the author and editors.',
                visibility: 'public',
            });
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E confidential criterion ${Date.now()}`,
                description: 'Visible to editors only.',
                visibility: 'editors',
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

            const selects = reviewerPage.locator('form#rating-grid select[name^="note_item_"]');
            await expect(selects).toHaveCount(2);
            await selects.nth(0).selectOption({ label: '4/5' });
            await selects.nth(1).selectOption({ label: '5/5' });

            await reviewerPage.waitForFunction(() => {
                const editors = window.tinymce?.get() ?? [];
                return editors.length >= 2 && editors.every(editor => editor.initialized);
            });
            await reviewerPage.evaluate(
                ([pub, conf]) => {
                    const editors = window.tinymce.get();
                    editors[0].setContent(`<p>${pub}</p>`);
                    editors[1].setContent(`<p>${conf}</p>`);
                },
                [publicComment, confidentialComment]
            );
            await reviewerPage.locator('form#rating-grid input[type="file"]').setInputFiles(MINIMAL_PDF);
            await reviewerPage.locator('#validateRating').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);
        });

        await test.step('the editorial team sees both criteria and the confidential attachment', async () => {
            await openPaperManagement(adminPage, docid);
            await expect(adminPage.locator('#ratings')).toContainText(publicComment);
            await expect(adminPage.locator('#ratings')).toContainText(confidentialComment);

            const attachmentLink = adminPage.locator(`#ratings a[href*="/${docid}/report/"]`).first();
            await expect(attachmentLink).toBeAttached();
            await expect(attachmentLink).toHaveAttribute('href', new RegExp(`/${docid}/report/`));
            confidentialAttachmentHref = await attachmentLink.getAttribute('href');

            // Trigger a minor revision so that completed reports are displayed on the author page
            await applyDecision(adminPage, docid, 'minor-revision-modal');
        });

        await test.step('the author sees only the public criterion and is denied the editors-only attachment', async () => {
            await authorPage.goto(`paper/view/id/${docid}`);
            await expect(authorPage.locator('body')).toContainText(publicComment);
            await expect(authorPage.locator('body')).not.toContainText(confidentialComment);

            const response = await authorPage.request.get(confidentialAttachmentHref.replace(/^\//, ''));
            expect([401, 403, 404]).toContain(response.status());
        });
    });

    test('an unassigned editor is denied rating access, and a pending invitation can be cancelled', async ({
        authorPage,
        adminPage,
        editorPage,
    }) => {
        test.setTimeout(180000);
        let docid;

        await test.step('an unassigned user cannot access the rating form of a paper', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1603.04467' });
            await editorPage.goto(`paper/rating/id/${docid}`);
            await expect(editorPage.locator('form#rating-grid')).toHaveCount(0);
        });

        await test.step('a pending invitation can be cancelled before the reviewer answers', async () => {
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            await openPaperManagement(adminPage, docid);

            const pending = adminPage.locator('#pending-invitations');
            await adminPage.locator('[data-target="#pending-invitations"]').click();
            await expect(pending).toBeVisible();

            await pending.locator('.reviewer', { hasText: REVIEWER_NAME }).locator('.popover-link').click();
            await adminPage.locator('.popover .context-menu a[href^="/administratepaper/removereviewer/"]').click();

            const modal = adminPage.locator('#modal-box');
            await expect(modal.locator('#reviewer-removal-form')).toBeVisible();
            await adminPage.waitForFunction(() => Boolean(window.tinymce?.get('body')?.initialized));

            const saved = adminPage.waitForResponse(response =>
                response.url().includes('/administratepaper/savereviewerremoval')
            );
            await adminPage.locator('#submit-modal').click();
            expect((await (await saved).json()).status).toBe(1);
            await expect(modal).toBeHidden();

            await expect(adminPage.locator('#pending-invitations')).toHaveCount(0);
            await expect(adminPage.locator('#cancelled-invitations')).toBeAttached();
        });
    });
});
