/**
 * Reviewer suggestions for the search field of /reviewersstats (ARIA 1.2 combobox pattern).
 *
 * Picking a suggestion (Enter on the active option, or a click) opens that reviewer's detail
 * page; submitting the typed text still filters the list. Suggestions follow the form's
 * current period and "only my papers" filters, fetched from /reviewersstats/suggest.
 * All text is inserted with textContent: nothing from the server is parsed as HTML.
 */
const ReviewerSuggest = {
    MIN_LENGTH: 2,
    DELAY_MS: 250,

    init(input) {
        if (!input || !input.dataset.suggestUrl) {
            return null;
        }
        const container = input.closest('.reviewer-suggest');
        const state = {
            input,
            form: input.form,
            list: container.querySelector('[role="listbox"]'),
            status: container.querySelector('[data-suggest-status]'),
            suggestions: [],
            active: -1,
            timer: null,
            controller: null,
        };

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', state.list.id);
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('autocomplete', 'off');

        input.addEventListener('input', () => this.schedule(state));
        input.addEventListener('keydown', event =>
            this.onKeydown(state, event)
        );
        input.addEventListener('blur', () => this.close(state));
        // mousedown (not click) fires before the input's blur closes the list
        state.list.addEventListener('mousedown', event => {
            const option = event.target.closest('[role="option"]');
            if (option) {
                event.preventDefault();
                this.go(state, Number(option.dataset.index));
            }
        });

        return state;
    },

    schedule(state) {
        clearTimeout(state.timer);
        const query = state.input.value.trim();
        if (query.length < this.MIN_LENGTH) {
            this.abort(state);
            this.close(state);
            return;
        }
        state.timer = setTimeout(
            () => this.fetchSuggestions(state, query),
            this.DELAY_MS
        );
    },

    abort(state) {
        if (state.controller) {
            state.controller.abort();
            state.controller = null;
        }
    },

    buildUrl(state, query) {
        const params = new URLSearchParams({ q: query });
        const period = state.form && state.form.elements.namedItem('period');
        const mine =
            state.form &&
            state.form.elements.namedItem('only_my_responsibility');
        if (period && period.value) {
            params.set('period', period.value);
        }
        if (mine && mine.checked) {
            params.set('only_my_responsibility', '1');
        }
        return state.input.dataset.suggestUrl + '?' + params.toString();
    },

    async fetchSuggestions(state, query) {
        // only the latest request counts: an older, slower answer must not overwrite it
        this.abort(state);
        state.controller = new AbortController();
        try {
            const response = await fetch(this.buildUrl(state, query), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                signal: state.controller.signal,
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            this.render(state, await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                // suggestions are a convenience: the plain search keeps working
                this.close(state);
            }
        }
    },

    render(state, suggestions) {
        state.suggestions = Array.isArray(suggestions) ? suggestions : [];
        state.active = -1;
        state.list.replaceChildren(
            ...state.suggestions.map((suggestion, index) =>
                this.renderOption(state, suggestion, index)
            )
        );

        const count = state.suggestions.length;
        state.status.textContent =
            count === 0
                ? state.input.dataset.labelNone
                : count === 1
                  ? state.input.dataset.labelOne
                  : state.input.dataset.labelMany.replace('%d', String(count));

        if (count === 0) {
            this.close(state);
            return;
        }
        state.list.hidden = false;
        state.input.setAttribute('aria-expanded', 'true');
        state.input.removeAttribute('aria-activedescendant');
    },

    renderOption(state, suggestion, index) {
        const option = document.createElement('li');
        option.id = state.list.id + '-' + index;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.dataset.index = String(index);
        option.className = 'reviewer-suggest-option';

        const name = document.createElement('span');
        name.className = 'reviewer-suggest-name';
        name.textContent = suggestion.name;
        option.append(name);

        if (suggestion.overdue > 0) {
            const overdue = document.createElement('span');
            overdue.className = 'reviewer-suggest-overdue text-danger';
            const icon = document.createElement('span');
            icon.className = 'glyphicon glyphicon-alert';
            icon.setAttribute('aria-hidden', 'true');
            overdue.append(icon, ' ' + state.input.dataset.labelOverdue);
            option.append(overdue);
        }

        if (suggestion.email) {
            const email = document.createElement('span');
            email.className = 'reviewer-suggest-email';
            email.textContent = suggestion.email;
            option.append(email);
        }

        return option;
    },

    setActive(state, index) {
        const options = state.list.querySelectorAll('[role="option"]');
        options.forEach((option, i) =>
            option.setAttribute('aria-selected', String(i === index))
        );
        state.active = index;
        if (index >= 0) {
            state.input.setAttribute(
                'aria-activedescendant',
                options[index].id
            );
            options[index].scrollIntoView({ block: 'nearest' });
        } else {
            state.input.removeAttribute('aria-activedescendant');
        }
    },

    onKeydown(state, event) {
        const isOpen = !state.list.hidden;
        const count = state.suggestions.length;

        switch (event.key) {
            case 'ArrowDown':
                if (isOpen) {
                    event.preventDefault();
                    this.setActive(state, (state.active + 1) % count);
                }
                break;
            case 'ArrowUp':
                if (isOpen) {
                    event.preventDefault();
                    this.setActive(
                        state,
                        state.active <= 0 ? count - 1 : state.active - 1
                    );
                }
                break;
            case 'Enter':
                // no active option: let the form submit the typed text (list filter)
                if (isOpen && state.active >= 0) {
                    event.preventDefault();
                    this.go(state, state.active);
                }
                break;
            case 'Escape':
                if (isOpen) {
                    event.preventDefault();
                    this.close(state);
                }
                break;
            default:
                break;
        }
    },

    go(state, index) {
        const suggestion = state.suggestions[index];
        if (suggestion && suggestion.url) {
            this.navigate(suggestion.url);
        }
    },

    navigate(url) {
        window.location.assign(url);
    },

    close(state) {
        clearTimeout(state.timer);
        state.list.hidden = true;
        state.active = -1;
        state.input.setAttribute('aria-expanded', 'false');
        state.input.removeAttribute('aria-activedescendant');
    },
};

if (typeof document !== 'undefined' && typeof module === 'undefined') {
    document.addEventListener('DOMContentLoaded', () =>
        ReviewerSuggest.init(document.querySelector('input[data-suggest-url]'))
    );
}

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReviewerSuggest;
}
