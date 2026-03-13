/**
 * Mailing List Management JS
 * Handles member selection, filtering and audience preview.
 */
const EpisciencesMailingList = (function() {
    'use strict';

    function init(options) {
        const announcementContainer = document.getElementById('member-announcements');
        const userSearchInput = document.getElementById('user-search');
        const roleFilterSelect = document.getElementById('role-filter');
        const modalUsersTableBody = document.querySelector('#modal-users-table tbody');
        const selectedUsersTableBody = document.querySelector('#users-table tbody');
        const userModal = document.getElementById('userModal');
        
        // Audience Preview Elements
        const audienceSearchInput = document.getElementById('audience-search');
        const audienceTableBody = document.querySelector('#audience-table tbody');
        const refreshPreviewBtn = document.getElementById('refresh-preview');
        const refreshIcon = document.getElementById('refresh-icon');

        if (!selectedUsersTableBody || !modalUsersTableBody) return;

        function announce(message) {
            if (announcementContainer) {
                announcementContainer.textContent = message;
            }
        }

        function filterModalUsers() {
            if (!userSearchInput || !roleFilterSelect) return;
            const searchValue = userSearchInput.value.toLowerCase();
            const roleValue = roleFilterSelect.value;
            const rows = modalUsersTableBody.querySelectorAll('tr');

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const matchesSearch = searchData.indexOf(searchValue) > -1;
                let matchesRole = true;

                if (roleValue !== '') {
                    try {
                        const roles = JSON.parse(row.getAttribute('data-roles') || '[]');
                        matchesRole = Array.isArray(roles) && roles.indexOf(roleValue) > -1;
                    } catch (e) {
                        matchesRole = false;
                    }
                }

                row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
            });
        }

        if (userSearchInput) userSearchInput.addEventListener('keyup', filterModalUsers);
        if (roleFilterSelect) roleFilterSelect.addEventListener('change', filterModalUsers);

        // Search/Filter in audience preview
        if (audienceSearchInput) {
            audienceSearchInput.addEventListener('keyup', function() {
                const value = this.value.toLowerCase();
                const rows = audienceTableBody.querySelectorAll('.audience-row');
                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search') || '';
                    row.style.display = searchData.indexOf(value) > -1 ? '' : 'none';
                });
            });
        }

        // AJAX refresh for audience preview
        if (refreshPreviewBtn) {
            refreshPreviewBtn.addEventListener('click', function() {
                refreshPreviewBtn.disabled = true;
                if (refreshIcon) refreshIcon.classList.add('fa-spin');

                const roles = Array.from(document.querySelectorAll('.role-checkbox:checked')).map(cb => cb.value);
                const uids = Array.from(document.querySelectorAll('.uid-input')).map(input => input.value);

                const formData = new FormData();
                roles.forEach(role => formData.append('roles[]', role));
                uids.forEach(uid => formData.append('uids[]', uid));

                fetch(options.previewUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!audienceTableBody) return;
                    audienceTableBody.innerHTML = '';
                    if (data.length === 0) {
                        audienceTableBody.innerHTML = `<tr><td colspan="2" class="text-center" style="padding: 60px 0;"><p class="text-muted" style="font-size: 1.1em;">${options.noMembersMsg}</p></td></tr>`;
                    } else {
                        data.forEach(member => {
                            const firstName = member.FIRSTNAME || '';
                            const lastName = member.LASTNAME || '';
                            const email = member.EMAIL || '';
                            const searchData = `${firstName} ${lastName} ${email}`.toLowerCase();

                            const row = document.createElement('tr');
                            row.className = 'audience-row';
                            row.setAttribute('data-search', searchData);

                            const tdName = document.createElement('td');
                            tdName.style.cssText = 'padding: 12px 20px; vertical-align: middle;';
                            const nameSpan = document.createElement('span');
                            nameSpan.style.cssText = 'font-weight: 600; color: #333;';
                            nameSpan.textContent = `${firstName} ${lastName}`;
                            tdName.appendChild(nameSpan);

                            const tdEmail = document.createElement('td');
                            tdEmail.style.cssText = 'padding: 12px 20px; vertical-align: middle;';
                            const emailCode = document.createElement('code');
                            emailCode.style.cssText = 'background: #f4f4f4; color: #555; padding: 2px 6px; border-radius: 4px; border: none;';
                            emailCode.textContent = email;
                            tdEmail.appendChild(emailCode);

                            row.appendChild(tdName);
                            row.appendChild(tdEmail);
                            audienceTableBody.appendChild(row);
                        });
                    }
                    announce(options.previewUpdatedMsg);
                })
                .catch(error => {
                    console.error('Error fetching preview:', error);
                    alert(options.previewFailedMsg);
                })
                .finally(() => {
                    refreshPreviewBtn.disabled = false;
                    if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                });
            });
        }

        // Event delegation for dynamic buttons
        const manageForm = document.getElementById('manage-members-form');
        if (manageForm) {
            manageForm.addEventListener('click', function(event) {
                // Add user from modal handled below or here? 
                // Wait, the modal is outside the form usually in Bootstrap.
                // But the 'remove' buttons are inside.
                const removeBtn = event.target.closest('.remove-user');
                if (removeBtn) {
                    const uid = removeBtn.getAttribute('data-uid');
                    const row = document.getElementById('user-row-' + uid);
                    if (row) {
                        const screenNameCell = row.querySelector('td');
                        const screenName = screenNameCell ? screenNameCell.textContent.trim() : '';
                        row.remove();
                        const remainingRows = selectedUsersTableBody.querySelectorAll('tr.member-row');
                        if (remainingRows.length === 0) {
                            const emptyRow = document.createElement('tr');
                            emptyRow.id = 'no-users-msg';
                            const emptyTd = document.createElement('td');
                            emptyTd.colSpan = 2;
                            emptyTd.className = 'text-center';
                            emptyTd.style.cssText = 'padding: 40px 0; border-top: none;';
                            const emptyP = document.createElement('p');
                            emptyP.className = 'text-muted';
                            emptyP.style.cssText = 'margin-bottom: 0; font-style: italic;';
                            emptyP.textContent = options.noUsersSelectedMsg;
                            emptyTd.appendChild(emptyP);
                            emptyRow.appendChild(emptyTd);
                            selectedUsersTableBody.appendChild(emptyRow);
                        }
                        announce(screenName + ' ' + options.removedMsg);
                    }
                }
            });
        }

        // The modal 'add' buttons are indeed outside the main form.
        // We can attach a listener to the modal body.
        const modalBody = document.querySelector('#userModal .modal-body');
        if (modalBody) {
            modalBody.addEventListener('click', function(event) {
                const addBtn = event.target.closest('.add-user-btn');
                if (addBtn) {
                    const uid = addBtn.getAttribute('data-uid');
                    const screenName = addBtn.getAttribute('data-screenname');
                    
                    if (!document.getElementById('user-row-' + uid)) {
                        const noUsersMsg = document.getElementById('no-users-msg');
                        if (noUsersMsg) {
                            noUsersMsg.remove();
                        }

                        const newRow = document.createElement('tr');
                        newRow.id = 'user-row-' + uid;
                        newRow.className = 'member-row';

                        const tdName = document.createElement('td');
                        tdName.style.cssText = 'vertical-align: middle; border-top: 1px solid #f9f9f9;';
                        const nameSpan = document.createElement('span');
                        nameSpan.style.fontWeight = '500';
                        nameSpan.textContent = screenName;
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'uids[]';
                        hiddenInput.value = uid;
                        hiddenInput.className = 'uid-input';
                        tdName.appendChild(nameSpan);
                        tdName.appendChild(hiddenInput);

                        const tdAction = document.createElement('td');
                        tdAction.style.cssText = 'vertical-align: middle; text-align: right; border-top: 1px solid #f9f9f9;';
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn btn-link btn-xs remove-user text-danger';
                        removeBtn.setAttribute('data-uid', uid);
                        removeBtn.setAttribute('aria-label', options.removeUserMsg + ' ' + screenName);
                        const removeIcon = document.createElement('span');
                        removeIcon.className = 'glyphicon glyphicon-remove';
                        removeIcon.setAttribute('aria-hidden', 'true');
                        removeBtn.appendChild(removeIcon);
                        tdAction.appendChild(removeBtn);

                        newRow.appendChild(tdName);
                        newRow.appendChild(tdAction);
                        selectedUsersTableBody.appendChild(newRow);
                        announce(screenName + ' ' + options.addedMsg);
                    }

                    const parentRow = addBtn.closest('tr');
                    parentRow.style.backgroundColor = '#f0fff0';
                    setTimeout(() => {
                        parentRow.style.backgroundColor = '';
                    }, 500);
                }
            });
        }

        // Modal focus management
        if (userModal && typeof window.jQuery !== 'undefined') {
            window.jQuery(userModal).on('shown.bs.modal', function() {
                if (userSearchInput) userSearchInput.focus();
            });
        }
    }

    function initDashboard() {
        const table = document.querySelector('table[aria-labelledby="mailing-lists-title"]');
        if (!table) return;

        table.addEventListener('click', function(event) {
            const copyBtn = event.target.closest('.copy-btn');
            if (!copyBtn) return;

            const name = copyBtn.getAttribute('data-name');
            const row = copyBtn.closest('tr');
            const feedback = row.querySelector('.copy-feedback');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(name).then(() => {
                    showFeedback(feedback);
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    fallbackCopy(name, feedback);
                });
            } else {
                fallbackCopy(name, feedback);
            }
        });

        function fallbackCopy(text, feedback) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showFeedback(feedback);
            } catch (err) {
                console.error('Fallback copy failed', err);
            }
            document.body.removeChild(textArea);
        }

        function showFeedback(el) {
            if (!el) return;
            el.style.display = 'inline-block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 2000);
        }
    }

    return {
        init: init,
        initDashboard: initDashboard
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = EpisciencesMailingList;
}
