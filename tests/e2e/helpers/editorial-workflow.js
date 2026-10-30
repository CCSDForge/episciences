const path = require('path');
const { expect } = require('@playwright/test');

/**
 * Page helpers shared by the editorial workflow specs (submission, review, decisions, revisions,
 * publication). Each helper drives the real user interface; none of them writes to the database.
 *
 * Papers are always submitted from arXiv. Publishing a paper from HAL sends a COAR Notify
 * announcement to HAL: the suite must never trigger it.
 */

const ARXIV_REPO_ID = '2';
const REVIEWER_USERNAME = 'e2e_reviewer';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const MINIMAL_PDF = path.resolve(__dirname, '..', 'fixtures', 'files', 'minimal.pdf');

// Fetching metadata from arXiv goes over the network
const REPOSITORY_TIMEOUT = 60000;

/**
 * Submits an arXiv preprint as the logged-in author.
 * @param {import('@playwright/test').Page} page author page
 * @param {Object} options
 * @param {string} options.arxivId arXiv identifier, without version
 * @param {number} [options.version=1]
 * @param {string} [options.sectionTitle] section to submit in, when the journal lets authors pick one
 * @returns {Promise<number>} docid of the submitted paper
 */
async function submitArxivPaper(page, { arxivId, version = 1, sectionTitle }) {
    await page.goto('submit');
    await page.locator('#search_doc-repoId').selectOption(ARXIV_REPO_ID);
    await page.locator('#search_doc-docId').fill(arxivId);
    await page.locator('#search_doc-version').fill(String(version));
    await page.locator('#search_doc-getPaper').click();

    const submitForm = page.locator('#submitForm');
    await expect(submitForm).toBeVisible({ timeout: REPOSITORY_TIMEOUT });

    if (sectionTitle) {
        await page.locator('#sections').selectOption({ label: sectionTitle });
    }

    // Journal setting: some journals require a data descriptor along with the article
    if (await page.locator('#file_data_descriptor').count()) {
        await page.locator('#file_data_descriptor').setInputFiles(MINIMAL_PDF);
    }

    await page.locator('#disclaimers-disclaimer1').check();
    await page.locator('#disclaimers-disclaimer2').check();
    await page.locator('#submitPaper').click();

    await expect(page).toHaveURL(/\/paper\/view\/id\/\d+/, { timeout: REPOSITORY_TIMEOUT });
    return Number(page.url().match(/\/paper\/view\/id\/(\d+)/)[1]);
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {number} docid
 */
async function openPaperManagement(page, docid) {
    await page.goto(`administratepaper/view/id/${docid}`);
    return page.locator('.panel:has(h2:has-text("Article status"))');
}

/**
 * @param {import('@playwright/test').Page} page any page showing the "Article status" panel
 * @returns {import('@playwright/test').Locator}
 */
function statusPanel(page) {
    return page.locator('.panel:has(h2:has-text("Article status"))');
}

/**
 * Applies an editorial decision from the "Change article status" menu and sends the email prefilled
 * in its modal.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {string} decision CSS class of the decision modal, for instance "minor-revision-modal"
 */
async function applyDecision(page, docid, decision) {
    const panel = await openPaperManagement(page, docid);
    await panel.getByRole('button', { name: 'Change article status' }).click();
    await panel.locator(`a[data-target=".${decision}"]`).click();

    const modal = page.locator(`.${decision}`);
    await expect(modal).toBeVisible();
    await modal.locator('button.submit-modal').click();
    await page.waitForURL(new RegExp(`administratepaper/view(/id/|\\?id=)${docid}`));
}

/**
 * Invites a reviewer already known to the journal.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {string} username
 */
async function inviteKnownReviewer(page, docid, username) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);

    await page.locator('#known-reviewers tbody tr', { hasText: username }).click();
    await expect(page.locator('#step-2')).toBeVisible();
    await page.locator('#submit-invitation').click();
    await page.waitForURL(new RegExp(`administratepaper/view\\?id=${docid}`));
}

/**
 * Invites a reviewer without an account, by e-mail address.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {Object} reviewer
 * @param {string} reviewer.email no account may use it
 * @param {string} reviewer.lastname no account may have it either, or the homonyms are listed instead
 *   (the form has no first name field: Episciences_PapersManager::getTmpReviewerForm())
 */
async function inviteNewReviewer(page, docid, { email, lastname }) {
    await openPaperManagement(page, docid);
    await page.locator(`#invite_reviewer_${docid}`).click();
    await expect(page).toHaveURL(/administratepaper\/invitereviewer/);

    await page.locator('#new_user_button').click();
    // Typing the address looks for the accounts using it: the button waits for the answer
    await page.locator('#email').fill(email);
    await page.locator('#lastname').fill(lastname);
    await page.locator('#next').click();
    await expect(page.locator('#step-2')).toBeVisible();

    await page.locator('#submit-invitation').click();
    await page.waitForURL(new RegExp(`administratepaper/view\\?id=${docid}`));
}

/**
 * Has the E2E reviewer review a paper: the journal requires a completed review before a paper can be
 * accepted, published or sent back for revision (requiredReviewers, set by app:e2e-seed).
 * The paper is first assigned to a volume with its own rating grid, so that the review form does not
 * depend on the default grid of the journal.
 * @param {Object} pages
 * @param {import('@playwright/test').Page} pages.adminPage editorial page
 * @param {import('@playwright/test').Page} pages.reviewerPage
 * @param {import('./mailpit-client').MailpitClient} pages.mailpit
 * @param {number} docid
 * @param {Object} [options]
 * @param {number} [options.vid] volume to review the paper in, a new one by default
 */
