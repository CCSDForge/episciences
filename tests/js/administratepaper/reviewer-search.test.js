'use strict';

/**
 * Invitation page, step 1 in one search (git #900): public/js/administratepaper/reviewer-search.js
 */

global.translate = jest.fn((s) => s);
global.setInvitationValues = jest.fn();
global.step2 = jest.fn();
global.reviewerWorkloadItems = jest.fn((workload) =>
    (workload.papers || []).map((paper) => ({ text: ' paper', paper, paperFirst: true }))
);
global.renderReviewerWorkload = jest.fn((element, items) => {
    element.replaceChildren(...items.map((item) => {
        const li = document.createElement('li');
        li.textContent = '#' + item.paper.docid + item.text;
        return li;
    }));
});

function page() {
    document.body.innerHTML = `
        <div id="reviewer-search" data-docid="12">
            <form id="reviewer-search-form"><input id="reviewer-search-term"></form>
            <p id="reviewer-search-status"></p>
            <div id="reviewer-search-results"></div>
            <details id="reviewer-browse"><summary>Browse</summary>
                <select id="reviewer-browse-order"><option value="name">Name</option><option value="least_requested">Least</option></select>
                <p id="reviewer-browse-status"></p><div id="reviewer-browse-results"></div>
                <nav id="reviewer-browse-pages" hidden><button id="reviewer-browse-previous"></button><span id="reviewer-browse-page"></span><button id="reviewer-browse-next"></button></nav>
            </details>
            <button id="new-person-toggle" aria-expanded="false"></button>
            <form id="new-person" hidden>
                <input id="new-person-email"><input id="new-person-firstname"><input id="new-person-lastname">
                <select id="new-person-locale"><option value="en" selected>English</option></select>
                <div id="new-person-errors"></div><div id="new-person-existing"></div>
                <button type="submit" id="new-person-submit"></button>
            </form>
        </div>`;
}

function person(overrides = {}) {
    return {
        key: 'uid:7', group: 'journal', uid: 7, firstname: 'Ada', lastname: 'Lovelace', full_name: 'Ada Lovelace',
        email: 'ada@example.org', username: 'ada', refusal: null, workload: null,
        user: { id: 7, email: 'ada@example.org', full_name: 'Ada Lovelace', locale: 'en', invitation_type: 1 },
        ...overrides,
    };
}

