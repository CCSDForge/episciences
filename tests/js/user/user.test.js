/**
 * Test suite for the profile photo deletion button (public/js/user/user.js).
 */

const fs = require('fs');
const path = require('path');

describe('Profile photo deletion', function () {
    let userJs;

    beforeAll(function () {
        userJs = fs.readFileSync(
            path.join(__dirname, '../../../public/js/user/user.js'),
            'utf8'
        );
    });

    beforeEach(function () {
        document.head.innerHTML =
            '<meta name="csrf-token" content="session-token">';
        document.body.innerHTML = `
            <img class="user-photo-thumb" src="/user/photo/size/thumb" alt="">
            <img class="user-photo" src="/user/photo/uid/42" alt="">
            <a href="#" id="delete-photo" attr-uid="42">Delete</a>
        `;
        global.message = jest.fn();
        global.fetch = jest.fn();
        // Runs the repository script under test, as the other suites do
        eval(userJs);
        document.dispatchEvent(new Event('DOMContentLoaded'));
    });

    afterEach(function () {
        delete global.message;
        delete global.fetch;
    });

    function respond(status, body) {
        global.fetch.mockResolvedValue({
            ok: status >= 200 && status < 300,
            status: status,
            text: () => Promise.resolve(body),
        });
    }

    async function clickDelete() {
        document.getElementById('delete-photo').click();
        // Let the fetch promise chain settle
        await new Promise(resolve => setTimeout(resolve, 0));
    }

    it('reports a failure and keeps the photos when the deletion is refused', async function () {
        respond(403, '');

        await clickDelete();

        expect(global.message).toHaveBeenCalledWith(
            'La suppression a échoué.',
            'alert-danger'
        );
        expect(global.message).not.toHaveBeenCalledWith(
            'Photo supprimée.',
            'alert-success'
        );
        expect(document.querySelector('.user-photo').style.opacity).toBe('');
        expect(document.querySelector('.user-photo-thumb').style.opacity).toBe(
            ''
        );
    });

    it("hides the deleted photo but not the caller's own navbar thumbnail for another account", async function () {
        respond(200, '2');

        await clickDelete();

        expect(document.querySelector('.user-photo').style.opacity).toBe('0');
        expect(document.querySelector('.user-photo-thumb').style.opacity).toBe(
            ''
        );
        expect(global.message).toHaveBeenCalledWith(
            'Photo supprimée.',
            'alert-success'
        );
    });

    it("also hides the navbar thumbnail when the caller's own photo is deleted", async function () {
        respond(200, '1');

        await clickDelete();

        expect(document.querySelector('.user-photo-thumb').style.opacity).toBe(
            '0'
        );
        expect(document.querySelector('.user-photo').style.opacity).toBe('0');
    });

    it('posts the photo owner uid as an AJAX request', async function () {
        respond(200, '1');

        await clickDelete();

        const [url, options] = global.fetch.mock.calls[0];
        expect(url).toBe('/user/ajaxdeletephoto');
        expect(options.method).toBe('POST');
        expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(options.headers['X-CSRF-Token']).toBe('session-token');
        expect(options.body.get('uid')).toBe('42');
    });
});
