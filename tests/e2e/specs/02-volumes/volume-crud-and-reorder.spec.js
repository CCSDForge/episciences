const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Volume administration and reordering', () => {
    test('secretary can view volume list, create a new volume, reorder, and delete it', async ({ secretaryPage }) => {
        // 1. Navigate to volume list
        await secretaryPage.goto('volume/list');
        // The table is only rendered once the journal has a volume
        await expect(secretaryPage.getByRole('heading', { level: 1, name: 'Volumes' })).toBeVisible();

        // 2. Open volume creation form
        const addBtn = secretaryPage.locator('a[href*="/volume/add"]');
        await expect(addBtn).toBeVisible();
        await addBtn.click();

        // Fill volume form
        await secretaryPage.waitForLoadState('domcontentloaded');
        const titleInputs = secretaryPage.locator('input[id^="title_"], input[name^="title_"]');
        const titleCount = await titleInputs.count();
        expect(titleCount).toBeGreaterThan(0);

        const testVolumeTitle = `E2E Volume ${Date.now()}`;
        for (let i = 0; i < titleCount; i++) {
            await titleInputs.nth(i).fill(testVolumeTitle);
        }

        const yearInput = secretaryPage.locator('input#year, input[name="year"]');
        await expect(yearInput).toBeVisible();
        await yearInput.fill('2026');

        const numInput = secretaryPage.locator('input#num, input[name="num"]');
        await expect(numInput).toBeVisible();
        await numInput.fill('99');

        // Submit creation using specific primary submit button
        const submitBtn = secretaryPage.getByRole('button', { name: /Valider|Save|Submit/i }).first();
        await submitBtn.click();

        // Should redirect back to volume list with success message
        await expect(secretaryPage).toHaveURL(/volume\/list/);

        // Verify the created volume is in the table
        const createdRow = secretaryPage.locator(`table#volumes tr:has-text("${testVolumeTitle}")`).first();
        await expect(createdRow).toBeVisible();

        // 3. Test reordering interaction (reorder-bar)
        const positionInput = createdRow.locator('.reorder-position-input');
        await expect(positionInput).toBeVisible();
        await positionInput.fill('1');
        await positionInput.dispatchEvent('input');
        await positionInput.dispatchEvent('change');

        const reorderBar = secretaryPage.locator('#reorder-bar');
        await expect(reorderBar).toBeVisible();

        const saveReorderBtn = secretaryPage.locator('#reorder-save');
        await expect(saveReorderBtn).toBeVisible();
        await saveReorderBtn.click();

        // Wait for save operation to complete (busy spinner hidden)
        await expect(secretaryPage.locator('.reorder-save__busy')).not.toBeVisible({ timeout: 10000 });

        // 4. Delete the test volume via Bootbox
        const deleteBtn = createdRow.locator('a.delete, a[href*="volume/delete"]').first();
        await deleteBtn.click();

        // Confirm Bootbox dialog
        const confirmBtn = secretaryPage.locator('.bootbox button[data-bb-handler="confirm"], .modal button.btn-primary').first();
        await expect(confirmBtn).toBeVisible({ timeout: 5000 });
        await confirmBtn.click();

        // Ensure volume was removed
        await expect(secretaryPage.locator(`table#volumes tr:has-text("${testVolumeTitle}")`)).not.toBeVisible({ timeout: 10000 });
    });
});