async function completeReview({ adminPage, reviewerPage, mailpit }, docid, { vid } = {}) {
    vid ??= await createVolume(adminPage, `E2E Review volume ${Date.now()}`);
    await createVolumeGrid(adminPage, vid);
    await addQuantitativeCriterion(adminPage, vid, {
        title: `E2E soundness ${Date.now()}`,
        description: 'Is the paper sound?',
    });
    await assignMasterVolume(adminPage, docid, vid);

    await inviteKnownReviewer(adminPage, docid, REVIEWER_USERNAME);

    const email = await mailpit.waitForEmail({
        to: REVIEWER_EMAIL,
        subjectContains: [`#${docid} `, 'You are invited to review an article'],
    });
    const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
    expect(invitationUrl).toBeTruthy();

    // The link targets the configured application URL: only its path is kept
    await reviewerPage.goto(new URL(invitationUrl).pathname.replace(/^\//, ''));
    await reviewerPage.locator('#submitaccept').click();
    await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));

    // The grid has a single criterion, graded on a 0-5 scale, with a comment
    const grade = reviewerPage.locator('form#rating-grid select[name^="note_item_"]');
    await expect(grade).toHaveCount(1);
    await grade.selectOption({ label: '4/5' });
    // Comment fields are TinyMCE editors, initialised once the page has loaded
    await reviewerPage.waitForFunction(() => {
        const editors = window.tinymce?.get() ?? [];
        return editors.length > 0 && editors.every(editor => editor.initialized);
    });
    await reviewerPage.evaluate(() => {
        window.tinymce.get().forEach(editor => editor.setContent('<p>E2E review.</p>'));
    });
    await reviewerPage.locator('#validateRating').click();
    await expect(reviewerPage).toHaveURL(/paper\/ratings/);
}

/**
 * Creates a section.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {string} title must start with "E2E " so that the next run removes it
 */
async function createSection(page, title) {
    await page.goto('section/add');
    // One required title field per journal language
    const titleInputs = page.locator('input[id^="title_"]');
    await expect(titleInputs.first()).toBeVisible();
    for (const titleInput of await titleInputs.all()) {
        await titleInput.fill(title);
    }
    await page.locator('#submit').click();
    await expect(page).not.toHaveURL(/section\/add/);
}

/**
 * Creates a volume and returns its id.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {string} title must start with "E2E " so that the next run removes it
 * @returns {Promise<number>}
 */
async function createVolume(page, title) {
    await page.goto('volume/add');
    // One required title field per journal language
    const titleInputs = page.locator('input[id^="title_"]');
    await expect(titleInputs.first()).toBeVisible();
    for (const titleInput of await titleInputs.all()) {
        await titleInput.fill(title);
    }
    await page.locator('#year').fill(String(new Date().getFullYear()));
    await page.locator('#submit').click();
    await expect(page).toHaveURL(/volume\/list/);

    const row = page.locator('table#volumes tbody tr', { hasText: title });
    await expect(row).toBeVisible();
    return Number((await row.getAttribute('id')).replace('volume_', ''));
}

/**
 * Sets the master volume of a paper from its management page.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 * @param {number} vid
 */
async function assignMasterVolume(page, docid, vid) {
    await openPaperManagement(page, docid);
    const volumesPanel = page.locator('.panel:has(h2:has-text("Volumes & sections"))');
    await volumesPanel.locator(`button[data-modal="master-volume"][data-docid="${docid}"]`).click();

    const select = page.locator('#master_volume_select');
    await expect(select).toBeAttached();
    // The select is enhanced by Tom Select, which hides the native element
    await select.evaluate((element, value) => element.tomselect.setValue(value), String(vid));

    const saved = page.waitForResponse(response => response.url().includes('/administratepaper/savemastervolume'));
    await page.locator('dialog[open] .paper-modal__save').click();
    expect(await (await saved).text()).toBe('1');
}

/**
 * Creates the rating grid of a volume: papers whose master volume is this one are reviewed with it.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} vid
 */
async function createVolumeGrid(page, vid) {
    await page.goto('grid/list');
    const form = page.locator('form[action="/grid/add"]');
    await form.locator('#volume').selectOption(String(vid));
    await form.locator('#submit').click();
    await expect(page.getByText('The new review grid has been successfully saved.').first()).toBeVisible();
}

/**
 * Adds a quantitative criterion (graded on a 0-5 scale, with a comment) to a rating grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} rgid volume id of the grid, 0 for the default grid
 * @param {Object} criterion
 * @param {string} criterion.title
 * @param {string} criterion.description
 * @param {'public'|'contributor'|'editors'} [criterion.visibility='public']
 */
async function addQuantitativeCriterion(page, rgid, { title, description, visibility = 'public' }) {
    await page.goto(`grid/addcriterion?rgid=${rgid}`);
    await page.locator('#critere').fill(title);
    await page.locator('#description').fill(description);
    await page.locator('#visibility').selectOption(visibility);
    await page.locator('#evaluation_type').selectOption('quantitative');
    await page.locator('#quantitative_rating_type').selectOption('2'); // 0-5 scale
    await page.locator('#comment').selectOption('1');
    await page.locator('#submit').click();
    await expect(page).toHaveURL(/grid\/list/);
}

module.exports = {
    MINIMAL_PDF,
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    openPaperManagement,
    statusPanel,
    applyDecision,
    inviteKnownReviewer,
    inviteNewReviewer,
    completeReview,
    createSection,
    createVolume,
    assignMasterVolume,
    createVolumeGrid,
    addQuantitativeCriterion,
};
