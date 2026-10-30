const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { E2E_PASSWORD } = require('../../helpers/login');
const {
    submitArxivPaper,
    inviteKnownReviewer,
    inviteNewReviewer,
    createVolume,
    createVolumeGrid,
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

                const loginForm = page.locator('form#loginform, form#credential');
                await loginForm.locator('input#username, input[name="username"]').fill(E2E_USERNAMES.reviewer);
                await loginForm.locator('input#password, input[name="password"]').fill(E2E_PASSWORD);
                await loginForm.locator('button[type="submit"], input[type="submit"]').first().click();

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

                await accountForm.locator('[name="cancel-user"]').click();
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
