/**
 * Test suite for public/js/administratepaper/change-contributor.js
 *
 * Covers the modal lifecycle:
 *  - the autocomplete is created only once, however many times the modal opens
 *  - document-level drag listeners are released when the modal closes
 *  - the modal closes on Escape and on backdrop click
 *  - the focus goes back to the trigger button
 */

'use strict';

const SCRIPT = '../../../public/js/administratepaper/change-contributor';

function buildDom() {
    document.body.innerHTML = `
        <button id="btn-change-contributor" type="button">Change</button>
        <div id="changeContributorModal" class="modal">
            <div class="modal-dialog">
                <div class="modal-header">
                    <button type="button" data-dismiss="modal">x</button>
                </div>
                <input id="newContributorInput">
                <input id="newContributorUid" value="0">
                <input id="changeContributorDocId" value="1">
                <input id="addAsCoauthorCheckbox" type="checkbox" checked>
                <button id="confirmChangeContributor" type="button">OK</button>
            </div>
        </div>`;
}

describe('change-contributor modal', () => {
    let addSpy;
    let removeSpy;

    const openModal = () => {
        document.getElementById('btn-change-contributor').click();
        jest.advanceTimersByTime(20);
    };
    const modal = () => document.getElementById('changeContributorModal');
    const count = (spy, type) =>
        spy.mock.calls.filter(call => call[0] === type).length;

    beforeEach(() => {
        jest.useFakeTimers();
        buildDom();
        global.createUserAutocomplete = jest.fn();
        global.translate = jest.fn(s => s);
        addSpy = jest.spyOn(document, 'addEventListener');
        removeSpy = jest.spyOn(document, 'removeEventListener');

        jest.isolateModules(() => require(SCRIPT));
        document.dispatchEvent(new Event('DOMContentLoaded'));

        // Drop the init handler so the next test does not re-run it
        addSpy.mock.calls
            .filter(call => call[0] === 'DOMContentLoaded')
            .forEach(call => document.removeEventListener(...call));
        addSpy.mockClear();
    });

    afterEach(() => {
        addSpy.mockRestore();
        removeSpy.mockRestore();
        jest.useRealTimers();
    });

    test('creates the autocomplete only once across several openings', () => {
        for (let i = 0; i < 3; i++) {
            openModal();
            modal().querySelector('[data-dismiss="modal"]').click();
        }
        expect(global.createUserAutocomplete).toHaveBeenCalledTimes(1);
    });

    test('releases the document drag listeners on close', () => {
        for (let i = 0; i < 3; i++) {
            openModal();
            modal().querySelector('[data-dismiss="modal"]').click();
        }
        for (const type of ['mousemove', 'mouseup', 'keydown']) {
            expect(count(addSpy, type)).toBe(3);
            expect(count(removeSpy, type)).toBe(3);
        }
    });

    test('closes on Escape and gives the focus back to the trigger', () => {
        openModal();
        expect(modal().style.display).toBe('block');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(modal().style.display).toBe('none');
        expect(document.activeElement).toBe(
            document.getElementById('btn-change-contributor')
        );
    });

    test('closes on backdrop click but not on content click', () => {
        openModal();
        modal().querySelector('.modal-dialog').click();
        expect(modal().style.display).toBe('block');

        modal().click();
        expect(modal().style.display).toBe('none');
    });

    test('drops the dragged position when the modal closes', () => {
        openModal();
        const dialog = modal().querySelector('.modal-dialog');
        dialog.style.left = '120px';

        modal().querySelector('[data-dismiss="modal"]').click();

        expect(dialog.getAttribute('style')).toBeNull();
    });
});
