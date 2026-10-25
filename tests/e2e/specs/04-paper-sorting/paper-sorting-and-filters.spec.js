const { test, expect } = require('../../fixtures/e2e-fixtures');

test.describe('Paper DataTables Sorting, Search & Length Pagination', () => {
    test('secretary can sort columns, filter papers, and change page size', async ({ secretaryPage }) => {
        await secretaryPage.goto('administratepaper/list');
        await expect(secretaryPage.locator('#papers_wrapper, table#papers').first()).toBeVisible();

        // 1. Column Sorting: Click on Permanent Id column header
        const docIdHeader = secretaryPage.locator('#papers th:has-text("Id document"), #papers th:has-text("Permanent Id")').first();
        if (await docIdHeader.isVisible()) {
            await docIdHeader.click();
            // DataTables adds sorting_asc or sorting_desc class
            await expect(docIdHeader).toHaveClass(/sorting_asc|sorting_desc/, { timeout: 5000 });

            // Click again to toggle sort order
            await docIdHeader.click();
            await expect(docIdHeader).toHaveClass(/sorting_asc|sorting_desc/, { timeout: 5000 });
        }

        // 2. Real-time Search: Search for the first paper's permanent identifier or docid
        const firstRow = secretaryPage.locator('#papers tbody tr').first();
        await expect(firstRow).toBeVisible();
        const firstRowText = await firstRow.locator('td').first().textContent();
        const searchTerm = (firstRowText || '').trim();

        const searchInput = secretaryPage.locator('#papers_filter input, input[type="search"]').first();
        if (await searchInput.isVisible() && searchTerm) {
            await searchInput.fill(searchTerm);

            // Wait for DataTables processing indicator to hide
            const processingIndicator = secretaryPage.locator('#papers_processing');
            if (await processingIndicator.count() > 0) {
                await expect(processingIndicator).not.toBeVisible({ timeout: 5000 });
            }

            // Results table should contain the searched term
            const matchedRow = secretaryPage.locator(`#papers tbody tr:has-text("${searchTerm}")`).first();
            await expect(matchedRow).toBeVisible({ timeout: 10000 });

            // Clear search to restore rows
            await searchInput.fill('');
            if (await processingIndicator.count() > 0) {
                await expect(processingIndicator).not.toBeVisible({ timeout: 5000 });
            }
        }

        // 3. Page Length Selector: Change to 25 or 50 lines
        const lengthSelect = secretaryPage.locator('select[name="papers_length"], #papers_length select').first();
        if (await lengthSelect.isVisible()) {
            await lengthSelect.selectOption('25');
            // Ensure table remains visible and operational after page length change
            await expect(secretaryPage.locator('#papers tbody tr').first()).toBeVisible();
        }
    });
});
