const { test, expect } = require('../../fixtures/e2e-fixtures');

const QUICK_OPINION = 'Quick opinion';
const TYPE_UNANSWERED_INVITATION = '0';
const TYPE_BEFORE_REVISION_DEADLINE = '3';

test.describe('Automatic reminders limited to a request type (#319)', () => {
    test('a reminder to the reviewers can be limited to the quick opinion', async ({ adminPage }) => {
        // a delay of its own: the reminder created by this test is found among the ones of the journal
        const delay = String(80 + Math.floor(Math.random() * 19));
        const ownReminder = adminPage
            .locator('#reminders .reminder')
            .filter({ hasText: QUICK_OPINION })
            .filter({ hasText: `(${delay} days` });

        await adminPage.goto('administratemail/reminders');
        await adminPage.locator('#addreminder').click();
        const form = adminPage.locator('#modal-box #reminder_form');
        await expect(form).toBeVisible();
        const requestType = form.locator('#request_type_id-element');

        await test.step('the request type is offered only for the reminders about review requests', async () => {
            await expect(requestType).toBeVisible();
            await form.locator('#type').selectOption(TYPE_BEFORE_REVISION_DEADLINE);
            await expect(requestType).toBeHidden();
            await form.locator('#type').selectOption(TYPE_UNANSWERED_INVITATION);
            await expect(requestType).toBeVisible();
        });

        await test.step('the reminder is saved for the quick opinion and named so', async () => {
            await form.locator('#request_type_id').selectOption({ label: QUICK_OPINION });
            await form.locator('#delay').fill(delay);
            await adminPage.locator('#modal-box #submit-modal').click();
            await expect(ownReminder).toHaveCount(1);
        });

        await test.step('it is opened again with its request type', async () => {
            await ownReminder.locator('a.modal-opener').click();
            await expect(form.locator('#request_type_id option:checked')).toHaveText(QUICK_OPINION);
            await adminPage.locator('#modal-box').getByRole('button', { name: 'Close' }).click();
        });

        await test.step('the reminder is removed', async () => {
            await ownReminder.locator('a.delete').click();
            await adminPage.locator('.bootbox-confirm .bootbox-accept').click();
            await expect(ownReminder).toHaveCount(0);
        });
    });
});
