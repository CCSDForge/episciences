const DATATABLE_LANGUAGE = {
    lengthMenu: translate('Afficher') + ' _MENU_ ' + translate('lignes'),
    search: translate('Rechercher') + ' :',
    zeroRecords: translate('Aucun résultat'),
    info:
        translate('Lignes') +
        ' _START_ ' +
        translate('à') +
        ' _END_, ' +
        translate('sur') +
        ' _TOTAL_ ',
    infoEmpty: translate('Aucun résultat affiché'),
    infoFiltered: '(' + translate('filtrés sur les') + ' _MAX_)',
    paginate: { sPrevious: '', sNext: '' },
};

var $modal_box;
var $modal_body;
var $modal_footer;
var $modal_button;
var $modal_form;

var openedPopover;

$(document).ready(function () {
    $modal_box = $('#modal-box');
    $modal_body = $modal_box.find('.modal-body');
    $modal_footer = $modal_box.find('.modal-footer');
    $modal_button = $('#submit-modal');
    $modal_form = $modal_box.find('form');

    // fix for making TinyMCE dialog boxes work with bootstrap modals
    $(document).on('focusin', function (e) {
        if ($(e.target).closest('.tox-dialog').length) {
            //https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable
            e.stopImmediatePropagation();
        }
    });

    // block close button
    $('.collapsable').each(function () {
        applyCollapse(this);
    });

    // Auto-expand the panel targeted by the URL hash
    if (window.location.hash) {
        var $targetPanel = $(window.location.hash + '.collapsable');
        if ($targetPanel.length && !$targetPanel.find('.panel-body:first').is(':visible')) {
            $targetPanel.find('.panel-heading:first').trigger('click');
            $('html, body').animate({ scrollTop: $targetPanel.offset().top }, 300);
        }
    }

    $('.collapse').on({
        shown: function () {
            $(this).css('overflow', 'visible');
        },
        hide: function () {
            $(this).css('overflow', 'hidden');
        },
    });

    // tooltips activation
    activateTooltips();

    // create modal structure
    if ($('.modal-opener').length && !modalStructureExists()) {
        createModalStructure();
    }

    // modal activation
    $(document).on('click', 'a.modal-opener', function (e) {
        e.preventDefault();
        $('.popover-link').popover('destroy');
        openModal(
            $(this).attr('href'),
            $(this).attr('title'),
            $(this).data(),
            e
        );
        return false;
    });

    // close popovers when clicking somewhere in the page
    $(document).on('click', function (e) {
        let isPopover = $(e.target).is('.popover-link');
        let inPopover = $(e.target).closest('.popover').length > 0;
        if (!isPopover && !inPopover) {
            openedPopover = null;
            $('.popover-link').popover('destroy');
        }
    });
});

/**
 * Get the first value from an object or array
 * @param {Object|Array} data - The object or array to get the first value from
 * @returns {*} The first value found, or undefined if data is empty/invalid
 */
function getFirstOf(data) {
    // Input validation
    if (!data || typeof data !== 'object') {
        return undefined;
    }

    // Handle arrays more efficiently
    if (Array.isArray(data)) {
        return data.length > 0 ? data[0] : undefined;
    }

    // Handle objects
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            return data[key];
        }
    }

    return undefined;
}

function readableBytes(bytes, locale) {
    // Convert string numbers to actual numbers
    if (typeof bytes === 'string' && !isNaN(bytes) && bytes.trim() !== '') {
        bytes = parseFloat(bytes);
    }

    // Input validation
    if (
        typeof bytes !== 'number' ||
        isNaN(bytes) ||
        bytes < 0 ||
        !isFinite(bytes)
    ) {
        return '0 bytes';
    }

    // Handle zero bytes
    if (bytes === 0) {
        return locale === 'fr' ? '0 octet' : '0 bytes';
    }

    // Define unit arrays with proper pluralization
    var units =
        locale === 'fr'
            ? ['octet', 'Ko', 'Mo', 'Go', 'To', 'Po']
            : ['byte', 'KB', 'MB', 'GB', 'TB', 'PB'];

    var pluralUnits =
        locale === 'fr'
            ? ['octets', 'Ko', 'Mo', 'Go', 'To', 'Po']
            : ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];

    // Calculate the appropriate unit scale
    var unitIndex = Math.floor(Math.log(bytes) / Math.log(1024));

    // Clamp to available units (prevent array out of bounds)
    unitIndex = Math.min(unitIndex, units.length - 1);

    // Calculate the scaled value
    var scaledValue = bytes / Math.pow(1024, unitIndex);

    // Format with appropriate precision
    var formattedValue;
    if (scaledValue >= 100) {
        formattedValue = Math.round(scaledValue);
    } else if (scaledValue >= 10) {
        formattedValue = Math.round(scaledValue * 10) / 10;
    } else {
        formattedValue = Math.round(scaledValue * 100) / 100;
    }

    // Choose singular or plural unit
    var unit =
        formattedValue === 1 && unitIndex === 0
            ? units[unitIndex]
            : pluralUnits[unitIndex];

    return formattedValue + ' ' + unit;
}

