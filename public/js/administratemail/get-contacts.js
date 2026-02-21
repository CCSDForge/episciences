let $contact_list;
let $contacts;
let $contact_type_dropdown;

/**
 *
 */
function initGetContacts() {
    console.log('executed initGetContacts');

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
    $contacts.on('click', function () {
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
    $('#' + target + '_tags')
        .find('.recipient-tag')
        .each(function () {
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
    let tagId = addRecipient(target, user, 'known');
    $('#' + tagId)
        .find('.remove-recipient')
        .on('click', function () {
            $('#contact_' + uid).removeClass('selected');
        });
}

function unselect(row) {
    let uid = $(row).attr('id').replace(/[^\d]/g, '');
    let tag = $('#' + target + '_tags .recipient-tag[data-uid="' + uid + '"]');
    removeRecipient(tag);
}
