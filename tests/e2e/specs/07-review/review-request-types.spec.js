const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    submitInvitation,
    addQuantitativeCriterion,
} = require('../../helpers/editorial-workflow');
const { flushMailQueue } = require('../../helpers/app-cli');

// papers of their own: specs run in parallel, and each one submits a different paper
const ARXIV_ID = '1704.04861';
const PASSED_ON_ARXIV_ID = '1506.02640';
const QUICK_OPINION = 'Quick opinion';
const REVIEW_REPORT = 'Review report';
const AUTHOR_EMAIL = 'e2e_author@example.org';
const REVIEWER_USERNAME = 'e2e_reviewer';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

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
 * Settings of a request type, read on its form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @returns {Promise<{lightAnswer: boolean, visibleToAuthor: boolean}>}
 */
async function requestTypeSettings(page, typeId) {
    await page.goto(`requesttype/edit?id=${typeId}`);
    return {
        lightAnswer: await page.locator('#light_answer').isChecked(),
        visibleToAuthor: await page.locator('#visible_to_author').isChecked(),
    };
}

/**
 * Saves settings of a request type from its form; the settings not given are left as they are.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @param {Object} settings
 * @param {boolean} [settings.lightAnswer] answered without an account
 * @param {boolean} [settings.visibleToAuthor] its reports are passed on to the author
 */
async function saveRequestTypeSettings(
    page,
    typeId,
    { lightAnswer, visibleToAuthor }
) {
    await page.goto(`requesttype/edit?id=${typeId}`);
    if (lightAnswer !== undefined) {
        await page.locator('#light_answer').setChecked(lightAnswer);
    }
    if (visibleToAuthor !== undefined) {
        await page.locator('#visible_to_author').setChecked(visibleToAuthor);
    }
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByText('The request type has been saved.').first()
    ).toBeVisible();
}

/**
 * Sets whether a request type is answered without an account, from its form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @param {boolean} lightAnswer
 */
async function setLightAnswer(page, typeId, lightAnswer) {
    await saveRequestTypeSettings(page, typeId, { lightAnswer });
}

/**
 * Creates the grid of a request type from its block on the grids page, unless it already exists; the page then
 * opens on the grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @returns {Promise<boolean>} whether the grid was created
 */
async function ensureRequestTypeGrid(page, typeId) {
    await page.goto(`grid/list#rating-grid-t${typeId}`);
    const createGrid = page.locator(
        `form[action="/grid/add"]:has(input[name="request_type"][value="${typeId}"]) button`
    );

    if ((await createGrid.count()) === 0) {
        return false;
    }

    await createGrid.click();
    await expect(page).toHaveURL(new RegExp(`grid/list#rating-grid-t${typeId}$`));
    return true;
}

/**
 * Removes a criterion from the grid of a request type, when it is there.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @param {string} title title of the criterion
 */
async function removeRequestTypeCriterion(page, typeId, title) {
    await page.goto(`grid/list#rating-grid-t${typeId}`);
    const remove = page
        .locator(`#grid_t${typeId} tbody tr`, { hasText: title })
        .locator('a.delete');

    if ((await remove.count()) === 0) {
        return;
    }

    await remove.first().click();
    await page.locator('.bootbox-confirm .bootbox-accept').click();
    await expect(remove).toHaveCount(0);
}

/**
 * Leaves the grid of a request type as a test found it: removed if the test created it, otherwise without the
 * criterion the test added.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} typeId
 * @param {boolean} created whether the test created the grid
 * @param {string} criterionTitle title of the criterion the test added
 */
