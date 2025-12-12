$(function () {
    let subform = 'search_doc';
    let $search_button = $('#' + subform + '-getPaper');
    let $search_form = $('#searchForm');
    let $cancel_button = $('#searchAgain');
    let $submit_form = $('#submitForm');
    let $submit_button = $('#submitPaper');
    let $submit_modal = $('#submit-modal');
    let $result_container = $('#showResult');
    let $volumes = $('#volumes');
    let $sections = $('#sections');
    let $specialIssuesAccessCode = $('#specialIssueAccessCode');
    let $suggest_editors = $('#suggestEditors');
    let $sectionsElement = $('#sections-element');
    let $suggestEditorsElement = $('#suggestEditors-element');
    let $firstDisclaimersDisclaimer = $('#disclaimers-disclaimer1');
    let $secondDisclaimersDisclaimer = $('#disclaimers-disclaimer2');
    let $specialIssuesAccessCodeElement = $('#specialIssueAccessCode-element');
    let $searchDocVersion = $('#' + subform + '-version');
    let $searchPaperPassword = $('#' + subform + '-paperPassword');
    let $searchRequiredPwd = $('#' + subform + '-h_requiredPwd');

    // if it is a modal, disable submit button
    disableModalSubmitButton();

    // dropdown menu css styling (volume selection)
    $volumes.find('option:first').css('font-weight', 'bold');
    $volumes.find('option:not(:first)').css('padding-left', '15px');

    $sectionsElement.on('change', function () {
        activateDeactivateSubmitButton();
    });

    $suggestEditorsElement.on('change', function () {
        activateDeactivateSubmitButton();
    });

    $firstDisclaimersDisclaimer.on('change', function () {
        activateDeactivateSubmitButton();
    });

    $secondDisclaimersDisclaimer.on('change', function () {
        activateDeactivateSubmitButton();
    });

    $search_button.on('click', function () {
        doSearching();
    });

    $cancel_button.click(function () {
        showSearchForm();
    });

    // special volume access code
    $specialIssuesAccessCode.on('input propertychange', function () {
        let access_code = $(this).val();
        let button = $('#submit-code');
        if (access_code.trim() !== '') {
            button.html('<img src="/img/loading.gif" />');
            let request = $.ajax({
                type: 'POST',
                url: '/submit/accesscode/',
                data: { code: access_code },
            });

            request.done(function (result) {
                if (result.status === 1) {
                    button.html(
                        '<span class="glyphicon glyphicon-ok green"></span>'
                    );
                    valid_code(result);
                } else {
                    if ($specialIssuesAccessCode.val() !== '') {
                        button.html(
                            '<span class="glyphicon glyphicon-remove red"></span>'
                        );
                    }
                    resetVolumesElement(result);
                    resetSuggestEditorsElement(result);
                }
            });

            request.fail(function () {
                button.html(
                    '<span class="glyphicon glyphicon-remove red"></span>'
                );
            });
        } else {
            button.html('');
        }
    });

    //Submission Zenodo
    if (isFromZSubmit) {
        doSearching();
    }

    function valid_code(data) {
        // create volume selection menu
        let volumes_options =
            '<option value="' + data.vid + '">' + data.volume + '</option>';
        let isSelected = data.allEditorsSelected;
        if ($volumes.length) {
            $('#volumes').html(volumes_options);
        } else {
            $specialIssuesAccessCodeElement.after(
                createVolumesElement(volumes_options)
            );
        }

        // create editor selection menu
        if (data.canPickEditor > 0) {
            // contributors can choose editors
            // convert select element to multiple
            if (!isMultiple('suggestEditors')) {
                $suggest_editors.attr('name', 'suggestEditors[]');
                $suggest_editors.attr('multiple', 'multiple');
            }

            let suggest_editors_options = createSuggestEditorOptions(
                data.editors,
                isSelected
            );
            let label = 'Je souhaite que mon article soit supervisé par :';
            if (isSelected) {
                label = 'Cet article sera supervisé par :';
                $("label[for*='suggestEditors']").text(translate(label));
                $suggest_editors.prop('disabled', 'disabled');
            }

            if ($suggest_editors.length) {
                $suggest_editors.html(suggest_editors_options);
            } else {
                $('#disclaimers-disclaimer1-element').before(
                    createSuggestEditorsElement(label, suggest_editors_options)
                );
            }
        }
    }

    // search a document in a repository (AJAX)
    function search() {
        let id = $('#' + subform + '-docId').val();
        let repoId = $('#' + subform + '-repoId').val();
        let version = $('#' + subform + '-version').val();

        let $newVersionOf = $('#' + subform + '-newVersionOf');

        let latestObsoleteDocId = null;

        // S'agit-il d'une nouvelle version d'un document
        if ($newVersionOf.length) {
            latestObsoleteDocId = $newVersionOf.val();
        }

        if (!id) {
            alert(translate("Veuillez indiquer l'identifiant du document."));
            return;
        }

        $search_button.prop('disabled', true);
        $search_button.toggleClass('disabled');
        $submit_form.fadeOut('fast');

        $result_container.html(
            '<div class="panel panel-default"><div class="panel-body">' +
                getLoader() +
                '</div></div>'
        );
        if ($result_container.css('display') === 'none') {
            $result_container.fadeIn();
        }
        scrollTo($result_container, $('#modal-box'));

        let request = $.ajax({
            type: 'POST',
            url: '/submit/getdoc/',
            data: {
                docId: id,
                repoId: repoId,
                version: version,
                latestObsoleteDocId: latestObsoleteDocId,
            },
        });

        request.done(function (xml) {
            showResult(xml);
        });

        request.fail(function (response) {
            fail(response);
        });
    }

    // display the document if it has been found
    function showResult(result) {
        let message = '';

        if (result['status'] === 0) {
            // document not found: display an error
            message =
                '<div class="panel panel-danger"><div class="panel-body">' +
                result['error'] +
                '</div></div>';
        } else {
            toggleDD('file_data_descriptor', result['ddOptions']);

            if ('conceptIdentifier' in result) {
                $submit_form.append(
                    '<input id = "concept_identifier" type="hidden" name="concept_identifier" value="' +
                        result.conceptIdentifier +
                        '">'
                );
            }

            if ('hookVersion' in result) {
                $searchDocVersion.val(result.hookVersion);
            }

            // document found: hide search form
            $search_form.hide();
            $('#form_required').hide();
            // document already exists in episciences
            if (result['status'] === 2) {
                if ('newVerErrors' in result) {
                    let newVersionErrors = JSON.parse(result['newVerErrors']);
                    $submit_form.append(
                        '<input type="hidden" name="can_replace" value="' +
                            newVersionErrors.canBeReplaced +
                            '">'
                    );
                    if (newVersionErrors.canBeReplaced) {
                        $submit_form.append(
                            '<input id = "old_docid" type="hidden" name="old_docid" value="' +
                                newVersionErrors.oldDocId +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_identifier" type="hidden" name="old_identifier" value="' +
                                newVersionErrors.oldIdentifier +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_version" type="hidden" name="old_version" value="' +
                                newVersionErrors.oldVersion +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_repoid" type="hidden" name="old_repoid" value="' +
                                newVersionErrors.oldRepoId +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_paper_status" type="hidden" name="old_paper_status" value="' +
                                newVersionErrors.oldPaperStatus +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_paper_sid" type="hidden" name="old_paper_sid" value="' +
                                newVersionErrors.oldSid +
                                '">'
                        );
                        $submit_form.append(
                            '<input id = "old_paper_vid" type="hidden" name="old_paper_vid" value="' +
                                newVersionErrors.oldVid +
                                '">'
                        );
                        if (newVersionErrors.oldPaperId) {
                            $submit_form.append(
                                '<input id= "old_paperid" type="hidden" name="old_paperid" value="' +
                                    newVersionErrors.oldPaperId +
                                    '">'
                            );
                        }
                        if (newVersionErrors.submissionDate) {
                            $submit_form.append(
                                '<input id = "old_submissiondate" type="hidden" name="old_submissiondate" value="' +
                                    newVersionErrors.submissionDate +
                                    '">'
                            );
                        }
                    }

                    let hideResultMessage = function hideResultMessage() {
                        $('#result_message').hide(); // Cacher le message indiquant l'existance d'une ancienne version
                        $('#submitForm').fadeIn();
                        $('#form_required').show();
                        applyAction(
                            [
                                'specialIssueAccessCode-element',
                                'volumes-element',
                                'sections-element',
                                'suggestEditors-element',
                            ],
                            'hide'
                        );
                    };

                    message =
                        '<div id="result_message" class="panel panel-danger">';
                    message += '<div class="panel-body red">';
                    message += '<span class="badge">';
                    message += newVersionErrors.oldIdentifier;
                    message += '</span>';
                    message += '<br>';
                    message += '<strong>';
                    message += newVersionErrors.message;
                    message += '</strong>';
                    message += '</div>';
                    message += '</div>';
                    message += '<script>';
                    message += hideResultMessage;
                    message += '</script>';
                }
                $submit_form.fadeOut();
            } else {
                $('#form_required').show();
                $submit_form.fadeIn();
                // if it is a modal, re-enable submit button
                enableModalSubmitButton();
                $('.modal-footer').hide();
                applyAction(
                    [
                        'specialIssueAccessCode-element',
                        'volumes-element',
                        'sections-element',
                        'suggestEditors-element',
                    ],
                    'display'
                );
            }
            // load and display result
            message += result['xslt'];
            $('#xml').val(result['record']); // insert record in hidden field

            if (!isEmptyData(result['enrichment'])) {
                $('#h_enrichment').val(JSON.stringify(result['enrichment']));
            }
        }

        $result_container.html(message);
        MathJax.Hub.Typeset();

        $search_button.prop('disabled', false);
        $search_button.toggleClass('disabled');
    }

    // display an error if document not found
    function fail(response = null) {
        let message = !response
            ? '<div class="panel panel-danger"><div class="panel-body">' +
              translate(
                  "Une erreur s'est produite pendant la récupération des informations. Parfois l'archive ouverte ne répond pas assez vite. Nous vous suggérons de ré-essayer dans quelques instants. Si le problème persiste vous devriez contacter le support de la revue."
              ) +
              '</div></div>'
            : response;
        $result_container.html(message);
        $search_button.prop('disabled', false);
        $search_button.toggleClass('disabled');
    }

    // if there was an error at form submission
    function error() {
        search();
    }

    function showSearchForm() {
        $search_form.fadeIn();
        $result_container.hide();
        $submit_form.hide();
        $search_button.prop('disabled', false);
        $submit_button.prop('disabled', true);

        // if it is a modal, disable submission button
        disableModalSubmitButton();
    }

    /**
     *
     * @param data
     */
    function resetVolumesElement(data) {
        $('#volumes-element').remove();
        if (data.canChooseVolumes) {
            $('#specialIssueAccessCode-element').after(
                createVolumeMenu(data.volumesOptions)
            );
        }
    }

    /**
     *
     * @param data
     */
    function resetSuggestEditorsElement(data) {
        let canPickEditor = data.canPickEditor;
        if (canPickEditor > 0 && $suggest_editors.length) {
            $suggest_editors.empty();
            $("label[for*='suggestEditors']").text(
                translate('Je souhaite que mon article soit supervisé par :')
            );
            $suggest_editors.html(
                createSuggestEditorOptions(
                    data.editors,
                    data.allEditorsSelected
                )
            );
            $suggest_editors.prop('disabled', false);

            if (canPickEditor === 3) {
                // convert multiple select to simple select
                if (isMultiple('suggestEditors')) {
                    $suggest_editors.attr('name', 'suggestEditors');
                    $suggest_editors.removeAttr('multiple');
                }
            }
        }
    }

    /**
     * Check if all required fields are not completed
     * @returns {boolean}
     */
    function isRequiredFieldsNotCompleted() {
        return !(
            ($sectionsElement.is(':visible') &&
                $sectionsElement.find('label').hasClass('required') &&
                $sections.val() === '0') ||
            ($suggestEditorsElement.is(':visible') &&
                $suggestEditorsElement.find('label').hasClass('required') &&
                ($suggest_editors.val() === '0' ||
                    null === $suggest_editors.val())) ||
            !$firstDisclaimersDisclaimer.is(':checked') ||
            !$secondDisclaimersDisclaimer.is(':checked')
        );
    }

    /**
     * Deactivate / ACTIVATE  the "Submit" button.
     */
    function activateDeactivateSubmitButton() {
        if (isRequiredFieldsNotCompleted()) {
            $submit_button.attr('disabled', false);
            $submit_button.attr('aria-disabled', false);
        } else {
            $submit_button.attr('disabled', true);
            $submit_button.attr('aria-disabled', true);
        }
    }

    function doSearching() {
        let $checkBoxCondition1 = $('#disclaimers-disclaimer1');
        let $checkBoxCondition2 = $('#disclaimers-disclaimer2');

        let version;
        let $isRequiredVersion = $searchDocVersion.length > 0;

        if ($isRequiredVersion) {
            version = $searchDocVersion.val();
        }

        // submission error: attempt to re-submit
        if ($checkBoxCondition1.is(':checked')) {
            $checkBoxCondition1.prop('checked', false);
        }

        if ($checkBoxCondition2.is(':checked')) {
            $checkBoxCondition2.prop('checked', false);
        }

        if (
            $isRequiredVersion &&
            isRequiredVersion &&
            ('' === version || isNaN(version))
        ) {
            alert(
                translate(
                    'Veuillez indiquer la version du document (nombre uniquement).'
                )
            );
            return;
        }

        if (
            $searchPaperPassword.length > 0 &&
            $searchRequiredPwd.length > 0 &&
            $searchRequiredPwd.val() === '1' &&
            $searchPaperPassword.val() === ''
        ) {
            alert(
                translate(
                    'Veuillez indiquer le mot de passe arXiv du document.'
                )
            );
            return;
        }

        search();
    }
}); // end Ready

