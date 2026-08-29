// Extracted from public/js/submit/function.js for testing
// Global variables Déclaration
let $sectionsElement = null;
let $sections = null;
let $suggestEditorsElement = null;
let $suggest_editors = null;
let $firstDisclaimersDisclaimer = null;
let $secondDisclaimersDisclaimer = null;
let $isRequiredDescriptor = null;
let $fileDescriptor = null;


function isFormValid() {

    // Sections check
    if (
        $sectionsElement.is(':visible') &&
        $sectionsElement.find('label').hasClass('required') &&
        $sections.val() === '0'

    ) {
        return false;
    }

    // Editors check
    if (
        $suggestEditorsElement.is(':visible') &&
        $suggestEditorsElement.find('label').hasClass('required')
    ) {
        const suggestedEditors = $suggest_editors.val();
        if (
            suggestedEditors === '0' ||
            null === suggestedEditors
        ) {
            return false;
        }
    }

    //Disclaimers check
    if (
        !$firstDisclaimersDisclaimer.is(':checked') ||
        !$secondDisclaimersDisclaimer.is(':checked')
    ) {
        return false;
    }

    // data/software descriptor check
    return !(
        $isRequiredDescriptor.length > 0 &&
        $isRequiredDescriptor.val() === 'true' &&
        $fileDescriptor.val() === '');
}

// Helper for configuring global mocks from tests
function setMockGlobals(mocks) {
    $sectionsElement = mocks.$sectionsElement;
    $sections = mocks.$sections;
    $suggestEditorsElement = mocks.$suggestEditorsElement;
    $suggest_editors = mocks.$suggest_editors;
    $firstDisclaimersDisclaimer = mocks.$firstDisclaimersDisclaimer;
    $secondDisclaimersDisclaimer = mocks.$secondDisclaimersDisclaimer;
    $isRequiredDescriptor = mocks.$isRequiredDescriptor;
    $fileDescriptor = mocks.$fileDescriptor;
}

module.exports = {isFormValid, setMockGlobals};