async function restoreRequestTypeGrid(page, typeId, created, criterionTitle) {
    if (created) {
        await removeRequestTypeGrid(page, typeId);
    } else {
        await removeRequestTypeCriterion(page, typeId, criterionTitle);
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

    // The address is checked against the accounts before the message is shown (git #900)
    await page.locator('#new-person-toggle').click();
    await page.locator('#new-person-email').fill(email);
    await page.locator('#new-person-lastname').fill(lastname);
    await page.locator('#new-person-submit').click();

    await expect(page.locator('#step-2')).toBeVisible();
    await page
        .locator('#invitation-form select[name="request_type_id"]')
        .selectOption(String(typeId));
    await submitInvitation(page, docid);
}

/**
 * Invites a reviewer who has an account for a request type, from the search of the invitation page.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {string} username
 * @param {number} typeId
 */
async function inviteKnownReviewerFor(page, docid, username, typeId) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);

    await page.locator('#reviewer-search-term').fill(username);
    await page
        .locator('#reviewer-search-results tbody tr', { hasText: username })
        .locator('button')
        .click();
    await expect(page.locator('#step-2')).toBeVisible();
    await page
        .locator('#invitation-form select[name="request_type_id"]')
        .selectOption(String(typeId));
    await submitInvitation(page, docid);
}

/**
 * The reviewer accepts the invitation from the e-mail link, then fills in and sends the grid: the highest grade and
 * the same comment for every criterion.
 * @param {import('@playwright/test').Page} reviewerPage
 * @param {string} invitationUrl link of the invitation e-mail
 * @param {number} docid
 * @param {string} comment
 */
async function acceptAndRate(reviewerPage, invitationUrl, docid, comment) {
    // The link targets the configured application URL: only its path is kept
    await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
    await reviewerPage.locator('#submitaccept').click();
    await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));

    const grades = reviewerPage.locator(
        'form#rating-grid select[name^="note_item_"]'
    );
    await expect(grades.first()).toBeVisible();
    for (const grade of await grades.all()) {
        await grade.selectOption({ index: 1 }, { timeout: 10000 });
    }
    // Comment fields are TinyMCE editors, initialised once the page has loaded
    await reviewerPage.waitForFunction(() => {
        const editors = window.tinymce?.get() ?? [];
        return editors.length > 0 && editors.every(editor => editor.initialized);
    });
    await reviewerPage.evaluate(text => {
        window.tinymce.get().forEach(editor => editor.setContent(`<p>${text}</p>`));
    }, comment);
    await reviewerPage.locator('#validateRating').click();
    await expect(reviewerPage).toHaveURL(/paper\/ratings/);
}

/**
 * Opens the refusal form of a paper and returns the message prefilled for the author, where %%PAPER_RATINGS%% has
 * been replaced by the reports passed on to them. The form is left without being sent.
 * @param {import('@playwright/test').Page} page administrator page
 * @param {number} docid
 * @returns {Promise<string>} HTML of the message
 */
