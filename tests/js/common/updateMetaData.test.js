/**
 * @jest-environment jsdom
 */

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
    path.join(__dirname, '../../../public/js/common/updateMetaData.js'),
    'utf8'
);

// Evaluates the script as the browser does (jsdom cannot reload: `location` is stubbed)
function loadScript(location) {
    return new Function(
        'location',
        source +
            '\nreturn { updateMetaData, showPendingUpdateMetaDataMessage };'
    )(location);
}

const ERROR_MESSAGE =
    'Une erreur est survenue lors de la mise à jour des métadonnées.';

describe('updateMetaData', () => {
    let reloadMock;
    let fakeLocation;

    beforeEach(() => {
        document.body.innerHTML =
            '<div id="record-loading"></div><div><button id="update">Update</button></div>';
        sessionStorage.clear();
        global.message = jest.fn();
        global.getLoader = jest.fn(() => '<span>loading</span>');
        global.fetch = jest.fn();

        reloadMock = jest.fn();
        fakeLocation = { reload: reloadMock };
    });

    afterEach(() => {
        delete global.message;
        delete global.getLoader;
    });

    function respondWith(status, body) {
        global.fetch.mockResolvedValue({
            status,
            text: () => Promise.resolve(body),
        });
    }

    test('reports an error when the response is not JSON', async () => {
        respondWith(403, '');
        const { updateMetaData } = loadScript(fakeLocation);

        await updateMetaData(document.getElementById('update'), 12);

        expect(global.message).toHaveBeenCalledWith(
            ERROR_MESSAGE,
            'alert-danger'
        );
        expect(reloadMock).not.toHaveBeenCalled();
        expect(document.getElementById('record-loading').style.display).toBe(
            'none'
        );
    });

    test('reports a network error', async () => {
        global.fetch.mockRejectedValue(new Error('offline'));
        const { updateMetaData } = loadScript(fakeLocation);

        await updateMetaData(document.getElementById('update'), 12);

        expect(global.message).toHaveBeenCalledWith(
            ERROR_MESSAGE,
            'alert-danger'
        );
    });

    test('displays the backend error message', async () => {
        respondWith(
            200,
            JSON.stringify({ error: 'boom', message: 'Erreur interne' })
        );
        const { updateMetaData } = loadScript(fakeLocation);

        await updateMetaData(document.getElementById('update'), 12);

        expect(global.message).toHaveBeenCalledWith(
            'Erreur interne',
            'alert-danger'
        );
    });

    test('displays an info message when metadata is already up to date', async () => {
        respondWith(
            200,
            JSON.stringify({ affectedRows: 0, message: 'À jour' })
        );
        const { updateMetaData } = loadScript(fakeLocation);

        await updateMetaData(document.getElementById('update'), 12);

        expect(global.message).toHaveBeenCalledWith('À jour', 'alert-info');
        expect(reloadMock).not.toHaveBeenCalled();
    });

    test('reloads and displays the success message once, after the reload', async () => {
        respondWith(
            200,
            JSON.stringify({ affectedRows: 1, message: 'Mis à jour' })
        );
        const { updateMetaData } = loadScript(fakeLocation);

        await updateMetaData(document.getElementById('update'), 12);

        expect(reloadMock).toHaveBeenCalled();
        expect(global.message).not.toHaveBeenCalled();

        // Simulates the reloaded page loading the script again
        loadScript(fakeLocation);
        expect(global.message).toHaveBeenCalledWith(
            'Mis à jour',
            'alert-success'
        );

        global.message.mockClear();
        loadScript(fakeLocation);
        expect(global.message).not.toHaveBeenCalled();
    });
});
