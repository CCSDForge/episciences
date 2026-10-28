const { test, expect } = require('../../fixtures/e2e-fixtures');
const {
    createSection,
    createVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
} = require('../../helpers/editorial-workflow');

/**
 * @param {import('@playwright/test').Locator} form
 * @returns {Promise<string[]>} every submitted "name=value" pair of the form, request tokens excluded
 */
function formValues(form) {
    return form.evaluate(element =>
        [...new FormData(element).entries()]
            .filter(([name]) => !/csrf|token/i.test(name))
            .map(([name, value]) => `${name}=${typeof value === 'string' ? value : '[file]'}`)
    );
}

test.describe('Journal configuration', () => {
    test('a volume rating grid can be built, edited, previewed and removed', async ({ adminPage }) => {
        test.setTimeout(120000);
        const criterionTitle = `E2E clarity ${Date.now()}`;
        const vid = await createVolume(adminPage, `E2E Grid volume ${Date.now()}`);
        const gridActions = adminPage.locator('#grid-actions', {
            has: adminPage.locator(`a[href="/grid/addcriterion?rgid=${vid}"]`),
        });

        await test.step('the grid is created empty, with the actions of a volume grid', async () => {
            await createVolumeGrid(adminPage, vid);
            await expect(gridActions.getByRole('button', { name: 'Copy default grid' })).toBeVisible();
            await expect(gridActions.locator(`a.delete[href="/grid/delete?rgid=${vid}"]`)).toBeVisible();
        });

        await test.step('a criterion is added, then renamed', async () => {
            await addQuantitativeCriterion(adminPage, vid, { title: criterionTitle, description: 'Is the paper clear?' });

            await adminPage.locator(`a[href^="/grid/editcriterion?rgid=${vid}&"]`).first().click();
            const name = adminPage.locator('#critere-element');
            // Multilingual field: the pencil moves the value back into the input, the visible button saves it
            await name.locator('button:has(.glyphicon-pencil)').click();
            await name.locator('input[type="text"]').fill(`${criterionTitle} (renamed)`);
            await name.locator('span.input-group-btn:visible > button.btn-primary').click();
            await adminPage.locator('#submit').click();
            await expect(adminPage).toHaveURL(/grid\/list/);
        });

        await test.step('the preview shows the criterion as reviewers will see it', async () => {
            await adminPage.goto(`grid/view?rgid=${vid}`);
            await expect(adminPage.getByText(`${criterionTitle} (renamed)`)).toBeVisible();
            await expect(adminPage.getByRole('option', { name: '5/5' })).toBeAttached();
        });

        await test.step('the criterion, then the grid are removed', async () => {
            await adminPage.goto('grid/list');
            await adminPage.locator(`a.delete[href^="/grid/deletecriterion?rgid=${vid}&"]`).first().click();
            await adminPage.locator('.bootbox-confirm .bootbox-accept').click();
            await expect(adminPage.getByText(`${criterionTitle} (renamed)`)).toHaveCount(0);

            await gridActions.locator(`a.delete[href="/grid/delete?rgid=${vid}"]`).click();
            await adminPage.locator('.bootbox-confirm .bootbox-accept').click();
            await expect(adminPage.locator(`a[href*="rgid=${vid}"]`)).toHaveCount(0);
        });
    });

    test('a journal setting takes effect for authors and saving it keeps every other setting', async ({
        adminPage,
        authorPage,
    }) => {
        const settingsForm = adminPage.locator('form:has(#canPickSEction)');
        // Authors are only offered a choice when the journal has sections
        await createSection(adminPage, `E2E Configuration section ${Date.now()}`);

        await adminPage.goto('review/settings');
        const initialValues = await formValues(settingsForm);
        const initialSectionChoice = await adminPage.locator('#canPickSEction').inputValue();
        // The test switches this setting off: it has nothing to check if the journal already forbids it
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip(initialSectionChoice === '0', 'The journal already forbids authors to choose a section');

        try {
            await test.step('authors are no longer allowed to choose a section', async () => {
                await adminPage.locator('#canPickSEction').selectOption('0');
                await settingsForm.locator('#submit').click();
                await expect(adminPage.getByText('Changes have been saved.').first()).toBeVisible();

                await authorPage.goto('submit');
                await expect(authorPage.locator('#submit_doc')).toBeAttached();
                await expect(authorPage.locator('#sections')).toHaveCount(0);
            });
        } finally {
            await adminPage.goto('review/settings');
            await adminPage.locator('#canPickSEction').selectOption(initialSectionChoice);
            await settingsForm.locator('#submit').click();
        }

        await test.step('restoring the setting gives back exactly the initial configuration', async () => {
            await adminPage.goto('review/settings');
            expect(await formValues(settingsForm)).toEqual(initialValues);

            await authorPage.goto('submit');
            await expect(authorPage.locator('#sections')).toBeAttached();
        });
    });
});
