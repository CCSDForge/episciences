/**
 * Invitation page, step 1 (git #900): one search for the person to invite, the reviewers of the journal page by page,
 * and the invitation of a new person. Answers come from administratepaper/searchreviewers.
 *
 * Relies on invitereviewer-modal.js, loaded before: setInvitationValues(), step2(), reviewerWorkloadItems(),
 * workloadPaperLink(), translate().
 */

/** Delay before searching while the editor types, in milliseconds */
const REVIEWER_SEARCH_DELAY = 300;
/** Shortest term searched (ReviewerSearch::MIN_LENGTH) */
const REVIEWER_SEARCH_MIN_LENGTH = 2;
/** Papers listed per person in the results */
const REVIEWER_SEARCH_PAPERS = 3;

const REVIEWER_SEARCH_GROUPS = [
    ['journal', 'Relecteurs de la revue'],
    ['accounts', 'Autres comptes Episciences'],
    ['without_account', 'Déjà invités sans compte'],
];

const REVIEWER_SEARCH_REFUSALS = {
    already_invited: 'Déjà invité sur cette version',
    author: "Auteur de l'article",
    self: 'Vous-même',
    conflict: "Conflit d'intérêts déclaré",
};

/**
 * @param {number|string} docid
 * @param {Object<string, string|number>} params term, or browse, page and order
 * @returns {string}
 */
function reviewerSearchUrl(docid, params) {
    const query = new URLSearchParams({ docid: String(docid), ...params });
    return '/administratepaper/searchreviewers?' + query.toString();
}

/**
 * @param {number|string} docid
 * @param {Object<string, string|number>} params
 * @param {AbortSignal} [signal]
 * @returns {Promise<Object>}
 */
async function fetchReviewerCandidates(docid, params, signal) {
    const response = await fetch(reviewerSearchUrl(docid, params), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error('Search failed: ' + response.status);
    }

    return response.json();
}

/**
 * The requests already made to a person, at most REVIEWER_SEARCH_PAPERS papers listed, as in the known reviewers
 * table of git #320
 * @param {Object|null} workload
 * @returns {HTMLElement}
 */
function reviewerSearchWorkloadCell(workload) {
    const cell = document.createElement('td');

    if (!workload) {
        const never = document.createElement('span');
        never.className = 'text-muted';
        never.textContent = translate('Jamais invité');
        cell.appendChild(never);
        return cell;
    }

    const papers = workload.papers || [];
    const shown = {
        ...workload,
        papers: papers.slice(0, REVIEWER_SEARCH_PAPERS),
        other_papers:
            (workload.other_papers || 0) +
            Math.max(0, papers.length - REVIEWER_SEARCH_PAPERS),
    };
    const list = document.createElement('div');
    renderReviewerWorkload(list, reviewerWorkloadItems(shown));
    // the title of the alert is not needed in a table cell
    list.querySelector('strong')?.remove();
    list.hidden = false;
    cell.appendChild(list);

    return cell;
}

/**
 * @param {Object} person a person of administratepaper/searchreviewers
 * @returns {HTMLTableRowElement}
 */
function reviewerSearchRow(person) {
    const row = document.createElement('tr');
    row.dataset.person = person.key;
    const name = person.full_name || person.email;

    for (const text of [name, person.email, person.username || '']) {
        const cell = document.createElement('td');
        cell.textContent = text;
        row.appendChild(cell);
    }

    row.appendChild(reviewerSearchWorkloadCell(person.workload));

    const action = document.createElement('td');
    action.className = 'text-right';

    if (person.refusal) {
        const reason = document.createElement('span');
        reason.className = 'text-muted';
        reason.textContent = translate(
            REVIEWER_SEARCH_REFUSALS[person.refusal] || person.refusal
        );
        action.appendChild(reason);
    } else {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary btn-xs';
        button.textContent = translate('Inviter');
        button.setAttribute(
            'aria-label',
            translate('Inviter %s').replace('%s', name)
        );
        button.addEventListener('click', () => inviteReviewerCandidate(person));
        action.appendChild(button);
    }

    row.appendChild(action);

    return row;
}

/**
 * @param {Object[]} people
 * @param {string} caption
 * @returns {HTMLTableElement}
 */
