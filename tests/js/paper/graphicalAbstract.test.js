'use strict';

/**
 * Tests for public/js/paper/graphicalAbstract.js (illustration or graphical abstract form).
 * FilePond is mocked on window.FilePond, as loaded by the "filepond" webpack entry.
 */

const {
    graphAbsPage,
    createIllustrationPond,
    validateGraphicalAbstractForm,
    submitGraphicalAbstract,
    deleteGraphicalAbstract,
    initGraphicalAbstractForm,
} = require('../../../public/js/paper/graphicalAbstract.js');

function renderForm({ alt = '', withDelete = false } = {}) {
    document.head.innerHTML = '<meta name="csrf-token" content="tok-123">';
    document.body.innerHTML = `
        <form id="f-graph-abs" action="/administrategraphabstract/addgraphabs/"
              data-delete-url="/administrategraphabstract/deletegraphabs/" novalidate>
            <input type="hidden" name="docId" value="42">
            <div id="graph-abs-status" role="status" aria-live="polite"></div>
            <div class="form-group" data-field="illustration_file">
                <label for="illustration_file">Image</label>
                <input type="file" id="illustration_file" name="illustration_file"
                       data-max-file-size="512000"
                       data-accepted-types="image/jpeg,image/png,image/webp,image/gif"
                       aria-describedby="illustration_file-help illustration_file-error">
                <p id="illustration_file-help"></p>
                <p id="illustration_file-error" hidden></p>
            </div>
            <div class="form-group" data-field="illustration_alt">
                <textarea id="illustration_alt" name="illustration_alt">${alt}</textarea>
                <p id="illustration_alt-counter"><span data-counter-value>0</span> / 1000</p>
                <p id="illustration_alt-error" hidden></p>
            </div>
            <div class="form-group" data-field="illustration_license">
                <input type="text" id="illustration_license" name="illustration_license">
                <p id="illustration_license-error" hidden></p>
            </div>
            <button type="submit">Save</button>
            ${withDelete ? '<button type="button" data-graph-abs-delete data-file="graphical_abstract.png">Delete</button>' : ''}
        </form>`;
    return document.getElementById('f-graph-abs');
}

function makePondMock() {
    const handlers = {};
    const element = document.createElement('div');
    element.innerHTML =
        '<input type="file" id="filepond--browser-x" aria-labelledby="filepond--drop-label-x">';
    return {
        element,
        handlers,
        on: jest.fn((name, handler) => {
            handlers[name] = handler;
        }),
        removeFiles: jest.fn(),
    };
}

function mockFetchResponse(ok, body) {
    global.fetch.mockResolvedValueOnce({
        ok,
        json: () => Promise.resolve(body),
    });
}

