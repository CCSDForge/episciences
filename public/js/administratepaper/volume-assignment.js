var openedPopover = null;

/**
 * Filter option descriptors by a search query (case-insensitive, trimmed).
 * @param {{ value: string, text: string }[]} options
 * @param {string} query
 * @returns {{ value: string, text: string }[]}
 */
function filterOptions(options, query) {
    if (!query) return options;
    const q = query.toLowerCase();
    return options.filter(opt => opt.text.toLowerCase().includes(q));
}

/**
 * Rebuild a <select> element's options from a list, preserving the current selection.
 * @param {HTMLSelectElement} select
 * @param {{ value: string, text: string }[]} options
 * @param {string} currentVal
 */
function rebuildSelectOptions(select, options, currentVal) {
    while (select.options.length > 0) {
        select.remove(0);
    }
    options.forEach(opt => {
        const option = new Option(opt.text, opt.value);
        if (String(opt.value) === String(currentVal)) {
            option.selected = true;
        }
        select.add(option);
    });
}

/**
 * Attach search-filter behaviour to a text input / select pair.
 * Expands the select (size > 1) while the user types, collapses on selection.
 * @param {HTMLInputElement} searchInput
 * @param {HTMLSelectElement} volumeSelect
 */
function initVolumeSearch(searchInput, volumeSelect) {
    const allOptions = Array.from(volumeSelect.options).map(opt => ({
        value: opt.value,
        text: opt.text,
    }));

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const filtered = filterOptions(allOptions, query);
        rebuildSelectOptions(volumeSelect, filtered, volumeSelect.value);
        volumeSelect.size = query ? Math.min(filtered.length || 1, 8) : 1;
    });

    volumeSelect.addEventListener('change', function () {
        this.size = 1;
        searchInput.value = '';
        rebuildSelectOptions(volumeSelect, allOptions, this.value);
    });

    searchInput.focus();
}

function getMasterVolumeForm(button, docid, oldVid, partial) {
    let isPartial = partial !== '' ? JSON.parse(partial) : false;
    // Configuration du popup
    let placement = 'bottom';

    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    // Récupération du formulaire
    let request = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'administratepaper/volumeform',
        data: { docid: docid },
    });

    const volumePopoverTemplate =
        '<div class="popover volume-form-popover" role="tooltip">' +
        '<div class="arrow"></div>' +
        '<h3 class="popover-title"></h3>' +
        '<div class="popover-content"></div>' +
        '</div>';

    $(button)
        .popover({
            placement: placement,
            container: 'body',
            html: true,
            content: getLoader(),
            template: volumePopoverTemplate,
        })
        .popover('show');

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                placement: placement,
                container: 'body',
                html: true,
                content: result,
                template: volumePopoverTemplate,
            })
            .popover('show');

        // Initialize search filter for volume select
        const searchInput = document.getElementById('volume_search_input');
        const volumeSelect = document.getElementById('master_volume_select');
        if (searchInput && volumeSelect) {
            initVolumeSearch(searchInput, volumeSelect);
        }

        let actionForm = JS_PREFIX_URL + 'administratepaper/savemastervolume';

        $('form[action^="' + actionForm + '"]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);
                // Traitement AJAX du formulaire
                $.ajax({
                    url: actionForm,
                    type: 'POST',
                    datatype: 'json',
                    data: $(this).serialize() + '&docid=' + docid,
                    success: function (result) {
                        if (parseInt(result) === 1) {
                            let vid = $('#master_volume_select').val();
                            $(button).popover('destroy');

                            if (!isPartial) {
                                // not partial
                                location.replace(location.href);
                            } else {
                                // refresh all master volumes display

                                let url =
                                    JS_PREFIX_URL +
                                    'administratepaper/refreshallmastervolumes';
                                let jData = {
                                    docid: docid,
                                    vid: vid,
                                    old_vid: oldVid,
                                    from: 'list',
                                };
                                let refreshPositionsRequest = ajaxRequest(
                                    url,
                                    jData
                                );

                                refreshPositionsRequest.done(function (result) {
                                    let jResult =
                                        result !== '' ? JSON.parse(result) : {};
                                    $.each(jResult, function (index, value) {
                                        let $container = $(
                                            '#master_volume_name_' + index
                                        );
                                        $container.hide();
                                        $container.html(value);
                                        $container.fadeIn();
                                    });
                                });
                            }
                        }
                    },
                });
            }
            return false;
        });
    });
}

