const ConferenceProceeding = require('../../../public/js/volume/confproceeding.js');

const PREFIX = '10.46298/mbj.proceedings.';
const SUFFIX_INVALID = 'Invalid suffix';
const END_BEFORE_START = 'End before start';

/**
 * Same markup as volume/form.phtml + volume/proceedings_fieldset.phtml +
 * volume/doi_proceedings_row.phtml (checked server side by ProceedingsFieldsetViewTest).
 */
function render({
    checked = false,
    status = 'not-assigned',
    suffix = '',
    summary = false,
} = {}) {
    const disabled = checked ? '' : 'disabled';
    const required = checked ? 'required aria-required="true"' : '';
    document.body.innerHTML = `
        <form>
            ${summary ? '<div id="form-error-summary" class="alert alert-danger" role="alert" tabindex="-1"></div>' : ''}
            <div id="is_proceeding-element" class="form-group">
                <input type="checkbox" name="is_proceeding" id="is_proceeding" value="1" aria-controls="conference-fields" ${checked ? 'checked' : ''}>
                <label for="is_proceeding">Proceedings</label>
            </div>
            <fieldset id="conference-fields" ${checked ? '' : 'hidden'} data-text-end-before-start="${END_BEFORE_START}">
                <legend>Conference and proceedings</legend>
                <div id="conference_name-element" class="form-group">
                    <label for="conference_name" class="required">Name</label>
                    <input type="text" id="conference_name" name="conference_name" data-proceedings-required ${required} ${disabled}>
                </div>
                <div id="conference_acronym-element" class="form-group">
                    <label for="conference_acronym" class="optional">Acronym</label>
                    <input type="text" id="conference_acronym" name="conference_acronym" aria-describedby="conference_acronym-help" ${disabled}>
                    <p id="conference_acronym-help" class="help-block">e.g. MFPS</p>
                </div>
                <div id="conference_start-element" class="form-group">
                    <label for="conference_start" class="required">Start</label>
                    <input type="date" id="conference_start" name="conference_start" data-proceedings-required aria-describedby="conference_start-help" ${required} ${disabled}>
                    <p id="conference_start-help" class="help-block">YYYY-MM-DD</p>
                </div>
                <div id="conference_end-element" class="form-group">
                    <label for="conference_end" class="required">End</label>
                    <input type="date" id="conference_end" name="conference_end" data-proceedings-required aria-describedby="conference_end-help" ${required} ${disabled}>
                    <p id="conference_end-help" class="help-block">YYYY-MM-DD</p>
                </div>
                <div id="conference_doi_group" class="form-group" data-prefix="${PREFIX}" data-status="${status}"
                     data-text-preview="DOI to deposit: %s" data-text-empty="No DOI" data-text-invalid="${SUFFIX_INVALID}">
                    <label for="conference_proceedings_doi">Proceedings DOI</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-addon" id="conference_proceedings_doi-prefix">${PREFIX}</span>
                        <input type="text" id="conference_proceedings_doi" name="conference_proceedings_doi" value="${suffix}"
                               maxlength="20" pattern="[A-Za-z0-9._\\-]{1,20}" autocomplete="off"
                               aria-describedby="conference_proceedings_doi-prefix conference_proceedings_doi-help conference_proceedings_doi-preview"
                               ${status === 'requested' || status === 'public' ? 'readonly' : ''} ${disabled}>
                    </div>
                    <p id="conference_proceedings_doi-help" class="help-block">Help</p>
                    <p id="conference_proceedings_doi-preview" class="help-block" aria-live="polite">initial</p>
                </div>
            </fieldset>
        </form>
    `;
}

const byId = id => document.getElementById(id);

function fire(element, type) {
    element.dispatchEvent(new Event(type, { bubbles: true }));
}

