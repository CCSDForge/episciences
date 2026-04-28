$.widget('ui.autocomplete', $.ui.autocomplete, {
    options: {
        renderItem: null,
        renderMenu: null,
    },
    _renderItem: function (ul, item) {
        if ($.isFunction(this.options.renderItem))
            return this.options.renderItem(ul, item);
        else return this._super(ul, item);
    },
    _renderMenu: function (ul, items) {
        if ($.isFunction(this.options.renderMenu)) {
            this.options.renderMenu(ul, items);
        }
        this._super(ul, items);
    },
});

function initModal() {
    if (!modalStructureExists()) {
        createModalStructure();
    }
}

$(document).ready(function () {
    let $jsDocId = $('#docid').val();

    // already in a modal, do not open another one
    initModal();

    $('form input').each(function (i, input) {
        let input_id = $(input).attr('id');
        $(input).keydown(function (e) {
            let code = e.keyCode || e.which;
            // shift+tab: previous input
            if (e.shiftKey && code == 9) {
                e.preventDefault();
                $('#' + input_id + '-element')
                    .prevAll('div:first')
                    .find('input')
                    .focus();
            }
            // tab : next input
            else if (code == 9) {
                e.preventDefault();
                let next = $('#' + input_id + '-element').nextAll('div:first');
                if ($(next).attr('id') == 'content-element') {
                    tinyMCE.get('content').focus();
                } else {
                    $(next).find('input').focus();
                }
            }
        });
    });

    // "send me a copy" checkbox
    $('#copy').on('click', function (e) {
        if ($('#copy').prop('checked')) {
            addRecipient(
                'bcc',
                $.grep(users, function (e) {
                    return e.uid == sender_uid;
                })[0],
                'known'
            );
            //resizeInput('#bcc', 'add');
        } else {
            removeRecipient(
                $('#bcc_tags .recipient-tag[data-uid="' + sender_uid + '"]')
            );
            //resizeInput('#bcc', 'remove');
        }
    });

    $('.show_contacts_button').on('click', function (e) {
        e.preventDefault();
        // fetch and parse button url
        let oUrl = $.url($(this).attr('href'));
        let urlParams = oUrl.param();

        if (in_modal) {
            // fetch and display contacts
            urlParams['ajax'] = true;
            let displayContactsRequest = ajaxRequest(
                oUrl.attr('path'),
                urlParams
            );
            displayContactsRequest.done(function (content) {
                $('#send_form').hide();
                $('#add_contacts_box').html(content);
                $('#add_contacts_box').show();
            });

            updateModalButton('addContacts');
        } else {
            openModal(
                $(this).attr('href'),
                $(this).attr('title'),
                { callback: 'addContacts' },
                e
            );
        }
    });

    if (in_modal) {
        updateModalButton('send_mail');

        $('#modal-box').on('hidden.bs.modal', function () {
            // restore default button behaviour
            //$('#modal-box .modal-footer').show();
            location.reload();
        });
    }

    setDefaultRecipient();
    initAutocomplete();

    // refresh parent page on close modal
    $('button.close').on('click', function () {
        if ($jsDocId) {
            refreshPaperHistory($jsDocId);
        }
    });

    if (tinyMCE.activeEditor !== null) {
        tinyMCE.activeEditor.on('keyup', function () {
            let msgContent = this.getContent();
            setWithExpiry('mailContent' + $jsDocId, msgContent, 7200000);
        });

        tinyMCE.activeEditor.on('init', function () {
            if (getWithExpiry('mailContent' + $jsDocId) !== null) {
                tinyMCE.activeEditor.setContent(
                    getWithExpiry('mailContent' + $jsDocId)
                );
            }
        });
    }
    let isdirty = 0;
    $('#send_form').on('change input', function () {
        isdirty = 1;
    });
    $('#modal-box').on('hide.bs.modal', function (e) {
        let editorContent = tinyMCE.activeEditor.getContent();
        if (
            (isdirty ||
                $('span#bcc_tags div').length ||
                $('span#cc_tags div').length ||
                editorContent !== '') &&
            $('#add_contacts_box').css('display') === 'none'
        ) {
            if (!confirm('Are you sure, you want to close?')) return false;
        }
        if ($('#add_contacts_box').css('display') === 'block') {
            e.preventDefault();
            $('#add_contacts_box').hide();
            $('#send_form').show();
            updateModalButton('send_mail');
        }
    });
});