function getOtherVolumesForm(button, docid, partial) {
    let isPartial = partial !== '' ? JSON.parse(partial) : false; // not user
    // Configuration du popup
    let placement = 'bottom';

    // Destruction des anciens popups
    $('button').popover('destroy');

    // Toggle : est-ce qu'on ouvre ou est-ce qu'on ferme le popup ?
    if (openedPopover && openedPopover == docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    // Récupération du formulaire
    let request = $.ajax({
        type: 'POST',
        url: JS_PREFIX_URL + 'administratepaper/othervolumesform',
        data: { docid: docid },
    });

    const otherVolumesPopoverTemplate =
        '<div class="popover volume-form-popover" role="tooltip">' +
        '<div class="arrow"></div>' +
        '<h3 class="popover-title"></h3>' +
        '<div class="popover-content"></div>' +
        '</div>';

    $(button)
        .popover({
            placement: placement,
            container: 'body',
            html: true,
            content: getLoader(),
            template: otherVolumesPopoverTemplate,
        })
        .popover('show');

    request.done(function (result) {
        // Destruction du popup de chargement
        $(button).popover('destroy');

        // Affichage du formulaire dans le popover
        $(button)
            .popover({
                placement: placement,
                container: 'body',
                html: true,
                content: result,
                template: otherVolumesPopoverTemplate,
            })
            .popover('show');

        // Initialize search filter and checkbox selected state
        const searchInput = document.getElementById(
            'other_volumes_search_input'
        );
        const container = document.getElementById('other_volumes_list');
        if (searchInput && container) {
            initCheckboxSearch(searchInput, container);
            container.querySelectorAll('.multicheckbox_option').forEach(opt => {
                opt.addEventListener('click', function () {
                    const input = this.querySelector('input[type="checkbox"]');
                    if (input) {
                        this.classList.toggle('selected', input.checked);
                    }
                });
            });
        }

        let actionForm = JS_PREFIX_URL + 'administratepaper/saveothervolumes';

        $('form[action^="' + actionForm + '"]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);
                // Traitement AJAX du formulaire
                $.ajax({
                    url: actionForm,
                    type: 'POST',
                    datatype: 'json',
                    data: $(this).serialize() + '&docid=' + docid,
                    success: function (result) {
                        if (result === '1') {
                            // Destruction du popup
                            $(button).popover('destroy');

                            // refresh secondary volumes display
                            refreshVolumes(
                                $(this).serialize() + '&docid=' + docid,
                                'others',
                                $('#other_volumes_list_' + docid)
                            );
                            // refresh paper history
                            refreshPaperHistory(docid);
                        }
                    },
                });
            }
            return false;
        });
    });
}

function closeResult() {
    $('button').popover('destroy');
}

/**
 * refresh volumes
 * @param $jsonData
 * @param volumeType
 * @param $container
 * @returns {*}
 */
function refreshVolumes($jsonData, volumeType = 'master', $container = null) {
    let url = JS_PREFIX_URL + 'administratepaper/refreshmastervolume';

    if (volumeType === 'others') {
        // seconder volumes
        url = JS_PREFIX_URL + 'administratepaper/refreshothervolumes';
    }

    let request = ajaxRequest(url, $jsonData);

    if ($container) {
        request.done(function (result) {
            $container.hide();
            $container.html(result);
            $container.fadeIn();
        });
    }

    return request;
}

/**
 * Filter a checkbox list by the visible text of each .multicheckbox_option item.
 * Hidden items are removed from tab order via the `hidden` attribute.
 * @param {HTMLInputElement} searchInput
 * @param {HTMLElement} container - element containing .multicheckbox_option labels
 */
function initCheckboxSearch(searchInput, container) {
    const items = Array.from(
        container.querySelectorAll('.multicheckbox_option')
    );

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        items.forEach(item => {
            const span = item.querySelector('span');
            const text = span ? span.textContent.toLowerCase() : '';
            item.hidden = query ? !text.includes(query) : false;
        });
    });

    searchInput.focus();
}

if (typeof module !== 'undefined') {
    module.exports = {
        filterOptions,
        rebuildSelectOptions,
        initVolumeSearch,
        initCheckboxSearch,
    };
}
