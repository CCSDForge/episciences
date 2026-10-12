/**
 * Journal licenses page: keep the default license selected in the allowed licenses.
 *
 * An empty allowed list means "no restriction" (all the recommended licenses):
 * it is left untouched. Otherwise the default license is (re)selected, so it
 * cannot be deselected while other licenses are allowed.
 */

/**
 * @param {HTMLSelectElement} defaultSelect
 * @param {HTMLSelectElement} allowedSelect
 * @returns {boolean} true if the default license has been selected
 */
function selectDefaultInAllowedLicenses(defaultSelect, allowedSelect) {
    const code = defaultSelect.value;

    if (!code) {
        return false;
    }

    const options = Array.from(allowedSelect.options);

    if (!options.some(option => option.selected)) {
        return false;
    }

    const defaultOption = options.find(option => option.value === code);

    if (!defaultOption || defaultOption.selected) {
        return false;
    }

    defaultOption.selected = true;
    return true;
}

/**
 * @param {Document|HTMLElement} root
 */
function initLicensesSettings(root = document) {
    const defaultSelect = root.querySelector('#licenseDefault');
    const allowedSelect = root.querySelector('#licensesAllowed');

    if (!defaultSelect || !allowedSelect) {
        return;
    }

    const sync = () =>
        selectDefaultInAllowedLicenses(defaultSelect, allowedSelect);

    defaultSelect.addEventListener('change', sync);
    allowedSelect.addEventListener('change', sync);
    sync();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initLicensesSettings());
}

if (typeof module !== 'undefined') {
    module.exports = {
        selectDefaultInAllowedLicenses,
        initLicensesSettings,
    };
}
