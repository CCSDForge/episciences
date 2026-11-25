const { test, expect, E2E_USERNAMES } = require('../../fixtures/e2e-fixtures');
const { runReminders } = require('../../helpers/app-cli');
const {
    submitArxivPaper,
    openPaperManagement,
    inviteKnownReviewer,
    acceptReviewInvitation,
} = require('../../helpers/editorial-workflow');

const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const REVIEWER_NAME = /E2E Reviewer/i;
const TYPE_UNANSWERED_INVITATION = '0';
const TYPE_AFTER_REVIEWING_DEADLINE = '2';
const DAY = 86400 * 1000;

/**
 * Formats a Date as YYYY-MM-DD in local time.
 * @param {Date} date
 * @returns {string}
 */
function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * @param {import('@playwright/test').Page} page reminders page
 * @returns {Promise<string[]>} ids of the reminders listed on the page
 */
async function listReminderIds(page) {
    const hrefs = await page.locator('#reminders a.delete').evaluateAll(links => links.map(link => link.getAttribute('href')));
    return hrefs.map(href => href.match(/id=(\d+)/)[1]);
}

/**
 * Creates a reminder sent to the reviewer one day after its trigger, never repeated.
 * @param {import('@playwright/test').Page} page administrator page
 * @param {string} type reminder type
 * @returns {Promise<string>} id of the new reminder
 */
async function createReviewerReminder(page, type) {
    await page.goto('administratemail/reminders');
    const before = await listReminderIds(page);

    await page.locator('#addreminder').click();
    const modal = page.locator('#modal-box');
    await expect(modal.locator('#reminder_form')).toBeVisible();
    await modal.locator('#type').selectOption(type);
    await modal.locator('#recipient').selectOption('reviewer');
    // The form only accepts a delay of one day or more
    await modal.locator('#delay').fill('1');
    await modal.locator('#repetition').selectOption('0');

    const saved = page.waitForResponse(response => response.url().includes('/administratemail/savereminder'));
    await modal.locator('#submit-modal').click();
    expect(await (await saved).text()).toBe('1');
    await expect(modal).toBeHidden();

    await expect.poll(async () => (await listReminderIds(page)).length).toBe(before.length + 1);
    return (await listReminderIds(page)).find(id => !before.includes(id));
}

/**
 * @param {import('@playwright/test').Page} page administrator page
 * @param {string} id
 */
async function deleteReminder(page, id) {
    await page.goto('administratemail/reminders');
    const removed = page.waitForResponse(response => response.url().includes(`/administratemail/deletereminder?id=${id}`));
    await page.locator(`#reminders a.delete[href$="id=${id}"]`).click();
    await page.locator('.bootbox .btn-primary').click();
    expect((await removed).ok()).toBeTruthy();
}

test.describe('Automatic reviewer reminders', () => {
    test('a reviewer is reminded of an unanswered invitation, then of a late report', async ({
        authorPage,
        adminPage,
        reviewerPage,
        mailpit,
    }) => {
        test.setTimeout(240000);
        // The reminders are journal settings: they are removed whatever happens to the test
        const reminderIds = [];
        let docid;

        try {
            await test.step('the editorial team sets up both reminders', async () => {
                reminderIds.push(await createReviewerReminder(adminPage, TYPE_UNANSWERED_INVITATION));
                reminderIds.push(await createReviewerReminder(adminPage, TYPE_AFTER_REVIEWING_DEADLINE));
            });

            await test.step('a reviewer who has not answered the invitation is reminded the next day', async () => {
                docid = await submitArxivPaper(authorPage, { arxivId: '1608.03983' });
                await inviteKnownReviewer(adminPage, docid, E2E_USERNAMES.reviewer);

                runReminders(toIsoDate(new Date(Date.now() + DAY)));
                await mailpit.waitForEmail({
                    to: REVIEWER_EMAIL,
                    subjectContains: [`#${docid} `, 'Unanswered reviewer invitation'],
                });
            });

            let deadline;

            await test.step('the reviewer accepts and the editorial team sets the reviewing deadline', async () => {
                await acceptReviewInvitation(mailpit, reviewerPage, docid, REVIEWER_EMAIL);

                await openPaperManagement(adminPage, docid);
                const activeReviewer = adminPage.locator('#active-invitations .reviewer', { hasText: REVIEWER_NAME });
                await expect(activeReviewer).toBeVisible();
                await activeReviewer.locator('.popover-link').click();
                await expect(adminPage.locator('.popover .context-menu')).toBeVisible();
                await adminPage.locator('.popover .context-menu a[href*="/administratepaper/updatedeadline/"]').click();

                const modal = adminPage.locator('#modal-box');
                const deadlineInput = modal.locator('#deadline-id');
                // The modal content is loaded over AJAX
                await expect(deadlineInput).toBeVisible({ timeout: 15000 });
                await adminPage.waitForFunction(() => Boolean(window.tinymce?.get('body')?.initialized));

                // The new deadline has to stay within the bounds the journal allows around the current one
                const minDate = await deadlineInput.getAttribute('attr-mindate');
                expect(minDate).toMatch(/^\d{4}-\d{2}-\d{2}$/);
                deadline = new Date(`${minDate}T12:00:00`);
                await deadlineInput.fill(minDate);
                await deadlineInput.dispatchEvent('change');

                const saved = adminPage.waitForResponse(response =>
                    response.url().includes('/administratepaper/savenewdeadline')
                );
                await adminPage.locator('#submit-modal').click();
                expect((await (await saved).json()).status).toBe(1);
            });

            await test.step('the reviewer is reminded the day after the deadline', async () => {
                runReminders(toIsoDate(new Date(deadline.getTime() + DAY)));
                await mailpit.waitForEmail({
                    to: REVIEWER_EMAIL,
                    subjectContains: [`#${docid} `, 'Reviewing deadline is over!'],
                });
            });
        } finally {
            for (const id of reminderIds) {
                await deleteReminder(adminPage, id);
            }
        }
    });
});
