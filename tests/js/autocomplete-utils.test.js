'use strict';

/**
 * Tests for public/js/autocomplete-utils.js: search URL of the user autocomplete.
 */

const {
    createUserAutocomplete,
} = require('../../public/js/autocomplete-utils.js');

function renderInput() {
    document.body.innerHTML =
        '<div><input type="text" id="autocomplete" aria-label="Search a user"></div>';
}

describe('user autocomplete search URL', () => {
    beforeEach(renderInput);

    test('sends the search term', () => {
        const autocomplete = createUserAutocomplete({
            inputId: 'autocomplete',
            url: '/user/findcasusers',
        });

        expect(autocomplete.buildSearchUrl('dupont & co')).toBe(
            '/user/findcasusers?term=dupont+%26+co'
        );
    });

    test('sends the ignored user ids as ignore_list[]', () => {
        const autocomplete = createUserAutocomplete({
            inputId: 'autocomplete',
            url: '/user/findcasusers',
            ignoreList: [12, '34'],
        });

        const params = new URL(
            autocomplete.buildSearchUrl('dupont'),
            'https://example.org'
        ).searchParams;
        expect(params.get('term')).toBe('dupont');
        expect(params.getAll('ignore_list[]')).toEqual(['12', '34']);
    });

    test('ignores an ignore list that is not an array', () => {
        const autocomplete = createUserAutocomplete({
            inputId: 'autocomplete',
            ignoreList: null,
        });

        expect(autocomplete.buildSearchUrl('dupont')).toBe(
            '/user/findcasusers?term=dupont'
        );
    });

    test('appends query parameters with & when the url already has a query string', () => {
        const autocomplete = createUserAutocomplete({
            inputId: 'autocomplete',
            url: '/user/findcasusers?scope=journal',
            ignoreList: [12],
        });

        expect(autocomplete.buildSearchUrl('dupont')).toBe(
            '/user/findcasusers?scope=journal&term=dupont&ignore_list%5B%5D=12'
        );
    });
});