describe('ConferenceProceeding', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('pure helpers', () => {
        test('buildDoi joins the prefix and the trimmed suffix', () => {
            expect(ConferenceProceeding.buildDoi(PREFIX, ' 51 ')).toBe(
                `${PREFIX}51`
            );
            expect(ConferenceProceeding.buildDoi(PREFIX, '  ')).toBe('');
        });

        test.each([
            ['51', true],
            ['mfps.xl_2024-a', true],
            ['a'.repeat(20), true],
            ['a'.repeat(21), false],
            ['', false],
            ['é51', false],
            ['51/2', false],
            ['51 2', false],
        ])(
            'isSuffixValid(%p) is %p, as the server rule',
            (suffix, expected) => {
                expect(ConferenceProceeding.isSuffixValid(suffix)).toBe(
                    expected
                );
            }
        );

        test('the suffix rule matches the pattern attribute of the input', () => {
            render();
            const pattern = new RegExp(
                `^(?:${byId('conference_proceedings_doi').getAttribute('pattern')})$`
            );
            ['51', 'a.b-c_d', 'é', 'a'.repeat(21)].forEach(suffix => {
                expect(pattern.test(suffix)).toBe(
                    ConferenceProceeding.isSuffixValid(suffix)
                );
            });
        });

        test('only requested and public DOIs are locked', () => {
            expect(ConferenceProceeding.isLocked('requested')).toBe(true);
            expect(ConferenceProceeding.isLocked('public')).toBe(true);
            expect(ConferenceProceeding.isLocked('assigned')).toBe(false);
            expect(ConferenceProceeding.isLocked('not-assigned')).toBe(false);
        });
    });

    describe('proceedings checkbox', () => {
        test('checking shows the fields and requires name and both dates', () => {
            render();
            ConferenceProceeding.init(document);
            const checkbox = byId('is_proceeding');

            checkbox.checked = true;
            fire(checkbox, 'change');

            expect(byId('conference-fields').hidden).toBe(false);
            expect(checkbox.getAttribute('aria-expanded')).toBe('true');
            ['conference_name', 'conference_start', 'conference_end'].forEach(
                id => {
                    expect(byId(id).required).toBe(true);
                    expect(byId(id).getAttribute('aria-required')).toBe('true');
                    expect(byId(id).disabled).toBe(false);
                }
            );
            expect(byId('conference_acronym').required).toBe(false);
        });

        test('unchecking removes required from all three fields and disables every hidden input', () => {
            render({ checked: true });
            ConferenceProceeding.init(document);
            const checkbox = byId('is_proceeding');

            checkbox.checked = false;
            fire(checkbox, 'change');

            expect(byId('conference-fields').hidden).toBe(true);
            expect(checkbox.getAttribute('aria-expanded')).toBe('false');
            ['conference_name', 'conference_start', 'conference_end'].forEach(
                id => {
                    expect(byId(id).required).toBe(false);
                }
            );
            document
                .querySelectorAll('#conference-fields input')
                .forEach(input => {
                    expect(input.disabled).toBe(true);
                });
            expect(document.querySelector('form').checkValidity()).toBe(true);
        });

        test('init applies the state of an already checked box', () => {
            render({ checked: true });
            ConferenceProceeding.init(document);

            expect(byId('is_proceeding').getAttribute('aria-expanded')).toBe(
                'true'
            );
            expect(
                byId('conference_doi_group').closest('fieldset').hidden
            ).toBe(false);
        });
    });

    describe('DOI suffix', () => {
        test('typing shows the resulting DOI in the live region', () => {
            render({ checked: true });
            ConferenceProceeding.init(document);
            const input = byId('conference_proceedings_doi');

            input.value = '51';
            fire(input, 'input');
            expect(byId('conference_proceedings_doi-preview').textContent).toBe(
                `DOI to deposit: ${PREFIX}51`
            );

            input.value = '';
            fire(input, 'input');
            expect(byId('conference_proceedings_doi-preview').textContent).toBe(
                'No DOI'
            );
        });

        test('an invalid suffix is reported on change, linked to the input, and cleared once fixed', () => {
            render({ checked: true });
            ConferenceProceeding.init(document);
            const input = byId('conference_proceedings_doi');

            input.value = 'é/51';
            fire(input, 'input');
            expect(byId('conference_proceedings_doi-error')).toBeNull();

            fire(input, 'change');
            const error = byId('conference_proceedings_doi-error');
            expect(error.textContent).toBe(SUFFIX_INVALID);
            expect(input.getAttribute('aria-invalid')).toBe('true');
            expect(input.getAttribute('aria-describedby')).toContain(
                'conference_proceedings_doi-error'
            );
            expect(
                byId('conference_doi_group').classList.contains('has-error')
            ).toBe(true);

            input.value = '51';
            fire(input, 'blur');
            expect(byId('conference_proceedings_doi-error')).toBeNull();
            expect(input.hasAttribute('aria-invalid')).toBe(false);
            expect(input.getAttribute('aria-describedby')).not.toContain(
                'conference_proceedings_doi-error'
            );
        });

        test('a server error is not removed by the client check', () => {
            render({ checked: true, suffix: '51' });
            const server = document.createElement('p');
            server.id = 'conference_proceedings_doi-error';
            server.textContent = 'Already used';
            byId('conference_doi_group').appendChild(server);
            ConferenceProceeding.init(document);

            fire(byId('conference_proceedings_doi'), 'blur');

            expect(byId('conference_proceedings_doi-error').textContent).toBe(
                'Already used'
            );
        });

        test.each(['requested', 'public'])(
            'a %s DOI stays read-only, posted, and its preview is kept',
            status => {
                render({ checked: true, status, suffix: '51' });
                ConferenceProceeding.init(document);
                const input = byId('conference_proceedings_doi');

                input.value = 'changed';
                fire(input, 'input');

                expect(input.readOnly).toBe(true);
                expect(input.disabled).toBe(false);
                expect(
                    new FormData(document.querySelector('form')).get(
                        'conference_proceedings_doi'
                    )
                ).toBe('changed');
                expect(
                    byId('conference_proceedings_doi-preview').textContent
                ).toBe('initial');
            }
        );
    });

    describe('conference dates', () => {
        test('an end date before the start date is reported on the end date', () => {
            render({ checked: true });
            ConferenceProceeding.init(document);
            byId('conference_start').value = '2026-10-27';
            const end = byId('conference_end');

            end.value = '2026-10-20';
            fire(end, 'change');
            expect(byId('conference_end-error').textContent).toBe(
                END_BEFORE_START
            );
            expect(end.getAttribute('aria-describedby')).toBe(
                'conference_end-help conference_end-error'
            );

            end.value = '2026-10-27';
            fire(end, 'blur');
            expect(byId('conference_end-error')).toBeNull();
            expect(end.getAttribute('aria-describedby')).toBe(
                'conference_end-help'
            );
        });
    });

    describe('error summary', () => {
        test('init focuses the error summary after a refused submit', () => {
            render({ summary: true });
            ConferenceProceeding.init(document);

            expect(document.activeElement).toBe(byId('form-error-summary'));
        });

        test('init does nothing without summary nor proceedings markup', () => {
            document.body.innerHTML = '<form></form>';

            expect(() => ConferenceProceeding.init(document)).not.toThrow();
        });
    });
});
