const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Section (Rubrique) Administration & Reordering', () => {
    test('secretary can view section list, create a new section, reorder, and delete it', async ({ secretaryPage }) => {
        // 1. Navigate to section list
        await secretaryPage.goto('section/list');
        await expect(secretaryPage.locator('#sections_wrapper, table#sections').first()).toBeVisible();

        // 2. Open section creation form
        const addBtn = secretaryPage.locator('a[href*="/section/add"]');
        await expect(addBtn).toBeVisible();
        await addBtn.click();

        // Fill section form
        await secretaryPage.waitForLoadState('domcontentloaded');
        const titleInputs = secretaryPage.locator('input[id^="title_"], input[name^="title_"]');
        const titleCount = await titleInputs.count();
        expect(titleCount).toBeGreaterThan(0);

        const testSectionTitle = `E2E Section ${Date.now()}`;
        for (let i = 0; i < titleCount; i++) {
            await titleInputs.nth(i).fill(testSectionTitle);
        }

        // Submit creation using specific primary submit button
        const submitBtn = secretaryPage.getByRole('button', { name: /Valider|Save|Submit/i }).first();
        await submitBtn.click();

        // Should redirect back to section list with success message
        await secretaryPage.waitForLoadState('networkidle');
        expect(secretaryPage.url()).toContain('section');

        // Verify the created section is in the table
        const createdRow = secretaryPage.locator(`table#sections tr:has-text("${testSectionTitle}")`).first();
        await expect(createdRow).toBeVisible();

        // 3. Test reordering interaction
        const positionInput = createdRow.locator('.reorder-position-input');
        if (await positionInput.isVisible()) {
            await positionInput.fill('1');
            await positionInput.dispatchEvent('input');
            await positionInput.dispatchEvent('change');

            const reorderBar = secretaryPage.locator('#reorder-bar');
            await expect(reorderBar).toBeVisible();

            const saveReorderBtn = secretaryPage.locator('#reorder-save');
            await expect(saveReorderBtn).toBeVisible();
            await saveReorderBtn.click();

            await expect(secretaryPage.locator('.reorder-save__busy')).not.toBeVisible({ timeout: 10000 });
        }

        // 4. Delete the test section
        const deleteBtn = createdRow.locator('a.delete, a[href*="section/delete"]').first();
        if (await deleteBtn.isVisible()) {
            await deleteBtn.click();

            const confirmBtn = secretaryPage.locator('.bootbox button[data-bb-handler="confirm"], .modal button.btn-primary').first();
            await expect(confirmBtn).toBeVisible({ timeout: 5000 });
            await confirmBtn.click();

            await secretaryPage.waitForLoadState('networkidle');
            await expect(secretaryPage.locator(`table#sections tr:has-text("${testSectionTitle}")`)).not.toBeVisible({ timeout: 10000 });
        }
    });
});
