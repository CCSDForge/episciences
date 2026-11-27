const path = require('path');
const { toAppPath } = require('./app-url');
const { expect } = require('@playwright/test');

/**
 * Page helpers shared by the editorial workflow specs (submission, review, decisions, revisions,
 * publication). Each helper drives the real user interface; none of them writes to the database.
 *
 * Papers are always submitted from arXiv. Publishing a paper from HAL sends a COAR Notify
 * announcement to HAL: the suite must never trigger it.
 */

const HAL_REPO_ID = '1';
const ARXIV_REPO_ID = '2';
const REVIEWER_USERNAME = 'e2e_reviewer';
const REVIEWER_EMAIL = 'e2e_reviewer@example.org';
const MINIMAL_PDF = path.resolve(__dirname, '..', 'fixtures', 'files', 'minimal.pdf');

// Fetching metadata from arXiv or HAL goes over the network
const REPOSITORY_TIMEOUT = 60000;
// A repository sometimes answers too slowly: the server gives up and the page displays an error.
// The suite runs without Playwright retries, so the fetch itself is retried.
const REPOSITORY_FETCH_ATTEMPTS = 3;
const REPOSITORY_RETRY_DELAY = 5000;

/**
 * Fetches the document typed in the search form from its repository, retrying on fetch errors,
 * until the submission form is displayed.
 * @param {import('@playwright/test').Page} page author page, on the submission page
 */
async function fetchRepositoryDocument(page) {
    const submitForm = page.locator('#submitForm');
    // A document already submitted to the journal has its own alert (#result_message): not a fetch error
    const fetchError = page.locator('#showResult .alert-danger:not(#result_message)');

    for (let attempt = 1; attempt <= REPOSITORY_FETCH_ATTEMPTS; attempt++) {
        // A new search replaces the previous error with a loader
        await page.locator('#search_doc-getPaper').click();
        await expect(submitForm.or(fetchError).first()).toBeVisible({ timeout: REPOSITORY_TIMEOUT });

        if ((await submitForm.isVisible()) || attempt === REPOSITORY_FETCH_ATTEMPTS) {
            break;
        }
        await new Promise(resolve => setTimeout(resolve, REPOSITORY_RETRY_DELAY));
    }

    await expect(submitForm).toBeVisible();
}

/**
 * Submits a preprint from a repository as the logged-in author.
 * @param {import('@playwright/test').Page} page author page
 * @param {Object} options
 * @param {string} options.repoId repository identifier
 * @param {string} options.docId document identifier, without version
 * @param {number} [options.version=1]
 * @param {string} [options.sectionTitle] section to submit in, when the journal lets authors pick one
 * @returns {Promise<number>} docid of the submitted paper
 */
async function submitRepositoryPaper(page, { repoId, docId, version = 1, sectionTitle }) {
    await page.goto('submit');
    await page.locator('#search_doc-repoId').selectOption(repoId);
    await page.locator('#search_doc-docId').fill(docId);
    await page.locator('#search_doc-version').fill(String(version));
    await fetchRepositoryDocument(page);

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

    await expect(page).toHaveURL(/\/paper\/view(?:\/id\/|\?id=)\d+/, { timeout: REPOSITORY_TIMEOUT });
    return Number(page.url().match(/\/paper\/view(?:\/id\/|\?id=)(\d+)/)[1]);
}

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
    return submitRepositoryPaper(page, { repoId: ARXIV_REPO_ID, docId: arxivId, version, sectionTitle });
}

/**
 * Submits a HAL preprint as the logged-in author.
 * Never publish a paper submitted with this helper: publishing a HAL paper sends a COAR Notify
 * announcement to HAL.
 * @param {import('@playwright/test').Page} page author page
 * @param {Object} options
 * @param {string} options.halId HAL identifier, without version (e.g. "hal-05428815")
 * @param {number} [options.version=1]
 * @param {string} [options.sectionTitle] section to submit in, when the journal lets authors pick one
 * @returns {Promise<number>} docid of the submitted paper
 */