function stripAccents(string) {
    try {
        // Test if Unicode property escapes are supported
        new RegExp('\\p{Diacritic}', 'u');
        // If no error, use the more robust method
        return string.normalize('NFD').replace(/\p{Diacritic}/gu, '');
    } catch (e) {
        // Fallback to the compatible method
        return string.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
}

function getLoader() {
    const loading = `
        <div class="loader">
            <div class="text-info text-center" style="font-size: 12px;">
                ${translate('Chargement en cours')}
            </div>
            <div class="progress progress-striped active" style="height: 7px;">
                <div class="progress-bar" role="progressbar" aria-valuenow="100"
                     aria-valuemin="0" aria-valuemax="100" style="width: 100%;">
                </div>
            </div>
        </div>
    `;
    return loading.trim();
}

function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function isEmail(email) {
    // Simple, efficient email validation pattern that avoids backtracking issues
    var pattern =
        /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return pattern.test(email);
}

/**
 * Check if a string matches ISO date format (YYYY-MM-DD)
 * @param {string} input - The string to validate
 * @param {RegExp} [pattern] - Optional custom pattern (defaults to ISO date pattern)
 * @param {boolean} [strict=false] - If true, validates that the date actually exists
 * @returns {boolean} True if input matches the pattern and is a valid date (if strict)
 */
function isISOdate(input, pattern, strict = false) {
    // Input validation
    if (typeof input !== 'string' || input.trim() === '') {
        return false;
    }

    // Default pattern for ISO date format (YYYY-MM-DD)
    if (!pattern) {
        pattern = /^\d{4}-\d{2}-\d{2}$/;
    }

    // Check pattern match (reset global regex state)
    if (pattern.global) {
        pattern.lastIndex = 0; // Reset global regex state
    }
    if (!pattern.test(input)) {
        return false;
    }

    // If strict validation is requested, check if the date actually exists
    if (strict) {
        const date = new Date(input + 'T00:00:00.000Z'); // Add time to avoid timezone issues

        // Check if date is valid and matches the input
        if (isNaN(date.getTime())) {
            return false;
        }

        // Extract parts from input
        const parts = input.split('-');
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const day = parseInt(parts[2], 10);

        // Verify the date components match (handles invalid dates like 2023-02-30)
        return (
            date.getUTCFullYear() === year &&
            date.getUTCMonth() === month - 1 && // Month is 0-indexed
            date.getUTCDate() === day
        );
    }

    return true;
}

/**
 * Check if a date string in YYYY-MM-DD format represents a valid date
 * @param {string} input - The date string to validate
 * @param {string} [separator='-'] - The separator used in the date string
 * @returns {boolean} True if the date is valid and actually exists
 */
function isValidDate(input, separator) {
    // Input validation
    if (typeof input !== 'string' || input.trim() === '') {
        return false;
    }

    // Default separator
    if (!separator) separator = '-';

    // Trim whitespace from input
    input = input.trim();

    // If using default separator (-), leverage isISOdate function
    if (separator === '-') {
        return isISOdate(input, null, true); // Use strict validation
    }

    // For custom separators, convert to ISO format and validate
    const parts = input.trim().split(separator);

    // Must have exactly 3 parts
    if (parts.length !== 3) {
        return false;
    }

    // Check format: should be YYYY-MM-DD order
    const year = parts[0];
    const month = parts[1];
    const day = parts[2];

    // Basic format validation (4 digits for year, 2 digits for month/day)
    if (
        !/^\d{4}$/.test(year) ||
        !/^\d{2}$/.test(month) ||
        !/^\d{2}$/.test(day)
    ) {
        return false;
    }

    // Convert to ISO format and use isISOdate for validation
    const isoDate = `${year}-${month}-${day}`;
    return isISOdate(isoDate, null, true); // Use strict validation
}

/**
 * Check if a date is between two other dates (inclusive)
 * @param {string|Date} input - The date to check
 * @param {string|Date} min - The minimum date (inclusive)
 * @param {string|Date} max - The maximum date (inclusive)
 * @returns {boolean} True if date is between min and max, false otherwise
 */
function dateIsBetween(input, min, max) {
    // If either boundary is missing, return true (no constraints)
    if (!min || !max) return true;

    // Convert all inputs to Date objects
    const inputDate = new Date(input);
    const minDate = new Date(min);
    const maxDate = new Date(max);

    // Validate all dates are valid
    if (
        isNaN(inputDate.getTime()) ||
        isNaN(minDate.getTime()) ||
        isNaN(maxDate.getTime())
    ) {
        return false;
    }

    // Ensure min is not greater than max
    if (minDate > maxDate) {
        return false;
    }

    // Check if input date is between min and max (inclusive)
    return inputDate >= minDate && inputDate <= maxDate;
}

function nl2br(str) {
    if (str == null) return '';
    const breakTag = '<br>';
    return String(str).replace(
        /([^\r\n]*)(\r\n|\n\r|\r|\n)/g,
        `$1${breakTag}$2`
    );
}

function isPositiveInteger(s) {
    return typeof s === 'string' && /^[1-9][0-9]*$/.test(s.trim());
}

/**
 * Optimized filterList function with caching and reduced DOM manipulation
 * Performance improvements:
 * - Cache compiled RegExp objects
 * - Cache stripped text content
 * - Batch DOM updates using native methods
 * - Minimize jQuery overhead
 * - Use requestAnimationFrame for smooth filtering
 */
function filterList(input, elements) {
    // Get input value and early return if empty
    const inputEl = input.nodeType ? input : document.querySelector(input);
    const query = inputEl ? stripAccents(inputEl.value).trim() : '';

    // Get elements array (convert jQuery/selector to native elements)
    const elementsArray = elements.nodeType
        ? [elements]
        : typeof elements === 'string'
          ? Array.from(document.querySelectorAll(elements))
          : elements.length !== undefined
            ? Array.from(elements)
            : [];

    if (elementsArray.length === 0) return;

    // Cache for this filter operation
    const cacheKey = elements.toString ? elements.toString() : elements;
    if (!filterList._cache) filterList._cache = new Map();
    if (!filterList._textCache) filterList._textCache = new Map();

    // Use cached compiled regex or create new one
    let regex = null;
    if (query.length > 0) {
        const regexKey = query.toLowerCase();
        if (filterList._cache.has(regexKey)) {
            regex = filterList._cache.get(regexKey);
        } else {
            // Escape special regex characters for safety
            const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            regex = new RegExp(escapedQuery, 'gi');
            filterList._cache.set(regexKey, regex);

            // Limit cache size to prevent memory leaks
            if (filterList._cache.size > 100) {
                const firstKey = filterList._cache.keys().next().value;
                filterList._cache.delete(firstKey);
            }
        }
    }

    // Batch DOM updates for better performance
    const showElements = [];
    const hideElements = [];

    for (let i = 0; i < elementsArray.length; i++) {
        const element = elementsArray[i];
        const elementKey = `${cacheKey}_${i}`;

        // Get cached text content or compute and cache it
        let textContent;
        if (filterList._textCache.has(elementKey)) {
            textContent = filterList._textCache.get(elementKey);
        } else {
            textContent = stripAccents(
                element.textContent || element.innerText || ''
            );
            filterList._textCache.set(elementKey, textContent);

            // Limit text cache size
            if (filterList._textCache.size > 500) {
                const firstKey = filterList._textCache.keys().next().value;
                filterList._textCache.delete(firstKey);
            }
        }

        // Determine visibility
        const shouldShow =
            query.length === 0 || (regex && regex.test(textContent));

        if (shouldShow) {
            showElements.push(element);
        } else {
            hideElements.push(element);
        }

        // Reset regex lastIndex for global regex
        if (regex && regex.global) {
            regex.lastIndex = 0;
        }
    }

    // Batch DOM updates using requestAnimationFrame for smooth performance
    requestAnimationFrame(() => {
        // Hide elements
        hideElements.forEach(el => {
            el.style.display = 'none';
            const nextBr = el.nextElementSibling;
            if (nextBr && nextBr.tagName === 'BR') {
                nextBr.style.display = 'none';
            }
        });

        // Show elements
        showElements.forEach(el => {
            el.style.display = '';
            const nextBr = el.nextElementSibling;
            if (nextBr && nextBr.tagName === 'BR') {
                nextBr.style.display = '';
            }
        });
    });
}

// Clear caches when needed (useful for memory management)
filterList.clearCache = function () {
    if (filterList._cache) filterList._cache.clear();
    if (filterList._textCache) filterList._textCache.clear();
};

function scrollTo(target, container) {
    window.location.hash = target;
}

function htmlEntities(str) {
    if (str == null) return '';
    const entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };
    return String(str).replace(/[&<>"']/g, match => entityMap[match]);
}

/**
 * create a permalink from a string
 * @param str
 * @returns string
 */
function permalink(str) {
    str = str.toLowerCase();
    var from = 'àáäâèéëêìíïîòóöôùúüûñç·_,:;';
    var to = 'aaaaeeeeiiiioooouuuunc-----';
    for (var i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
    }
    str = str.replace(/\s/g, '-');
    str = str.replace(/[^a-zA-Z0-9\-]/g, '');
    str = str.toLowerCase();
    str = str.replace(/-+/g, '-');
    str = str.replace(/(^-)|(-$)/g, '');
    return str;
}

function message(text, type) {
    $('#flash-messages').html(getMessageHtml(text, type));
    setTimeout(function () {
        $('#flash-messages').find('.alert').alert('close');
    }, 10000);
}

function getMessageHtml(text, type) {
    return (
        '<div class="alert ' +
        type +
        '"><button type="button" class="close" data-dismiss="alert">&times;</button>' +
        htmlEntities(text) +
        '</div>'
    );
}

/**
 * activate tooltip on each element with data-toggle="tooltip"
 * @param params
 */
function activateTooltips(params = {}) {
    //params = params || {};
    params.container = params.container || 'body';
    params.placement = params.placement || 'bottom';
    params.html = params.html || true;
    params.show = params.show || 200;
    params.hide = params.hide || 100;

    $("[data-toggle~='tooltip']").tooltip({
        container: params.container,
        placement: params.placement,
        html: params.html,
        delay: { show: params.show, hide: params.hide },
    });
}

/**
 * activate designated tooltip
 * @param $target target element jquery selector
 */
function activateTooltip($target) {
    $target.tooltip({
        delay: { show: 500 },
        html: true,
        placement: 'bottom',
    });
}

function addToggleButton(datatable, target) {
    var toggle_button = '';
    toggle_button +=
        '<button class="dt-toggle-button btn btn-default btn-sm pull-right" ';
    toggle_button += 'style="margin-left: 2px" ';
    toggle_button += 'title="' + translate('Afficher / Masquer') + '">';
    toggle_button += '<span class="glyphicon glyphicon-list-alt"></span>';
    toggle_button += '</button>';
    $(target).prepend(toggle_button);

    var content = '<div class="dt-columns">';
    $(datatable)
        .DataTable()
        .columns()
        .every(function () {
            var th = this.header();
            var i = this.index();
            var label = $(th).data('name') ? $(th).data('name') : $(th).text();
            var checked = this.visible() ? 'checked' : '';
            content +=
                '<div><input id="col-' +
                i +
                '" name="col-' +
                i +
                '" type="checkbox" value="' +
                i +
                '" ' +
                checked +
                ' /> <label for="col-' +
                i +
                '">' +
                htmlEntities(label) +
                '</label></div>';
        });
    content += '</div>';

    $('.dt-toggle-button').popover({
        container: '#container',
        placement: 'bottom',
        html: true,
        content: content,
    });

    $('body').on('change', '.dt-columns :checkbox', function () {
        var column = $(datatable).DataTable().column($(this).val());
        column.visible($(this).is(':checked'));
    });

    $('.dt-toggle-button')
        .popover()
        .on('shown.bs.popover', function () {
            $(datatable)
                .DataTable()
                .columns()
                .every(function () {
                    $('#col-' + this.index()).prop('checked', this.visible());
                });
        });
}

function applyCollapse(object) {
    // alert($(object).attr('id') + ' : collapsable');
    var openTooltip = translate('Déplier');
    var closeTooltip = translate('Replier');
    var style = $(object).find('.panel-body:first').hasClass('in')
        ? 'glyphicon-chevron-up'
        : 'glyphicon-chevron-down';
    var tooltip = $(object).find('.panel-body:first').hasClass('in')
        ? closeTooltip
        : openTooltip;
    var button =
        '<div class="collapseButton" data-toggle="tooltip" title="' +
        htmlEntities(tooltip) +
        '"><span class="glyphicon ' +
        style +
        '"></span></div>';
    $(object).find('.panel-heading:first .panel-title').append(button);
    if (!$(object).find('.panel-body:first').hasClass('in')) {
        $(object).find('.panel-body').css('display', 'none');
    }

    $('.collapseButton', object).tooltip();
    $('.panel-heading:first', object).click(function () {
        $(this).closest('.panel').find('.panel-body').toggle();
        if (
            $(this)
                .find('.collapseButton span')
                .hasClass('glyphicon-chevron-down')
        ) {
            $(this)
                .find('.collapseButton span')
                .removeClass('glyphicon-chevron-down');
            $(this)
                .find('.collapseButton span')
                .addClass('glyphicon-chevron-up');

            $(this).find('.collapseButton').tooltip('destroy');
            $(this).find('.collapseButton').attr('title', closeTooltip);
            $(this).find('.collapseButton').tooltip();
        } else {
            $(this)
                .find('.collapseButton span')
                .removeClass('glyphicon-chevron-up');
            $(this)
                .find('.collapseButton span')
                .addClass('glyphicon-chevron-down');

            $(this).find('.collapseButton').tooltip('destroy');
            $(this).find('.collapseButton').attr('title', openTooltip);
            $(this).find('.collapseButton').tooltip();
        }
    });
}

function modalStructureExists() {
    return $modal_box && $modal_box.length;
}

function createModalStructure(params) {
    // create modal structure from a view script
    $.ajax({
        url: '/partial/modal',
        type: 'POST',
        data: params,
        success: function (modalStructure) {
            $('body').append(modalStructure);
            $modal_box = $('#modal-box');
            $modal_body = $modal_box.find('.modal-body');
            $modal_footer = $modal_box.find('.modal-footer');
            $modal_button = $('#submit-modal');
        },
    });
}

function openModal(url, title, params, source) {
    // set css params
    if (params) {
        for (let key in params) {
            if ($('body').css(key) !== 'undefined') {
                $modal_box.find('.modal-dialog').css(key, params[key]);
            }
        }
    }

    // if modal does not exist, return false
    if (!modalStructureExists()) {
        return false;
    }

    params.hidesubmit ? $modal_button.hide() : $modal_button.show();

    // run callback method (if there is one)
    if (params['callback']) {
        $modal_button.off('click.callback');
        $modal_button.on(
            'click.callback',
            { callback: params['callback'] },
            function (e) {
                var callback = e.data.callback;
                if (jQuery.isFunction(window[callback])) {
                    window[callback](source.target);
                }
            }
        );
    } else {
        $modal_button.on('click', function (e) {
            // submit form if necessary
            if ($modal_form.length && $modal_form.data('submission') != false) {
                $modal_form.submit();
            }
            $modal_box.modal('hide');
        });
    }

    // init modal
    $modal_box.find('.modal-title').text(title);
    $modal_box.draggable({ handle: '.modal-header' });
    if (url) {
        // if ajax, destroy TinyMCE editors before refreshing content
        $modal_box.find('textarea').each(function () {
            if (typeof tinyMCE != 'undefined') {
                tinyMCE.execCommand(
                    'mceRemoveEditor',
                    false,
                    $(this).attr('id')
                );
            }
        });
        $modal_body.html(getLoader());
    }
    $modal_box.modal();

    // load content from remote url (ajax)
    if (url) {
        let oUrl = $.url(url);
        let urlParams = oUrl.param();
        urlParams['ajax'] = true;

        let displayContactsRequest = ajaxRequest(oUrl.attr('path'), urlParams);
        displayContactsRequest.done(function (content) {
            $modal_body.html(content);
            $modal_form = $modal_box.find('form');
        });
    } else if (params['content']) {
        $modal_body.html(params['content']);
    } else if (params['source']) {
        $(params['source']).appendTo('#modal-box .modal-body');
    }

    // run init method (if there is one)
    if (params['init']) {
        if (jQuery.isFunction(window[params['init']])) {
            window[params['init']](source.target);
        }
    }

    // run onclocse method (if there is one)
    if (params['onclose'] && jQuery.isFunction(window[params['onclose']])) {
        $modal_box.on('hidden.bs.modal', function () {
            window[params['onclose']](source.target);
        });
    }

    $('#myModal').on('hidden.bs.modal', function () {
        // do something…
    });
}

function resizeModal(width, height) {
    if (!width) {
        width = '560px';
    }
    if (!height) {
        height = '400px';
    }

    $modal_box.find('.modal').css('width', width);
    $modal_box.find('.modal').css('margin-left', function () {
        return -($(this).width() / 2);
    });
    $modal_body.css({ 'max-height': height });
}

/**
 * Envoie une chaine de caractères correspondant à la date exprimée selon une locale.
 * Les arguments locales et options permettent de définir le langage utilisé pour
 * les conventions de format et permettent de personnaliser le comportement de la fonction
 * @param date
 * @param oLocale
 * @param options
 * @returns {string}
 */

function getLocaleDate(
    date,
    oLocale = { language: 'en', country: 'br' },
    options = { year: 'numeric', month: 'long', day: 'numeric' }
) {
    let parseDate = Date.parse(date);

    if (isNaN(parseDate)) {
        return 'Invalid Date';
    }

    let lang = oLocale.language;
    let count = oLocale.country;

    let locale = lang + '-' + count.toUpperCase();

    return new Date(parseDate).toLocaleDateString(locale, options);
}

/**
 *
 * @param body
 * @param tagName
 * @param date
 * @param locale
 * @returns {*}
 */
function updateDeadlineTag(body, tagName, date, locale = 'en') {
    let search = new RegExp('<span class="' + tagName + '">(.*?)<\/span>');
    let content = body.getContent();
    let replace =
        '<span class="revision_deadline">' +
        translate('dès que possible', locale) +
        '</span>';

    if (isISOdate(date) && isValidDate(date) && !isBackDated(date)) {
        let localeDate = getLocaleDate(date, {
            language: locale,
            country: locale,
        });

        replace = '<span class="' + tagName + '">' + localeDate + '</span>';
    }

    content = content.replace(search, replace);
    body.setContent(content);

    return body;
}

/**
 * get content form tinymce object.
 * @returns {string}
 * @param {object} name
 */
function getObjectNameFromTinyMce(name) {
    let body = {};
    if (tinymce) {
        body = tinymce.get(name);
    }
    return body;
}

/**
 *
 * @param url
 * @param jData{*}
 * @param type
 * @param dataType
 * @returns {*}
 */
function ajaxRequest(url, jData, type = 'POST', dataType = null) {
    let params = {
        url: url,
        type: type,
        data: jData,
    };

    if (dataType) {
        params.datatype = dataType;
    }
    return $.ajax(params);
}

/**
 *get multi js scripts
 * @param sArr
 * @returns {*}
 */

function getMultiScripts(sArr) {
    let _sArr = $.map(sArr, function (scr) {
        return $.getScript((scr.path || '') + scr.script);
    });

    _sArr.push(
        $.Deferred(function (deferred) {
            $(deferred.resolve);
        })
    );

    return $.when.apply($, _sArr);
}

function in_array(needle, haystack, strict = false) {
    // Input validation
    if (!Array.isArray(haystack)) {
        return -1;
    }

    // Handle empty array
    if (haystack.length === 0) {
        return -1;
    }

    // Use built-in methods for better performance when appropriate
    if (strict) {
        // For strict comparison, use indexOf which uses ===
        return haystack.indexOf(needle);
    } else {
        // For loose comparison, we need to check each element manually
        for (let i = 0; i < haystack.length; i++) {
            // Use == for loose comparison (mimics PHP in_array behavior)
            if (haystack[i] == needle) {
                return i;
            }
        }
        return -1;
    }
}

/**
 * Clear error messages
 * @param selector
 */
function clearErrors(selector = '.errors') {
    if ($(selector).length) {
        if ($(selector)) {
            $(selector).empty();
        }
    }
}

function isValidHttpUrl(string) {
    // Input validation
    if (typeof string !== 'string' || string.trim() === '') {
        return false;
    }

    // Trim whitespace
    string = string.trim();

    // Basic length check (URLs shouldn't be too long)
    if (string.length > 2048) {
        return false;
    }

    let url;

    try {
        url = new URL(string);
    } catch (_) {
        return false;
    }

    // Check protocol
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
    }

    // Check hostname exists and is valid
    if (!url.hostname || url.hostname.trim() === '') {
        return false;
    }

    // Basic hostname validation - no spaces, basic format check
    if (/\s/.test(url.hostname) || url.hostname.includes('..')) {
        return false;
    }

    // Check for valid hostname format (allow localhost, IP addresses, and domains)
    const hostnamePattern =
        /^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$|^localhost$|^(\d{1,3}\.){3}\d{1,3}$|^\[([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]*\]$/;

    return hostnamePattern.test(url.hostname);
}

// jQuery sortable table bug fix
let fixHelperSortable = function (e, ui) {
    ui.children().each(function () {
        $(this).width($(this).width());
    });
    return ui;
};

function disableModalSubmitButton($selector = null) {
    if ($selector) {
        $selector.prop('disabled', true);
        return;
    }

    if (typeof $modal_button !== 'undefined' && $modal_button.length > 0) {
        $modal_button.prop('disabled', true);
    }
}

function enableModalSubmitButton($selector = null) {
    if ($selector) {
        $selector.prop('disabled', false);
        return;
    }

    if (typeof $modal_button !== 'undefined' && $modal_button.length > 0) {
        $modal_button.prop('disabled', false);
    }
}

function isBackDated(input) {
    // Input validation
    if (input == null || input === '') {
        return false;
    }

    // Handle different input types
    let inputDate;
    if (input instanceof Date) {
        inputDate = input;
    } else if (typeof input === 'string' || typeof input === 'number') {
        inputDate = new Date(input);
    } else {
        return false; // Invalid input type
    }

    // Check if input date is valid
    if (isNaN(inputDate.getTime())) {
        return false;
    }

    // Get current date and time
    const now = new Date();

    // Compare dates - input is backdated if it's before now
    return inputDate < now;
}

/**
 *
 * @param $element
 * @param newTitle
 */
function updateTooltipTitle($element, newTitle = '') {
    $element
        .attr('title', translate(newTitle))
        .tooltip('fixTitle')
        .tooltip('show');
}
function isEmptyData(value, visited = new WeakSet()) {
    // Handle null and undefined
    if (value === null || value === undefined) {
        return true;
    }

    // Handle arrays
    if (Array.isArray(value)) {
        // Check for circular reference
        if (visited.has(value)) {
            return false; // Circular arrays are not considered empty
        }
        visited.add(value);

        const result =
            value.length === 0 ||
            value.every(item => isEmptyData(item, visited));
        visited.delete(value);
        return result;
    }

    // Handle objects (but not Date, RegExp, etc.)
    if (typeof value === 'object' && value.constructor === Object) {
        // Check for circular reference
        if (visited.has(value)) {
            return false; // Circular objects are not considered empty
        }
        visited.add(value);

        const result =
            Object.keys(value).length === 0 ||
            Object.values(value).every(val => isEmptyData(val, visited));
        visited.delete(value);
        return result;
    }

    // Handle strings (including whitespace-only strings)
    if (typeof value === 'string') {
        return value.trim() === '';
    }

    // Handle numbers (0 is considered empty for chart data)
    if (typeof value === 'number') {
        return value === 0;
    }

    // Handle booleans (false is not considered empty)
    if (typeof value === 'boolean') {
        return false;
    }

    // For other types (functions, symbols, etc.), consider them not empty
    return false;
}

function truncate(str, length, suffix = '...') {
    if (typeof str !== 'string') return '';
    return str.length <= length ? str : str.slice(0, length) + suffix;
}
