'use strict';

/**
 * Tests for public/js/paper/rating.js: overall score shown under the reviewer rating form.
 */

global.translate = text => (text === 'Note globale' ? 'Overall rating' : text);

const {
    computeOverallScore,
    readGrades,
    initOverallScore,
} = require('../../../public/js/paper/rating.js');

function renderForm(coefficients, maxScoreAttribute = 'data-max-score="10"') {
    document.body.innerHTML = `
        <form id="rating-grid" data-coefficients='${JSON.stringify(coefficients)}' ${maxScoreAttribute}>
            <select name="note_item_1" data-max-note="5">
                ${[0, 1, 2, 3, 4, 5].map(i => `<option value="${i}">${i}/5</option>`).join('')}
            </select>
            <select name="note_item_2" data-max-note="10">
                ${[...Array(11).keys()].map(i => `<option value="${i}">${i}/10</option>`).join('')}
            </select>
            <select name="note_item_3" data-max-note="2">
                <option value="0">Yes</option><option value="1">No</option><option value="2">Maybe</option>
            </select>
            <input type="hidden" name="noteGlobale" id="noteGlobale" value="">
            <div class="overallValue" aria-live="polite"></div>
        </form>`;
    return document.getElementById('rating-grid');
}

function grade(form, name, value) {
    const select = form.querySelector(`select[name="${name}"]`);
    select.value = String(value);
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

describe('computeOverallScore', () => {
    test('weights the grades like Episciences_Rating_Report::calculateScore()', () => {
        // (5/5 x 3 + 0/10 x 1) / (3 + 1) x 10 = 7.5
        const grades = [
            { note: 5, maxNote: 5, coefficient: 3 },
            { note: 0, maxNote: 10, coefficient: 1 },
        ];
        expect(computeOverallScore(grades, 10)).toBe(7.5);
    });

    test('rounds to one decimal', () => {
        // (1/3 x 1) / 1 x 10 = 3.333...
        expect(
            computeOverallScore([{ note: 1, maxNote: 3, coefficient: 1 }], 10)
        ).toBe(3.3);
    });

    test('ignores the criteria without coefficient', () => {
        const grades = [
            { note: 4, maxNote: 5, coefficient: 2 },
            { note: 0, maxNote: 2, coefficient: 0 },
        ];
        expect(computeOverallScore(grades, 10)).toBe(8);
    });

    test('gives null when no criterion is weighted', () => {
        expect(
            computeOverallScore([{ note: 1, maxNote: 2, coefficient: 0 }], 10)
        ).toBeNull();
        expect(computeOverallScore([], 10)).toBeNull();
    });
});

describe('overall score under the rating form', () => {
    test('reads the grades with their coefficient and maximum grade', () => {
        const form = renderForm({ item_1: 3, item_2: 1 });
        grade(form, 'note_item_1', 4);

        expect(readGrades(form)).toEqual([
            { note: 4, maxNote: 5, coefficient: 3 },
            { note: 0, maxNote: 10, coefficient: 1 },
            { note: 0, maxNote: 2, coefficient: 0 },
        ]);
    });

    test('is shown when the form loads and updated when a grade changes, without throwing', () => {
        const form = renderForm({ item_1: 3, item_2: 1 });
        initOverallScore(form);

        expect(form.querySelector('.overallValue').textContent).toBe(
            'Overall rating : 0/10'
        );

        grade(form, 'note_item_1', 5);
        expect(form.querySelector('.overallValue').textContent).toBe(
            'Overall rating : 7.5/10'
        );
        expect(form.querySelector('#noteGlobale').value).toBe('7.5');

        // a qualitative criterion does not change the score
        grade(form, 'note_item_3', 2);
        expect(form.querySelector('.overallValue').textContent).toBe(
            'Overall rating : 7.5/10'
        );
    });

    test('uses a maximum score of 10 only when the form does not give one', () => {
        const form = renderForm({ item_1: 1 }, '');
        initOverallScore(form);
        grade(form, 'note_item_1', 5);

        expect(form.querySelector('.overallValue').textContent).toBe(
            'Overall rating : 10/10'
        );
    });

    test('follows a maximum score of 0 like the server', () => {
        const form = renderForm({ item_1: 1 }, 'data-max-score="0"');
        initOverallScore(form);
        grade(form, 'note_item_1', 5);

        expect(form.querySelector('.overallValue').textContent).toBe(
            'Overall rating : 0/0'
        );
    });

    test('shows nothing when the grid has no weighted criterion', () => {
        const form = renderForm({});
        initOverallScore(form);
        grade(form, 'note_item_1', 5);

        expect(form.querySelector('.overallValue').textContent).toBe('');
    });
});
