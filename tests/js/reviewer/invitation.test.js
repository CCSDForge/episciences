/**
 * Test suite for the reviewer invitation answer (public/js/reviewer/invitation.js)
 *
 * The accept and decline buttons open their form in place of the buttons, cancelling closes it,
 * and the focus always lands on what the user has to read or fill next.
 */

/**
 * Same structure as reviewer/invitation_response_form.phtml for a reviewer without an account.
 * @param {string} [openOnLoad] value of data-open-on-load
 * @param {boolean} [invalidComment] whether the refusal reason is marked invalid
 */
function renderAnswer(openOnLoad = '', invalidComment = false) {
    document.body.innerHTML = `
        <div class="invitation-answer">
            <div id="invitation-answer-actions" class="form-actions text-center"${openOnLoad ? ` data-open-on-load="${openOnLoad}"` : ''}>
                <button id="accept-button" type="button" aria-expanded="false" aria-controls="accept_form">Accept</button>
                <button id="refuse-button" type="button" aria-expanded="false" aria-controls="refuse_form">Decline</button>
            </div>
            <form id="accept_form" hidden aria-labelledby="accept-form-title">
                <h2 class="panel-title" id="accept-form-title" tabindex="-1">Account details</h2>
                <button name="canceluser" type="button" data-answer-cancel="true">Cancel</button>
            </form>
            <div id="refuse_form" hidden>
                <h2 class="panel-title" id="refuse-form-title" tabindex="-1">Decline the invitation</h2>
                <form aria-labelledby="refuse-form-title">
                    <input name="suggestreviewer">
                    <textarea name="comment"${invalidComment ? ' aria-invalid="true"' : ''}></textarea>
                    <button name="cancelaccept" type="button" data-answer-cancel="true">Cancel</button>
                </form>
            </div>
        </div>`;

    jest.isolateModules(() => {
        require('../../../public/js/reviewer/invitation.js');
    });
}

const byId = id => document.getElementById(id);

describe('Reviewer invitation answer', () => {
    it('keeps both forms closed by default', () => {
        renderAnswer();

        expect(byId('invitation-answer-actions').hidden).toBe(false);
        expect(byId('refuse_form').hidden).toBe(true);
        expect(byId('accept_form').hidden).toBe(true);
    });

    it('opens the refusal form in place of the buttons and moves the focus to its heading', () => {
        renderAnswer();

        byId('refuse-button').click();

        expect(byId('refuse_form').hidden).toBe(false);
        expect(byId('invitation-answer-actions').hidden).toBe(true);
        expect(byId('refuse-button').getAttribute('aria-expanded')).toBe(
            'true'
        );
        expect(document.activeElement).toBe(byId('refuse-form-title'));
    });

    it('closes the refusal form on cancel and gives the focus back to the decline button', () => {
        renderAnswer();
        byId('refuse-button').click();

        document.querySelector('[name="cancelaccept"]').click();

        expect(byId('refuse_form').hidden).toBe(true);
        expect(byId('invitation-answer-actions').hidden).toBe(false);
        expect(byId('refuse-button').getAttribute('aria-expanded')).toBe(
            'false'
        );
        expect(document.activeElement).toBe(byId('refuse-button'));
    });

    it('opens one form at a time', () => {
        renderAnswer();
        byId('refuse-button').click();

        byId('accept-button').click();

        expect(byId('accept_form').hidden).toBe(false);
        expect(byId('refuse_form').hidden).toBe(true);
        expect(byId('refuse-button').getAttribute('aria-expanded')).toBe(
            'false'
        );
        expect(byId('accept-button').getAttribute('aria-expanded')).toBe(
            'true'
        );
    });

    it('closes the account form on cancel and gives the focus back to the accept button', () => {
        renderAnswer();
        byId('accept-button').click();
        expect(document.activeElement).toBe(byId('accept-form-title'));

        document.querySelector('[name="canceluser"]').click();

        expect(byId('accept_form').hidden).toBe(true);
        expect(document.activeElement).toBe(byId('accept-button'));
    });

    it('reopens a form sent back by the server, on its first invalid field', () => {
        renderAnswer('refuse_form', true);

        expect(byId('refuse_form').hidden).toBe(false);
        expect(byId('invitation-answer-actions').hidden).toBe(true);
        expect(document.activeElement).toBe(
            document.querySelector('[name="comment"]')
        );
    });

    it('reopens a form sent back without field errors on its heading', () => {
        renderAnswer('refuse_form');

        expect(document.activeElement).toBe(byId('refuse-form-title'));
    });

    it('does nothing on a page without the answer buttons', () => {
        document.body.innerHTML = '<p>Already answered</p>';

        expect(() => {
            jest.isolateModules(() => {
                require('../../../public/js/reviewer/invitation.js');
            });
        }).not.toThrow();
    });
});