async function refusalMessage(page, docid) {
    const panel = await openPaperManagement(page, docid);
    await panel.getByRole('button', { name: 'Change article status' }).click();
    await panel.locator('a[data-target=".refusal-modal"]').click();
    const modal = page.locator('.refusal-modal');
    await expect(modal).toBeVisible();
    // Zend_Form drops the dash of the element name "refusal-message"
    return modal
        .locator('textarea[name="refusalmessage"]')
        .inputValue({ timeout: 10000 });
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
    // The tests change the settings and the grid of the quick opinion: they run one after the other
    test.describe.configure({ mode: 'default' });

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
            // the option applies to the invitations sent afterwards (#319)
            await expect(adminPage.locator('#light_answer-help')).toContainText(
                'the invitations already sent keep their mode of answer'
            );
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
        reviewerPage,
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
        const criterionTitle = `E2E interest ${stamp}`;
        let gridCreated = false;
        let docid;
        let invitationPath;

        try {
            await test.step('the quick opinion is answered without an account, with a grid of its own', async () => {
                await setLightAnswer(adminPage, quickOpinion, true);

                gridCreated = await ensureRequestTypeGrid(adminPage, quickOpinion);
                await addQuantitativeCriterion(adminPage, gridKey, {
                    title: criterionTitle,
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
                // and so is its mode of answer, fixed when it was sent
                await expect(
                    adminPage.locator('#reviewers .light-answer-mode', {
                        hasText: 'Without an account',
                    })
                ).toBeAttached();
            });

            await test.step('the editorial team sends the invitation again, with the same link', async () => {
                const subject = [`#${docid} `, 'Request for a quick opinion'];
                const first = await mailpit.waitForEmail({ to: invitee.email, subjectContains: subject });
                const linkOf = email =>
                    mailpit.extractLinks(email.Text).find(url => url.includes('/reviewer/invitation/'));

                // the pending invitations are folded
                await adminPage.locator('[data-target="#pending-invitations"]').click();
                await adminPage
                    .locator('#pending-invitations .reviewer', { hasText: invitee.lastname })
                    .locator('.popover-link')
                    .click();
                adminPage.once('dialog', dialog => dialog.accept());
                await adminPage.getByRole('link', { name: 'Send the invitation again' }).click();
                await expect(
                    adminPage.getByText('The invitation has been sent again.').first()
                ).toBeVisible();
                await expect(adminPage.locator('#history')).toContainText('Review invitation sent again');
                // its details: who sent it again, to whom
                await adminPage
                    .getByRole('link', { name: 'View details for Review invitation sent again' })
                    .click();
                await expect(adminPage.getByText('Sent again by').first()).toBeVisible();
                await expect(adminPage.getByText(invitee.lastname).last()).toBeVisible();

                // a second e-mail, with the link of the first one
                let messages = [];
                await expect
                    .poll(async () => {
                        // the application queues its e-mails: send them first
                        flushMailQueue();
                        messages = (await mailpit.search(`to:"${invitee.email}"`)).filter(message =>
                            subject.every(fragment => (message.Subject || '').includes(fragment))
                        );
                        return messages.length;
                    }, { timeout: 30000 })
                    .toBe(2);
                const resent = await mailpit.getMessage(messages[0].ID);
                expect(linkOf(resent)).toBe(linkOf(first));
            });

            await test.step('the mode of answer is the one of the request when it was sent', async () => {
                // the invitations already sent keep answering without an account
                await setLightAnswer(adminPage, quickOpinion, false);
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
                invitationPath = url.pathname.replace(/^\//, '') + url.search;

                const context = await newLoggedOutContext(browser, baseURL);
                try {
                    const page = await context.newPage();
                    await page.goto(
                        url.pathname.replace(/^\//, '') + url.search
                    );

                    const form = page.locator('form#rating-grid');
                    await expect(form).toBeVisible();
                    // the journal grid may already have criteria of its own: each one is graded
                    for (const grade of await form.locator('select').all()) {
                        await grade.selectOption({ index: 1 }, { timeout: 10000 });
                    }
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

            await test.step('signed in with another address, the person associates the answer with their account', async () => {
                await reviewerPage.goto(invitationPath);
                await reviewerPage
                    .getByRole('button', { name: 'Associate this answer with my account' })
                    .click();
                await expect(
                    reviewerPage.getByText('This answer is associated with your account').first()
                ).toBeVisible();

                // their reviews list it, and open it without the invitation link
                await reviewerPage.goto('paper/ratings');
                const row = reviewerPage.locator('#light-answers tbody tr', {
                    hasText: String(docid),
                });
                await expect(row).toContainText(QUICK_OPINION);
                await row.getByRole('link', { name: `View my answer on article ${docid}` }).click();
                await expect(reviewerPage).not.toHaveURL(/token/);
                await expect(
                    reviewerPage.getByText('This answer is associated with your account').first()
                ).toBeVisible();

                // the editorial team sees the account, the answer stays an answer without an account
                await openPaperManagement(adminPage, docid);
                await expect(
                    adminPage.locator('.light-answer-linked-account').first()
                ).toContainText('associated with the account');
            });
        } finally {
            // the journal settings are left as they were
            await setLightAnswer(adminPage, quickOpinion, wasLightAnswer);
            await restoreRequestTypeGrid(
                adminPage,
                quickOpinion,
                gridCreated,
                criterionTitle
            );
        }
    });

    test('a quick opinion reaches the author only when its type passes its reports on', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const stamp = Date.now();
        const comment = `E2E quick opinion with an account ${stamp}`;
        const quickOpinion = await requestTypeId(adminPage, QUICK_OPINION);
        const initialSettings = await requestTypeSettings(adminPage, quickOpinion);
        const criterionTitle = `E2E relevance ${stamp}`;
        let gridCreated = false;
        let docid;

        try {
            await test.step('the quick opinion is answered with an account, on a grid the author may see', async () => {
                await saveRequestTypeSettings(adminPage, quickOpinion, {
                    lightAnswer: false,
                    visibleToAuthor: true,
                });
                gridCreated = await ensureRequestTypeGrid(adminPage, quickOpinion);
                await addQuantitativeCriterion(adminPage, `t${quickOpinion}`, {
                    title: criterionTitle,
                    description: 'Is the paper relevant to the journal?',
                    visibility: 'contributor',
                });
            });

            await test.step('the reviewer gives a quick opinion with their account', async () => {
                docid = await submitArxivPaper(authorPage, {
                    arxivId: PASSED_ON_ARXIV_ID,
                });
                await inviteKnownReviewerFor(
                    adminPage,
                    docid,
                    REVIEWER_USERNAME,
                    quickOpinion
                );
                // a request answered with an account has no mention of the mode
                await expect(adminPage.locator('#reviewers .light-answer-mode')).toHaveCount(0);
                const email = await mailpit.waitForEmail({
                    to: REVIEWER_EMAIL,
                    subjectContains: [`#${docid} `, 'Request for a quick opinion'],
                });
                const link = mailpit
                    .extractLinks(email.Text)
                    .find(url => url.includes('/reviewer/invitation/'));
                expect(link).toBeTruthy();
                await acceptAndRate(reviewerPage, link, docid, comment);
            });

            await test.step('passed on: the decision e-mail gives the opinion, with the request type', async () => {
                const message = await refusalMessage(adminPage, docid);
                // the journal template must keep %%PAPER_RATINGS%%, as the default one does
                expect(message).toContain(comment);
                expect(message).toContain(`(${QUICK_OPINION})`);
            });

            await test.step('not passed on: the opinion is left out of the decision e-mail', async () => {
                await saveRequestTypeSettings(adminPage, quickOpinion, {
                    visibleToAuthor: false,
                });
                expect(await refusalMessage(adminPage, docid)).not.toContain(comment);

                await applyDecision(adminPage, docid, 'refusal-modal');
                await expect(statusPanel(adminPage)).toContainText('Rejected');
                const email = await mailpit.waitForEmail({
                    to: AUTHOR_EMAIL,
                    subjectContains: [`#${docid} `, 'has been rejected'],
                });
                expect(email.HTML).not.toContain(comment);
                expect(email.Text).not.toContain(comment);
            });

            await test.step('not passed on: the author does not see the opinion on the paper page', async () => {
                await authorPage.goto(`paper/view/id/${docid}`);
                await expect(statusPanel(authorPage)).toContainText('Rejected');
                await expect(authorPage.locator('body')).not.toContainText(comment);
            });

            await test.step('passed on again: the author sees the opinion, labelled with its type', async () => {
                await saveRequestTypeSettings(adminPage, quickOpinion, {
                    visibleToAuthor: true,
                });
                await authorPage.goto(`paper/view/id/${docid}`);
                // the reviews panel; the list of reports inside it has the same id
                const ratings = authorPage.locator('.panel#ratings');
                await expect(ratings).toContainText(comment);
                await expect(ratings).toContainText(QUICK_OPINION);
            });
        } finally {
            // the journal settings are left as they were
            await saveRequestTypeSettings(adminPage, quickOpinion, initialSettings);
            await restoreRequestTypeGrid(
                adminPage,
                quickOpinion,
                gridCreated,
                criterionTitle
            );
        }
    });
});
