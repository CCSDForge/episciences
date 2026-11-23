const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    inviteKnownReviewer,
    acceptReviewInvitation,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const AUTHOR_EMAIL = 'e2e_author@example.org';

test.describe('Anonymous messages between a reviewer and the author', () => {
    // Enabled by app:e2e-seed (reviewersCanCommentArticles)
    test('a reviewer asks the author a question and gets the answer without disclosing their identity', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        const question = `E2E question to the author ${Date.now()}`;
        const answer = `E2E answer to the reviewer ${Date.now()}`;
        let docid;

        await test.step('the reviewer accepts to review the paper', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1804.02767' });
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            await acceptReviewInvitation(mailpit, reviewerPage, docid, REVIEWER_EMAIL);
        });

        await test.step('the reviewer sends a message to the author from the rating page', async () => {
            // The panel is folded as long as no message has been sent
            await reviewerPage.getByRole('heading', { name: 'Send a message to the author' }).click();
            await expect(reviewerPage.locator('#postComment')).toBeVisible();
            await reviewerPage.waitForFunction(() => Boolean(window.tinymce?.get('comment')?.initialized));
            await reviewerPage.evaluate(content => window.tinymce.get('comment').setContent(content), `<p>${question}</p>`);
            await reviewerPage.locator('#postComment').click();
            await expect(reviewerPage.getByText('Your comment has been successfully sent.').first()).toBeVisible();

            await mailpit.waitForEmail({
                to: AUTHOR_EMAIL,
                subjectContains: [`#${docid} `, 'Comment request about your article'],
            });
        });

        let commentItem;

        await test.step('the author reads the message under the reviewer alias', async () => {
            await authorPage.goto(`paper/view/id/${docid}`);
            const comments = authorPage.locator('#comments');
            await expect(comments).toContainText(question);
            await expect(comments).toContainText(/reviewer 1/i);
            await expect(comments).not.toContainText(/E2E Reviewer/i);
            await expect(comments).not.toContainText(E2E_USERNAMES.reviewer);
            commentItem = comments.locator('article.timeline-item--demand', { hasText: question });
        });

        await test.step('the author answers and the reviewer is notified', async () => {
            await commentItem.locator('button.replyButton').click();
            const replyForm = commentItem.locator('form[id^="replyForm_"]');
            await expect(replyForm).toBeVisible();
            await replyForm.locator('textarea[id^="comment_"]').fill(answer);
            await replyForm.locator('button[id^="postReply_"]').click();
            await expect(authorPage.getByText('Your answer has been saved.').first()).toBeVisible();

            await mailpit.waitForEmail({
                to: REVIEWER_EMAIL,
                subjectContains: [`#${docid} `, 'New answer from an author to your comment'],
            });

            await reviewerPage.goto(`paper/rating/id/${docid}`);
            await expect(reviewerPage.locator('body')).toContainText(answer);
        });
    });
});
