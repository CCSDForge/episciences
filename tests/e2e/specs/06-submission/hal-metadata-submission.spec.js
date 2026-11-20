const { test, expect } = require('../../fixtures/e2e-fixtures');
const { submitHalPaper, openPaperManagement } = require('../../helpers/editorial-workflow');

// hal-05428815v4 carries rich TEI metadata (ORCID, affiliations with ROR, CC BY 4.0 license, linked data).
// Never publish this paper: publishing a HAL paper sends a COAR Notify announcement to HAL.
const HAL_ID = 'hal-05428815';
const HAL_VERSION = 4;
const HAL_REPO_ID = '1';

/**
 * Ensures HAL is enabled among the journal's submission repositories, without disabling arXiv.
 * Returns the initial list of selected repository IDs if it had to be changed, or null otherwise.
 * @param {import('@playwright/test').Page} adminPage
 * @returns {Promise<string[]|null>}
 */
async function ensureHalRepositoryEnabled(adminPage) {
    await adminPage.goto('review/settings');
    const select = adminPage.locator('#repositories');
    await expect(select).toBeAttached();
    const initialRepos = await select.evaluate(el => [...el.selectedOptions].map(opt => opt.value));
    if (initialRepos.includes(HAL_REPO_ID)) {
        return null;
    }

    await select.selectOption([...initialRepos, HAL_REPO_ID]);
    await adminPage.locator('form:has(#repositories) #submit').click();
    await expect(adminPage.getByText('Changes have been saved.').first()).toBeVisible();
    return initialRepos;
}

/**
 * Restores the journal's submission repositories when modified by the test.
 * @param {import('@playwright/test').Page} adminPage
 * @param {string[]|null} initialRepos
 */
async function restoreRepositories(adminPage, initialRepos) {
    if (!initialRepos) {
        return;
    }
    await adminPage.goto('review/settings');
    const select = adminPage.locator('#repositories');
    await select.selectOption(initialRepos);
    await adminPage.locator('form:has(#repositories) #submit').click();
    await expect(adminPage.getByText('Changes have been saved.').first()).toBeVisible();
}

test.describe('HAL submission metadata enrichment', () => {
    test('submitting hal-05428815v4 retrieves title, authors, ORCIDs, affiliations, license and linked data', async ({
        authorPage,
        adminPage,
    }) => {
        test.setTimeout(180000);
        let docid;
        const initialRepos = await ensureHalRepositoryEnabled(adminPage);

        try {
            await test.step('the author submits hal-05428815 version 4 from HAL', async () => {
                docid = await submitHalPaper(authorPage, { halId: HAL_ID, version: HAL_VERSION });
                await expect(authorPage.getByText('Your article has been submitted.').first()).toBeVisible();
                await expect(authorPage.locator('body')).toContainText(
                    'One-dimensional elastoplastic models with isotropic hardening as differential inclusions in primal form'
                );
            });

            await test.step('the paper view exposes authors, all 4 ORCIDs, affiliations, keywords and license', async () => {
                const body = authorPage.locator('body');
                await expect(body).toContainText('Lisa Eberhardt');
                await expect(body).toContainText('Jonas Breuling');
                await expect(body).toContainText('Simon R. Eugster');
                await expect(body).toContainText('Remco I. Leine');

                await expect(body).toContainText('0000-0002-9857-7080');
                await expect(body).toContainText('0000-0003-1065-2216');
                await expect(body).toContainText('0000-0002-4562-1287');
                await expect(body).toContainText('0000-0001-9859-7519');

                await expect(body).toContainText('Institute for Nonlinear Mechanics');
                await expect(body).toContainText('Department of Mechanical Engineering [Eindhoven]');
                await expect(body).toContainText('Isotropic hardening');
                // The licence label depends on the environment: "Creative Commons Attribution 4.0 International"
                // or "Attribution 4.0 International (CC BY 4.0)"
                await expect(body).toContainText(/Attribution 4\.0 International/);
            });

            await test.step('the editorial management page exposes affiliations, ORCIDs and license', async () => {
                await openPaperManagement(adminPage, docid);
                const body = adminPage.locator('body');
                await expect(body).toContainText(/Attribution 4\.0 International|CC-BY-4\.0/i);
                await expect(body).toContainText('Institute for Nonlinear Mechanics');
                await expect(body).toContainText('Department of Mechanical Engineering [Eindhoven]');
                await expect(body).toContainText('0000-0002-9857-7080');
            });
        } finally {
            await restoreRepositories(adminPage, initialRepos);
        }
    });
});
