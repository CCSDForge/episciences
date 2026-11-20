/**
 * Journal settings pages (review/settings, review/licenses, doi/settings): forms with a data-unsaved-warning attribute.
 * - warn about unsaved changes (all of them);
 * - show the first field in error (all of them);
 * Journal settings page only:
 * - dim the settings that have no effect with the current value of the setting they depend on
 *   (data-depends-on attribute); they stay editable and are always saved;
 * - show the first field in error;
 * - highlight the current section in the table of contents.
 */

/**
 * Whether the setting controlling a dependent setting is enabled:
 * a checked checkbox, or a select whose value is not "0".
 * @param {HTMLInputElement|HTMLSelectElement|null} control
 * @returns {boolean}
 */
function journalSettingsIsEnabled(control) {
    if (!control) {
        return true;
    }

    if (control.type === 'checkbox') {
        return control.checked;
    }

    return control.value !== '' && control.value !== '0';
}

/**
 * Dim the dependent settings whose controlling setting is disabled.
 * @param {HTMLFormElement} form
 * @returns {number} number of dimmed settings
 */
function journalSettingsUpdateDependencies(form) {
    let inactive = 0;

    form.querySelectorAll('[data-depends-on]').forEach(dependent => {
        const name = dependent.getAttribute('data-depends-on');
        const control = form.querySelector(
            `input[type="checkbox"][name="${name}"], select[name="${name}"]`
        );
        const enabled = journalSettingsIsEnabled(control);
        const group = dependent.closest('.form-group') || dependent;

        group.classList.toggle('journal-settings-inactive', !enabled);

        if (!enabled) {
            inactive++;
        }
    });

    return inactive;
}

/**
 * Serialized values of the form, to know whether it has been changed.
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function journalSettingsSnapshot(form) {
    return new URLSearchParams(new FormData(form)).toString();
}

/**
 * @param {Document|HTMLElement} root
 * @param {Window|null} win
 * @returns {{isDirty: function(): boolean}|null}
 */
function initJournalSettings(
    root = document,
    win = typeof window !== 'undefined' ? window : null
) {
    const form = root.querySelector('form[data-unsaved-warning]');

    if (!form) {
        return null;
    }

    const warning = root.querySelector('.journal-settings-unsaved');
    const actions = form.querySelector('.form-actions');

    // next to the save button, which stays on screen
    if (warning && actions) {
        actions.prepend(warning);
    }

    const initial = journalSettingsSnapshot(form);
    let submitting = false;

    const isDirty = () =>
        !submitting && journalSettingsSnapshot(form) !== initial;

    const onChange = () => {
        journalSettingsUpdateDependencies(form);

        if (warning) {
            warning.hidden = !isDirty();
        }
    };

    form.addEventListener('input', onChange);
    form.addEventListener('change', onChange);
    form.addEventListener('submit', () => {
        submitting = true;
    });

    if (win) {
        win.addEventListener('beforeunload', event => {
            if (isDirty()) {
                event.preventDefault();
                // required by some browsers to show the confirmation
                event.returnValue = '';
            }
        });
    }

    journalSettingsUpdateDependencies(form);

    // after a failed save, go to the first field in error
    const firstError = form.querySelector('.errors, .help-block.error');

    if (firstError) {
        const group = firstError.closest('.form-group') || firstError;
        const field = group.querySelector(
            'input:not([type="hidden"]), select, textarea'
        );

        if (typeof group.scrollIntoView === 'function') {
            group.scrollIntoView({ block: 'center' });
        }

        if (field) {
            field.focus({ preventScroll: true });
        }
    }

    initJournalSettingsToc(root, win);

    return { isDirty };
}

/**
 * Highlight the link of the section shown on screen.
 * @param {Document|HTMLElement} root
 * @param {Window|null} win
 */
function initJournalSettingsToc(root, win) {
    const links = Array.from(
        root.querySelectorAll('.journal-settings-toc a[href^="#"]')
    );

    if (
        links.length === 0 ||
        !win ||
        typeof win.IntersectionObserver !== 'function'
    ) {
        return;
    }

    const byId = new Map(
        links.map(link => [link.getAttribute('href').slice(1), link])
    );

    const observer = new win.IntersectionObserver(
        entries => {
            entries
                .filter(entry => entry.isIntersecting)
                .forEach(entry => {
                    links.forEach(link => link.removeAttribute('aria-current'));
                    const link = byId.get(entry.target.id);

                    if (link) {
                        link.setAttribute('aria-current', 'true');
                    }
                });
        },
        { rootMargin: '0px 0px -70% 0px' }
    );

    byId.forEach((link, id) => {
        const section = root.querySelector(`#${CSS.escape(id)}`);

        if (section) {
            observer.observe(section);
        }
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initJournalSettings());
}

if (typeof module !== 'undefined') {
    module.exports = {
        journalSettingsIsEnabled,
        journalSettingsUpdateDependencies,
        journalSettingsSnapshot,
        initJournalSettings,
    };
}
