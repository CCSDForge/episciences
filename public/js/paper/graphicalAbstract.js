// Illustration or graphical abstract form of the paper page
// (paper/paper_graphical_abstract.phtml, AdministrategraphabstractController).
//
// The image is picked with FilePond (window.FilePond, "filepond" webpack entry) in storeAsFile
// mode: the file stays in the form and is sent along with the text alternative and the license
// in a single request, with no asynchronous upload nor temporary file on the server.
//
// Wrapped in an IIFE: function names such as clearErrors() would otherwise clash with
// the globals of public/js/functions.js loaded on the same page.

(function () {
    const GRAPH_ABS_FIELDS = {
        file: 'illustration_file',
        alt: 'illustration_alt',
        license: 'illustration_license',
    };

    // Response message that is not tied to a form field
    const GRAPH_ABS_GLOBAL_MESSAGE = 'form';

    // Wrapper so tests can replace the page reload
    const graphAbsPage = {
        reload: () => location.reload(),
    };

    // Per-session request token, sent with every mutating call
    function appendRequestToken(formData) {
        let csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.content);
        }
    }

    function createIllustrationPond(input) {
        if (!window.FilePond) {
            // the plain file input is still sent with the form
            return null;
        }

        const options = {
            storeAsFile: true,
            allowMultiple: false,
            credits: false,
            // same unit as the help text and the server message (500 KB = 512000 bytes)
            fileSizeBase: 1024,
            name: input.name,
            maxFileSize: Number(input.dataset.maxFileSize),
            acceptedFileTypes: (input.dataset.acceptedTypes || '')
                .split(',')
                .filter(Boolean),
        };

        if (
            typeof locale !== 'undefined' &&
            locale === 'fr' &&
            window.FilePondLocaleFrFr
        ) {
            Object.assign(options, window.FilePondLocaleFrFr);
        }

        const label = document.querySelector('label[for="' + input.id + '"]');
        const describedBy = input.getAttribute('aria-describedby');
        // FilePond renders its own input asynchronously, after create() returns
        options.oninit = () => linkPondToLabel(pond, label, describedBy);

        const pond = window.FilePond.create(input, options);

        return pond;
    }

    // FilePond replaces the input with its own "browse" input, labelled by its drop area only:
    // keep the form label and the help/error messages attached to it.
    function linkPondToLabel(pond, label, describedBy) {
        const browser =
            pond && pond.element
                ? pond.element.querySelector('input[type="file"]')
                : null;

        if (!browser) {
            return;
        }

        if (label) {
            label.id = label.id || browser.id + '-label';
            label.htmlFor = browser.id;
            const labelledBy = browser.getAttribute('aria-labelledby');
            browser.setAttribute(
                'aria-labelledby',
                labelledBy ? label.id + ' ' + labelledBy : label.id
            );
        }

        if (describedBy) {
            browser.setAttribute('aria-describedby', describedBy);
        }
    }

    function getFieldErrorElement(form, field) {
        return form.querySelector('#' + field + '-error');
    }

    function getFieldControl(form, field) {
        const group = form.querySelector('[data-field="' + field + '"]');
        return group ? group.querySelector('input, textarea') : null;
    }

    function setStatus(form, message, isError) {
        const status = form.querySelector('#graph-abs-status');
        if (!status) {
            return;
        }
        status.textContent = message || '';
        status.classList.toggle('text-danger', Boolean(isError && message));
    }

    function clearErrors(form) {
        Object.values(GRAPH_ABS_FIELDS).forEach(field => {
            const group = form.querySelector('[data-field="' + field + '"]');
            const errorElement = getFieldErrorElement(form, field);
            const control = getFieldControl(form, field);

            if (group) {
                group.classList.remove('has-error');
            }
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.hidden = true;
            }
            if (control) {
                control.removeAttribute('aria-invalid');
            }
        });
        setStatus(form, '', false);
    }

    /**
     * Shows the messages under their field (or in the status region when not tied to a field)
     * and moves the focus to the first field in error.
     *
     * @param {HTMLFormElement} form
     * @param {Object<string, string>} messages field name => message
     */
    function showErrors(form, messages) {
        let firstControl = null;
        const globalMessages = [];

        Object.entries(messages || {}).forEach(([field, message]) => {
            const errorElement = getFieldErrorElement(form, field);

            if (!errorElement) {
                globalMessages.push(message);
                return;
            }

            const group = form.querySelector('[data-field="' + field + '"]');
            const control = getFieldControl(form, field);

            errorElement.textContent = message;
            errorElement.hidden = false;
            if (group) {
                group.classList.add('has-error');
            }
            if (control) {
                control.setAttribute('aria-invalid', 'true');
                firstControl = firstControl || control;
            }
        });

        setStatus(
            form,
            globalMessages.length
                ? globalMessages.join(' ')
                : translate('Veuillez corriger les erreurs du formulaire.'),
            true
        );

        if (firstControl) {
            firstControl.focus();
        }
    }

    /**
     * Client-side checks, the server performs the same ones.
     *
     * @return {Object<string, string>} field name => message
     */
    function validateGraphicalAbstractForm(form, pondError) {
        const errors = {};
        const alt = form.querySelector('#' + GRAPH_ABS_FIELDS.alt);

        if (pondError) {
            errors[GRAPH_ABS_FIELDS.file] = pondError;
        }

        if (alt && alt.value.trim() === '') {
            errors[GRAPH_ABS_FIELDS.alt] = translate(
                'Le texte alternatif est obligatoire.'
            );
        }

        return errors;
    }

    /**
     * @return {Promise<{ok: boolean, messages: Object<string, string>}>}
     */
    function postGraphicalAbstract(url, formData) {
        appendRequestToken(formData);

        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response =>
                response
                    .json()
                    .catch(() => ({}))
                    .then(data => ({
                        ok: response.ok && data.status === 'success',
                        messages: data.messages || {},
                    }))
            )
            .catch(() => ({ ok: false, messages: {} }));
    }

    function handleResponse(form, result) {
        if (result.ok) {
            graphAbsPage.reload();
            return;
        }

        const messages = Object.keys(result.messages).length
            ? result.messages
            : {
                  [GRAPH_ABS_GLOBAL_MESSAGE]: translate(
                      'Une erreur est survenue, veuillez réessayer.'
                  ),
              };

        showErrors(form, messages);
    }

    function setBusy(form, busy) {
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        form.querySelectorAll('button').forEach(button => {
            button.disabled = busy;
        });
    }

    function submitGraphicalAbstract(form, pondState) {
        clearErrors(form);

        const errors = validateGraphicalAbstractForm(form, pondState.error);

        if (Object.keys(errors).length) {
            showErrors(form, errors);
            return Promise.resolve(false);
        }

        setBusy(form, true);
        setStatus(form, translate('Enregistrement en cours…'), false);

        return postGraphicalAbstract(form.action, new FormData(form)).then(
            result => {
                setBusy(form, false);
                handleResponse(form, result);
                return result.ok;
            }
        );
    }

    function deleteGraphicalAbstract(form, button) {
        if (!confirm(translate('Voulez-vous supprimer cette illustration ?'))) {
            return Promise.resolve(false);
        }

        clearErrors(form);

        const formData = new FormData();
        formData.append('docId', form.elements.docId.value);
        formData.append('file', button.dataset.file || '');

        setBusy(form, true);

        return postGraphicalAbstract(form.dataset.deleteUrl, formData).then(
            result => {
                setBusy(form, false);
                handleResponse(form, result);
                return result.ok;
            }
        );
    }

    function updateAltCounter(form) {
        const alt = form.querySelector('#' + GRAPH_ABS_FIELDS.alt);
        const counter = form.querySelector(
            '#' + GRAPH_ABS_FIELDS.alt + '-counter [data-counter-value]'
        );
        if (alt && counter) {
            counter.textContent = String(alt.value.length);
        }
    }

    function initGraphicalAbstractForm(form) {
        if (!form) {
            return null;
        }

        const fileInput = form.querySelector('#' + GRAPH_ABS_FIELDS.file);
        const pond = fileInput ? createIllustrationPond(fileInput) : null;
        // error of the file picked in FilePond (type, size), reported on submit
        const pondState = { error: '' };

        if (pond) {
            pond.on('addfile', error => {
                pondState.error = error
                    ? [error.main, error.sub].filter(Boolean).join('. ')
                    : '';
            });
            pond.on('removefile', () => {
                pondState.error = '';
            });
        }

        const alt = form.querySelector('#' + GRAPH_ABS_FIELDS.alt);
        if (alt) {
            alt.addEventListener('input', () => updateAltCounter(form));
        }

        form.addEventListener('submit', event => {
            event.preventDefault();
            submitGraphicalAbstract(form, pondState);
        });

        form.addEventListener('reset', () => {
            if (pond) {
                pond.removeFiles();
            }
            clearErrors(form);
            // the form fields are reset after the event
            setTimeout(() => updateAltCounter(form), 0);
        });

        const deleteButton = form.querySelector('[data-graph-abs-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', () =>
                deleteGraphicalAbstract(form, deleteButton)
            );
        }

        return { pond, pondState };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            graphAbsPage,
            appendRequestToken,
            createIllustrationPond,
            validateGraphicalAbstractForm,
            showErrors,
            clearErrors,
            submitGraphicalAbstract,
            deleteGraphicalAbstract,
            initGraphicalAbstractForm,
        };
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () =>
            initGraphicalAbstractForm(document.getElementById('f-graph-abs'))
        );
    } else {
        initGraphicalAbstractForm(document.getElementById('f-graph-abs'));
    }
})();
