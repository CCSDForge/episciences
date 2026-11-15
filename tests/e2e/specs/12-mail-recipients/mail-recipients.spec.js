const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    submitArxivPaper,
    inviteKnownReviewer,
    openPaperManagement,
    statusPanel,
} = require('../../helpers/editorial-workflow');

const AUTHOR_EMAIL = 'e2e_author@example.org';
const EDITOR_EMAIL = 'e2e_editor@example.org';
const SECRETARY_EMAIL = 'e2e_secretary@example.org';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';

const XHR_HEADERS = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };

/**
 * Recipient input (combobox) of a mail form
 * @param {import('@playwright/test').Locator} form
 * @param {'to'|'cc'|'bcc'} target
 */
function recipientInput(form, target) {
    return form.locator(`input.recipient-input[data-recipient-target="${target}"]`);
}

/**
 * Chosen recipients of a field
 * @param {import('@playwright/test').Locator} form
 * @param {'to'|'cc'|'bcc'} target
 */
function recipientTags(form, target) {
    return form.locator(`.recipient-tags[data-recipient-target="${target}"] .recipient-tag`);
}

/**
 * Popup of a recipient input (its id is given by aria-controls)
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} input
 */
async function listboxOf(page, input) {
    return page.locator(`#${await input.getAttribute('aria-controls')}`);
}

