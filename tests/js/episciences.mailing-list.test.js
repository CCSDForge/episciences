const EpisciencesMailingList = require('../../public/js/library/episciences.mailing-list.js');

describe('EpisciencesMailingList', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="member-announcements"></div>
            <form id="manage-members-form">
                <table id="users-table">
                    <tbody>
                        <tr id="no-users-msg"><td>No users</td></tr>
                    </tbody>
                </table>
                <div id="audience-search-container">
                    <input type="text" id="audience-search">
                    <table id="audience-table">
                        <tbody>
                            <tr class="audience-row" data-search="john@test.com"><td>John</td></tr>
                        </tbody>
                    </table>
                </div>
                <button id="refresh-preview" type="button"><span id="refresh-icon"></span></button>
            </form>

            <div id="userModal" class="modal">
                <div class="modal-body">
                    <input type="text" id="user-search">
                    <select id="role-filter">
                        <option value="">All roles</option>
                        <option value="editor">Editor</option>
                    </select>
                    <table id="modal-users-table">
                        <tbody>
                            <tr data-search="john doe" data-roles='["editor"]'>
                                <td>John Doe</td>
                                <td><button class="add-user-btn" type="button" data-uid="1" data-screenname="jdoe"></button></td>
                            </tr>
                            <tr data-search="jane smith" data-roles='["reviewer"]'>
                                <td>Jane Smith</td>
                                <td><button class="add-user-btn" type="button" data-uid="2" data-screenname="jsmith"></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve([{ FIRSTNAME: 'New', LASTNAME: 'User', EMAIL: 'new@test.com' }]),
            })
        );

        EpisciencesMailingList.init({
            previewUrl: '/preview',
            noMembersMsg: 'No members',
            previewUpdatedMsg: 'Updated',
            previewFailedMsg: 'Failed',
            removeUserMsg: 'Remove',
            addedMsg: 'added',
            removedMsg: 'removed',
            noUsersSelectedMsg: 'None'
        });
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('it filters modal users by search text', () => {
        const searchInput = document.getElementById('user-search');
        const rows = document.querySelectorAll('#modal-users-table tbody tr');
        
        searchInput.value = 'jane';
        searchInput.dispatchEvent(new Event('keyup'));

        expect(rows[0].style.display).toBe('none');
        expect(rows[1].style.display).toBe('');
    });

    test('it filters modal users by role', () => {
        const roleFilter = document.getElementById('role-filter');
        const rows = document.querySelectorAll('#modal-users-table tbody tr');
        
        roleFilter.value = 'editor';
        roleFilter.dispatchEvent(new Event('change'));

        expect(rows[0].style.display).toBe('');
        expect(rows[1].style.display).toBe('none');
    });

    test('it adds a user from the modal to the selected users table', () => {
        const addBtn = document.querySelector('.add-user-btn[data-uid="1"]');
        const selectedTable = document.querySelector('#users-table tbody');
        
        addBtn.click();

        expect(document.getElementById('user-row-1')).toBeTruthy();
        expect(document.getElementById('user-row-1').classList.contains('member-row')).toBeTruthy();
        expect(document.getElementById('no-users-msg')).toBeFalsy();
        expect(selectedTable.innerHTML).toContain('jdoe');
    });

    test('it removes a user from the selected users table', () => {
        const addBtn = document.querySelector('.add-user-btn[data-uid="1"]');
        addBtn.click();
        
        const removeBtn = document.querySelector('.remove-user');
        removeBtn.click();

        expect(document.getElementById('user-row-1')).toBeFalsy();
        expect(document.getElementById('no-users-msg')).toBeTruthy();
    });

    test('it updates the audience preview via fetch', async () => {
        const refreshBtn = document.getElementById('refresh-preview');
        
        refreshBtn.click();

        // Wait for promise
        await new Promise(process.nextTick);

        const audienceRows = document.querySelectorAll('.audience-row');
        expect(global.fetch).toHaveBeenCalled();
        expect(audienceRows.length).toBe(1);
        expect(audienceRows[0].innerHTML).toContain('new@test.com');
    });

    describe('initDashboard', () => {
        beforeEach(() => {
            document.body.innerHTML = `
                <table aria-labelledby="mailing-lists-title">
                    <tbody>
                        <tr>
                            <td>
                                <button class="copy-btn" data-name="test@domain.com">
                                    Copy
                                </button>
                                <span class="copy-feedback" style="display: none;">Copied!</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            `;
            // Mock document.execCommand for fallback copy
            document.execCommand = jest.fn();
            EpisciencesMailingList.initDashboard();
        });

        test('it copies text to clipboard and shows feedback', () => {
            const copyBtn = document.querySelector('.copy-btn');
            const feedback = document.querySelector('.copy-feedback');
            
            copyBtn.click();

            expect(document.execCommand).toHaveBeenCalledWith('copy');
            expect(feedback.style.display).toBe('inline-block');
        });
    });
});
