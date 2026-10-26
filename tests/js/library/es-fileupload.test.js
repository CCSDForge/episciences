'use strict';

// public/js/library/es.fileupload.js is a plain global script (no
// module.exports). As with tests/js/paper/edit-master-file.test.js, the repo's
// own file is run at test time so that the real ajaxDeleteFile() is tested.
// The jQuery ready callback is not run (see the $ mock in tests/js/setup.js):
// only the global functions of the file are defined.
const fs = require('fs');
const path = require('path');

const esFileUploadJsSource = fs.readFileSync(
    path.join(__dirname, '../../../public/js/library/es.fileupload.js'),
    'utf8'
);

// Journal URL prefix (see public/const.php): every internal URL is built from it
global.JS_PREFIX_URL = '/dev/';

// Indirect eval: the function declarations of the script become globals
(0, eval)(esFileUploadJsSource);

describe('ajaxDeleteFile', function () {
    beforeEach(function () {
        global.fetch = jest.fn();
    });

    it('posts the form data to the journal file/delete URL, kept alive when the page is left', async function () {
        global.fetch.mockResolvedValue({ ok: true, status: 200 });

        await ajaxDeleteFile({
            csrf_token: 'csrf-123',
            path: 'ce_attachments',
            docId: '42',
            pcId: '7',
            file: 'report final.pdf',
            token: 'file-token',
        });

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const [url, options] = global.fetch.mock.calls[0];

        expect(url).toBe('/dev/file/delete');
        expect(options.method).toBe('POST');
        expect(options.credentials).toBe('same-origin');
        expect(options.keepalive).toBe(true);
        expect(options.headers).toEqual({
            'X-Requested-With': 'XMLHttpRequest',
        });
        expect(options.body).toBeInstanceOf(URLSearchParams);
        expect(Object.fromEntries(options.body)).toEqual({
            csrf_token: 'csrf-123',
            path: 'ce_attachments',
            docId: '42',
            pcId: '7',
            file: 'report final.pdf',
            token: 'file-token',
        });
    });

    it('does not send undefined or null values as text', async function () {
        global.fetch.mockResolvedValue({ ok: true, status: 200 });

        await ajaxDeleteFile({
            file: 'a.pdf',
            token: 'file-token',
            docId: undefined,
            paperId: null,
            pcId: 0,
        });

        const body = global.fetch.mock.calls[0][1].body;

        expect(body.has('docId')).toBe(false);
        expect(body.has('paperId')).toBe(false);
        expect(body.get('pcId')).toBe('0');
        expect(body.toString()).not.toContain('undefined');
        expect(body.toString()).not.toContain('null');
    });

    it('resolves when the server refuses the deletion', async function () {
        global.fetch.mockResolvedValue({ ok: false, status: 403 });

        await expect(
            ajaxDeleteFile({ file: 'a.pdf', token: 'bad-token' })
        ).resolves.toBeUndefined();
    });

    it('resolves on a network failure, so that the file is removed from the form anyway', async function () {
        global.fetch.mockRejectedValue(new TypeError('Failed to fetch'));
        const onSettled = jest.fn();

        await ajaxDeleteFile({ file: 'a.pdf', token: 'file-token' }).finally(
            onSettled
        );

        expect(onSettled).toHaveBeenCalledTimes(1);
    });
});
