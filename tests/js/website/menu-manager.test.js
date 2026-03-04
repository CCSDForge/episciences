'use strict';

/**
 * Test suite for MenuManager (public/js/website/menu-manager.js).
 *
 * Covers:
 *  1. serializeOrder() — flat list (2 root items)
 *  2. serializeOrder() — 1 level of nesting
 *  3. serializeOrder() — 2 levels of nesting
 *  4. post() / saveOrder() — correct headers and body
 *  5. addPage() — fetch called, new <li> injected
 *  6. deletePage() — fetch called, <li> removed
 *  7. Max depth constraint — _getDepth() returns correct depth
 *  8. toggleEditForm() — toggles hidden + aria-expanded
 *  9. setVisibility() — hides/shows .multicheckbox
 * 10. announce() — writes to the aria-live region
 */

// ---------------------------------------------------------------------------
// Stub window.Sortable so the constructor does not crash in jsdom
// ---------------------------------------------------------------------------
global.Sortable = {
    create: () => ({ el: null }),
};

// ---------------------------------------------------------------------------
// Load the module under test
// ---------------------------------------------------------------------------
const { MenuManager } = require('../../../public/js/website/menu-manager');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a root <ul> with top-level <li id="page_N"> children. */
function makeRootList(items) {
    const ul = document.createElement('ul');
    ul.className = 'menu-sortable';
    items.forEach(({ id, nested }) => {
        const li = document.createElement('li');
        li.id = `page_${id}`;
        if (nested && nested.length > 0) {
            const subUl = document.createElement('ul');
            nested.forEach(({ id: subId, nested: subNested }) => {
                const subLi = document.createElement('li');
                subLi.id = `page_${subId}`;
                if (subNested && subNested.length > 0) {
                    const subSubUl = document.createElement('ul');
                    subNested.forEach(({ id: subSubId }) => {
                        const subSubLi = document.createElement('li');
                        subSubLi.id = `page_${subSubId}`;
                        subSubUl.appendChild(subSubLi);
                    });
                    subLi.appendChild(subSubUl);
                }
                subUl.appendChild(subLi);
            });
            li.appendChild(subUl);
        }
        ul.appendChild(li);
    });
    return ul;
}

/** Create a minimal DOM and return a MenuManager bound to it. */
function makeManager(items = []) {
    document.body.innerHTML = '<div id="menu-announcer"></div>';
    const rootList = makeRootList(items);
    document.body.appendChild(rootList);
    return new MenuManager(rootList);
}

// ---------------------------------------------------------------------------
// Reset DOM between tests
// ---------------------------------------------------------------------------
afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.lang = 'fr';
    jest.restoreAllMocks();
    global.fetch = undefined;
});

// ---------------------------------------------------------------------------
// 1. serializeOrder — flat list (2 root items)
// ---------------------------------------------------------------------------
describe('serializeOrder — flat list', () => {
    test('two root items are serialised as page[id]=root', () => {
        const manager = makeManager([{ id: 0 }, { id: 1 }]);
        const params = manager.serializeOrder(manager.rootList);

        expect(params.get('page[0]')).toBe('root');
        expect(params.get('page[1]')).toBe('root');
    });

    test('skips <li> elements without a page_ id prefix', () => {
        const manager = makeManager([{ id: 5 }]);
        // Inject a non-page li
        const spurious = document.createElement('li');
        spurious.id = 'other_item';
        manager.rootList.appendChild(spurious);

        const params = manager.serializeOrder(manager.rootList);
        expect(params.get('page[5]')).toBe('root');
        expect([...params.keys()]).toHaveLength(1);
    });
});

// ---------------------------------------------------------------------------
// 2. serializeOrder — 1 level of nesting
// ---------------------------------------------------------------------------
describe('serializeOrder — 1 level of nesting', () => {
    test('nested child is serialised with the parent id as parent', () => {
        const manager = makeManager([
            { id: 0, nested: [{ id: 2 }] },
            { id: 1 },
        ]);
        const params = manager.serializeOrder(manager.rootList);

        expect(params.get('page[0]')).toBe('root');
        expect(params.get('page[1]')).toBe('root');
        expect(params.get('page[2]')).toBe('0');
    });
});

