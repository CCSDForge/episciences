/**
 * Tests for the journal settings page (review/settings)
 */

const {
    journalSettingsIsEnabled,
    journalSettingsUpdateDependencies,
    initJournalSettings,
} = require('../../../public/js/review/settings');

function render({ coi = false, canPickEditors = '0', withError = false } = {}) {
    document.body.innerHTML = `
        <p class="journal-settings-unsaved" hidden>Unsaved</p>
        <form id="journal-settings-form" data-unsaved-warning="1">
            <div class="form-group" id="canPickEditors-element">
                <select name="canPickEditors" id="canPickEditors">
                    <option value="0"${canPickEditors === '0' ? ' selected' : ''}>No</option>
                    <option value="1"${canPickEditors === '1' ? ' selected' : ''}>Yes</option>
                </select>
            </div>
            <div class="form-group" id="doNotAllowEditorInChiefSelection-element">
                <input type="hidden" name="doNotAllowEditorInChiefSelection" value="0">
                <input type="checkbox" name="doNotAllowEditorInChiefSelection" id="doNotAllowEditorInChiefSelection" value="1" data-depends-on="canPickEditors">
            </div>
            <div class="form-group" id="isCoiEnabled-element">
                <input type="hidden" name="isCoiEnabled" value="0">
                <input type="checkbox" name="isCoiEnabled" id="isCoiEnabled" value="1"${coi ? ' checked' : ''}>
            </div>
            <div class="form-group" id="coiCommentsToEditorsEnabled-element">
                <input type="checkbox" name="coiCommentsToEditorsEnabled" id="coiCommentsToEditorsEnabled" value="1" data-depends-on="isCoiEnabled">
            </div>
            <div class="form-actions"><button type="submit">Save</button></div>
            <div class="form-group" id="ISSN-element">
                <input type="text" name="ISSN" id="ISSN" value="1234-5678">
                ${withError ? '<ul class="errors"><li>Error</li></ul>' : ''}
            </div>
        </form>`;

    return document.getElementById('journal-settings-form');
}

const isInactive = id =>
    document
        .getElementById(`${id}-element`)
        .classList.contains('journal-settings-inactive');

describe('journalSettingsIsEnabled', () => {
    test('a checked checkbox is enabled', () => {
        render({ coi: true });
        expect(
            journalSettingsIsEnabled(document.getElementById('isCoiEnabled'))
        ).toBe(true);
    });

    test('an unchecked checkbox is disabled', () => {
        render();
        expect(
            journalSettingsIsEnabled(document.getElementById('isCoiEnabled'))
        ).toBe(false);
    });

    test('a select set to "0" is disabled, another value is enabled', () => {
        render();
        const select = document.getElementById('canPickEditors');
        expect(journalSettingsIsEnabled(select)).toBe(false);
        select.value = '1';
        expect(journalSettingsIsEnabled(select)).toBe(true);
    });

    test('without the controlling setting, the setting is enabled', () => {
        expect(journalSettingsIsEnabled(null)).toBe(true);
    });
});

describe('journalSettingsUpdateDependencies', () => {
    test('dims the settings whose controlling setting is disabled', () => {
        const form = render();

        expect(journalSettingsUpdateDependencies(form)).toBe(2);
        expect(isInactive('doNotAllowEditorInChiefSelection')).toBe(true);
        expect(isInactive('coiCommentsToEditorsEnabled')).toBe(true);
    });

    test('does not dim the settings whose controlling setting is enabled', () => {
        const form = render({ coi: true, canPickEditors: '1' });

        expect(journalSettingsUpdateDependencies(form)).toBe(0);
        expect(isInactive('doNotAllowEditorInChiefSelection')).toBe(false);
        expect(isInactive('coiCommentsToEditorsEnabled')).toBe(false);
    });

    test('dimmed settings stay editable', () => {
        const form = render();
        journalSettingsUpdateDependencies(form);

        expect(
            document.getElementById('coiCommentsToEditorsEnabled').disabled
        ).toBe(false);
    });
});

describe('initJournalSettings', () => {
    test('warns about unsaved changes on the other settings pages (licenses, DOI)', () => {
        document.body.innerHTML = `
            <p class="journal-settings-unsaved" hidden>Unsaved</p>
            <form id="doi-settings-form" data-unsaved-warning="1">
                <input type="text" name="doiPrefix" id="doiPrefix" value="10.12345">
                <div class="form-actions"><button type="submit">Save</button></div>
            </form>`;
        const page = initJournalSettings(document, null);
        const warning = document.querySelector('.journal-settings-unsaved');
        const prefix = document.getElementById('doiPrefix');

        expect(warning.parentElement.className).toBe('form-actions');
        prefix.value = '10.46298';
        prefix.dispatchEvent(new Event('input', { bubbles: true }));
        expect(page.isDirty()).toBe(true);
        expect(warning.hidden).toBe(false);
    });

    test('ignores a form without the data-unsaved-warning attribute', () => {
        document.body.innerHTML = '<form id="other"><input name="x"></form>';
        expect(initJournalSettings(document, null)).toBeNull();
    });

    test('does nothing without the form', () => {
        document.body.innerHTML = '<p>Nothing</p>';
        expect(initJournalSettings(document, null)).toBeNull();
    });

    test('follows the changes of the controlling settings', () => {
        render();
        initJournalSettings(document, null);
        const coi = document.getElementById('isCoiEnabled');

        expect(isInactive('coiCommentsToEditorsEnabled')).toBe(true);
        coi.checked = true;
        coi.dispatchEvent(new Event('change', { bubbles: true }));
        expect(isInactive('coiCommentsToEditorsEnabled')).toBe(false);
    });

    test('shows the unsaved changes warning until the changes are undone', () => {
        render();
        const page = initJournalSettings(document, null);
        const warning = document.querySelector('.journal-settings-unsaved');
        const issn = document.getElementById('ISSN');

        expect(page.isDirty()).toBe(false);
        expect(warning.hidden).toBe(true);

        issn.value = '1234-0000';
        issn.dispatchEvent(new Event('input', { bubbles: true }));
        expect(page.isDirty()).toBe(true);
        expect(warning.hidden).toBe(false);

        issn.value = '1234-5678';
        issn.dispatchEvent(new Event('input', { bubbles: true }));
        expect(page.isDirty()).toBe(false);
        expect(warning.hidden).toBe(true);
    });

    test('moves the unsaved changes warning next to the save button', () => {
        render();
        initJournalSettings(document, null);

        expect(
            document.querySelector('.form-actions .journal-settings-unsaved')
        ).not.toBeNull();
    });

    test('asks for a confirmation before leaving with unsaved changes', () => {
        render();
        initJournalSettings(document, window);
        const issn = document.getElementById('ISSN');
        issn.value = '1234-0000';
        issn.dispatchEvent(new Event('input', { bubbles: true }));

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    test('does not ask for a confirmation when the form is submitted', () => {
        const form = render();
        const page = initJournalSettings(document, null);
        const issn = document.getElementById('ISSN');
        issn.value = '1234-0000';
        issn.dispatchEvent(new Event('input', { bubbles: true }));

        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(page.isDirty()).toBe(false);
    });

    test('moves the focus to the first field in error', () => {
        render({ withError: true });
        initJournalSettings(document, null);

        expect(document.activeElement).toBe(document.getElementById('ISSN'));
    });
});
