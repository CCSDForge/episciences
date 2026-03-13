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

    test('it sends POST request to the preview URL', async () => {
        document.getElementById('refresh-preview').click();
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledWith('/preview', expect.objectContaining({ method: 'POST' }));
    });

    test('it re-enables the refresh button after preview completes', async () => {
        const btn = document.getElementById('refresh-preview');
        btn.click();
        expect(btn.disabled).toBe(true);

        await new Promise(process.nextTick);

        expect(btn.disabled).toBe(false);
    });

    test('it shows no-members placeholder when preview returns empty array', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({ json: () => Promise.resolve([]) })
        );

        document.getElementById('refresh-preview').click();
        await new Promise(process.nextTick);

        const tbody = document.querySelector('#audience-table tbody');
        expect(tbody.innerHTML).toContain('No members');
        expect(document.querySelectorAll('.audience-row').length).toBe(0);
    });

    test('it shows alert and re-enables button on fetch failure', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));
        global.alert = jest.fn();

        const btn = document.getElementById('refresh-preview');
        btn.click();
        await new Promise(process.nextTick);

        expect(global.alert).toHaveBeenCalledWith('Failed');
        expect(btn.disabled).toBe(false);
    });

    test('it filters audience preview rows on search', () => {
        const audienceSearch = document.getElementById('audience-search');
        const audienceRow = document.querySelector('.audience-row');

        audienceSearch.value = 'nomatch';
        audienceSearch.dispatchEvent(new Event('keyup'));
        expect(audienceRow.style.display).toBe('none');

        audienceSearch.value = 'john';
        audienceSearch.dispatchEvent(new Event('keyup'));
        expect(audienceRow.style.display).toBe('');
    });

    test('it hides all modal rows when search and role filter have no combined match', () => {
        const searchInput = document.getElementById('user-search');
        const roleFilter = document.getElementById('role-filter');
        const rows = document.querySelectorAll('#modal-users-table tbody tr');

        // role=editor matches only row[0] (john), search='jane' matches only row[1]
        roleFilter.value = 'editor';
        roleFilter.dispatchEvent(new Event('change'));
        searchInput.value = 'jane';
        searchInput.dispatchEvent(new Event('keyup'));

        expect(rows[0].style.display).toBe('none');
        expect(rows[1].style.display).toBe('none');
    });

    test('it shows only rows matching both search text and role filter', () => {
        const searchInput = document.getElementById('user-search');
        const roleFilter = document.getElementById('role-filter');
        const rows = document.querySelectorAll('#modal-users-table tbody tr');

        // role=editor, search='john' → row[0] matches both
        roleFilter.value = 'editor';
        roleFilter.dispatchEvent(new Event('change'));
        searchInput.value = 'john';
        searchInput.dispatchEvent(new Event('keyup'));

        expect(rows[0].style.display).toBe('');
        expect(rows[1].style.display).toBe('none');
    });

    test('it does not add a duplicate row when add-user-btn is clicked twice', () => {
        const addBtn = document.querySelector('.add-user-btn[data-uid="1"]');
        addBtn.click();
        addBtn.click();

        expect(document.querySelectorAll('#user-row-1').length).toBe(1);
    });

    test('it adds a hidden uid-input with name uids[] when user is added', () => {
        document.querySelector('.add-user-btn[data-uid="1"]').click();

        const hiddenInput = document.querySelector('.uid-input[value="1"]');
        expect(hiddenInput).toBeTruthy();
        expect(hiddenInput.name).toBe('uids[]');
        expect(hiddenInput.type).toBe('hidden');
    });

    test('it announces when a user is added', () => {
        document.querySelector('.add-user-btn[data-uid="1"]').click();

        expect(document.getElementById('member-announcements').textContent).toContain('added');
    });

    test('it announces when a user is removed', () => {
        document.querySelector('.add-user-btn[data-uid="1"]').click();
        document.querySelector('.remove-user').click();

        expect(document.getElementById('member-announcements').textContent).toContain('removed');
    });

    test('it announces after the audience preview is refreshed', async () => {
        document.getElementById('refresh-preview').click();
        await new Promise(process.nextTick);

        expect(document.getElementById('member-announcements').textContent).toBe('Updated');
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
