'use strict';

/**
 * Test suite for pure helper functions exported from
 * public/js/administratepaper/volume-assignment.js.
 *
 * Covers:
 *  - filterOptions   : case-insensitive filtering of option descriptors
 *  - rebuildSelectOptions : DOM rebuild of a <select> element
 *  - initVolumeSearch : event-driven integration (input filter + change collapse)
 *
 * Excluded from this suite (jQuery/Bootstrap popover dependencies):
 *  - getMasterVolumeForm, getOtherVolumesForm, refreshVolumes
 */

// ---------------------------------------------------------------------------
// Globals required to load volume-assignment.js without errors
// ---------------------------------------------------------------------------

global.$ = () => ({
    popover: () => global.$(),
    on: () => global.$(),
    data: () => null,
    serialize: () => '',
    hide: () => global.$(),
    html: () => global.$(),
    fadeIn: () => global.$(),
    val: () => '',
    each: () => global.$(),
});
global.getLoader = () => '';
global.ajaxRequest = () => ({ done: () => {} });

// ---------------------------------------------------------------------------
// Load the module under test
// ---------------------------------------------------------------------------

const {
    filterOptions,
    rebuildSelectOptions,
    initVolumeSearch,
    initCheckboxSearch,
} = require('../../../public/js/administratepaper/volume-assignment');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a plain <select> element pre-populated with the given options. */
function makeSelect(optionData) {
    const select = document.createElement('select');
    optionData.forEach(({ value, text, selected }) => {
        const opt = new Option(text, value);
        if (selected) opt.selected = true;
        select.add(opt);
    });
    return select;
}

/** Build the full DOM used by initVolumeSearch and return the two elements. */
function makeSearchDOM(optionData) {
    document.body.innerHTML = `
        <input type="text" id="volume_search_input">
        <select id="master_volume_select">
            ${optionData
                .map(
                    ({ value, text, selected }) =>
                        `<option value="${value}"${selected ? ' selected' : ''}>${text}</option>`
                )
                .join('')}
        </select>
    `;
    return {
        searchInput: document.getElementById('volume_search_input'),
        volumeSelect: document.getElementById('master_volume_select'),
    };
}

const SAMPLE_OPTIONS = [
    { value: '1', text: 'Volume 1', selected: false },
    { value: '2', text: 'Special Issue', selected: true },
    { value: '3', text: 'VOLUME 3', selected: false },
    { value: '0', text: 'Hors volume', selected: false },
];

// ---------------------------------------------------------------------------
// filterOptions
// ---------------------------------------------------------------------------

describe('filterOptions', () => {
    const options = [
        { value: '1', text: 'Volume 1' },
        { value: '2', text: 'Special Issue' },
        { value: '3', text: 'VOLUME 3' },
        { value: '0', text: 'Hors volume' },
    ];

    test('empty query returns all options', () => {
        expect(filterOptions(options, '')).toHaveLength(options.length);
    });

    test('null-ish query (empty string) does not filter', () => {
        expect(filterOptions(options, '')).toStrictEqual(options);
    });

    test('matches case-insensitively (lowercase query, mixed-case options)', () => {
        // 'Volume 1', 'VOLUME 3', 'Hors volume' all contain 'volume'
        expect(filterOptions(options, 'volume')).toHaveLength(3);
    });

    test('matches case-insensitively (uppercase query)', () => {
        expect(filterOptions(options, 'SPECIAL')).toHaveLength(1);
        expect(filterOptions(options, 'SPECIAL')[0].value).toBe('2');
    });

    test('partial match works', () => {
        expect(filterOptions(options, 'hors')).toHaveLength(1);
        expect(filterOptions(options, 'hors')[0].value).toBe('0');
    });

    test('no match returns empty array', () => {
        expect(filterOptions(options, 'xyz_no_match')).toHaveLength(0);
    });

    test('empty options array returns empty array regardless of query', () => {
        expect(filterOptions([], 'volume')).toHaveLength(0);
    });

    test('returns a new array and does not mutate the original', () => {
        const original = [{ value: '1', text: 'Volume 1' }];
        const result = filterOptions(original, 'xyz');
        expect(result).not.toBe(original);
        expect(original).toHaveLength(1);
    });

    test('matches substring in the middle of text', () => {
        expect(filterOptions(options, 'ial iss')).toHaveLength(1);
    });
});

// ---------------------------------------------------------------------------
// rebuildSelectOptions
// ---------------------------------------------------------------------------

