const { test, expect } = require('../../fixtures/e2e-fixtures');
const { submitArxivPaper } = require('../../helpers/editorial-workflow');

// Two papers of the list are known, whatever the journal already holds
const SEARCHED_PAPER = { arxivId: '1608.06993', titleWords: 'Densely Connected Convolutional' };
const OTHER_PAPER = { arxivId: '1610.02357' };

test.describe('Paper DataTables Sorting, Search & Length Pagination', () => {
    test('secretary can sort columns, filter papers, and change page size', async ({ authorPage, secretaryPage }) => {
        test.setTimeout(120000);
        const searchedDocid = await submitArxivPaper(authorPage, { arxivId: SEARCHED_PAPER.arxivId });
        const otherDocid = await submitArxivPaper(authorPage, { arxivId: OTHER_PAPER.arxivId });

        await secretaryPage.goto('administratepaper/list');
        const rows = secretaryPage.locator('#papers tbody tr');
        const processingIndicator = secretaryPage.locator('#papers_processing');
        await expect(rows.first()).toBeVisible();

        // 1. Column Sorting: Click on Permanent Id column header. With horizontal scrolling, DataTables
        // renders a visible copy of the header and keeps the original one collapsed: the first match is the copy.
        const docIdHeader = secretaryPage
            .locator('#papers_wrapper')
            .getByRole('columnheader', { name: /^Permanent Id/ })
            .first();
        await expect(docIdHeader).toBeVisible();
        await docIdHeader.click();
        // DataTables adds sorting_asc or sorting_desc class
        await expect(docIdHeader).toHaveClass(/sorting_asc|sorting_desc/, { timeout: 5000 });

        // Click again to toggle sort order
        await docIdHeader.click();
        await expect(docIdHeader).toHaveClass(/sorting_asc|sorting_desc/, { timeout: 5000 });
        await expect(processingIndicator).toBeHidden({ timeout: 5000 });

        // 2. Search: the list is filtered by the server, the search starts on a key press
        const searchInput = secretaryPage.locator('#papers_filter input[type="search"]');
        await expect(searchInput).toBeVisible();
        await searchInput.fill(SEARCHED_PAPER.titleWords);
        await searchInput.press('Enter');

        await expect(rows.filter({ hasText: SEARCHED_PAPER.titleWords }).first()).toContainText(String(searchedDocid));
        await expect(rows.filter({ hasText: String(otherDocid) })).toHaveCount(0);

        // Clearing the search restores the other papers
        await searchInput.fill('');
        await searchInput.press('Enter');
        await expect(rows.filter({ hasText: String(otherDocid) }).first()).toBeVisible({ timeout: 10000 });

        // 3. Page Length Selector: Change to 25 lines
        const lengthSelect = secretaryPage.locator('select[name="papers_length"], #papers_length select').first();
        await expect(lengthSelect).toBeVisible();
        await lengthSelect.selectOption('25');
        // Ensure table remains visible and operational after page length change
        await expect(rows.first()).toBeVisible();
    });
});