/**
 *
 * @param volumeNames
 * @returns {string}
 */
function createVolumeMenu(volumeNames) {
    let oVolumesNames = '';

    $.each(volumeNames, function (vid, name) {
        oVolumesNames += '<option value="' + vid + '">' + name + '</option>';
    });

    return createVolumesElement(oVolumesNames);
}

/**
 *
 * @returns {string}
 */
function createVolumesElement(options) {
    return (
        '<div id="volumes-element" class="form-group row">' +
        '<label class="col-md-3 control-label optional" for="volumes">' +
        translate('Proposer dans le volume :') +
        '</label>' +
        '<div class="col-md-9">' +
        '<select class="form-control input-sm" style="width:33%" id="volumes" name="volumes">' +
        options +
        '</select>' +
        '</div>' +
        '</div>'
    );
}

/**
 *
 * @param editors
 * @param isSelected
 * @returns {string}
 */
function createSuggestEditorOptions(editors, isSelected) {
    let suggestEditorsOptions = '';

    $.each(editors, function (key, value) {
        if (value.uid && value.fullname) {
            suggestEditorsOptions += '<option value="' + value.uid + '" ';
            if (isSelected) {
                suggestEditorsOptions += 'selected="selected" ';
            }
            suggestEditorsOptions += '>' + value.fullname + '</option>';
        }
    });

    return suggestEditorsOptions;
}

