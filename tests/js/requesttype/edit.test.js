'use strict';

const {
    initLightAnswerInstructions,
} = require('../../../public/js/requesttype/edit');

describe('review request type form (#319)', () => {
    const render = checked => {
        document.body.innerHTML = `
            <form>
                <input type="checkbox" id="light_answer" name="light_answer" value="1"${checked ? ' checked' : ''}
                       aria-controls="light-answer-instructions" aria-expanded="${checked}">
                <div id="light-answer-instructions">
                    <textarea name="instructions[fr]">Consigne</textarea>
                </div>
            </form>`;
        return {
            checkbox: document.getElementById('light_answer'),
            group: document.getElementById('light-answer-instructions'),
        };
    };

    test('the instructions are hidden when the answer without an account is not checked', () => {
        const { checkbox, group } = render(false);

        expect(initLightAnswerInstructions(document)).toBe(true);
        expect(group.hidden).toBe(true);
        expect(checkbox.getAttribute('aria-expanded')).toBe('false');
    });

    test('the instructions are shown when the answer without an account is checked', () => {
        const { group } = render(true);

        initLightAnswerInstructions(document);

        expect(group.hidden).toBe(false);
    });

    test('checking or unchecking the box shows or hides the instructions, which are kept', () => {
        const { checkbox, group } = render(false);
        initLightAnswerInstructions(document);

        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));
        expect(group.hidden).toBe(false);
        expect(checkbox.getAttribute('aria-expanded')).toBe('true');

        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change'));
        expect(group.hidden).toBe(true);
        // hidden fields are still posted: the instructions are not lost
        expect(
            new FormData(document.querySelector('form')).get('instructions[fr]')
        ).toBe('Consigne');
    });

    test('counting as review reports is disabled while the answer without an account is checked', () => {
        const { checkbox } = render(true);
        document
            .querySelector('form')
            .insertAdjacentHTML(
                'beforeend',
                '<input type="checkbox" id="counts_as_report" name="counts_as_report" value="1" checked>'
            );
        const countsAsReport = document.getElementById('counts_as_report');
        initLightAnswerInstructions(document);

        expect(countsAsReport.disabled).toBe(true);

        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change'));
        expect(countsAsReport.disabled).toBe(false);
        // the choice made before is kept
        expect(countsAsReport.checked).toBe(true);
    });

    test('a form without the option (review report) is left unchanged', () => {
        document.body.innerHTML =
            '<form><input type="text" name="code"></form>';

        expect(initLightAnswerInstructions(document)).toBe(false);
    });
});
