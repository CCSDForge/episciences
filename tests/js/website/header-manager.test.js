'use strict';

/**
 * Test suite for HeaderManager (public/js/website/header-manager.js).
 */

// Stub window.Sortable so the constructor does not crash in jsdom
global.Sortable = {
    create: () => ({ el: null }),
};

// Stub JS_PREFIX_URL global used in addLogo()
global.JS_PREFIX_URL = '/';

// Load the module under test
const { HeaderManager } = require('../../../public/js/website/header-manager');

// Helpers
function makeRootList() {
    const ul = document.createElement('ul');
    ul.className = 'menu-sortable';
    return ul;
}

function makeManager(uniq = 0) {
    document.body.innerHTML = `
        <div id="header-announcer"></div>
        <template id="logo-template">
            <li class="logo">
                <div class="menu-item">
                    <button type="button" class="menu-drag-handle"></button>
                    <div class="menu-item-label">
                        <span class="menu-item-name">
                            <span class="glyphicon"></span>&nbsp;-&nbsp;<span class="menu-item-name-text"></span>
                        </span>
                        <div class="menu-edit-form" hidden></div>
                    </div>
                    <div class="menu-item-actions">
                        <button type="button" data-action="toggle-edit"></button>
                        <button type="button" data-action="delete-logo"></button>
                    </div>
                </div>
            </li>
        </template>
    `;
    const rootList = makeRootList();
    document.body.appendChild(rootList);
    return new HeaderManager(rootList, uniq);
}

// Reset DOM between tests
afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.lang = 'fr'; // Reset to default expected by some tests
    jest.restoreAllMocks();
    global.fetch = undefined;
});

