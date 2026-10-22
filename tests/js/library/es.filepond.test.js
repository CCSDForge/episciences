'use strict';

const {
    setSubmitButtonsDisabled,
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
