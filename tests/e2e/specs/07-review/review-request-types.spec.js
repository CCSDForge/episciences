const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    submitInvitation,
    addQuantitativeCriterion,
} = require('../../helpers/editorial-workflow');

// a paper of its own: specs run in parallel, and each one submits a different paper
const ARXIV_ID = '1704.04861';
const QUICK_OPINION = 'Quick opinion';
const REVIEW_REPORT = 'Review report';

/**
 * Id of a default request type of the journal, read on the grids page (its block is "#rating-grid-t<id>").
 * @param {import('@playwright/test').Page} page editorial page
 * @param {string} label English label of the type
 * @returns {Promise<number>}
 */
async function requestTypeId(page, label) {
    await page.goto('grid/list');
    const block = page.locator('[id^="rating-grid-t"]', {
        has: page.locator('.panel-title', { hasText: label }),
    });
    const id = await block.first().getAttribute('id');
    return Number(id.replace('rating-grid-t', ''));
}

/**
 * Whether a request type is answered without an account, read on its form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @returns {Promise<boolean>}
 */
async function isLightAnswer(page, typeId) {
    await page.goto(`requesttype/edit?id=${typeId}`);
    return page.locator('#light_answer').isChecked();
}

/**
 * Sets whether a request type is answered without an account, from its form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @param {boolean} lightAnswer
 */
async function setLightAnswer(page, typeId, lightAnswer) {
    await page.goto(`requesttype/edit?id=${typeId}`);
    await page.locator('#light_answer').setChecked(lightAnswer);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByText('The request type has been saved.').first()
    ).toBeVisible();
}

/**
 * Creates the grid of a request type from its block on the grids page, unless it already exists; the page then
 * opens on the grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 */
async function ensureRequestTypeGrid(page, typeId) {
    await page.goto(`grid/list#rating-grid-t${typeId}`);
    const createGrid = page.locator(
        `form[action="/grid/add"]:has(input[name="request_type"][value="${typeId}"]) button`
    );

    if ((await createGrid.count()) > 0) {
        await createGrid.click();
        await expect(page).toHaveURL(new RegExp(`grid/list#rating-grid-t${typeId}$`));
    }
}

/**
 * Removes the grid of a request type, when it exists.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 */
async function removeRequestTypeGrid(page, typeId) {
    await page.goto(`grid/list#rating-grid-t${typeId}`);
    const remove = page.locator(`a.delete[href="/grid/delete?rgid=t${typeId}"]`);

    if ((await remove.count()) === 0) {
        return;
    }

    await remove.click();
    await page.locator('.bootbox-confirm .bootbox-accept').click();
    await expect(remove).toHaveCount(0);
}

/**
 * Opens the invitation form of a paper for a new reviewer (by e-mail), chooses the request type and sends it.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {Object} reviewer
 * @param {string} reviewer.email
 * @param {string} reviewer.lastname
 * @param {number} typeId the type is chosen by its id: a journal may rename it
 */
async function inviteNewReviewerFor(
    page,
    docid,
    { email, lastname },
    typeId
) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);

    await page.locator('#new_user_button').click();
    await page.locator('#email').fill(email);
    await page.locator('#lastname').fill(lastname);
    await page.locator('#next').click();

    await expect(page.locator('#step-2')).toBeVisible();
    await page
        .locator('#invitation-form select[name="request_type_id"]')
        .selectOption(String(typeId));
    await submitInvitation(page, docid);
}

/**
 * A browser context without any session, as an invitee opening the e-mail link.
 * @param {import('@playwright/test').Browser} browser
 * @param {string} baseURL
 * @returns {Promise<import('@playwright/test').BrowserContext>}
 */
async function newLoggedOutContext(browser, baseURL) {
    const context = await browser.newContext({
        baseURL,
        ignoreHTTPSErrors: true,
    });
    await context.addCookies([
        {
            name: 'cookieconsent_status',
            value: 'dismiss',
            domain: new URL(baseURL).hostname,
            path: '/',
        },
    ]);
    return context;
}

