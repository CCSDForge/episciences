const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { E2E_PASSWORD } = require('../../helpers/login');
const { runPhp } = require('../../helpers/app-cli');
const {
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    completeReview,
    inviteKnownReviewer,
    inviteNewReviewer,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
    assignMasterVolume,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const SESSION_EXPIRED = 'Your session has expired or the page has been open for too long';
const NOT_LINKED = 'This invitation is not linked to the current account';

/**
 * Replaces the request token of a form, as an expired session would make it: the server refuses it.
 * @param {import('@playwright/test').Locator} form
 */
async function expireRequestToken(form) {
    await form.locator('input[name="csrf_token"]').evaluate(input => {
        input.value = 'expired';
    });
}

/**
 * Returns the path of the invitation link received at this address for this paper (with its link token).
 * @param {import('../../helpers/mailpit-client').MailpitClient} mailpit
 * @param {number} docid
 * @param {string} to
 * @returns {Promise<string>} path relative to the base URL
 */
async function receivedInvitationPath(mailpit, docid, to) {
    const email = await mailpit.waitForEmail({
        to,
        subjectContains: [`#${docid} `, 'You are invited to review an article'],
    });
    const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
    expect(invitationUrl).toBeTruthy();

    // The link targets the configured application URL: only its path is kept (the link token is a path segment)
    const url = new URL(invitationUrl);
    return url.pathname.replace(/^\//, '') + url.search;
}

/**
 * Invites the E2E reviewer and returns the path of the invitation link (with its link token).
 * @param {Object} fixtures
 * @param {import('@playwright/test').Page} fixtures.adminPage
 * @param {import('../../helpers/mailpit-client').MailpitClient} fixtures.mailpit
 * @param {number} docid
 * @returns {Promise<string>} path relative to the base URL
 */
async function inviteReviewer({ adminPage, mailpit }, docid) {
    await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);
    return receivedInvitationPath(mailpit, docid, REVIEWER_EMAIL);
}

/**
 * A browser context without any session, as an invitee opening the e-mail link.
 * @param {import('@playwright/test').Browser} browser
 * @param {string} baseURL
 * @returns {Promise<import('@playwright/test').BrowserContext>}
 */
async function newLoggedOutContext(browser, baseURL) {
    const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
    await context.addCookies([
        { name: 'cookieconsent_status', value: 'dismiss', domain: new URL(baseURL).hostname, path: '/' },
    ]);
    return context;
}

/**
 * Fills the login form the invitee was sent to.
 * @param {import('@playwright/test').Page} page
 * @param {string} username
 */
async function signIn(page, username) {
    const loginForm = page.locator('form#loginform, form#credential');
    await loginForm.locator('input#username, input[name="username"]').fill(username);
    await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
    await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();
}

/**
 * Sends the lost password form displayed on the page.
 * @param {import('@playwright/test').Page} page
 * @param {string} username
 */
async function requestNewPassword(page, username) {
    const form = page.locator('form#accountlostpassword');
    await form.locator('input#USERNAME').fill(username);
    // Environment setting: the Altcha widget is only displayed when it is the configured CAPTCHA
    if (await form.locator('altcha-widget').count()) {
        // It solves its proof of work once checked (its box is covered by its tick mark)
        await form.locator('altcha-widget label', { hasText: "I'm not a robot" }).click();
        await expect(form.locator('altcha-widget [data-state="verified"]')).toBeAttached({ timeout: 30000 });
    }
    await form.locator('button[type="submit"]').click();
}

/**
 * Creates an account as another application sharing the accounts would: no Episciences profile.
 * @param {string} username
 * @param {string} email
 * @param {string} password
 * @param {Object} [options]
 * @param {boolean} [options.active=false] activated, or not activated yet
 */
function createExternalAccount(username, email, password, { active = false } = {}) {
    runPhp(
        `scripts/console.php app:e2e-external-account --username=${username} --email=${email} --password=${password}${active ? '' : ' --inactive'}`
    );
}

test.describe('Reviewer invitation answer', () => {
    test('only the answer form accepts an invitation, including after logging in', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1411.4038' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E Invitation answer volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        await test.step('a GET request with the former acceptance parameter only displays the invitation', async () => {
            for (const path of [`${invitationPath}/is-accepted/1`, `${invitationPath}?is-accepted=1`]) {
                await reviewerPage.goto(path);
                await expect(reviewerPage).toHaveURL(/reviewer\/invitation/);
                await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
            }
        });

        await test.step('an acceptance posted without the form token is not stored', async () => {
            const response = await reviewerPage.request.post(invitationPath, {
                form: { submitaccept: '' },
                maxRedirects: 0,
            });
            // A stored acceptance redirects to the rating page; a rejected one displays the invitation again
            expect(response.status()).toBe(200);

            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
        });

        await test.step('an acceptance posted together with a refusal is not stored', async () => {
            await reviewerPage.goto(invitationPath);
            const acceptForm = reviewerPage.locator('form', { has: reviewerPage.locator('#submitaccept') });
            const csrfToken = await acceptForm.locator('input[name="csrf_token"]').inputValue();

            const response = await reviewerPage.request.post(invitationPath, {
                form: { submitaccept: '', submitrefuse: '', csrf_token: csrfToken },
                maxRedirects: 0,
            });
            // Neither answer is stored: no redirection, the invitation can still be answered
            expect(response.status()).toBe(200);

            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
        });

        await test.step('a reviewer who accepts while logged out confirms once logged in', async () => {
            const context = await newLoggedOutContext(browser, baseURL);
            const page = await context.newPage();
            try {
                await page.goto(invitationPath);
                // Before answering, the invitee is told that accepting needs signing in, declining does not
                await expect(page.getByText('To accept, you will need to sign in with your account.')).toBeVisible();
                await expect(page.getByText('Declining does not require an account.')).toBeVisible();
                await expect(page.getByRole('link', { name: 'Sign in now' })).toHaveAttribute('href', /user\/login/);

                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(/user\/login/);

                await signIn(page, E2E_USERNAMES.reviewer);

                // Back on the invitation: logging in alone does not accept it
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toBeVisible();

                // The invitee changes their mind: an invalid refusal (reason too long) reopens the refusal form,
                // without asking to confirm the acceptance
                await page.locator('#refuse-button').click();
                // The browser stops at the limit: a reason too long can only come from a forged request
                const comment = page.locator('#refuse_form textarea[name="comment"]');
                await comment.evaluate(textarea => textarea.removeAttribute('maxlength'));
                await comment.fill('x'.repeat(5001));
                await page.locator('#refuse_form [name="submitrefuse"]').click();
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.locator('#refuse_form')).toBeVisible();
                await expect(comment).toHaveAttribute('aria-invalid', 'true');
                await expect(comment).toBeFocused();
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toHaveCount(0);

                // Back to the acceptance, the focus on the button that opened the refusal form
                await page.locator('#refuse_form [name="cancelaccept"]').click();
                await expect(page.locator('#refuse-button')).toBeFocused();
                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            } finally {
                await context.close();
            }
        });
    });

    test('a reviewer declines an invitation with the refusal form', async ({ authorPage, adminPage, reviewerPage, mailpit }) => {
        test.setTimeout(180000);
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1506.01497' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        await test.step('a refusal sent with an expired session keeps what was typed', async () => {
            await reviewerPage.goto(invitationPath);
            await reviewerPage.locator('#refuse-button').click();
            // The refusal form replaces the answer buttons and takes the focus
            await expect(reviewerPage.locator('#refuse-form-title')).toBeFocused();
            await expect(reviewerPage.locator('#refuse-button')).toBeHidden();
            const refuseForm = reviewerPage.locator('#refuse_form');
            await refuseForm.locator('[name="suggestreviewer"]').fill('Jane Doe');
            await refuseForm.locator('textarea[name="comment"]').fill('E2E: not my field.');
            await expireRequestToken(refuseForm);
            await refuseForm.locator('[name="submitrefuse"]').click();

            // Nothing is stored: the refusal form is open again, as it was typed
            await expect(reviewerPage).toHaveURL(/reviewer\/invitation/);
            await expect(reviewerPage.getByText(SESSION_EXPIRED).first()).toBeVisible();
            await expect(refuseForm).toBeVisible();
            await expect(refuseForm.locator('[name="suggestreviewer"]')).toHaveValue('Jane Doe');
            await expect(refuseForm.locator('textarea[name="comment"]')).toHaveValue('E2E: not my field.');
        });

        await test.step('the reviewer declines, sending the same refusal again', async () => {
            // The form displayed again carries the current token: sending it as it is stores the refusal
            await reviewerPage.locator('#refuse_form [name="submitrefuse"]').click();

            // A stored refusal leaves the invitation page for the home page
            await expect(reviewerPage).toHaveURL(url => !url.pathname.includes('reviewer/invitation'));
            await expect(reviewerPage.getByText('Your answer has been saved.').first()).toBeVisible();
        });

        await test.step('the invitation can no longer be answered', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText("You've already answered this invitation.")).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
        });
    });

    test('an accepted invitation can no longer be answered, whoever opens it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        editorPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let invitationPath;

        await test.step('the reviewer accepts the invitation', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1703.06870' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E Answered invitation volume ${Date.now()}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);

            await reviewerPage.goto(invitationPath);
            await reviewerPage.locator('#submitaccept').click();
            await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
        });

        await test.step('the reviewer is told they already answered, with the answer', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText("You've already answered this invitation.")).toBeVisible();
            await expect(reviewerPage.getByText('Invitation accepted on')).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
            await expect(reviewerPage.locator('#refuse-button')).toHaveCount(0);
        });

        await test.step('another account is only told that a reply was sent', async () => {
            await editorPage.goto(invitationPath);
            await expect(editorPage.getByText('It appears that a reply has already been sent.')).toBeVisible();
            await expect(editorPage.locator('#submitaccept')).toHaveCount(0);
            // Nothing to link to another account once answered
            await expect(editorPage.locator('#linkedInvitationForm')).toHaveCount(0);
        });

        await test.step('a visitor who is not signed in is only told that a reply was sent', async () => {
            const context = await newLoggedOutContext(browser, baseURL);
            const page = await context.newPage();
            try {
                await page.goto(invitationPath);
                await expect(page.getByText('It appears that a reply has already been sent.')).toBeVisible();
                await expect(page.locator('#submitaccept')).toHaveCount(0);
                await expect(page.locator('#refuse-button')).toHaveCount(0);
            } finally {
                await context.close();
            }
        });
    });

    test('a cancelled invitation can no longer be answered', async ({ authorPage, adminPage, reviewerPage, mailpit }) => {
        test.setTimeout(180000);
        let docid;
        let invitationPath;
        let csrfToken;

        await test.step('the editorial team invites a reviewer', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1606.06565' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);

            // The request token of the session, as the reviewer had the answer form open before the cancellation
            await reviewerPage.goto(invitationPath);
            const acceptForm = reviewerPage.locator('form', { has: reviewerPage.locator('#submitaccept') });
            csrfToken = await acceptForm.locator('input[name="csrf_token"]').inputValue();
        });

        await test.step('the editorial team removes the reviewer before any answer', async () => {
            await openPaperManagement(adminPage, docid);
            // The pending invitations are collapsed
            await adminPage.locator('[data-target="#pending-invitations"]').click();
            const pending = adminPage.locator('#pending-invitations');
            await expect(pending).toBeVisible();
            await pending.locator('.reviewer', { hasText: /E2E Reviewer/i }).locator('.popover-link').click();
            await adminPage.locator('.popover .context-menu a', { hasText: 'Remove this reviewer' }).click();

            const removed = adminPage.waitForResponse(response => response.url().includes('/administratepaper/savereviewerremoval'));
            await adminPage.locator('#submit-modal').click();
            expect((await removed).ok()).toBeTruthy();
            await expect(adminPage.locator('#pending-invitations .reviewer', { hasText: /E2E Reviewer/i })).toHaveCount(0);
        });

        await test.step('the reviewer is told the invitation was cancelled, and cannot answer it', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(
                reviewerPage.getByText("This invitation has been cancelled, you don't need to review this article anymore.")
            ).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
            await expect(reviewerPage.locator('#refuse-button')).toHaveCount(0);
        });

        await test.step('an acceptance posted from the form opened before is not stored', async () => {
            const response = await reviewerPage.request.post(invitationPath, {
                form: { submitaccept: '', csrf_token: csrfToken },
                maxRedirects: 0,
            });
            // A stored acceptance redirects to the rating page
            expect(response.status()).toBe(200);

            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText('This invitation has been cancelled')).toBeVisible();
        });
    });

    test('an expired invitation can no longer be answered', async ({ authorPage, adminPage, reviewerPage, mailpit }) => {
        test.setTimeout(180000);
        let invitationPath;
        let csrfToken;

        await test.step('the editorial team invites a reviewer, who opens the invitation', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1608.08710' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);

            await reviewerPage.goto(invitationPath);
            const acceptForm = reviewerPage.locator('form', { has: reviewerPage.locator('#submitaccept') });
            csrfToken = await acceptForm.locator('input[name="csrf_token"]').inputValue();
        });

        // No user action makes an invitation expire before its deadline
        const invitationId = invitationPath.match(/\/id\/(\d+)/)[1];
        runPhp(`scripts/console.php app:e2e-expire-invitation --id=${invitationId}`);

        await test.step('the reviewer is told the invitation expired, and cannot answer it', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText('This invitation has expired.')).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
            await expect(reviewerPage.locator('#refuse-button')).toHaveCount(0);
        });

        await test.step('an acceptance posted from the form opened before is not stored', async () => {
            const response = await reviewerPage.request.post(invitationPath, {
                form: { submitaccept: '', csrf_token: csrfToken },
                maxRedirects: 0,
            });
            // A stored acceptance redirects to the rating page
            expect(response.status()).toBe(200);

            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText('This invitation has expired.')).toBeVisible();
        });
    });

    test('an invitation to review a rejected paper can no longer be answered', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer, then rejects the paper', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1603.05027' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
            await applyDecision(adminPage, docid, 'refusal-modal');
            await expect(statusPanel(adminPage)).toContainText('Rejected');
        });

        await test.step('the reviewer is told the paper no longer needs a review, and cannot answer', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(
                reviewerPage.getByText('Thank you but this article has been rejected, it is useless to review it now.')
            ).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
            await expect(reviewerPage.locator('#refuse-button')).toHaveCount(0);
        });
    });

    test('an invitation to review a paper under revision can no longer be answered', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const invitedEmail = `e2e-revision-reviewer-${suffix}@example.org`;
        let invitationPath;

        await test.step('a second reviewer is invited, then the paper is sent back for revision', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1605.07146' });
            // The journal requires a completed review before a revision request
            await completeReview({ adminPage, reviewerPage, mailpit }, docid);
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname: `Revisionreviewer${suffix}` });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
            await applyDecision(adminPage, docid, 'minor-revision-modal');
        });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('the invitee is told the paper no longer needs a review, and cannot answer', async () => {
                await page.goto(invitationPath);
                await expect(
                    page.getByText('Thank you but this article is under revision, it is useless to review it now.')
                ).toBeVisible();
                await expect(page.locator('#accept-button')).toHaveCount(0);
                await expect(page.locator('#refuse-button')).toHaveCount(0);
            });
        } finally {
            await context.close();
        }
    });

    test('a reviewer without an account is offered to create one, and declines without it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        // No account uses this address: accepting would create one
        const suffix = Date.now();
        const invitedEmail = `e2e-new-reviewer-${suffix}@example.org`;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '2311.14261' });
            await inviteNewReviewer(adminPage, docid, {
                email: invitedEmail,
                lastname: `Newreviewer${suffix}`,
            });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('before answering, the invitee is told that accepting creates an account', async () => {
                await page.goto(invitationPath);
                await expect(page.getByText('No account yet? Click "I agree to review this article"')).toBeVisible();
                await expect(page.getByText('Declining does not require an account.')).toBeVisible();
                await expect(page.locator('.invitation-answer-account a', { hasText: 'Sign in' })).toHaveAttribute(
                    'href',
                    /user\/login/
                );
            });

            await test.step('the account form uses the invited address, and can be closed', async () => {
                const acceptButton = page.locator('#accept-button');
                await acceptButton.click();
                const accountForm = page.locator('#accept_form');
                await expect(accountForm).toBeVisible();
                await expect(acceptButton).toBeHidden();
                await expect(accountForm.locator('#EMAIL')).toHaveValue(invitedEmail);
                await expect(accountForm.locator('#EMAIL')).toHaveAttribute('readonly', /.*/);

                // the button the page script closes the form with (its name loses its hyphen in Zend_Form)
                await accountForm.locator('[data-answer-cancel]').click();
                await expect(accountForm).toBeHidden();
                await expect(acceptButton).toBeFocused();
            });

            await test.step('the invitee declines without creating an account', async () => {
                await page.locator('#refuse-button').click();
                await page.locator('#refuse_form textarea[name="comment"]').fill('E2E: no time for it.');
                await page.locator('#refuse_form [name="submitrefuse"]').click();

                await expect(page).toHaveURL(url => !url.pathname.includes('reviewer/invitation'));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();

                await page.goto(invitationPath);
                await expect(page.getByText('It appears that a reply has already been sent.')).toBeVisible();
                await expect(page.locator('#accept-button')).toHaveCount(0);
            });
        } finally {
            await context.close();
        }
    });

    test('a reviewer without an account creates one from the invitation, and accepts with it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        // No account uses this address: accepting creates one
        const suffix = Date.now();
        const invitedEmail = `e2e-created-reviewer-${suffix}@example.org`;
        const username = `e2e_created_${suffix}`;
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1602.07261' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume; the
            // review form is only displayed once the grid has a criterion
            const vid = await createVolume(adminPage, `E2E Created account volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await addQuantitativeCriterion(adminPage, vid, {
                title: `E2E soundness ${suffix}`,
                description: 'Is the paper sound?',
            });
            await assignMasterVolume(adminPage, docid, vid);
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname: `Createdreviewer${suffix}` });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('the invitee creates the account and accepts in one go', async () => {
                await page.goto(invitationPath);
                await page.locator('#accept-button').click();

                const accountForm = page.locator('#accept_form');
                await expect(accountForm).toBeVisible();
                await accountForm.locator('#USERNAME').fill(username);
                await accountForm.locator('#PASSWORD').fill(E2E_PASSWORD);
                await accountForm.locator('#PASSWORD_VERIFY').fill(E2E_PASSWORD);
                await accountForm.locator('#FIRSTNAME').fill('E2E');
                await accountForm.locator('#LASTNAME').fill(`Createdreviewer${suffix}`);
                await accountForm.locator('[name="submitaccept"]').click();

                // The new account is signed in: the rating page of the paper is displayed
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
                await expect(page.locator('form#rating-grid')).toBeVisible();
            });

            await test.step('the reviewer signs in again with the chosen password and reviews the paper', async () => {
                await page.goto('user/logout');
                await page.goto(`paper/rating/id/${docid}`);

                // The rating page displays the login form to a visitor who is not signed in
                await signIn(page, username);
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.locator('form#rating-grid')).toBeVisible();
            });

            await test.step('the invitation can no longer be answered', async () => {
                await page.goto(invitationPath);
                // Sent to the temporary account, it was accepted with the account created from it: the invitee's
                await expect(page.getByText("You've already answered this invitation.")).toBeVisible();
                await expect(page.getByText('Invitation accepted on')).toBeVisible();
                await expect(page.locator('#accept-button')).toHaveCount(0);
            });
        } finally {
            await context.close();
        }
    });

    test('an invitee whose address has an active account signs in with it to accept', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const invitedEmail = `e2e-active-reviewer-${suffix}@example.org`;
        const username = `e2e_active_${suffix}`;
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1511.06434' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E Active account volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname: `Activereviewer${suffix}` });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        // Created after the invitation, in another application: active, without any Episciences profile
        createExternalAccount(username, invitedEmail, E2E_PASSWORD, { active: true });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('the invitee is told to sign in with that account, not to create one', async () => {
                await page.goto(invitationPath);
                const notice = page.locator('.invitation-answer-account');
                await expect(notice).toContainText('An account already exists for the address this invitation was sent to.');
                await expect(notice).toContainText('Declining does not require an account.');
                await expect(page.locator('#accept_form')).toHaveCount(0);
                // Both the notice and the answer button go to the login page, then back to confirm the acceptance
                await expect(notice.getByRole('link', { name: 'sign in' })).toHaveAttribute('href', /user\/login/);
                await expect(page.locator('#accept-button')).toHaveAttribute('href', /user\/login/);
            });

            await test.step('signing in from the notice brings the invitee back to confirm the acceptance', async () => {
                await page.locator('.invitation-answer-account').getByRole('link', { name: 'sign in' }).click();
                await expect(page).toHaveURL(/user\/login/);
                await signIn(page, username);

                // The account has no Episciences profile yet: it is completed first, then the invitation is back
                await expect(page).toHaveURL(/user\/edit/);
                await page.getByRole('button', { name: 'Save changes' }).click();
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toBeVisible();
                await expect(page.locator('.invitation-answer-account')).toHaveCount(0);

                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            });
        } finally {
            await context.close();
        }
    });

    test('an invitee who forgot the password of their account resets it, then accepts with it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const invitedEmail = `e2e-forgetful-reviewer-${suffix}@example.org`;
        const username = `e2e_forgetful_${suffix}`;
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1611.07004' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E Forgotten password volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname: `Forgetfulreviewer${suffix}` });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        // An active account uses the invited address, with a password the invitee no longer remembers
        createExternalAccount(username, invitedEmail, `Forgotten-${suffix}`, { active: true });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('from the login page of the invitation, the invitee asks for a new password', async () => {
                await page.goto(invitationPath);
                await page.locator('#accept-button').click();
                await expect(page).toHaveURL(/user\/login/);

                await page.locator('#form-login .dropdown-toggle').click();
                await page.locator('#form-login').getByRole('link', { name: /forgot.*password/i }).click();
                await expect(page).toHaveURL(/user\/lostpassword/);
                await requestNewPassword(page, username);
                // The same answer whether or not an account matches the username
                await expect(page.getByText('If an account matches this username, an e-mail has just been sent')).toBeVisible();
            });

            await test.step('the invitee chooses a new password from the e-mail', async () => {
                const email = await mailpit.waitForEmail({ to: invitedEmail, subjectContains: ['Password reset request'] });
                const resetUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/user/resetpassword/'));
                expect(resetUrl).toBeTruthy();

                const url = new URL(resetUrl);
                await page.goto(url.pathname.replace(/^\//, '') + url.search);
                await page.locator('input[name="PASSWORD"]').fill(E2E_PASSWORD);
                await page.locator('input[name="PASSWORD_VERIFY"]').fill(E2E_PASSWORD);
                await page.locator('form:has(input[name="PASSWORD_VERIFY"]) [type="submit"]').click();
                await expect(page.locator('.alert-success').first()).toBeVisible();
            });

            await test.step('back to the invitation, the invitee signs in with the new password and accepts', async () => {
                await page.goto(invitationPath);
                await page.locator('#accept-button').click();
                await expect(page).toHaveURL(/user\/login/);
                await signIn(page, username);

                // The account has no Episciences profile yet: it is completed first, then the invitation is back
                await expect(page).toHaveURL(/user\/edit/);
                await page.getByRole('button', { name: 'Save changes' }).click();
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toBeVisible();

                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            });
        } finally {
            await context.close();
        }
    });

    test('an invitee whose address has an account of another application activates it, then accepts with it', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const invitedEmail = `e2e-external-reviewer-${suffix}@example.org`;
        const username = `e2e_external_${suffix}`;
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1312.5602' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E External account volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            await inviteNewReviewer(adminPage, docid, {
                email: invitedEmail,
                firstname: 'E2E',
                lastname: `Externalreviewer${suffix}`,
            });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        // Created after the invitation, in another application, maybe by someone else: not activated, no Episciences
        // profile, with a password the invitee does not know
        createExternalAccount(username, invitedEmail, `Someone-else-${suffix}`);

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('the invitee asks for a new activation link from the invitation', async () => {
                await page.goto(invitationPath);
                await expect(page.getByText('but it is not activated yet')).toBeVisible();
                await expect(page.locator('#accept-button')).toHaveCount(0);

                await page.getByRole('button', { name: 'Send me a new activation link' }).click();
                // The toast and its screen reader announcer both carry the message
                await expect(page.getByText(`An activation link has been sent to ${invitedEmail}`).first()).toBeVisible();

                // The page was displayed again with a GET: refreshing it does not ask for another link
                await page.reload();
                await expect(page.getByText('An activation link has been sent')).toHaveCount(0);
                await expect(page.getByText('An activation link was already sent')).toHaveCount(0);
            });

            await test.step('the invitee activates the account by choosing its password', async () => {
                const email = await mailpit.waitForEmail({ to: invitedEmail, subjectContains: ['Registration validation'] });
                const activationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/user/resetpassword/'));
                expect(activationUrl).toBeTruthy();

                const url = new URL(activationUrl);
                await page.goto(url.pathname.replace(/^\//, '') + url.search);
                await expect(page.getByRole('heading', { name: 'Account activation' })).toBeVisible();
                await page.locator('input[name="PASSWORD"]').fill(E2E_PASSWORD);
                await page.locator('input[name="PASSWORD_VERIFY"]').fill(E2E_PASSWORD);
                await page.getByRole('button', { name: 'Activate my account' }).click();
                await expect(page.getByText('Your account is activated: you can sign in with this password.')).toBeVisible();
            });

            await test.step('accepting goes through signing in, then back to the invitation to confirm', async () => {
                await page.goto(invitationPath);
                await expect(page.getByText('An account already exists for the address this invitation was sent to.')).toBeVisible();

                await page.locator('#accept-button').click();
                await expect(page).toHaveURL(/user\/login/);
                await signIn(page, username);

                // The account has no Episciences profile yet: it is completed first, then the invitation is back
                await expect(page).toHaveURL(/user\/edit/);
                await page.getByRole('button', { name: 'Save changes' }).click();
                await expect(page).toHaveURL(/reviewer\/invitation/);
                await expect(page.getByText('You are now signed in: please confirm your acceptance below.')).toBeVisible();

                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            });
        } finally {
            await context.close();
        }
    });

    test('an invitation opened with an account using the invited address is linked to it without confirmation', async ({
        browser,
        baseURL,
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        const suffix = Date.now();
        const invitedEmail = `e2e-same-address-${suffix}@example.org`;
        const username = `e2e_same_address_${suffix}`;
        let docid;
        let invitationPath;

        await test.step('the editorial team invites a reviewer by e-mail address', async () => {
            docid = await submitArxivPaper(authorPage, { arxivId: '1512.00567' });
            // Accepting an invitation creates the rating report from the grid of the paper's volume
            const vid = await createVolume(adminPage, `E2E Same address volume ${suffix}`);
            await createVolumeGrid(adminPage, vid);
            await assignMasterVolume(adminPage, docid, vid);
            await inviteNewReviewer(adminPage, docid, { email: invitedEmail, lastname: `Sameaddress${suffix}` });
            invitationPath = await receivedInvitationPath(mailpit, docid, invitedEmail);
        });

        // An account using the invited address, another account than the temporary one the invitation was sent to
        createExternalAccount(username, invitedEmail, E2E_PASSWORD, { active: true });

        const context = await newLoggedOutContext(browser, baseURL);
        const page = await context.newPage();
        try {
            await test.step('the invitee signs in with that account before opening the invitation', async () => {
                await page.goto('user/login');
                await signIn(page, username);
                // The account has no Episciences profile yet: it is completed first
                await expect(page).toHaveURL(/user\/edit/);
                await page.getByRole('button', { name: 'Save changes' }).click();
                await expect(page).not.toHaveURL(/user\/edit/);
            });

            await test.step('the invitation is linked to the account at once: it can be answered', async () => {
                await page.goto(invitationPath);
                await expect(page.getByText(NOT_LINKED)).toHaveCount(0);
                await expect(page.locator('#linkedInvitationForm')).toHaveCount(0);
                await expect(page.locator('#submitaccept')).toBeVisible();
            });

            await test.step('the paper history records an automatic link to the account', async () => {
                await openPaperManagement(adminPage, docid);
                await adminPage.locator('#history .log-entry.reviewer_invitation_linked').click();
                const details = adminPage.locator('#modal-box .log-details');
                await expect(details).toContainText(invitedEmail);
                await expect(details).toContainText('reviewer without an account');
                await expect(details.locator('tr', { hasText: 'Automatic linking (same e-mail address)' })).toContainText('Yes');
            });

            await test.step('the invitee accepts with the account', async () => {
                await page.locator('#submitaccept').click();
                await expect(page).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
                await expect(page.getByText('Your answer has been saved.').first()).toBeVisible();
            });
        } finally {
            await context.close();
        }
    });

    test('an invitation opened with another account stays with the invitee when the link is declined', async ({
        authorPage,
        adminPage,
        editorPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            const docid = await submitArxivPaper(authorPage, { arxivId: '1502.01852' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        const linkForm = editorPage.locator('#linkedInvitationForm');

        await test.step('another account opening the link declines to link the invitation', async () => {
            await editorPage.goto(invitationPath);
            await expect(editorPage.getByText(NOT_LINKED)).toBeVisible();
            await linkForm.locator('button[value="declineToLink"]').click();

            // Back to the papers the account reviews, the invitation not linked
            await expect(editorPage).toHaveURL(/paper\/ratings/);
        });

        await test.step('the invited account can still answer the invitation', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText('it has already been used by another account')).toHaveCount(0);
            await expect(reviewerPage.locator('#submitaccept')).toBeVisible();
        });

        await test.step('the other account opening the link again is asked again, nothing linked', async () => {
            await editorPage.goto(invitationPath);
            await expect(editorPage.getByText(NOT_LINKED)).toBeVisible();
            await expect(linkForm).toBeVisible();
            await expect(editorPage.locator('#submitaccept')).toHaveCount(0);
        });
    });

    test('an invitation opened with another account is only linked to it once confirmed', async ({
        authorPage,
        adminPage,
        editorPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        let invitationPath;

        await test.step('the editorial team invites a reviewer', async () => {
            // a preprint can be submitted once only: each spec uses its own (1512.03385 is reviewer-workflow's)
            const docid = await submitArxivPaper(authorPage, { arxivId: '1409.1556' });
            invitationPath = await inviteReviewer({ adminPage, mailpit }, docid);
        });

        const linkForm = editorPage.locator('#linkedInvitationForm');

        await test.step('another account opening the link is asked to link the invitation first', async () => {
            await editorPage.goto(invitationPath);
            await expect(editorPage.getByText(NOT_LINKED)).toBeVisible();
            await expect(linkForm).toBeVisible();
            await expect(editorPage.locator('#submitaccept')).toHaveCount(0);
        });

        await test.step('a link confirmed with an expired session is not stored', async () => {
            await expireRequestToken(linkForm);
            await linkForm.locator('button[value="acceptToLink"]').click();

            await expect(editorPage.getByText(SESSION_EXPIRED).first()).toBeVisible();
            await expect(editorPage.getByText(NOT_LINKED)).toBeVisible();
            await expect(linkForm).toBeVisible();
        });

        await test.step('once the link is confirmed, the invitation can be answered with this account', async () => {
            await linkForm.locator('button[value="acceptToLink"]').click();

            await expect(linkForm).toHaveCount(0);
            await expect(editorPage.locator('#submitaccept')).toBeVisible();
        });

        await test.step('the invited account can no longer use the invitation', async () => {
            await reviewerPage.goto(invitationPath);
            await expect(reviewerPage.getByText('it has already been used by another account')).toBeVisible();
            await expect(reviewerPage.locator('#submitaccept')).toHaveCount(0);
        });
    });
});
