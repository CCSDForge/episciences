let $contact_list;
let $contacts;
let $contact_type_dropdown;

/**
 *
 */
function initGetContacts() {
    $contact_list = $('#contact-list');
    $contacts = $contact_list.find('tr');
    $contact_type_dropdown = $('#contact-type-dropdown');

    // contact type dropdown
    $contact_type_dropdown.find('a').on('click', function () {
        showList($(this).parent('li'));
    });

    // toggle all contacts
    $('#toggleAll').on('click', function () {
        let action = $(this).data('action');

        $contacts.each(function () {
            if (action === 'select') {
                if (!$(this).hasClass('selected')) {
                    $(this).addClass('selected');
                    select($(this));
                }
            } else {
                $(this).removeClass('selected');
                unselect($(this));
            }
        });

        if (action === 'select') {
            $(this).data('action', 'unselect');
        } else {
            $(this).data('action', 'select');
        }
    });

    initList();

    $('#filter-input').keyup(function () {
        filterTable('#filter-input', '#contact-list tr');
    });
    $('#filter-input').on('paste', function () {
        setTimeout(function () {
            filterTable('#filter-input', '#contact-list tr');
        }, 4);
    });

    epSeedAddedContactsPreviewFromMainHidden();
}

/** Restore picker tags from the mail form hidden field (re-open modal with existing Cc/Bcc). */
function epSeedAddedContactsPreviewFromMainHidden() {
    if (!document.getElementById('added_contacts_tags')) {
        return;
    }
    const formEl = window.__epContactsForm;
    if (!formEl || typeof target === 'undefined' || !target) {
        return;
    }
    let hid = formEl.querySelector('#' + formEl.id + '-hidden_' + target);
    if (!hid) {
        hid = document.getElementById('hidden_' + target);
    }
    if (!hid || !hid.value || hid.value === '[]') {
        return;
    }
    let recs;
    try {
        recs = JSON.parse(hid.value);
    } catch (e) {
        return;
    }
    if (!Array.isArray(recs) || !recs.length) {
        return;
    }
    recs.forEach(function (r) {
        if (!r || !r.uid) {
            return;
        }
        let user;
        for (let j = 0; j < all_contacts.length; j++) {
            if (String(all_contacts[j].uid) === String(r.uid)) {
                user = all_contacts[j];
                break;
            }
        }
        if (user) {
            addRecipient(target, user, 'known');
        }
    });
    epResolveTagsContainer(target)
        .find('.recipient-tag')
        .each(function () {
            const uid = $(this).data('uid');
            if (uid) {
                $('#contact_' + uid).addClass('selected');
            }
        });
}

function epResolveTagsContainer(targetField) {
    const t = targetField !== undefined ? targetField : target;
    if (
        typeof epAddedContactsPickerActive === 'function' &&
        epAddedContactsPickerActive()
    ) {
        const $added = $('#added_contacts_tags');
        if ($added.length) {
            return $added;
        }
    }
    const formEl =
        typeof window.__epContactsForm !== 'undefined'
            ? window.__epContactsForm
            : null;
    const $scope = formEl ? $(formEl) : $(document);
    let $c = $();
    if (formEl && formEl.id) {
        $c = $scope.find('#' + formEl.id + '-' + t + '-tags');
    }
    if (!$c.length) {
        $c = $('#' + t + '_tags');
    }
    return $c;
}

function filterTable(input, elements) {
    var query = stripAccents($(input).val());
    var $elements = $(elements);

    if (query.length) {
        var r = new RegExp(query, 'i');
        $elements.hide();
        $elements
            .filter(function () {
                return $(this).text().match(r);
            })
            .show();
    } else {
        $elements.show();
    }
}

// when a contact is clicked, it is either added or removed
function initList() {
    $contacts.off('click.epContacts').on('click.epContacts', function () {
        let action = $(this).hasClass('selected') ? 'remove' : 'add';
        if (action === 'add') {
            select($(this));
        } else {
            unselect($(this));
        }
        $(this).toggleClass('selected');
    });
}

function showList($li) {
    $contact_type_dropdown.find('span:first').html($li.find('a').html());
    let contacts = eval($li.data('value'));

    let html = '';
    for (let i in contacts) {
        let user = contacts[i];

        html += '<tr id="contact_' + user['uid'] + '">';
        html += '   <td>' + user['fullname'] + '</td>';
        html += '   <td class="grey">' + user['username'] + '</td>';
        let roleArr = user['role'];
        html += '<td>';
        if (roleArr.length > 0) {
            roleArr.forEach(el => {
                if (el !== 'member')
                    html +=
                        '   <span class="label label-default role-' +
                        el +
                        '">' +
                        translate(el) +
                        '</span>';
            });
        }
        html += '</td>';
        html += '   <td>' + user['mail'] + '</td>';
        html += '</tr>';
    }

    $contact_list.find('table').html(html);
    $contacts = $contact_list.find('tr');
    initList();

    // (re)selection des contacts déjà ajoutés, dans la liste nouvellement chargée
    $('#added_contacts_tags').find('.recipient-tag').each(function () {
        $('#contact_' + $(this).data('uid')).addClass('selected');
    });
}

function select(row) {
    let uid = $(row).attr('id').replace(/[^\d]/g, '');
    let user;
    for (let i in all_contacts) {
        if (all_contacts[i].uid == uid) {
            user = all_contacts[i];
        }
    }
    let tagId = addRecipient('added_contacts', user, 'known');
    $('#' + tagId).find('.remove-recipient').on('click', function () {
        $('#contact_' + uid).removeClass('selected');
    });
}

function unselect(row) {
    let uid = $(row).attr('id').replace(/[^\d]/g, '');
    let tag = $('#added_contacts_tags .recipient-tag[data-uid="' + uid + '"]');
    removeRecipient(tag);
}