test.describe('Mail recipients', () => {
    test('the recipients search answers editorial XHR requests only', async ({ adminPage, authorPage }) => {
        await test.step('an editorial user finds a user of the journal', async () => {
            const response = await adminPage.request.get('administratemail/getrecipients?q=e2e_ed', {
                headers: XHR_HEADERS,
            });

            expect(response.status()).toBe(200);
            expect(response.headers()['content-type']).toContain('application/json');
            expect(response.headers()['cache-control']).toContain('no-store');
            const { results } = await response.json();
            expect(results.map(result => result.mail)).toContain(EDITOR_EMAIL);
        });

        await test.step('a request that is not an XHR is refused', async () => {
            const response = await adminPage.request.get('administratemail/getrecipients?q=e2e_ed');

            expect(response.status()).toBe(400);
        });

        await test.step('a user without an editorial role gets no users', async () => {
            const response = await authorPage.request.get('administratemail/getrecipients?q=e2e_ed', {
                headers: XHR_HEADERS,
                maxRedirects: 0,
            });

            expect(response.ok()).toBe(false);
            expect(await response.text()).not.toContain(EDITOR_EMAIL);
        });
    });

    test('recipients are searched, browsed, typed and removed on the mailing page', async ({
        adminPage,
        mailpit,
    }) => {
        // The mail queue is flushed by a PHP script, run until the mail is found
        test.setTimeout(90000);
        const subject = `E2E recipients ${Date.now()}`;
        const typedAddress = `e2e-typed-${Date.now()}@example.org`;
        await adminPage.goto('administratemail/send');
        const form = adminPage.locator('form#send_form');
        const to = recipientInput(form, 'to');
        const cc = recipientInput(form, 'cc');
        const bcc = recipientInput(form, 'bcc');

        await test.step('the recipient fields are comboboxes, labelled and described', async () => {
            await expect(to).toHaveAttribute('role', 'combobox');
            await expect(to).toHaveAttribute('aria-expanded', 'false');
            await expect(adminPage.getByLabel('To', { exact: true })).toHaveCount(1);
            await expect(to).toHaveAttribute('aria-describedby', /-help/);
        });

        await test.step('a user is found by their username and chosen', async () => {
            await to.fill('e2e_ed');
            const option = adminPage.getByRole('option').filter({ hasText: EDITOR_EMAIL });
            await expect(option).toBeVisible();
            await expect(to).toHaveAttribute('aria-expanded', 'true');

            await option.click();

            await expect(recipientTags(form, 'to')).toHaveCount(1);
            await expect(recipientTags(form, 'to').first()).toHaveAttribute('data-email', EDITOR_EMAIL);
            await expect(to).toHaveValue('');
            await expect(to).toHaveAttribute('aria-expanded', 'false');
        });

        await test.step('the editorial groups are browsed from the empty field, which stays open', async () => {
            await cc.click();
            const listbox = await listboxOf(adminPage, cc);
            await expect(listbox).toBeVisible();

            await listbox.getByRole('option', { name: 'Editors', exact: true }).click();
            // Already in To: listed, but cannot be chosen again
            await expect(listbox.getByRole('option').filter({ hasText: EDITOR_EMAIL })).toHaveAttribute(
                'aria-disabled',
                'true'
            );

            await listbox.getByRole('option', { name: 'Back to the groups' }).click();
            await listbox.getByRole('option', { name: 'Editorial Secretaries', exact: true }).click();
            await listbox.getByRole('option').filter({ hasText: SECRETARY_EMAIL }).click();

            await expect(recipientTags(form, 'cc')).toHaveCount(1);
            await expect(listbox).toBeVisible();

            await cc.press('Escape');
            await expect(listbox).toBeHidden();
        });

        await test.step('an invalid typed address is kept with an error, a valid one is added', async () => {
            await bcc.fill('not-an-address');
            await bcc.press('Enter');

            await expect(bcc).toHaveAttribute('aria-invalid', 'true');
            const errorId = `${await bcc.getAttribute('id')}-error`;
            await expect(bcc).toHaveAttribute('aria-describedby', new RegExp(errorId));
            await expect(adminPage.locator(`#${errorId}`)).toHaveText('Invalid email address: not-an-address');

            await bcc.fill(typedAddress);
            await bcc.press('Enter');

            await expect(bcc).not.toHaveAttribute('aria-invalid', 'true');
            await expect(recipientTags(form, 'bcc')).toHaveCount(1);
            await expect(recipientTags(form, 'bcc').first()).toContainText('(typed address)');
        });

        await test.step('a recipient is removed with the keyboard, the focus going back to the field', async () => {
            await bcc.fill('another@example.org');
            await bcc.press('Enter');
            await expect(recipientTags(form, 'bcc')).toHaveCount(2);

            // A first Backspace focuses the last recipient, a second one removes it
            await bcc.press('Backspace');
            await expect(form.getByRole('button', { name: 'Remove another@example.org' })).toBeFocused();
            await adminPage.keyboard.press('Backspace');

            await expect(recipientTags(form, 'bcc')).toHaveCount(1);
            await expect(form.getByRole('button', { name: 'Remove another@example.org' })).toHaveCount(0);
        });

        await test.step('the mail goes to the chosen recipients', async () => {
            await form.locator('#subject').fill(subject);
            await adminPage.frameLocator('#content_ifr').locator('body').fill('E2E recipients search.');
            await form.locator('#submit').click();

            const email = await mailpit.waitForEmail({ to: EDITOR_EMAIL, subjectContains: [subject] });
            expect((email.Cc || []).map(address => address.Address)).toEqual([SECRETARY_EMAIL]);
            expect((email.Bcc || []).map(address => address.Address)).toEqual([typedAddress]);
        });
    });

    test('a status modal does not send a mail showing a reviewer to the author', async ({
        authorPage,
        adminPage,
        mailpit,
    }) => {
        test.setTimeout(180000);
        const docid = await submitArxivPaper(authorPage, { arxivId: '1512.03385' });

        await test.step('the paper has a reviewer', async () => {
            await inviteKnownReviewer(adminPage, docid, 'e2e_reviewer');
        });

        const modal = adminPage.locator('.refusal-modal');
        const form = modal.locator('form').first();

        await test.step('the reviewer is suggested as such and chosen in Cc: a warning is shown', async () => {
            const panel = await openPaperManagement(adminPage, docid);
            await panel.getByRole('button', { name: 'Change article status' }).click();
            await panel.locator('a[data-target=".refusal-modal"]').click();
            // Bootstrap focuses the modal once it is shown: text typed before would lose the focus
            await expect(modal).toBeFocused();

            const cc = recipientInput(form, 'cc');
            await cc.fill('e2e_rev');
            const option = adminPage.getByRole('option').filter({ hasText: REVIEWER_EMAIL });
            await expect(option).toContainText('Reviewer');
            await option.click();

            await expect(modal.locator('.ep-anonymity-warning')).toBeVisible();
            await expect(modal.locator('.ep-anonymity-warning')).toHaveAttribute('role', 'alert');
        });

        await test.step('the mail is not sent while the warning is shown', async () => {
            await modal.locator('button.submit-modal').click();

            await expect(modal.locator('.ep-anonymity-warning')).toBeFocused();
            await expect(modal).toBeVisible();
            await expect(statusPanel(adminPage)).not.toContainText('Rejected');
        });

        await test.step('the reviewer is moved to Bcc and the decision is sent', async () => {
            await modal.getByRole('button', { name: 'Move the reviewers to Bcc' }).click();

            await expect(modal.locator('.ep-anonymity-warning')).toHaveCount(0);
            await expect(recipientTags(form, 'cc')).toHaveCount(0);
            await expect(recipientTags(form, 'bcc').first()).toHaveAttribute('data-email', REVIEWER_EMAIL);

            await modal.locator('button.submit-modal').click();
            await adminPage.waitForURL(new RegExp(`administratepaper/view(/id/|\\?id=)${docid}`));
            await expect(statusPanel(adminPage)).toContainText('Rejected');
        });

        await test.step('the author does not see the reviewer among the recipients', async () => {
            const email = await mailpit.waitForEmail({
                to: AUTHOR_EMAIL,
                subjectContains: [`#${docid} `, 'has been rejected'],
            });
            const visible = [...(email.To || []), ...(email.Cc || [])].map(address => address.Address);
            expect(visible).not.toContain(REVIEWER_EMAIL);
        });
    });
});