describe('rebuildSelectOptions', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('replaces existing options with the new list', () => {
        const select = makeSelect([{ value: '1', text: 'Old' }]);
        rebuildSelectOptions(select, [{ value: '2', text: 'New' }], '');
        expect(select.options).toHaveLength(1);
        expect(select.options[0].text).toBe('New');
        expect(select.options[0].value).toBe('2');
    });

    test('marks the option matching currentVal as selected', () => {
        const select = makeSelect([]);
        rebuildSelectOptions(
            select,
            [
                { value: '1', text: 'A' },
                { value: '2', text: 'B' },
                { value: '3', text: 'C' },
            ],
            '2'
        );
        expect(select.options[1].selected).toBe(true);
        expect(select.options[0].selected).toBe(false);
        expect(select.options[2].selected).toBe(false);
    });

    test('only the matching option is selected when currentVal matches one of many', () => {
        // With multiple options and no match, the browser auto-selects the first;
        // this test verifies the match logic with multiple candidates.
        const select = makeSelect([]);
        rebuildSelectOptions(
            select,
            [
                { value: '1', text: 'A' },
                { value: '2', text: 'B' },
            ],
            '99'
        );
        // Neither option explicitly selected — browser default selects first, both are fine.
        // What matters: option '2' is NOT selected when currentVal='99'.
        expect(select.options[1].selected).toBe(false);
    });

    test('preserves the order of the supplied options', () => {
        const select = makeSelect([]);
        const opts = [
            { value: '3', text: 'C' },
            { value: '1', text: 'A' },
            { value: '2', text: 'B' },
        ];
        rebuildSelectOptions(select, opts, '');
        expect(select.options[0].text).toBe('C');
        expect(select.options[1].text).toBe('A');
        expect(select.options[2].text).toBe('B');
    });

    test('empty options array leaves select empty', () => {
        const select = makeSelect([{ value: '1', text: 'Old' }]);
        rebuildSelectOptions(select, [], '');
        expect(select.options).toHaveLength(0);
    });

    test('coerces value comparison to string (number currentVal)', () => {
        const select = makeSelect([]);
        rebuildSelectOptions(select, [{ value: '42', text: 'Forty-two' }], 42);
        expect(select.options[0].selected).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// initVolumeSearch
// ---------------------------------------------------------------------------

describe('initVolumeSearch', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('filters select options when the user types', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.options).toHaveLength(1);
        expect(volumeSelect.options[0].text).toBe('Special Issue');
    });

    test('expands select size (> 1) while the user types', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'vol';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.size).toBeGreaterThan(1);
    });

    test('caps expanded size at 8 when many options match', () => {
        const manyOptions = Array.from({ length: 15 }, (_, i) => ({
            value: String(i + 1),
            text: `Volume ${i + 1}`,
            selected: false,
        }));
        const { searchInput, volumeSelect } = makeSearchDOM(manyOptions);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'vol';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.size).toBe(8);
    });

    test('sets size to 1 when no options match (avoids size=0)', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'xyz_no_match';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.size).toBe(1);
    });

    test('restores all options when query is cleared', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.options).toHaveLength(SAMPLE_OPTIONS.length);
    });

    test('collapses select size to 1 when query is cleared', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'vol';
        searchInput.dispatchEvent(new Event('input'));

        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.size).toBe(1);
    });

    test('collapses select and clears search on option change', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'vol';
        searchInput.dispatchEvent(new Event('input'));

        volumeSelect.dispatchEvent(new Event('change'));

        expect(volumeSelect.size).toBe(1);
        expect(searchInput.value).toBe('');
    });

    test('restores the full option list on option change', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        volumeSelect.dispatchEvent(new Event('change'));

        expect(volumeSelect.options).toHaveLength(SAMPLE_OPTIONS.length);
    });

    test('preserves the previously selected value after filtering', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        // SAMPLE_OPTIONS[1] (value='2') is pre-selected
        initVolumeSearch(searchInput, volumeSelect);

        // Filter to a set that still contains value '2'
        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.value).toBe('2');
    });

    test('does not throw when no options match the query', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        expect(() => {
            searchInput.value = 'xyz_no_match';
            searchInput.dispatchEvent(new Event('input'));
        }).not.toThrow();
    });

    test('filtering is case-insensitive', () => {
        const { searchInput, volumeSelect } = makeSearchDOM(SAMPLE_OPTIONS);
        initVolumeSearch(searchInput, volumeSelect);

        searchInput.value = 'SPECIAL';
        searchInput.dispatchEvent(new Event('input'));

        expect(volumeSelect.options).toHaveLength(1);
        expect(volumeSelect.options[0].text).toBe('Special Issue');
    });
});

