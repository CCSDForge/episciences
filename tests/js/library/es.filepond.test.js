'use strict';

const {
    setSubmitButtonsDisabled,
    getFileExtension,
    getUploadErrorMessages,
    trackAttachedFile,
    getAttachedFileToken,
    untrackAttachedFile,
} = require('../../../public/js/library/es.filepond');

function buildForm(buttonIds) {
    const form = document.createElement('form');
    const widget = document.createElement('div');
    widget.className = 'upload_widget';
    form.appendChild(widget);
    buttonIds.forEach(id => {
        const button = document.createElement('button');
        button.id = id;
        form.appendChild(button);
    });
    document.body.appendChild(form);
    return { form, widget };
}

describe('setSubmitButtonsDisabled', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('disables submit, submit-modal and copy-editing send buttons of the form', () => {
        const { form, widget } = buildForm([
            'submit',
            'submit-modal',
            'ce_reply_12',
        ]);

        setSubmitButtonsDisabled(widget, true);

        form.querySelectorAll('button').forEach(button => {
            expect(button.disabled).toBe(true);
        });
    });

    test('re-enables the buttons once uploads are done', () => {
        const { form, widget } = buildForm(['submit', 'ce_reply_12']);

        setSubmitButtonsDisabled(widget, true);
        setSubmitButtonsDisabled(widget, false);

        form.querySelectorAll('button').forEach(button => {
            expect(button.disabled).toBe(false);
        });
    });

    test('leaves other buttons untouched, such as copy-editing cancel', () => {
        const { form, widget } = buildForm(['ce_cancel_12', 'ce_reply_12']);

        setSubmitButtonsDisabled(widget, true);

        expect(form.querySelector('#ce_cancel_12').disabled).toBe(false);
        expect(form.querySelector('#ce_reply_12').disabled).toBe(true);
    });

    test('only affects the form containing the widget', () => {
        const { widget } = buildForm(['ce_reply_1']);
        const other = buildForm(['ce_reply_2']);

        setSubmitButtonsDisabled(widget, true);

        expect(other.form.querySelector('#ce_reply_2').disabled).toBe(false);
    });
});

describe('setSubmitButtonsDisabled in a modal', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('disables the modal footer button, outside the form', () => {
        document.body.innerHTML = `
            <div class="modal-content">
                <div class="modal-body">
                    <form><div class="upload_widget"></div></form>
                </div>
                <div class="modal-footer">
                    <button id="submit-modal-minor-revision"></button>
                </div>
            </div>
            <button id="submit-modal-other"></button>`;

        setSubmitButtonsDisabled(
            document.querySelector('.upload_widget'),
            true
        );

        expect(
            document.querySelector('#submit-modal-minor-revision').disabled
        ).toBe(true);
        expect(document.querySelector('#submit-modal-other').disabled).toBe(
            false
        );
    });
});

describe('getFileExtension', () => {
    test.each([
        ['article.PDF', '.pdf'],
        ['sources.tar.gz', '.gz'],
        ['refs.bib', '.bib'],
        ['README', ''],
        ['.hidden', ''],
    ])('%s -> "%s"', (filename, expected) => {
        expect(getFileExtension(filename)).toBe(expected);
    });
});

describe('getUploadErrorMessages', () => {
    test('keeps an exception message as a whole', () => {
        expect(getUploadErrorMessages('Disk full')).toEqual(['Disk full']);
    });

    test('lists validator messages', () => {
        expect(
            getUploadErrorMessages({
                fileExtensionFalse: 'Bad extension',
                fileMimeTypeFalse: 'Bad type',
            })
        ).toEqual(['Bad extension', 'Bad type']);
    });

    test('flattens nested messages', () => {
        expect(getUploadErrorMessages([['Too big']])).toEqual(['Too big']);
    });

    test.each([[undefined], [''], [{}], [[]]])(
        'falls back to a generic message for %p',
        messages => {
            expect(getUploadErrorMessages(messages)).toEqual([
                'Erreur inconnue.',
            ]);
        }
    );
});

describe('attached file tracking', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    function buildWidget() {
        const widget = document.createElement('div');
        widget.className = 'upload_widget';
        document.body.appendChild(widget);
        return widget;
    }

    test('adds a name and its upload token, paired by position', () => {
        const widget = buildWidget();

        trackAttachedFile(widget, 'a.pdf', 'token-a');
        trackAttachedFile(widget, 'b.pdf', 'token-b');

        const names = Array.from(
            widget.querySelectorAll('input[name="attachments[]"]')
        ).map(input => input.value);
        const tokens = Array.from(
            widget.querySelectorAll('input[name="attachment_tokens[]"]')
        ).map(input => input.value);

        expect(names).toEqual(['a.pdf', 'b.pdf']);
        expect(tokens).toEqual(['token-a', 'token-b']);
    });

    test('returns the upload token of a file', () => {
        const widget = buildWidget();
        trackAttachedFile(widget, 'a.pdf', 'token-a');
        trackAttachedFile(widget, 'b.pdf', 'token-b');

        expect(getAttachedFileToken(widget, 'b.pdf')).toBe('token-b');
        expect(getAttachedFileToken(widget, 'missing.pdf')).toBe('');
    });

    test('removes a name together with its upload token', () => {
        const widget = buildWidget();
        trackAttachedFile(widget, 'a.pdf', 'token-a');
        trackAttachedFile(widget, 'b.pdf', 'token-b');

        untrackAttachedFile(widget, 'a.pdf');

        expect(
            Array.from(widget.querySelectorAll('input')).map(
                input => input.value
            )
        ).toEqual(['b.pdf', 'token-b']);
    });
});