function reviewerSearchTable(people, caption) {
    const table = document.createElement('table');
    table.className = 'table table-condensed';
    const captionElement = document.createElement('caption');
    captionElement.textContent = caption;
    table.appendChild(captionElement);

    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    for (const label of [
        'Nom',
        'Adresse e-mail',
        'Identifiant',
        'Sollicitations dans la revue',
    ]) {
        const th = document.createElement('th');
        th.scope = 'col';
        th.textContent = translate(label);
        headRow.appendChild(th);
    }
    const actions = document.createElement('th');
    actions.scope = 'col';
    const actionsLabel = document.createElement('span');
    actionsLabel.className = 'sr-only';
    actionsLabel.textContent = translate('Actions');
    actions.appendChild(actionsLabel);
    headRow.appendChild(actions);
    head.appendChild(headRow);
    table.appendChild(head);

    const body = document.createElement('tbody');
    people.forEach(person => body.appendChild(reviewerSearchRow(person)));
    table.appendChild(body);

    return table;
}

/**
 * Shows the groups of a search; returns the number of people found
 * @param {HTMLElement} container
 * @param {Object<string, Object[]>} groups
 * @returns {number}
 */
function renderReviewerSearchGroups(container, groups) {
    container.replaceChildren();
    let found = 0;

    for (const [group, label] of REVIEWER_SEARCH_GROUPS) {
        const people = (groups && groups[group]) || [];
        if (people.length === 0) {
            continue;
        }

        found += people.length;
        const heading = document.createElement('h3');
        heading.className = 'h5';
        heading.textContent = translate(label) + ' (' + people.length + ')';
        container.append(
            heading,
            reviewerSearchTable(people, translate(label))
        );
    }

    return found;
}

/**
 * Opens the invitation message for a person found
 * @param {Object} person
 */
function inviteReviewerCandidate(person) {
    const user = { ...person.user };
    setInvitationValues(user, user.invitation_type);
    step2();
}

/**
 * The person to invite typed in the new person form, or the errors to fix
 * @param {{email: string, firstname: string, lastname: string, locale: string}} values
 * @returns {{user: (Object|null), errors: string[]}}
 */
function newPersonFromForm(values) {
    const email = values.email.trim();
    const firstname = values.firstname.trim();
    const lastname = values.lastname.trim();
    const errors = [];

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push(translate('Veuillez saisir une adresse e-mail valide'));
    }
    if (lastname === '') {
        errors.push(translate('Veuillez saisir un nom'));
    }
    if (errors.length > 0) {
        return { user: null, errors };
    }

    const fullName = (firstname + ' ' + lastname).trim();

    return {
        user: {
            id: null,
            email,
            firstname,
            lastname,
            full_name: fullName,
            screen_name: fullName,
            user_name: '',
            locale: values.locale,
            invitation_type: 3,
        },
        errors,
    };
}

/**
 * Initialises the search, the browsable list and the new person form of the page
 * @param {HTMLElement} root #reviewer-search
 */
function initReviewerSearch(root) {
    const docid = root.dataset.docid;
    initReviewerSearchTerm(root, docid);
    initReviewerBrowse(root, docid);
    initNewPerson(root, docid);
}

/**
 * @param {HTMLElement} root
 * @param {string} docid
 */
function initReviewerSearchTerm(root, docid) {
    const input = root.querySelector('#reviewer-search-term');
    const status = root.querySelector('#reviewer-search-status');
    const results = root.querySelector('#reviewer-search-results');
    let timer = null;
    let controller = null;

    root.querySelector('#reviewer-search-form').addEventListener(
        'submit',
        event => event.preventDefault()
    );

    input.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        const term = input.value.trim();

        if (term.length < REVIEWER_SEARCH_MIN_LENGTH) {
            results.replaceChildren();
            status.textContent = '';
            return;
        }

        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const answer = await fetchReviewerCandidates(
                    docid,
                    { term },
                    controller.signal
                );
                const found = renderReviewerSearchGroups(
                    results,
                    answer.groups
                );
                status.textContent =
                    found > 0
                        ? translate('Personnes trouvées : %d').replace(
                              '%d',
                              String(found)
                          )
                        : translate(
                              'Aucune personne trouvée : vous pouvez inviter une nouvelle personne.'
                          );
            } catch (error) {
                if (error.name !== 'AbortError') {
                    status.textContent = translate(
                        'La recherche a échoué. Veuillez réessayer.'
                    );
                }
            }
        }, REVIEWER_SEARCH_DELAY);
    });
}

/**
 * @param {HTMLElement} root
 * @param {string} docid
 */