// ---------------------------------------------------------------------------
// 3. serializeOrder — 2 levels of nesting
// ---------------------------------------------------------------------------
describe('serializeOrder — 2 levels of nesting', () => {
    test('deeply nested item has grand-parent id as parent', () => {
        const manager = makeManager([
            {
                id: 0,
                nested: [
                    {
                        id: 2,
                        nested: [{ id: 4 }],
                    },
                ],
            },
        ]);
        const params = manager.serializeOrder(manager.rootList);

        expect(params.get('page[0]')).toBe('root');
        expect(params.get('page[2]')).toBe('0');
        expect(params.get('page[4]')).toBe('2');
    });
});

// ---------------------------------------------------------------------------
// 4. post() / saveOrder() — headers and body
// ---------------------------------------------------------------------------
describe('saveOrder()', () => {
    test('sends POST with X-Requested-With and Content-Type headers', async () => {
        const manager = makeManager([{ id: 0 }, { id: 1 }]);

        let capturedOptions;
        global.fetch = jest.fn((_url, options) => {
            capturedOptions = options;
            return Promise.resolve({ ok: true });
        });

        await manager.saveOrder();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0]).toBe('/website/ajaxorder');
        expect(capturedOptions.method).toBe('POST');
        expect(capturedOptions.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(capturedOptions.headers['Content-Type']).toBe(
            'application/x-www-form-urlencoded'
        );
    });

    test('body contains page[id]=root entries', async () => {
        const manager = makeManager([{ id: 3 }]);

        let capturedBody;
        global.fetch = jest.fn((_url, options) => {
            capturedBody = options.body;
            return Promise.resolve({ ok: true });
        });

        await manager.saveOrder();

        expect(capturedBody).toContain('page%5B3%5D=root');
    });
});

// ---------------------------------------------------------------------------
// Helper: append a #clone template into the body (must be called AFTER makeManager)
// ---------------------------------------------------------------------------
function appendCloneTemplate() {
    const cloneDiv = document.createElement('div');
    cloneDiv.id = 'clone';
    cloneDiv.hidden = true;
    cloneDiv.innerHTML =
        '<li class=""><div class="page-content menu-item-label"></div></li>';
    document.body.appendChild(cloneDiv);
}

// ---------------------------------------------------------------------------
// 5. addPage() — fetch called, new <li> injected
// ---------------------------------------------------------------------------
describe('addPage()', () => {
    test('calls /website/ajaxformpage with the given type', async () => {
        const manager = makeManager([]);
        appendCloneTemplate();

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('<input type="hidden" value="7">New page content'),
            })
        );

        await manager.addPage('article');

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0]).toBe('/website/ajaxformpage');
    });

    test('injects a new <li> into the root list', async () => {
        const manager = makeManager([]);
        appendCloneTemplate();
        const spy = jest.spyOn(manager, 'announce');

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () =>
                    Promise.resolve('<input type="hidden" value="7">New page'),
            })
        );

        await manager.addPage('article');

        const newLi = document.getElementById('page_7');
        expect(newLi).not.toBeNull();
        expect(manager.rootList.contains(newLi)).toBe(true);
        expect(spy).toHaveBeenCalledWith(expect.stringContaining('Nouvelle page ajoutée'));
    });

    test('adds no-nest class for non-folder pages', async () => {
        const manager = makeManager([]);
        appendCloneTemplate();

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () =>
                    Promise.resolve('<input type="hidden" value="8">Content'),
            })
        );

        await manager.addPage('article');

        const newLi = document.getElementById('page_8');
        expect(newLi.classList.contains('no-nest')).toBe(true);
    });

    test('does not add no-nest class for folder pages', async () => {
        const manager = makeManager([]);
        appendCloneTemplate();

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () =>
                    Promise.resolve('<input type="hidden" value="9">Folder'),
            })
        );

        await manager.addPage('folder');

        const newLi = document.getElementById('page_9');
        expect(newLi.classList.contains('no-nest')).toBe(false);
    });
});