async function submitHalPaper(page, { halId, version = 1, sectionTitle }) {
    return submitRepositoryPaper(page, { repoId: HAL_REPO_ID, docId: halId, version, sectionTitle });
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {number} docid
 */
async function openPaperManagement(page, docid) {
    await page.goto(`administratepaper/view/id/${docid}`);
    // Journal setting (isCoiEnabled, E2E_COI=1): the editorial team must declare the absence of
    // conflict of interest before managing a paper. One answer covers every version of the paper.
    if (/\/coi\/report/.test(page.url())) {
        await declareNoConflict(page, docid);
    }
    return page.locator('.panel:has(h2:has-text("Article status"))');
}

/**
 * Answers "no conflict of interest" on the declaration page the editorial team is redirected to.
 * @param {import('@playwright/test').Page} page editorial page showing /coi/report
 * @param {number} docid
 */
async function declareNoConflict(page, docid) {
    await page.locator('#coi-no').click();
    await page.waitForURL(new RegExp(`administratepaper/view(/id/|\\?id=)${docid}`));
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
 * Sends the invitation e-mail shown at step 2 of the invitation page.
 * The page fills the message once its TinyMCE editor is initialised, after step 2 is shown:
 * submitting before fails the form validation (empty message, no %%INVITATION_URL%% tag)
 * and the page stays on the form.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} docid
 */
async function submitInvitation(page, docid) {
    await expect(page.locator('#step-2')).toBeVisible();
    // getContent() throws until the editor is initialised, which would end the wait
    await page.waitForFunction(() => {
        const editor = window.tinymce?.get('body');
        return Boolean(editor?.initialized) && editor.getContent().includes('%%INVITATION_URL%%');
    });
    await page.locator('#submit-invitation').click();
    await page.waitForURL(new RegExp(`administratepaper/view\\?id=${docid}`));
}

/**
 * Assigns an editor to a paper from its management page ("Assign editors" form).
 * @param {import('@playwright/test').Page} page secretary or administrator page
 * @param {number} docid
 * @param {string} editorFullName as listed in the form, for instance "E2E Editor"
 */
async function assignEditor(page, docid, editorFullName) {
    await openPaperManagement(page, docid);
    await page.locator('button#editors').click();

    const form = page.locator('form#assign-editors');
    await expect(form).toBeVisible();
    await form.locator('.editors-list label', { hasText: editorFullName }).locator('input[type="checkbox"]').check();

    const saved = page.waitForResponse(response => response.url().includes('/administratepaper/saveeditors'));
    await form.locator('button[name="submit_editors"]').click();
    expect((await (await saved).json()).result).toBeTruthy();
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

    // The table is paged: a journal with many reviewers may not list this one on the first page
    await page.locator('#known-reviewers_filter input').fill(username);
    await page.locator('#known-reviewers tbody tr', { hasText: username }).click();
    await submitInvitation(page, docid);
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
    await submitInvitation(page, docid);
}

/**
 * Opens the invitation link received by a reviewer and accepts the invitation.
 * @param {import('./mailpit-client').MailpitClient} mailpit
 * @param {import('@playwright/test').Page} reviewerPage page logged in as the invited reviewer
 * @param {number} docid
 * @param {string} reviewerEmail address the invitation was sent to
 */
async function acceptReviewInvitation(mailpit, reviewerPage, docid, reviewerEmail) {
    const email = await mailpit.waitForEmail({
        to: reviewerEmail,
        subjectContains: [`#${docid} `, 'You are invited to review an article'],
    });
    const invitationUrl = mailpit.extractLinks(email.Text).find(link => link.includes('/reviewer/invitation/'));
    expect(invitationUrl).toBeTruthy();

    await reviewerPage.goto(toAppPath(invitationUrl));
    await reviewerPage.locator('#submitaccept').click();
    await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));
}

/**
 * Fills the comment fields of the rating grid criteria (TinyMCE editors comment_item_<n>, initialised
 * once the page has loaded). The page may hold other editors, such as the message to the author when
 * the journal lets reviewers write to authors (reviewersCanCommentArticles): they are left untouched.
 * @param {import('@playwright/test').Page} page reviewer or editorial page showing the rating form
 * @param {string|string[]} contents HTML set in every criterion comment, or one HTML per criterion in
 *   the order of the grid
 */
async function fillRatingComments(page, contents) {
    const perCriterion = Array.isArray(contents) ? contents : null;
    await page.waitForFunction(minCount => {
        const editors = (window.tinymce?.get() ?? []).filter(editor => editor.id.startsWith('comment_item_'));
        return editors.length >= minCount && editors.every(editor => editor.initialized);
    }, perCriterion ? perCriterion.length : 1);
    await page.evaluate(
        ([single, many]) => {
            window.tinymce
                .get()
                .filter(editor => editor.id.startsWith('comment_item_'))
                .forEach((editor, index) => editor.setContent(many ? many[index] : single));
        },
        [perCriterion ? null : contents, perCriterion]
    );
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
    // Parallel workers may get here in the same millisecond: the title must still be unique
    vid ??= await createVolume(adminPage, `E2E Review volume ${Date.now()}-${Math.random().toString(36).slice(2, 8)}`);
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

    await reviewerPage.goto(toAppPath(invitationUrl));
    await reviewerPage.locator('#submitaccept').click();
    await expect(reviewerPage).toHaveURL(new RegExp(`paper/rating/id/${docid}`));

    // The grid has a single criterion, graded on a 0-5 scale, with a comment
    const grade = reviewerPage.locator('form#rating-grid select[name^="note_item_"]');
    await expect(grade).toHaveCount(1);
    await grade.selectOption({ label: '4/5' });
    await fillRatingComments(reviewerPage, '<p>E2E review.</p>');
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
    const form = page.locator('form[action$="/grid/add"]');
    await form.locator('#volume').selectOption(String(vid));
    await form.locator('#submit').click();
    await expect(page.getByText('The new review grid has been successfully saved.').first()).toBeVisible();
}