describe('HeaderManager', () => {
    test('initializes Sortable on the root list', () => {
        const spy = jest.spyOn(global.Sortable, 'create');
        const manager = makeManager();
        expect(spy).toHaveBeenCalledWith(manager.rootList, expect.any(Object));
    });

    describe('addLogo()', () => {
        test('calls /website/ajaxheader and injects a new logo', async () => {
            const manager = makeManager(5);
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve('<input type="file" name="temp">New logo form'),
                })
            );

            await manager.addLogo();

            expect(global.fetch).toHaveBeenCalledWith('/website/ajaxheader/id/logo_5', expect.any(Object));
            const newLi = document.getElementById('logo-5');
            expect(newLi).not.toBeNull();
            expect(manager.rootList.contains(newLi)).toBe(true);
            
            // Check if renameInputFile was called implicitly
            const fileInput = newLi.querySelector('input[type="file"]');
            expect(fileInput.name).toBe('logo_5[img]');

            // Check if initial text is set correctly
            const itemNameText = newLi.querySelector('.menu-item-name-text');
            expect(itemNameText.textContent).toBe('...');
        });

        test('announces new logo to screen readers', async () => {
            const manager = makeManager(0);
            const spy = jest.spyOn(manager, 'announce');
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve('Form'),
                })
            );

            await manager.addLogo();
            expect(spy).toHaveBeenCalledWith(expect.stringContaining('Nouveau logo ajouté'));
        });

        test('focuses the first input of the new form', async () => {
            const manager = makeManager(0);
            global.fetch = jest.fn(() =>
                Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve('<input type="text" id="focus-me">'),
                })
            );

            await manager.addLogo();
            const input = document.getElementById('focus-me');
            expect(document.activeElement).toBe(input);
        });
    });

    describe('security and HTTP', () => {
        test('_safeSetInnerHTML does NOT execute scripts', () => {
            const manager = makeManager(0);
            const container = document.createElement('div');
            const spy = jest.fn();
            window.scriptExecuted = spy;

            manager._safeSetInnerHTML(container, '<script>window.scriptExecuted()</script><p>Safe content</p>');
            
            expect(container.querySelector('p').textContent).toBe('Safe content');
            expect(spy).not.toHaveBeenCalled();
            delete window.scriptExecuted;
        });

        test('post() includes CSRF token from meta tag', async () => {
            const meta = document.createElement('meta');
            meta.name = 'csrf-token';
            meta.content = 'test-token';
            document.head.appendChild(meta);

            const manager = makeManager(0);
            global.fetch = jest.fn(() => Promise.resolve({ ok: true }));

            await manager.post('/test', 'data=1');

            expect(global.fetch).toHaveBeenCalledWith('/test', expect.objectContaining({
                headers: expect.objectContaining({
                    'X-CSRF-Token': 'test-token'
                })
            }));
            document.head.removeChild(meta);
        });
    });

    describe('deleteLogo()', () => {
        test('removes the logo after confirmation and moves focus', () => {
            const manager = makeManager(0);
            const li1 = document.createElement('li');
            li1.id = 'logo-1';
            const editBtn1 = document.createElement('button');
            editBtn1.setAttribute('data-action', 'toggle-edit');
            li1.appendChild(editBtn1);
            
            const li2 = document.createElement('li');
            li2.id = 'logo-2';
            const editBtn2 = document.createElement('button');
            editBtn2.setAttribute('data-action', 'toggle-edit');
            li2.appendChild(editBtn2);

            manager.rootList.appendChild(li1);
            manager.rootList.appendChild(li2);

            jest.spyOn(window, 'confirm').mockReturnValue(true);
            
            // Delete first logo, should focus second logo's edit button
            manager.deleteLogo('logo-1');
            expect(document.getElementById('logo-1')).toBeNull();
            expect(document.activeElement).toBe(editBtn2);
        });

        test('does not remove the logo if cancelled', () => {
            const manager = makeManager(0);
            const li = document.createElement('li');
            li.id = 'logo-test';
            manager.rootList.appendChild(li);

            jest.spyOn(window, 'confirm').mockReturnValue(false);
            manager.deleteLogo('logo-test');

            expect(document.getElementById('logo-test')).not.toBeNull();
        });
    });

    describe('toggleEditForm()', () => {
        test('toggles hidden attribute and aria-expanded', () => {
            const manager = makeManager(0);
            const btn = document.createElement('button');
            btn.setAttribute('aria-controls', 'form-test');
            btn.setAttribute('aria-expanded', 'false');
            
            const form = document.createElement('div');
            form.id = 'form-test';
            form.hidden = true;
            document.body.appendChild(form);

            manager.toggleEditForm(btn);
            expect(form.hidden).toBe(false);
            expect(btn.getAttribute('aria-expanded')).toBe('true');

            manager.toggleEditForm(btn);
            expect(form.hidden).toBe(true);
            expect(btn.getAttribute('aria-expanded')).toBe('false');
        });

        test('focuses the first input when opening the form', () => {
            const manager = makeManager(0);
            const btn = document.createElement('button');
            btn.setAttribute('aria-controls', 'form-focus');
            btn.setAttribute('aria-expanded', 'false');
            
            const form = document.createElement('div');
            form.id = 'form-focus';
            form.hidden = true;
            const input = document.createElement('input');
            form.appendChild(input);
            document.body.appendChild(form);

            manager.toggleEditForm(btn);
            expect(document.activeElement).toBe(input);
        });
    });

    describe('renameInputFile()', () => {
        test('updates the name of a file input inside a logo item', () => {
            const manager = makeManager(0);
            const li = document.createElement('li');
            li.id = 'logo-10';
            const input = document.createElement('input');
            input.type = 'file';
            li.appendChild(input);
            manager.rootList.appendChild(li);

            manager.renameInputFile('logo-10', 'new_name[img]');
            expect(input.name).toBe('new_name[img]');
        });
    });

    describe('conditional display and required attribute', () => {
        test('displayElements toggles required attribute based on visibility', () => {
            const manager = makeManager(0);
            const container = document.createElement('div');
            container.className = 'menu-edit-form';
            
            const select = document.createElement('select');
            select.setAttribute('elem', 'type');
            select.className = 'elem-link';
            select.innerHTML = '<option value="img" selected>Image</option><option value="text">Text</option>';
            container.appendChild(select);

            const groupImg = document.createElement('div');
            groupImg.className = 'form-group';
            const inputImg = document.createElement('input');
            inputImg.setAttribute('elem-link', 'type');
            inputImg.setAttribute('elem-value', 'img');
            inputImg.required = true; // Initial state
            groupImg.appendChild(inputImg);
            container.appendChild(groupImg);

            document.body.appendChild(container);
            Object.defineProperty(select, 'offsetParent', { get: () => document.body });

            // Initial: visible and required
            manager.displayElements(select);
            expect(groupImg.hidden).toBe(false);
            expect(inputImg.required).toBe(true);

            // Change to text: hidden and NOT required
            select.value = 'text';
            manager.displayElements(select);
            expect(groupImg.hidden).toBe(true);
            expect(inputImg.required).toBe(false);
            expect(inputImg.hasAttribute('data-was-required')).toBe(true);

            // Change back to img: visible and required again
            select.value = 'img';
            manager.displayElements(select);
            expect(groupImg.hidden).toBe(false);
            expect(inputImg.required).toBe(true);
            expect(inputImg.hasAttribute('data-was-required')).toBe(false);
        });
    });

    describe('dynamic logo synchronization', () => {
        test('_syncLogoItem updates icon and title correctly', () => {
            const manager = makeManager(0);
            const li = document.createElement('li');
            li.id = 'logo-sync';
            li.innerHTML = `
                <div class="menu-item">
                    <span class="menu-item-name">
                        <span class="glyphicon"></span>&nbsp;-&nbsp;<span class="menu-item-name-text"></span>
                    </span>
                    <div class="menu-edit-form">
                        <select elem="type">
                            <option value="text" selected>Text</option>
                            <option value="img">Image</option>
                        </select>
                        <input type="text" name="logo_0[text][fr]" value="Mon Logo" class="header-label-input">
                        <input type="hidden" name="logo_0[img_tmp]" value="image.png">
                        <input type="file" name="logo_0[img]">
                    </div>
                </div>
            `;
            manager.rootList.appendChild(li);
            manager._syncLogoItem(li);

            const iconSpan = li.querySelector('.menu-item-name .glyphicon');
            const itemNameText = li.querySelector('.menu-item-name-text');
            const typeSelect = li.querySelector('select');
            const textInput = li.querySelector('.header-label-input');

            // Initial (Text)
            expect(iconSpan.classList.contains('glyphicon-font')).toBe(true);
            expect(itemNameText.textContent).toBe('Mon Logo');

            // Change text
            textInput.value = 'Nouveau Titre';
            textInput.dispatchEvent(new Event('input'));
            expect(itemNameText.textContent).toBe('Nouveau Titre');

            // Change to Image
            typeSelect.value = 'img';
            typeSelect.dispatchEvent(new Event('change'));
            expect(iconSpan.classList.contains('glyphicon-picture')).toBe(true);
            expect(itemNameText.textContent).toBe('image.png');
        });
    });
});
