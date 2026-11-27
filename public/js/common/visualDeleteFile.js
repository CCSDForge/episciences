/**
 * Deletes contents
 * @param fileInputId the id of the <input type="file"> to clear (see
 *   Episciences_Form_Decorator_BootstrapInputFile, which renders it alongside a
 *   paired "value_<fileInputId>" text input)
 */
function clearFile(fileInputId) {
    const valueInput = document.getElementById('value_' + fileInputId);
    if (valueInput) {
        valueInput.value = ''; // to clear the value from the input field
    }

    const fileInput = document.getElementById(fileInputId);
    if (fileInput) {
        fileInput.value = '';
        // Dispatching "change" is crucial here: if the input is marked as required in a form to prevent submission (for example data descriptor file; if a listener "change" is attached to it)
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const container = document.getElementById(
        'tempFile_content_' + fileInputId
    );
    if (container) {
        container.replaceChildren();
    }
}

/**
 *
 * @param label
 * @param fileInputId
 * @returns {HTMLElement}
 */

function formatFileLabel(label, fileInputId) {
    const wrapper = document.createElement('div');
    wrapper.className = 'small grey';

    const cancel = document.createElement('span');
    cancel.className = 'glyphicon glyphicon-remove-circle';
    cancel.title = translate('Annuler');
    cancel.style.marginRight = '5px';
    cancel.style.cursor = 'pointer';
    cancel.addEventListener('click', () => clearFile(fileInputId));

    wrapper.append(cancel, label);
    return wrapper;
}

/**
 *
 * @param element
 * @param fileInputId
 * @returns {HTMLElement|null}
 */

function getContainer(element, fileInputId) {
    const containerId = 'tempFile_content_' + fileInputId;
    let container = document.getElementById(containerId);

    if (!container && element.parentElement?.tagName === 'DIV') {
        container = document.createElement('div');
        container.id = containerId;
        container.style.paddingTop = '10px';
        element.parentElement.appendChild(container);
    }

    return container;
}

// Event delegation handles both existing and dynamically added file inputs. Each file input's
// own id (unique per form/element) directly identifies its paired "value_<id>" input and
// "tempFile_content_<id>" container. FilePond's internal file input ("filepond--browser-<id>",
// see public/js/library/es.filepond.js) also starts with "file" but has its own file list, so it
// is left alone. Registered once, as some views load this script again with each modal they open.
if (!window.visualDeleteFileListening) {
    window.visualDeleteFileListening = true;

    document.addEventListener('change', event => {
        const input = event.target;

        if (
            !(input instanceof HTMLInputElement) ||
            !input.id.startsWith('file') ||
            input.id.startsWith('filepond--')
        ) {
            return;
        }

        const container = getContainer(input, input.id);
        if (!container) {
            return;
        }

        const filename =
            input.files && input.files.length ? input.files[0].name : '';
        container.replaceChildren(formatFileLabel(filename, input.id));
    });
}

if (typeof module !== 'undefined') {
    module.exports = { clearFile, formatFileLabel, getContainer };
}