// ---------------------------------------------------------------------------
// 6. deletePage() — fetch called, <li> removed
// ---------------------------------------------------------------------------
describe('deletePage()', () => {
    beforeEach(() => {
        jest.spyOn(window, 'confirm').mockReturnValue(true);
    });

    test('calls /website/ajaxrmpage with idx and page_id', async () => {
        const manager = makeManager([{ id: 3 }]);

        let capturedBody;
        global.fetch = jest.fn((_url, options) => {
            capturedBody = options.body;
            return Promise.resolve({ ok: true });
        });

        await manager.deletePage(3, 'my-page-resource');

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch.mock.calls[0][0]).toBe('/website/ajaxrmpage');
        expect(capturedBody).toContain('idx=3');
        expect(capturedBody).toContain('page_id=my-page-resource');
    });

    test('removes the <li> from the DOM after successful delete', async () => {
        const manager = makeManager([{ id: 5 }]);

        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));

        expect(document.getElementById('page_5')).not.toBeNull();

        await manager.deletePage(5, 'some-resource');

        expect(document.getElementById('page_5')).toBeNull();
    });

    test('does not call fetch when user cancels the confirmation', async () => {
        jest.spyOn(window, 'confirm').mockReturnValue(false);

        const manager = makeManager([{ id: 6 }]);
        global.fetch = jest.fn();

        await manager.deletePage(6, 'some-resource');

        expect(global.fetch).not.toHaveBeenCalled();
        expect(document.getElementById('page_6')).not.toBeNull();
    });
});

// ---------------------------------------------------------------------------
// 7. Max depth constraint — _getDepth()
// ---------------------------------------------------------------------------
describe('_getDepth()', () => {
    test('root list has depth 1', () => {
        const manager = makeManager([]);
        expect(manager._getDepth(manager.rootList)).toBe(1);
    });

    test('first level nested ul has depth 2', () => {
        const manager = makeManager([{ id: 0, nested: [{ id: 1 }] }]);
        const nestedUl = manager.rootList.querySelector('li#page_0 > ul');
        expect(manager._getDepth(nestedUl)).toBe(2);
    });

    test('second level nested ul has depth 3', () => {
        const manager = makeManager([
            { id: 0, nested: [{ id: 1, nested: [{ id: 2 }] }] },
        ]);
        const deepUl = manager.rootList.querySelector('li#page_1 > ul');
        expect(manager._getDepth(deepUl)).toBe(3);
    });
});

// ---------------------------------------------------------------------------
// 8. toggleEditForm() — toggles hidden + aria-expanded
// ---------------------------------------------------------------------------
describe('toggleEditForm()', () => {
    test('shows the form and sets aria-expanded=true when initially collapsed', () => {
        const manager = makeManager([]);

        const li = document.createElement('li');
        const item = document.createElement('div');
        item.className = 'menu-item';

        const btn = document.createElement('button');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', 'form-0');

        const form = document.createElement('div');
        form.id = 'form-0';
        form.hidden = true;

        item.appendChild(btn);
        item.appendChild(form);
        li.appendChild(item);
        manager.rootList.appendChild(li);

        manager.toggleEditForm(btn);

        expect(form.hidden).toBe(false);
        expect(btn.getAttribute('aria-expanded')).toBe('true');
    });

    test('hides the form and sets aria-expanded=false when initially expanded', () => {
        const manager = makeManager([]);

        const li = document.createElement('li');
        const item = document.createElement('div');
        item.className = 'menu-item';

        const btn = document.createElement('button');
        btn.setAttribute('aria-expanded', 'true');
        btn.setAttribute('aria-controls', 'form-1');

        const form = document.createElement('div');
        form.id = 'form-1';
        form.hidden = false;

        item.appendChild(btn);
        item.appendChild(form);
        li.appendChild(item);
        manager.rootList.appendChild(li);

        manager.toggleEditForm(btn);

        expect(form.hidden).toBe(true);
        expect(btn.getAttribute('aria-expanded')).toBe('false');
    });
});