/**
 * Adds a criterion to a rating grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} rgid volume id of the grid, 0 for the default grid
 * @param {Object} criterion
 * @param {string} criterion.title
 * @param {string} criterion.description
 * @param {'quantitative'|'qualitative'|'free'} [criterion.type='quantitative']
 * @param {'public'|'contributor'|'editors'} [criterion.visibility='public']
 * @param {'5'|'10'} [criterion.scale='5'] quantitative scale
 * @param {number} [criterion.coef] quantitative coefficient (1 to 5), 1 by default
 * @param {string[]} [criterion.options] custom values of a qualitative criterion, the standard
 *   Yes/No/Maybe values otherwise
 * @param {boolean} [criterion.comment=true] ask for a comment
 * @param {boolean} [criterion.upload=false] allow attaching a file to this criterion
 */
async function addCriterion(
    page,
    rgid,
    { title, description, type = 'quantitative', visibility = 'public', scale = '5', coef, options, comment = true, upload = false }
) {
    await page.goto(`grid/addcriterion?rgid=${rgid}`);
    await page.locator('#critere').fill(title);
    await page.locator('#description').fill(description);
    await page.locator('#visibility').selectOption(visibility);
    await page.locator('#evaluation_type').selectOption(type);

    if (type === 'quantitative') {
        await page.locator('#quantitative_rating_type').selectOption(scale === '10' ? '0' : '2');
        if (coef) {
            await page.locator('#coef').selectOption(String(coef));
        }
    }

    if (type === 'qualitative') {
        await page.locator('#qualitative_rating_type').selectOption(options ? '1' : '0');
        for (const [index, label] of (options ?? []).entries()) {
            if (index > 0) {
                await page.locator('#add_option').click();
            }
            await page.locator(`#fieldset-options li:nth-child(${index + 1}) input[name^="option_"]:visible`).first().fill(label);
        }
    }

    await page.locator('#comment').selectOption(comment ? '1' : '0');
    await page.locator('#upload').selectOption(upload ? '1' : '0');
    await page.locator('#submit').click();
    await expect(page).toHaveURL(/grid\/list/);
}

/**
 * Adds a quantitative criterion (graded on a 0-5 scale, with a comment) to a rating grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} rgid volume id of the grid, 0 for the default grid
 * @param {Object} criterion
 * @param {string} criterion.title
 * @param {string} criterion.description
 * @param {'public'|'contributor'|'editors'} [criterion.visibility='public']
 * @param {boolean} [criterion.upload=false] allow attaching a file to this criterion
 */
async function addQuantitativeCriterion(page, rgid, { title, description, visibility = 'public', upload = false }) {
    await addCriterion(page, rgid, { title, description, visibility, upload });
}

/**
 * Adds a separator (a heading between criteria) to a rating grid.
 * @param {import('@playwright/test').Page} page editorial page
 * @param {number} rgid volume id of the grid
 * @param {string} title
 */
async function addSeparator(page, rgid, title) {
    await page.goto(`grid/addseparator?rgid=${rgid}&type=separator`);
    await page.locator('#critere').fill(title);
    await page.locator('#visibility').selectOption('public');
    await page.locator('#submit').click();
    await expect(page).toHaveURL(/grid\/list/);
}

module.exports = {
    MINIMAL_PDF,
    REPOSITORY_TIMEOUT,
    submitArxivPaper,
    submitHalPaper,
    openPaperManagement,
    declareNoConflict,
    statusPanel,
    applyDecision,
    submitInvitation,
    assignEditor,
    inviteKnownReviewer,
    inviteNewReviewer,
    acceptReviewInvitation,
    fillRatingComments,
    completeReview,
    createSection,
    createVolume,
    assignMasterVolume,
    createVolumeGrid,
    addCriterion,
    addQuantitativeCriterion,
    addSeparator,
};
