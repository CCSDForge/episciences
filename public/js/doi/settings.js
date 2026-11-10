/**
 * DOI settings page (doi/settings): preview of a DOI built with the format being typed,
 * with example values. Same rules as Episciences_Review_DoiSettings::createDoiWithTemplate().
 */

/**
 * The numbers of a volume or section name, separated with a character
 * (Episciences_Review_DoiSettings::keepOnlyIntegersInTag()).
 * @param {string} name
 * @param {string} separator
 * @returns {string}
 */
function doiKeepOnlyIntegers(name, separator = '.') {
    const escaped = separator.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const trimSeparator = new RegExp(`^(${escaped})+|(${escaped})+$`, 'g');

    return String(name)
        .trim()
        .replace(/\D+/g, separator)
        .replace(trimSeparator, '')
        .replace(/^\.+|\.+$/g, '');
}

/**
 * DOI built from a prefix and a format.
 * @param {string} prefix
 * @param {string} format
 * @param {Object<string, string>} examples value of each tag, e.g. {'%R%': 'jcode', '%V%': 'Volume 12'}
 * @returns {string} '' without prefix
 */
function doiPreview(prefix, format, examples) {
    if (!prefix) {
        return '';
    }

    let doi = String(format || '');

    ['V', 'S'].forEach(letter => {
        const name = examples[`%${letter}%`] || '';
        doi = doi.replace(
            new RegExp(`%${letter}_INT\\[(.)\\]%`, 'g'),
            (match, separator) =>
                name ? doiKeepOnlyIntegers(name, separator) : ''
        );
    });

    // one tag after the other, in the order of the examples (that of the server), "%%" first
    const values = { '%%': '%', ...examples };
    Object.keys(values).forEach(tag => {
        doi = doi.split(tag).join(values[tag]);
    });

    doi = doi.replace(/ /g, '').replace(/\.\./g, '.').replace(/--/g, '-');

    return `${prefix}/${doi.toLowerCase()}`;
}

/**
 * @param {Document|HTMLElement} root
 */
function initDoiSettings(root = document) {
    const formatInput = root.querySelector('.doi-format-input');
    const prefixInput = root.querySelector('#doiPrefix');
    const output = root.querySelector('.doi-format-preview-value');

    if (!formatInput || !prefixInput || !output) {
        return;
    }

    let examples = {};
    try {
        examples = JSON.parse(
            formatInput.getAttribute('data-doi-examples') || '{}'
        );
    } catch (e) {
        examples = {};
    }

    const update = () => {
        const prefix =
            prefixInput.value.trim() ||
            prefixInput.getAttribute('placeholder') ||
            '10.12345';
        output.textContent = doiPreview(
            prefix,
            formatInput.value.trim(),
            examples
        );
    };

    formatInput.addEventListener('input', update);
    prefixInput.addEventListener('input', update);
    update();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initDoiSettings());
}

if (typeof module !== 'undefined') {
    module.exports = {
        doiKeepOnlyIntegers,
        doiPreview,
        initDoiSettings,
    };
}
