'use strict';

const {
    initTomSelectFilters,
    bootTomSelectFilters,
} = require('../../../public/js/common/tomselect-filters');

function buildSelect(id, withEmptyOption) {
    const select = document.createElement('select');
    select.id = id;
    select.multiple = true;

    if (withEmptyOption) {
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Tous';
        select.appendChild(empty);
    }

    document.body.appendChild(select);
    return select;
}

function buildLabel(forId, text) {
    const label = document.createElement('label');
    label.setAttribute('for', forId);
    label.textContent = text;
    document.body.appendChild(label);
    return label;
}

describe('initTomSelectFilters', () => {
    let instances;

    beforeEach(() => {
        document.body.innerHTML = '';
        instances = [];
        global.TomSelect = jest.fn().mockImplementation(function (el) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('ts-wrapper', 'form-control');

            const controlInput = document.createElement('input');
            const dropdownContent = document.createElement('div');

            const instance = {
                el,
                wrapper,
                control_input: controlInput,
                dropdown_content: dropdownContent,
                setTextboxValue: jest.fn(),
                on: jest.fn(),
            };
            instances.push(instance);
            return instance;
        });
    });

    afterEach(() => {
        delete global.TomSelect;
    });

    it('returns an empty array when TomSelect is not loaded', () => {
        delete global.TomSelect;
        buildSelect('status', true);
        expect(initTomSelectFilters(['status'])).toEqual([]);
    });

    it('skips ids that are missing from the page', () => {
        buildSelect('status', true);
        const result = initTomSelectFilters(['status', 'ratingStatus']);
        expect(result).toHaveLength(1);
    });

    it('skips elements already enhanced', () => {
        const el = buildSelect('status', true);
        el.tomselect = {};
        const result = initTomSelectFilters(['status']);
        expect(result).toHaveLength(0);
        expect(global.TomSelect).not.toHaveBeenCalled();
    });

    it('derives the placeholder from the empty option', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status']);
        expect(global.TomSelect.mock.calls[0][1].placeholder).toBe('Tous');
    });

    it('defaults to the remove_button plugin when no options are passed', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status']);
        expect(global.TomSelect.mock.calls[0][1].plugins).toEqual([
            'remove_button',
        ]);
    });

    it('passes through a custom plugin list', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status'], {
            plugins: ['checkbox_options', 'dropdown_input', 'remove_button'],
        });
        expect(global.TomSelect.mock.calls[0][1].plugins).toEqual([
            'checkbox_options',
            'dropdown_input',
            'remove_button',
        ]);
    });

    it('hides the native select and strips the form-control class from the wrapper', () => {
        const el = buildSelect('status', true);
        initTomSelectFilters(['status']);
        expect(el.style.getPropertyValue('display')).toBe('none');
        expect(instances[0].wrapper.classList.contains('form-control')).toBe(
            false
        );
    });

    it('sets aria-label from the associated label when aria-labelledby is absent', () => {
        buildSelect('status', true);
        buildLabel('status', "Statut de l'article");
        initTomSelectFilters(['status']);
        expect(instances[0].control_input.getAttribute('aria-label')).toBe(
            "Statut de l'article"
        );
    });

    it('does not override aria-labelledby already set by TomSelect itself', () => {
        buildSelect('status', true);
        buildLabel('status', "Statut de l'article");
        global.TomSelect = jest.fn().mockImplementation(function (el) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('ts-wrapper', 'form-control');
            const controlInput = document.createElement('input');
            controlInput.setAttribute('aria-labelledby', 'status-ts-label');
            const instance = {
                el,
                wrapper,
                control_input: controlInput,
                dropdown_content: document.createElement('div'),
                setTextboxValue: jest.fn(),
                on: jest.fn(),
            };
            instances.push(instance);
            return instance;
        });
        initTomSelectFilters(['status']);
        expect(instances[0].control_input.hasAttribute('aria-label')).toBe(
            false
        );
    });

    it('wires checkbox a11y patching only when checkbox_options is requested', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status'], {
            plugins: ['checkbox_options', 'dropdown_input', 'remove_button'],
        });
        expect(instances[0].on).toHaveBeenCalledWith(
            'type',
            expect.any(Function)
        );
        expect(instances[0].on).toHaveBeenCalledWith(
            'dropdown_open',
            expect.any(Function)
        );
    });

    it('does not wire checkbox a11y patching for the default remove_button-only plugin list', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status']);
        expect(instances[0].on).not.toHaveBeenCalled();
    });

    it('strips checkboxes from the tab order and hides them from assistive tech', () => {
        buildSelect('status', true);
        initTomSelectFilters(['status'], { plugins: ['checkbox_options'] });

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        instances[0].dropdown_content.appendChild(checkbox);

        const patch = instances[0].on.mock.calls.find(
            call => call[0] === 'dropdown_open'
        )[1];
        patch();

        expect(checkbox.getAttribute('tabindex')).toBe('-1');
        expect(checkbox.getAttribute('aria-hidden')).toBe('true');
    });
});

describe('bootTomSelectFilters', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        global.TomSelect = jest.fn().mockImplementation(function (el) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('ts-wrapper', 'form-control');
            return {
                el,
                wrapper,
                control_input: document.createElement('input'),
                dropdown_content: document.createElement('div'),
                setTextboxValue: jest.fn(),
                on: jest.fn(),
            };
        });
    });

    afterEach(() => {
        delete global.TomSelect;
    });

    it('initializes immediately when TomSelect is already loaded', () => {
        buildSelect('status', true);
        bootTomSelectFilters(['status']);
        expect(global.TomSelect).toHaveBeenCalledTimes(1);
    });

    it('waits for DOMContentLoaded when TomSelect is not loaded yet', () => {
        delete global.TomSelect;
        buildSelect('status', true);
        bootTomSelectFilters(['status']);

        global.TomSelect = jest.fn().mockImplementation(function (el) {
            return {
                el,
                wrapper: document.createElement('div'),
                control_input: document.createElement('input'),
                dropdown_content: document.createElement('div'),
                setTextboxValue: jest.fn(),
                on: jest.fn(),
            };
        });
        document.dispatchEvent(new Event('DOMContentLoaded'));

        expect(global.TomSelect).toHaveBeenCalledTimes(1);
    });
});
