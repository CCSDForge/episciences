/**
 * Overall score of a rating report, computed like Episciences_Rating_Report::calculateScore(1):
 * sum(note / maxNote * coefficient) / sum(coefficients) * maxScore, rounded to one decimal.
 * Only the weighted criteria (coefficient > 0) count.
 *
 * @param {Array<{note: number, maxNote: number, coefficient: number}>} grades
 * @param {number} maxScore
 * @returns {number|null} null when no criterion is weighted
 */
function computeOverallScore(grades, maxScore) {
    let weighted = 0;
    let coefficients = 0;

    grades.forEach(grade => {
        if (!(grade.coefficient > 0)) {
            return;
        }
        coefficients += grade.coefficient;
        if (grade.maxNote > 0 && Number.isFinite(grade.note)) {
            weighted += (grade.note / grade.maxNote) * grade.coefficient;
        }
    });

    if (coefficients === 0) {
        return null;
    }

    return Math.round((weighted / coefficients) * maxScore * 10) / 10;
}

/**
 * @param {HTMLFormElement} form rating form (data-coefficients: JSON map criterion id => coefficient)
 * @returns {Array<{note: number, maxNote: number, coefficient: number}>}
 */
function readGrades(form) {
    let coefficients = {};
    try {
        coefficients = JSON.parse(form.dataset.coefficients || '{}') || {};
    } catch (e) {
        coefficients = {};
    }

    return Array.from(form.querySelectorAll('select[name^="note_"]')).map(
        select => ({
            note: parseInt(select.value, 10),
            maxNote: parseInt(select.dataset.maxNote, 10),
            coefficient: Number(coefficients[select.name.substring(5)]) || 0,
        })
    );
}

/**
 * Shows the overall score under the rating form
 * @param {HTMLFormElement} form
 */
function updateOverallScore(form) {
    const maxScore = Number(form.dataset.maxScore) || 10;
    const score = computeOverallScore(readGrades(form), maxScore);
    const display = form.querySelector('.overallValue');

    if (score === null || !display) {
        return;
    }

    const hidden = form.querySelector('#noteGlobale');
    if (hidden) {
        hidden.value = String(score);
    }

    const value = document.createElement('span');
    value.className = 'text-warning';
    value.textContent = `${score}/${maxScore}`;
    display.replaceChildren(`${translate('Note globale')} : `, value);
}

/**
 * @param {HTMLFormElement|null} form
 */
function initOverallScore(form) {
    if (!form) {
        return;
    }
    form.addEventListener('change', event => {
        if (event.target.matches('select[name^="note_"]')) {
            updateOverallScore(form);
        }
    });
    updateOverallScore(form);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () =>
        initOverallScore(document.getElementById('rating-grid'))
    );
} else {
    initOverallScore(document.getElementById('rating-grid'));
}

/**
 * Confirme avant la suppression
 * @param url_file
 */

function confirmDeleteAttachment(url_file) {
    if (confirm(translate('Voulez-vous supprimer ce fichier ?'))) {
        location.href = url_file;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        computeOverallScore,
        readGrades,
        updateOverallScore,
        initOverallScore,
    };
}