// recipient input autocomplete
function initAutocomplete() {
    $('form .autocomplete').each(function (i, input) {
        let input_id = $(input).attr('id');
        let input_val;

        $(input).autocomplete({
            appendTo: $(input).closest('form'),

            source: function (request, response) {
                let matcher = new RegExp(
                    $.ui.autocomplete.escapeRegex(request.term),
                    'i'
                );
                response(
                    $.grep(users, function (value) {
                        value = value.label || value.value || value;
                        return (
                            matcher.test(value) ||
                            matcher.test(normalize(value))
                        );
                    })
                );
            },

            focus: function () {
                return false;
            },

            select: function (event, ui) {
                addRecipient(input_id, ui.item, 'known');
                $(input).val('');
                //resizeInput('#' + input_id, 'add');

                return false;
            },

            renderItem: function (ul, item) {
                return $('<li>')
                    .append('<a>' + item.htmlLabel + '</a>')
                    .appendTo(ul);
            },
        });

        // add recipient when focus is lost
        $(input).blur(function (e) {
            if ($(input).val() != '') {
                addRecipient(input_id, $(input).val(), 'unknown');
                $(input).autocomplete('close');
                $(input).val('');
                //resizeInput('#' + input_id, 'add');
            }
        });

        // enter: manual input
        $(input).keydown(function (e) {
            let code = e.keyCode || e.which;
            input_val = $(input).val().length;
            if (code == 13) {
                e.preventDefault();
                if ($(input).val() != '') {
                    addRecipient(input_id, $(input).val(), 'unknown');
                    $(input).autocomplete('close');
                    $(input).val('');
                    //resizeInput('#' + input_id, 'add');
                }
            }
        });

        // backspace : remove recipient
        $(input).keyup(function (e) {
            let code = e.keyCode || e.which;
            if (
                code == 8 &&
                input_val == 0 &&
                $('#' + input_id + '_tags').find('.recipient-tag').length
            ) {
                removeRecipient(
                    $('#' + input_id + '_tags').find('.recipient-tag:last')
                );
                //resizeInput('#' + input_id, 'remove');
            }
        });
    });
}

function normalize(term) {
    let accentMap = {
        à: 'a',
        â: 'a',
        é: 'e',
        è: 'e',
        ê: 'e',
        ë: 'e',
        ï: 'i',
        î: 'i',
        ô: 'o',
        ù: 'u',
        û: 'u',
    };
    let ret = '';
    for (let i = 0; i < term.length; i++) {
        ret += accentMap[term.charAt(i)] || term.charAt(i);
    }
    return ret;
}

function setDefaultRecipient() {
    if (typeof recipient !== 'undefined' && recipient) {
        addRecipient('to', recipient, 'known');
        //resizeInput('#to', 'add');
    }
}

function setWithExpiry(key, value, ttl) {
    const now = new Date();

    // `item` is an object which contains the original value
    // as well as the time when it's supposed to expire
    const item = {
        value: value,
        expiry: now.getTime() + ttl,
    };
    localStorage.setItem(key, JSON.stringify(item));
}
function getWithExpiry(key) {
    const itemStr = localStorage.getItem(key);
    // if the item doesn't exist, return null
    if (!itemStr) {
        return null;
    }
    const item = JSON.parse(itemStr);
    const now = new Date();
    // compare the expiry time of the item with the current time
    if (now.getTime() > item.expiry) {
        // If the item is expired, delete the item from storage
        // and return null
        localStorage.removeItem(key);
        return null;
    }
    return item.value;
}