describe('graphicalAbstract.js', () => {
    let pond;

    beforeEach(() => {
        pond = makePondMock();
        window.FilePond = { create: jest.fn(() => pond) };
        delete global.locale;
        delete window.FilePondLocaleFrFr;
        global.fetch.mockReset();
        window.confirm = jest.fn(() => true);
        graphAbsPage.reload = jest.fn();
    });

    describe('createIllustrationPond', () => {
        it('creates FilePond in storeAsFile mode with the limits of the input', () => {
            renderForm();
            const input = document.getElementById('illustration_file');

            createIllustrationPond(input);

            expect(window.FilePond.create).toHaveBeenCalledWith(
                input,
                expect.objectContaining({
                    storeAsFile: true,
                    allowMultiple: false,
                    credits: false,
                    name: 'illustration_file',
                    maxFileSize: 512000,
                    fileSizeBase: 1024,
                    acceptedFileTypes: [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                    ],
                })
            );
        });

        it('applies the French labels when the page locale is fr', () => {
            renderForm();
            global.locale = 'fr';
            window.FilePondLocaleFrFr = { labelIdle: 'Parcourir' };

            createIllustrationPond(
                document.getElementById('illustration_file')
            );

            expect(window.FilePond.create.mock.calls[0][1].labelIdle).toBe(
                'Parcourir'
            );
        });

        it('keeps the form label and help attached to the FilePond input', () => {
            renderForm();

            createIllustrationPond(
                document.getElementById('illustration_file')
            );

            const label = document.querySelector('label');
            // FilePond renders its input after create() returns
            expect(label.htmlFor).toBe('illustration_file');
            window.FilePond.create.mock.calls[0][1].oninit();

            const browser = pond.element.querySelector('input');
            expect(label.htmlFor).toBe('filepond--browser-x');
            expect(browser.getAttribute('aria-labelledby')).toBe(
                label.id + ' filepond--drop-label-x'
            );
            expect(browser.getAttribute('aria-describedby')).toBe(
                'illustration_file-help illustration_file-error'
            );
        });

        it('falls back on the plain input without FilePond', () => {
            renderForm();
            delete window.FilePond;

            expect(
                createIllustrationPond(
                    document.getElementById('illustration_file')
                )
            ).toBeNull();
        });
    });

    describe('validateGraphicalAbstractForm', () => {
        it('requires a non-blank alternative text', () => {
            const form = renderForm({ alt: '   ' });

            expect(validateGraphicalAbstractForm(form, '')).toEqual({
                illustration_alt: 'Le texte alternatif est obligatoire.',
            });
        });

        it('reports the FilePond error of the picked file', () => {
            const form = renderForm({ alt: 'A chart' });

            expect(
                validateGraphicalAbstractForm(form, 'File is too large')
            ).toEqual({ illustration_file: 'File is too large' });
        });
    });

    describe('submitGraphicalAbstract', () => {
        it('does not send the form and focuses the alt when it is empty', async () => {
            const form = renderForm();

            const sent = await submitGraphicalAbstract(form, { error: '' });

            expect(sent).toBe(false);
            expect(global.fetch).not.toHaveBeenCalled();
            const alt = document.getElementById('illustration_alt');
            expect(alt.getAttribute('aria-invalid')).toBe('true');
            expect(document.activeElement).toBe(alt);
            const error = document.getElementById('illustration_alt-error');
            expect(error.hidden).toBe(false);
            expect(error.textContent).toBe(
                'Le texte alternatif est obligatoire.'
            );
        });

        it('posts the form with the request token as an AJAX call', async () => {
            const form = renderForm({ alt: 'A chart' });
            mockFetchResponse(true, { status: 'success', messages: {} });

            const sent = await submitGraphicalAbstract(form, { error: '' });

            expect(sent).toBe(true);
            expect(graphAbsPage.reload).toHaveBeenCalled();
            const [url, options] = global.fetch.mock.calls[0];
            expect(url).toContain('/administrategraphabstract/addgraphabs/');
            expect(options.method).toBe('POST');
            expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
            expect(options.body.get('csrf_token')).toBe('tok-123');
            expect(options.body.get('docId')).toBe('42');
            expect(options.body.get('illustration_alt')).toBe('A chart');
        });

        it('shows the server errors inline and focuses the first field in error', async () => {
            const form = renderForm({ alt: 'A chart' });
            mockFetchResponse(false, {
                status: 'error',
                messages: { illustration_file: 'File type not accepted' },
            });

            const sent = await submitGraphicalAbstract(form, { error: '' });

            expect(sent).toBe(false);
            const error = document.getElementById('illustration_file-error');
            expect(error.textContent).toBe('File type not accepted');
            expect(error.hidden).toBe(false);
            expect(document.activeElement.id).toBe('illustration_file');
            expect(
                document.getElementById('graph-abs-status').textContent
            ).toBe('Veuillez corriger les erreurs du formulaire.');
            expect(form.querySelector('button').disabled).toBe(false);
        });

        it('announces the messages not tied to a field in the status region', async () => {
            const form = renderForm({ alt: 'A chart' });
            mockFetchResponse(false, {
                status: 'error',
                messages: { form: 'Unauthorized' },
            });

            await submitGraphicalAbstract(form, { error: '' });

            expect(
                document.getElementById('graph-abs-status').textContent
            ).toBe('Unauthorized');
        });

        it('shows a generic error when the response is not JSON', async () => {
            const form = renderForm({ alt: 'A chart' });
            global.fetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.reject(new SyntaxError('not json')),
            });

            await submitGraphicalAbstract(form, { error: '' });

            expect(
                document.getElementById('graph-abs-status').textContent
            ).toBe('Une erreur est survenue, veuillez réessayer.');
        });
    });

    describe('deleteGraphicalAbstract', () => {
        it('sends the displayed file name with the request token after confirmation', async () => {
            const form = renderForm({ withDelete: true });
            mockFetchResponse(true, { status: 'success', messages: {} });

            await deleteGraphicalAbstract(
                form,
                form.querySelector('[data-graph-abs-delete]')
            );

            const [url, options] = global.fetch.mock.calls[0];
            expect(url).toBe('/administrategraphabstract/deletegraphabs/');
            expect(options.body.get('file')).toBe('graphical_abstract.png');
            expect(options.body.get('docId')).toBe('42');
            expect(options.body.get('csrf_token')).toBe('tok-123');
        });

        it('does nothing when not confirmed', async () => {
            const form = renderForm({ withDelete: true });
            window.confirm = jest.fn(() => false);

            const deleted = await deleteGraphicalAbstract(
                form,
                form.querySelector('[data-graph-abs-delete]')
            );

            expect(deleted).toBe(false);
            expect(global.fetch).not.toHaveBeenCalled();
        });
    });

    describe('initGraphicalAbstractForm', () => {
        it('tracks the FilePond error of the picked file', () => {
            const form = renderForm();

            const state = initGraphicalAbstractForm(form);

            pond.handlers.addfile({ main: 'File is too large', sub: '500 KB' });
            expect(state.pondState.error).toBe('File is too large. 500 KB');
            pond.handlers.removefile();
            expect(state.pondState.error).toBe('');
        });

        it('updates the character counter of the alt', () => {
            const form = renderForm();
            initGraphicalAbstractForm(form);

            const alt = document.getElementById('illustration_alt');
            alt.value = 'abc';
            alt.dispatchEvent(new Event('input'));

            expect(form.querySelector('[data-counter-value]').textContent).toBe(
                '3'
            );
        });

        it('clears FilePond and the errors on reset', () => {
            const form = renderForm();
            initGraphicalAbstractForm(form);
            document.getElementById('illustration_alt-error').hidden = false;

            form.dispatchEvent(new Event('reset'));

            expect(pond.removeFiles).toHaveBeenCalled();
            expect(
                document.getElementById('illustration_alt-error').hidden
            ).toBe(true);
        });

        it('returns null without form', () => {
            expect(initGraphicalAbstractForm(null)).toBeNull();
        });
    });

    describe('as a plain browser script', () => {
        it('does not overwrite the globals of functions.js', () => {
            // functions.js defines a global clearErrors() that empties its target
            const globalClearErrors = jest.fn();
            window.clearErrors = globalClearErrors;
            renderForm({ alt: 'A chart' });

            const source = require('fs').readFileSync(
                require('path').join(
                    __dirname,
                    '../../../public/js/paper/graphicalAbstract.js'
                ),
                'utf8'
            );
            // global scope, without CommonJS module: as loaded by a <script> tag
            window.eval(source);

            expect(window.clearErrors).toBe(globalClearErrors);
            expect(window.submitGraphicalAbstract).toBeUndefined();
            mockFetchResponse(false, { status: 'error', messages: {} });

            document
                .getElementById('f-graph-abs')
                .dispatchEvent(new Event('submit', { cancelable: true }));

            expect(globalClearErrors).not.toHaveBeenCalled();
            expect(global.fetch.mock.calls[0][1].body.get('docId')).toBe('42');
            expect(document.getElementById('illustration_alt')).not.toBeNull();
            delete window.clearErrors;
        });
    });
});