test.describe('Review request types (#319)', () => {
    test('the grids page lists each request type with the grid it uses, and its form has no code', async ({
        adminPage,
    }) => {
        const quickOpinion = await requestTypeId(adminPage, QUICK_OPINION);

        await test.step('one page, one block per request type', async () => {
            await expect(adminPage.locator('[role="tablist"]')).toHaveCount(0);
            await expect(
                adminPage.locator('#request-types-heading')
            ).toHaveText('Request types');
            await expect(
                adminPage.locator('[id^="rating-grid-t"] .panel-title', {
                    hasText: REVIEW_REPORT,
                })
            ).toBeVisible();
            const quickBlock = adminPage.locator(
                `#rating-grid-t${quickOpinion}`
            );
            await expect(quickBlock).toContainText(QUICK_OPINION);
            await expect(
                quickBlock.locator(
                    `a[href="/requesttype/edit?id=${quickOpinion}"]`
                )
            ).toBeVisible();
        });

        await test.step('each type says whether its reports are passed on to the author; the review report always is (#319)', async () => {
            await expect(
                adminPage.locator('[id^="rating-grid-t"] .panel-title', {
                    hasText: REVIEW_REPORT,
                })
            ).toContainText('Passed on to the author');

            // the quick opinion follows the setting of the journal: its label matches its box
            await adminPage.goto(`requesttype/edit?id=${quickOpinion}`);
            const visibleToAuthor = adminPage.locator('#visible_to_author');
            await expect(visibleToAuthor).toBeEnabled();
            await expect(visibleToAuthor).toHaveAttribute(
                'aria-describedby',
                'visible_to_author-help'
            );
            const passedOn = await visibleToAuthor.isChecked();

            await adminPage.goto('grid/list');
            await expect(
                adminPage.locator(`#rating-grid-t${quickOpinion} .panel-title`)
            ).toContainText(
                passedOn ? 'Passed on to the author' : 'Editorial team only'
            );
        });

        await test.step('an answer without an account cannot count as a review report', async () => {
            await adminPage.goto(`requesttype/edit?id=${quickOpinion}`);
            await expect(adminPage.locator('#code')).toHaveCount(0);
            const counts = adminPage.locator('#counts_as_report');
            const lightAnswer = adminPage.locator('#light_answer');
            const initiallyChecked = await lightAnswer.isChecked();

            await lightAnswer.check();
            await expect(counts).toBeDisabled();
            await lightAnswer.uncheck();
            await expect(counts).toBeEnabled();

            // nothing is saved: the form is left as it was
            await lightAnswer.setChecked(initiallyChecked);
        });
    });

    test('a person invited for a quick opinion answers without an account by filling in its grid', async ({
        authorPage,
        adminPage,
        mailpit,
        browser,
        baseURL,
    }) => {
        test.setTimeout(240000);
        const stamp = Date.now();
        const invitee = {
            email: `e2e-quick-${stamp}@example.org`,
            lastname: `E2equick${stamp}`,
        };
        const comment = `E2E quick opinion ${stamp}`;
        const quickOpinion = await requestTypeId(adminPage, QUICK_OPINION);
        const gridKey = `t${quickOpinion}`;
        const wasLightAnswer = await isLightAnswer(adminPage, quickOpinion);
        let docid;

        try {
            await test.step('the quick opinion is answered without an account, with a grid of its own', async () => {
                await setLightAnswer(adminPage, quickOpinion, true);

                await ensureRequestTypeGrid(adminPage, quickOpinion);
                await addQuantitativeCriterion(adminPage, gridKey, {
                    title: `E2E interest ${stamp}`,
                    description: 'Is the paper of interest for the journal?',
                    visibility: 'editors',
                });
            });

            await test.step('the editorial team invites a new reviewer for a quick opinion', async () => {
                docid = await submitArxivPaper(authorPage, {
                    arxivId: ARXIV_ID,
                });
                await inviteNewReviewerFor(
                    adminPage,
                    docid,
                    invitee,
                    quickOpinion
                );
                // the request type is shown next to the invitation (in a section that may be folded)
                await expect(
                    adminPage
                        .locator('span.label.label-info', { hasText: QUICK_OPINION })
                        .first()
                ).toBeAttached();
            });

            await test.step('the invitee fills in the grid from the link, without an account', async () => {
                const email = await mailpit.waitForEmail({
                    to: invitee.email,
                    subjectContains: [
                        `#${docid} `,
                        'Request for a quick opinion',
                    ],
                });
                const link = mailpit
                    .extractLinks(email.Text)
                    .find(url => url.includes('/reviewer/invitation/'));
                expect(link).toBeTruthy();
                const url = new URL(link);

                const context = await newLoggedOutContext(browser, baseURL);
                try {
                    const page = await context.newPage();
                    await page.goto(
                        url.pathname.replace(/^\//, '') + url.search
                    );

                    const form = page.locator('form#rating-grid');
                    await expect(form).toBeVisible();
                    await form
                        .locator('select')
                        .first()
                        .selectOption({ index: 4 });
                    await form.locator('textarea').first().fill(comment);
                    await form
                        .getByRole('button', { name: 'Send my answer' })
                        .click();
                    await expect(
                        page
                            .getByText(
                                'Thank you, your answer has been sent to the editorial board.'
                            )
                            .first()
                    ).toBeVisible();
                } finally {
                    await context.close();
                }
            });

            await test.step('the editorial team reads the filled grid', async () => {
                await openPaperManagement(adminPage, docid);
                const answers = adminPage.locator('.panel', {
                    has: adminPage.locator('.panel-title', {
                        hasText: 'Answers given without an account',
                    }),
                });
                await expect(answers).toContainText(comment);
            });
        } finally {
            // the journal settings are left as they were
            await setLightAnswer(adminPage, quickOpinion, wasLightAnswer);
            await removeRequestTypeGrid(adminPage, quickOpinion);
        }
    });
});