// ---------------------------------------------------------------------------
// initCheckboxSearch
// ---------------------------------------------------------------------------

describe('initCheckboxSearch', () => {
    const VOLUME_NAMES = [
        'Volume 1',
        'Special Issue',
        'VOLUME 3',
        'Hors volume',
    ];

    /** Build a container with .multicheckbox_option labels and a search input. */
    function makeCheckboxDOM(names) {
        const container = document.createElement('div');
        container.id = 'other_volumes_list';
        names.forEach((name, i) => {
            const label = document.createElement('label');
            label.className = 'multicheckbox_option';
            label.innerHTML =
                `<input type="checkbox" id="cb_v_${i}" name="volume_${i}">` +
                `<span>${name}</span>`;
            container.appendChild(label);
        });
        document.body.innerHTML =
            '<input type="text" id="other_volumes_search_input">';
        document.body.appendChild(container);
        return {
            searchInput: document.getElementById('other_volumes_search_input'),
            container,
        };
    }

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('hides non-matching items when typing', () => {
        const { searchInput, container } = makeCheckboxDOM(VOLUME_NAMES);
        initCheckboxSearch(searchInput, container);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        const items = container.querySelectorAll('.multicheckbox_option');
        expect(items[0].hidden).toBe(true); // Volume 1
        expect(items[1].hidden).toBe(false); // Special Issue
        expect(items[2].hidden).toBe(true); // VOLUME 3
        expect(items[3].hidden).toBe(true); // Hors volume
    });

    test('shows all items when query is empty', () => {
        const { searchInput, container } = makeCheckboxDOM(VOLUME_NAMES);
        initCheckboxSearch(searchInput, container);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));

        container.querySelectorAll('.multicheckbox_option').forEach(item => {
            expect(item.hidden).toBe(false);
        });
    });

    test('filtering is case-insensitive', () => {
        const { searchInput, container } = makeCheckboxDOM(VOLUME_NAMES);
        initCheckboxSearch(searchInput, container);

        searchInput.value = 'VOLUME';
        searchInput.dispatchEvent(new Event('input'));

        const items = container.querySelectorAll('.multicheckbox_option');
        // 'Volume 1', 'VOLUME 3', 'Hors volume' — 3 matches
        const visible = Array.from(items).filter(item => !item.hidden);
        expect(visible).toHaveLength(3);
    });

    test('hides all items when no match', () => {
        const { searchInput, container } = makeCheckboxDOM(VOLUME_NAMES);
        initCheckboxSearch(searchInput, container);

        searchInput.value = 'xyz_no_match';
        searchInput.dispatchEvent(new Event('input'));

        container.querySelectorAll('.multicheckbox_option').forEach(item => {
            expect(item.hidden).toBe(true);
        });
    });

    test('does not throw when container has no items', () => {
        const { searchInput, container } = makeCheckboxDOM([]);
        expect(() => {
            initCheckboxSearch(searchInput, container);
            searchInput.value = 'vol';
            searchInput.dispatchEvent(new Event('input'));
        }).not.toThrow();
    });

    test('checked items are hidden by search like unchecked ones', () => {
        const { searchInput, container } = makeCheckboxDOM([
            'Volume 1',
            'Special Issue',
        ]);
        // Check first item
        const cb = container.querySelector('input[type="checkbox"]');
        cb.checked = true;

        initCheckboxSearch(searchInput, container);

        searchInput.value = 'special';
        searchInput.dispatchEvent(new Event('input'));

        const items = container.querySelectorAll('.multicheckbox_option');
        expect(items[0].hidden).toBe(true); // checked but hidden by filter
        expect(items[1].hidden).toBe(false);
    });

    test('partial match works', () => {
        const { searchInput, container } = makeCheckboxDOM(VOLUME_NAMES);
        initCheckboxSearch(searchInput, container);

        searchInput.value = 'hors';
        searchInput.dispatchEvent(new Event('input'));

        const items = container.querySelectorAll('.multicheckbox_option');
        const visible = Array.from(items).filter(i => !i.hidden);
        expect(visible).toHaveLength(1);
        expect(visible[0].querySelector('span').textContent).toBe(
            'Hors volume'
        );
    });
});