// ---------------------------------------------------------------------------
// 9. setVisibility() — hides/shows .multicheckbox
// ---------------------------------------------------------------------------
describe('setVisibility()', () => {
    function makeVisibilityDOM(value) {
        const container = document.createElement('div');
        container.className = 'menu-edit-form';

        const selectDiv = document.createElement('div');
        const select = document.createElement('select');
        select.id = 'pages_0_visibility';
        select.value = String(value);
        const opt = new Option(String(value), String(value));
        select.add(opt);
        selectDiv.appendChild(select);

        const checkboxDiv = document.createElement('div');
        const multicheckbox = document.createElement('div');
        multicheckbox.className = 'multicheckbox';
        checkboxDiv.appendChild(multicheckbox);

        container.appendChild(selectDiv);
        container.appendChild(checkboxDiv);
        document.body.appendChild(container);

        return { select, multicheckbox };
    }

    test('hides .multicheckbox when visibility value < 2', () => {
        const { select, multicheckbox } = makeVisibilityDOM(1);
        const manager = makeManager([]);
        manager.setVisibility(select);
        expect(multicheckbox.hidden).toBe(true);
    });

    test('shows .multicheckbox when visibility value >= 2', () => {
        const { select, multicheckbox } = makeVisibilityDOM(2);
        const manager = makeManager([]);
        manager.setVisibility(select);
        expect(multicheckbox.hidden).toBe(false);
    });
});

// ---------------------------------------------------------------------------
// 10. announce() — writes to the aria-live region
// ---------------------------------------------------------------------------
describe('announce()', () => {
    test('writes the message to the aria-live region', async () => {
        const manager = makeManager([]);

        // Mock requestAnimationFrame to execute the callback synchronously
        const originalRaf = global.requestAnimationFrame;
        global.requestAnimationFrame = (cb) => cb();

        manager.announce('Test announcement');

        expect(manager.liveRegion.textContent).toBe('Test announcement');

        global.requestAnimationFrame = originalRaf;
    });

    test('clears the region before writing (allows repeated announcements)', async () => {
        const manager = makeManager([]);

        const originalRaf = global.requestAnimationFrame;
        global.requestAnimationFrame = (cb) => cb();

        manager.announce('First');
        manager.announce('Second');

        expect(manager.liveRegion.textContent).toBe('Second');

        global.requestAnimationFrame = originalRaf;
    });

    test('does not throw when liveRegion is null', () => {
        const manager = makeManager([]);
        manager.liveRegion = null;
        expect(() => manager.announce('hello')).not.toThrow();
    });
});

// ---------------------------------------------------------------------------
// 11. _safeSetInnerHTML() — security and script execution
// ---------------------------------------------------------------------------
describe('_safeSetInnerHTML()', () => {
    test('injects HTML into the container', () => {
        const manager = makeManager([]);
        const container = document.createElement('div');
        manager._safeSetInnerHTML(container, '<p>Hello <strong>World</strong></p>');

        expect(container.querySelector('strong').textContent).toBe('World');
    });

    test('does NOT execute scripts within the injected HTML', () => {
        const manager = makeManager([]);
        const container = document.createElement('div');
        const spy = jest.fn();
        window.testValueSpy = spy;
        
        const html = '<script>window.testValueSpy();</script>';
        manager._safeSetInnerHTML(container, html);

        expect(spy).not.toHaveBeenCalled();
        delete window.testValueSpy;
    });
});
