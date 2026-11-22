const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    acceptReviewInvitation,
    fillRatingComments,
    createVolume,
    createVolumeGrid,
    addCriterion,
    addSeparator,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

test.describe('Rating grid criteria types and overall score', () => {
    test('a grid mixing weighted, qualitative and free criteria is rated and scored with the coefficients', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const titles = {
            separator: `E2E Methodology ${suffix}`,
            weighted: `E2E weighted soundness ${suffix}`,
            tenScale: `E2E clarity ${suffix}`,
            standard: `E2E reproducible ${suffix}`,
            custom: `E2E novelty ${suffix}`,
            free: `E2E free remarks ${suffix}`,
        };
        const freeComment = `E2E free-text remark ${suffix}`;
        let docid;
        let vid;

        await test.step('the editorial team builds a grid with every criterion type', async () => {
            vid = await createVolume(adminPage, `E2E Criteria volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await addSeparator(adminPage, vid, titles.separator);
            await addCriterion(adminPage, vid, { title: titles.weighted, description: 'Weighted 3.', coef: 3 });
            await addCriterion(adminPage, vid, { title: titles.tenScale, description: 'Out of 10.', scale: '10' });
            await addCriterion(adminPage, vid, { title: titles.standard, description: 'Yes, no or maybe.', type: 'qualitative' });
            await addCriterion(adminPage, vid, {
                title: titles.custom,
                description: 'Custom values.',
                type: 'qualitative',
                options: ['E2E Strong', 'E2E Weak'],
            });
            await addCriterion(adminPage, vid, { title: titles.free, description: 'Free text only.', type: 'free' });

            const grid = adminPage.locator(`table#grid_${vid}`);
            await expect(grid.locator('tr', { hasText: titles.weighted }).locator('td.c-coeff')).toHaveText('3');
            await expect(grid.locator('tr', { hasText: titles.tenScale })).toContainText('Quantitative rating (out of 10)');
            await expect(grid.locator('tr', { hasText: titles.standard })).toContainText('Qualitative rating (standard)');
            await expect(grid.locator('tr', { hasText: titles.custom })).toContainText('Qualitative rating (customized)');
            await expect(grid.locator('tr', { hasText: titles.free })).toContainText('Free rating');
            await expect(grid.locator('tr', { hasText: titles.separator })).toContainText('Separator');
        });

        await test.step('the reviewer gets one field per criterion type', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1709.01507' });
            await assignMasterVolume(adminPage, docid, vid);
            await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
            await acceptReviewInvitation(mailpit, reviewerPage, docid, REVIEWER_EMAIL);

            const form = reviewerPage.locator('form#rating-grid');
            await expect(form.locator('h2.separator')).toHaveText(titles.separator);
            // Two quantitative and two qualitative criteria are graded, the free one only has a comment
            await expect(form.locator('select[name^="note_item_"]')).toHaveCount(4);
            await expect(form.locator('textarea[id^="comment_item_"]')).toHaveCount(5);

            const fieldset = title => form.locator('fieldset', { hasText: title });
            await expect(fieldset(titles.weighted)).toContainText('coefficient 3');
            await expect(fieldset(titles.standard).locator('select option')).toHaveText(['Yes', 'No', 'Maybe']);
            await expect(fieldset(titles.custom).locator('select option')).toHaveText(['E2E Strong', 'E2E Weak']);
            await expect(fieldset(titles.free).locator('select')).toHaveCount(0);
        });

        await test.step('the reviewer rates every criterion and submits the review', async () => {
            const form = reviewerPage.locator('form#rating-grid');
            const grade = title => form.locator('fieldset', { hasText: title }).locator('select');
            await grade(titles.weighted).selectOption({ label: '5/5' });
            await grade(titles.tenScale).selectOption({ label: '0/10' });
            await grade(titles.standard).selectOption({ label: 'Yes' });
            await grade(titles.custom).selectOption({ label: 'E2E Weak' });
            await fillRatingComments(reviewerPage, `<p>${freeComment}</p>`);
            await reviewerPage.locator('#validateRating').click();
            await expect(reviewerPage).toHaveURL(/paper\/ratings/);
        });

        await test.step('the editorial team sees each answer and the weighted overall score', async () => {
            await openPaperManagement(adminPage, docid);
            const ratings = adminPage.locator('#ratings');
            await expect(ratings).toContainText(freeComment);
            await expect(ratings).toContainText('E2E Weak');
            await expect(ratings).toContainText('Yes');
            // (5/5 x 3 + 0/10 x 1) / (3 + 1) = 0.75: the qualitative and free criteria do not count
            await expect(ratings.locator('.panel-heading').first()).toContainText(/:\s*0\.8\b/);
        });
    });
});