function answer(json) {
    return Promise.resolve({ ok: true, json: () => Promise.resolve(json) });
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('reviewer-search.js', () => {
    let search;

    beforeEach(() => {
        jest.resetModules();
        jest.clearAllMocks();
        page();
        global.fetch = jest.fn();
        search = require('../../../public/js/administratepaper/reviewer-search');
    });

    afterEach(() => {
        jest.useRealTimers();
        delete global.fetch;
    });

    test('the search URL carries the paper and the term', () => {
        expect(search.reviewerSearchUrl(12, { term: 'ada lo' })).toBe(
            '/administratepaper/searchreviewers?docid=12&term=ada+lo'
        );
    });

    test('a person who may be invited has a button that opens the invitation message', () => {
        const row = search.reviewerSearchRow(person());
        const button = row.querySelector('button');

        expect(button.getAttribute('aria-label')).toBe('Inviter %s'.replace('%s', 'Ada Lovelace'));
        button.click();

        expect(global.setInvitationValues).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }), 1);
        expect(global.step2).toHaveBeenCalled();
    });

    test('a person who may not be invited shows the reason instead of a button', () => {
        const row = search.reviewerSearchRow(person({ refusal: 'already_invited' }));

        expect(row.querySelector('button')).toBeNull();
        expect(row.textContent).toContain('Déjà invité sur cette version');
    });

    test('names are written as text', () => {
        const row = search.reviewerSearchRow(person({ full_name: '<img src=x>' }));

        expect(row.querySelector('img')).toBeNull();
        expect(row.textContent).toContain('<img src=x>');
    });

    test('a person never asked is said so; the table lists three papers at most', () => {
        expect(search.reviewerSearchWorkloadCell(null).textContent).toBe('Jamais invité');

        const papers = [1, 2, 3, 4, 5].map((docid) => ({ docid, url: '/x', kind: 'review', date_label: '' }));
        search.reviewerSearchWorkloadCell({ papers, other_papers: 1 });

        expect(global.reviewerWorkloadItems).toHaveBeenCalledWith(
            expect.objectContaining({ other_papers: 3, papers: papers.slice(0, 3) })
        );
    });

    test('the groups found are shown with their number, the empty ones left out', () => {
        const container = document.getElementById('reviewer-search-results');

        const found = search.renderReviewerSearchGroups(container, {
            journal: [person()],
            accounts: [],
            without_account: [person({ key: 'email:x@example.org', group: 'without_account' })],
        });

        expect(found).toBe(2);
        expect([...container.querySelectorAll('h3')].map((h) => h.textContent)).toEqual([
            'Relecteurs de la revue (1)',
            'Déjà invités sans compte (1)',
        ]);
        expect(container.querySelectorAll('th[scope="col"]')).toHaveLength(10);
    });

    test('a new person needs a valid address and a name', () => {
        expect(search.newPersonFromForm({ email: 'nope', firstname: '', lastname: '', locale: 'en' }).errors).toEqual([
            'Veuillez saisir une adresse e-mail valide',
            'Veuillez saisir un nom',
        ]);

        const { user } = search.newPersonFromForm({ email: ' jane@example.org ', firstname: 'Jane', lastname: 'Doe', locale: 'en' });
        expect(user).toMatchObject({ id: null, email: 'jane@example.org', full_name: 'Jane Doe', invitation_type: 3 });
    });

    test('typing searches after a short delay, a term too short does not', async () => {
        jest.useFakeTimers();
        global.fetch.mockReturnValue(answer({ groups: { journal: [person()], accounts: [], without_account: [] } }));
        search.initReviewerSearch(document.getElementById('reviewer-search'));
        const input = document.getElementById('reviewer-search-term');

        input.value = 'a';
        input.dispatchEvent(new Event('input'));
        jest.advanceTimersByTime(500);
        expect(global.fetch).not.toHaveBeenCalled();

        input.value = 'ada';
        input.dispatchEvent(new Event('input'));
        jest.advanceTimersByTime(300);
        jest.useRealTimers();
        await flush();
        await flush();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(document.getElementById('reviewer-search-status').textContent).toBe('Personnes trouvées : 1');
        expect(document.querySelectorAll('#reviewer-search-results tbody tr')).toHaveLength(1);
    });

    test('a new person whose address has an account: the account is offered, nobody is invited yet', async () => {
        global.fetch.mockReturnValue(answer({ groups: { journal: [person()], accounts: [], without_account: [] } }));
        search.initReviewerSearch(document.getElementById('reviewer-search'));
        document.getElementById('new-person-email').value = 'ada@example.org';
        document.getElementById('new-person-lastname').value = 'Lovelace';

        document.getElementById('new-person').dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();

        expect(document.getElementById('new-person-existing').textContent).toContain('Un compte utilise déjà cette adresse');
        expect(global.setInvitationValues).not.toHaveBeenCalled();
    });

    test('a new person without an account goes to the invitation message', async () => {
        global.fetch.mockReturnValue(answer({ groups: { journal: [], accounts: [], without_account: [] } }));
        search.initReviewerSearch(document.getElementById('reviewer-search'));
        document.getElementById('new-person-email').value = 'new@example.org';
        document.getElementById('new-person-lastname').value = 'Newcomer';

        document.getElementById('new-person').dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();

        expect(global.setInvitationValues).toHaveBeenCalledWith(expect.objectContaining({ email: 'new@example.org', id: null }), 3);
        expect(global.step2).toHaveBeenCalled();
    });

    test('an invalid new person is pointed out on the faulty field, without searching', () => {
        search.initReviewerSearch(document.getElementById('reviewer-search'));
        document.getElementById('new-person-email').value = 'new@example.org';

        document.getElementById('new-person').dispatchEvent(new Event('submit', { cancelable: true }));

        expect(document.getElementById('new-person-lastname').getAttribute('aria-invalid')).toBe('true');
        expect(document.getElementById('new-person-email').getAttribute('aria-invalid')).toBe('false');
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('opening the browsable list loads the first page, in the chosen order', async () => {
        global.fetch.mockReturnValue(answer({ people: [person()], total: 30, page: 1, per_page: 25, order: 'name' }));
        search.initReviewerSearch(document.getElementById('reviewer-search'));
        const details = document.getElementById('reviewer-browse');

        details.open = true;
        details.dispatchEvent(new Event('toggle'));
        await flush();
        await flush();

        expect(global.fetch.mock.calls[0][0]).toContain('browse=1&page=1&order=name');
        expect(document.getElementById('reviewer-browse-pages').hidden).toBe(false);
        expect(document.getElementById('reviewer-browse-previous').disabled).toBe(true);
        expect(document.getElementById('reviewer-browse-page').textContent).toBe('Page 1 sur 2');
    });

});