function initReviewerBrowse(root, docid) {
    const details = root.querySelector('#reviewer-browse');
    const order = root.querySelector('#reviewer-browse-order');
    const status = root.querySelector('#reviewer-browse-status');
    const results = root.querySelector('#reviewer-browse-results');
    const pages = root.querySelector('#reviewer-browse-pages');
    let page = 1;

    const load = async () => {
        try {
            const answer = await fetchReviewerCandidates(docid, {
                browse: 1,
                page,
                order: order.value,
            });
            const last = Math.max(1, Math.ceil(answer.total / answer.per_page));
            results.replaceChildren(
                reviewerSearchTable(
                    answer.people,
                    translate('Relecteurs de la revue')
                )
            );
            status.textContent = translate(
                'Relecteurs de la revue : %d'
            ).replace('%d', String(answer.total));
            pages.hidden = last <= 1;
            root.querySelector('#reviewer-browse-page').textContent = translate(
                'Page %1$d sur %2$d'
            )
                .replace('%1$d', String(page))
                .replace('%2$d', String(last));
            root.querySelector('#reviewer-browse-previous').disabled =
                page <= 1;
            root.querySelector('#reviewer-browse-next').disabled = page >= last;
        } catch {
            status.textContent = translate(
                'La recherche a échoué. Veuillez réessayer.'
            );
        }
    };

    details.addEventListener('toggle', () => {
        if (details.open && results.childElementCount === 0) {
            load();
        }
    });
    order.addEventListener('change', () => {
        page = 1;
        load();
    });
    root.querySelector('#reviewer-browse-previous').addEventListener(
        'click',
        () => {
            page = Math.max(1, page - 1);
            load();
        }
    );
    root.querySelector('#reviewer-browse-next').addEventListener(
        'click',
        () => {
            page += 1;
            load();
        }
    );
}

/**
 * @param {HTMLElement} root
 * @param {string} docid
 */
function initNewPerson(root, docid) {
    const toggle = root.querySelector('#new-person-toggle');
    const form = root.querySelector('#new-person');
    const errors = root.querySelector('#new-person-errors');
    const existing = root.querySelector('#new-person-existing');
    const field = id => root.querySelector('#' + id);

    toggle.addEventListener('click', () => {
        form.hidden = !form.hidden;
        toggle.setAttribute('aria-expanded', String(!form.hidden));
        const term = field('reviewer-search-term').value.trim();
        if (
            !form.hidden &&
            term.includes('@') &&
            field('new-person-email').value === ''
        ) {
            field('new-person-email').value = term;
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        existing.replaceChildren();
        const { user, errors: messages } = newPersonFromForm({
            email: field('new-person-email').value,
            firstname: field('new-person-firstname').value,
            lastname: field('new-person-lastname').value,
            locale: field('new-person-locale').value,
        });
        errors.textContent = messages.join(' ');
        field('new-person-email').setAttribute(
            'aria-invalid',
            String(
                messages.includes(
                    translate('Veuillez saisir une adresse e-mail valide')
                )
            )
        );
        field('new-person-lastname').setAttribute(
            'aria-invalid',
            String(messages.includes(translate('Veuillez saisir un nom')))
        );

        if (!user) {
            return;
        }

        // an account may already use this address: invite it rather than create a duplicate
        try {
            const answer = await fetchReviewerCandidates(docid, {
                term: user.email,
            });
            const accounts = [
                ...(answer.groups.journal || []),
                ...(answer.groups.accounts || []),
            ];
            if (accounts.length > 0) {
                const notice = document.createElement('p');
                notice.className = 'alert alert-warning';
                notice.textContent = translate(
                    'Un compte utilise déjà cette adresse : invitez ce compte.'
                );
                existing.append(
                    notice,
                    reviewerSearchTable(accounts, notice.textContent)
                );
                return;
            }
        } catch {
            errors.textContent = translate(
                'La recherche a échoué. Veuillez réessayer.'
            );
            return;
        }

        setInvitationValues(user, 3);
        step2();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('reviewer-search');
    if (root) {
        initReviewerSearch(root);
    }
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        reviewerSearchUrl,
        fetchReviewerCandidates,
        reviewerSearchWorkloadCell,
        reviewerSearchRow,
        reviewerSearchTable,
        renderReviewerSearchGroups,
        inviteReviewerCandidate,
        newPersonFromForm,
        initReviewerSearch,
    };
}