/**
 *
 * @param label
 * @param suggestEditorsOptions
 * @returns {string}
 */
function createSuggestEditorsElement(label, suggestEditorsOptions) {
    return (
        '<div id="suggestEditors-element" class="form-group row">' +
        '<label class="col-md-3 control-label required" for="suggestEditors">' +
        translate(label) +
        '</label>' +
        '<div class="col-md-9">' +
        '<select class="form-control" multiple="multiple" id="suggestEditors" name="suggestEditors[]">' +
        suggestEditorsOptions +
        '</select>' +
        '</div>' +
        '</div>'
    );
}

/**
 *
 * @param elementId
 * @returns {boolean}
 */
function isMultiple(elementId) {
    return $('#' + elementId).attr('multiple') === 'multiple';
}

/**
 * affiche / cache les tous les elements $elementsId
 * @param actionName
 * @param $elementsIds
 */
function applyAction($elementsIds, actionName = 'hide') {
    $.each($elementsIds, function (key, value) {
        let id = '#' + value;
        if ($(id).length && actionName === 'hide') {
            $(id).hide();
        }

        if ($(id).length && actionName === 'display') {
            $(id).show();
        }
    });
}

function toggleDD(ddIdentifier, ddOptions) {
    let $ddLabel = $('label[for="' + ddIdentifier + '"]');
    let $hRequiredSelector = $('#' + ddIdentifier + '_is_required');
    let $ddSelectorElement = $('#' + ddIdentifier + '-element');

    $hRequiredSelector.val(ddOptions['displayDDForm']);

    if (!ddOptions.displayDDForm) {
        $ddSelectorElement.hide();
    } else {
        if (ddOptions.isSoftware) {
            $ddLabel.text(translate('Descripteur de logiciel'));
        }

        $ddSelectorElement.show();
    }
}
