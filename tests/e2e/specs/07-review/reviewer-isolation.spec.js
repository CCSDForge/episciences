const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { toAppPath } = require('../../helpers/app-url');
const {
    MINIMAL_PDF,
    submitArxivPaper,
    inviteKnownReviewer,
    acceptReviewInvitation,
    fillRatingComments,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const REFEREE_EMAIL = 'e2e_referee@example.org';

test.describe('Isolation between two reviewers of the same paper', () => {
    test('a reviewer can neither see, download nor delete the draft report of another reviewer', async ({
        authorPage,
        adminPage,
        reviewerPage,
        refereePage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const draftComment = `E2E confidential draft ${Date.now()}`;
        let docid;
        let attachmentHref;
        let deleteUrl;

        await test.step('two reviewers accept to review a paper whose grid accepts attachments', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1707.06347' });
            const vid = await createVolume(adminPage, `E2E Isolation volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E isolation criterion ${Date.now()}`,
                description: 'Score, comment and optional file.',
                upload: true,
            });
            await assignMasterVolume(adminPage, docid, vid);

            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.referee);
            await acceptReviewInvitation(mailpit, reviewerPage, docid, REVIEWER_EMAIL);
            await acceptReviewInvitation(mailpit, refereePage, docid, REFEREE_EMAIL);
        });

        await test.step('the first reviewer saves a draft with a comment and an attachment', async () => {
            const form = reviewerPage.locator('form#rating-grid');
            await form.locator('select[name^="note_item_"]').selectOption({ label: '2/5' });
            await fillRatingComments(reviewerPage, `<p>${draftComment}</p>`);
            await form.locator('input[type="file"]').setInputFiles(MINIMAL_PDF);
            await reviewerPage.locator('#submitRatingForm').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);

            await reviewerPage.goto(`paper/rating/id/${docid}`);
            const attachmentLink = reviewerPage.locator(`form#rating-grid a[href*="/${docid}/report/"]`);
            await expect(attachmentLink).toHaveCount(1);
            attachmentHref = toAppPath(await attachmentLink.getAttribute('href'));

            const deleteButton = reviewerPage.locator('form#rating-grid a[onclick*="deleteattachmentreport"]');
            deleteUrl = (await deleteButton.getAttribute('onclick')).match(/'([^']*\/paper\/deleteattachmentreport\/[^']+)'/)[1];

            // The owner of the draft can download its attachment
            expect((await reviewerPage.request.get(attachmentHref)).status()).toBe(200);
        });

        await test.step('the second reviewer only sees an empty form of their own', async () => {
            await refereePage.goto(`paper/rating/id/${docid}`);
            const form = refereePage.locator('form#rating-grid');
            await expect(form).toBeVisible();
            await expect(form.locator('select[name^="note_item_"]')).not.toHaveValue('2');
            await expect(form.locator(`a[href*="/${docid}/report/"]`)).toHaveCount(0);
            await expect(form.locator('a[onclick*="deleteattachmentreport"]')).toHaveCount(0);
            expect(await refereePage.content()).not.toContain(draftComment);
        });

        await test.step('the second reviewer cannot download the attachment of the first one', async () => {
            const response = await refereePage.request.get(attachmentHref);
            expect(response.status()).toBe(404);
        });

        await test.step('the second reviewer cannot delete the attachment of the first one', async () => {
            await refereePage.goto(toAppPath(deleteUrl));
            await expect(refereePage.getByText("You don't have the required permissions to delete this file").first()).toBeVisible();

            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await expect(reviewerPage.locator(`form#rating-grid a[href*="/${docid}/report/"]`)).toHaveCount(1);
            expect((await reviewerPage.request.get(attachmentHref)).status()).toBe(200);
        });
    });
});
