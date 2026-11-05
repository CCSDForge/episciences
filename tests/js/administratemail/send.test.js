/**
 * Mail form of the modal of the paper page (public/js/administratemail/send.js): closing the modal.
 */

/**
 * Minimal jQuery: selectors on the real DOM, namespaced events, Bootstrap modal('hide')
 * (hide.bs.modal, cancelled by a handler returning false, then hidden.bs.modal)
 */
function installJQuery() {
    const handlers = [];

    function trigger(element, type) {
        let cancelled = false;
        handlers
            .filter(h => h.element === element && h.type === type)
            .forEach(h => {
                if (h.handler.call(element, { type }) === false) {
                    cancelled = true;
                }
            });
        return !cancelled;
    }

    function wrap(elements) {
        const api = {
            ready: callback => callback(),
            val: () => (elements[0] ? elements[0].value : undefined),
            prop: name => (elements[0] ? elements[0][name] : undefined),
            on(events, handler) {
                events.split(' ').forEach(event => {
                    const [type, ...ns] = event.split('.');
                    const fullType = ns.length
                        ? [type, ...ns].slice(0, 3).join('.')
                        : type;
                    elements.forEach(element =>
                        handlers.push({
                            element,
                            type: fullType,
                            ns: ns.slice(2).join('.'),
                            handler,
                        })
                    );
                });
                return api;
            },
            off(event) {
                const parts = event.split('.');
                const type = parts.slice(0, 3).join('.');
                const ns = parts.slice(3).join('.');
                for (let i = handlers.length - 1; i >= 0; i--) {
                    const h = handlers[i];
                    if (
                        elements.includes(h.element) &&
                        h.type === type &&
                        h.ns === ns
                    ) {
                        handlers.splice(i, 1);
                    }
                }
                return api;
            },
            modal(action) {
                if (action === 'hide') {
                    elements.forEach(element => {
                        if (trigger(element, 'hide.bs.modal')) {
                            trigger(element, 'hidden.bs.modal');
                        }
                    });
                }
                return api;
            },
        };
        return api;
    }

    global.$ = selector => {
        if (selector === document) {
            return wrap([document]);
        }
        return wrap(Array.from(document.querySelectorAll(selector)));
    };
}

function loadScript() {
    jest.isolateModules(() => {
        require('../../../public/js/administratemail/send.js');
    });
}

describe('administratemail/send.js in the modal of the paper page', () => {
    let editor;

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="modal-box">
                <form id="send_form">
                    <input type="hidden" id="docid" name="docid" value="12">
                    <ul class="recipient-tags" data-recipient-target="cc"></ul>
                    <textarea id="content" class="tinymce"></textarea>
                </form>
            </div>`;
        installJQuery();
        editor = { remove: jest.fn() };
        global.in_modal = true;
        global.tinyMCE = {
            get: jest.fn(id => (id === 'content' ? editor : null)),
            activeEditor: {
                on: jest.fn(),
                getContent: () => '<p>Typed text</p>',
            },
        };
        global.updateModalButton = jest.fn();
        global.epInitRecipientFields = jest.fn();
        global.addRecipient = jest.fn();
        global.removeRecipient = jest.fn();
        global.refreshPaperHistory = jest.fn();
        global.message = jest.fn();
        global.translate = key => key;
        global.modalStructureExists = () => true;
        global.createModalStructure = jest.fn();
        global.confirm = jest.fn(() => false);
    });

    afterEach(() => {
        [
            '$',
            'in_modal',
            'tinyMCE',
            'updateModalButton',
            'epInitRecipientFields',
            'addRecipient',
            'removeRecipient',
            'refreshPaperHistory',
            'message',
            'translate',
            'modalStructureExists',
            'createModalStructure',
            'confirm',
        ].forEach(name => delete global[name]);
    });

    test('a sent mail closes the modal without asking, shows a toast and refreshes the history of the paper', () => {
        loadScript();

        document.getElementById('send_form').dispatchEvent(
            new CustomEvent('ep:mail-sent', {
                detail: { message: 'Votre e-mail a bien été envoyé.' },
            })
        );

        expect(global.confirm).not.toHaveBeenCalled();
        expect(global.message).toHaveBeenCalledWith(
            'Votre e-mail a bien été envoyé.',
            'alert-success'
        );
        expect(global.refreshPaperHistory).toHaveBeenCalledWith('12');
    });

    test('the editor of the closed modal is removed, so that the next opening creates a new one', () => {
        global.confirm = jest.fn(() => true);
        loadScript();

        $('#modal-box').modal('hide');

        expect(editor.remove).toHaveBeenCalledTimes(1);
        expect(global.refreshPaperHistory).not.toHaveBeenCalled();
    });

    test('closing with a typed text asks first, and keeps the modal when cancelled', () => {
        loadScript();

        $('#modal-box').modal('hide');

        expect(global.confirm).toHaveBeenCalledWith(
            'Fermer sans envoyer ce message ?'
        );
        expect(editor.remove).not.toHaveBeenCalled();
    });

    test('the script loaded at each opening replaces its former handlers', () => {
        loadScript();
        loadScript();

        $('#modal-box').modal('hide');

        expect(global.confirm).toHaveBeenCalledTimes(1);
    });

    test('once closed, the mail handlers do not run for another content of the shared modal', () => {
        global.confirm = jest.fn(() => true);
        loadScript();
        $('#modal-box').modal('hide');
        expect(global.confirm).toHaveBeenCalledTimes(1);

        // Another modal of the page, without any editor
        document.getElementById('modal-box').innerHTML =
            '<form id="other"></form>';
        global.tinyMCE.activeEditor = null;
        editor.remove.mockClear();

        expect(() => $('#modal-box').modal('hide')).not.toThrow();
        expect(global.confirm).toHaveBeenCalledTimes(1);
        expect(editor.remove).not.toHaveBeenCalled();
    });

    test('a mail form removed from the page does not ask for a confirmation', () => {
        loadScript();
        document.getElementById('send_form').remove();
        global.tinyMCE.activeEditor = null;

        expect(() => $('#modal-box').modal('hide')).not.toThrow();
        expect(global.confirm).not.toHaveBeenCalled();
    });
});
