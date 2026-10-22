'use strict';

const { clearFile } = require('../../../public/js/common/visualDeleteFile');

function selectFile(input, name) {
    Object.defineProperty(input, 'files', {
        configurable: true,
        value: [new File(['x'], name)],
    });
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

describe('visualDeleteFile', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('shows the selected file name next to a regular file input', () => {
        document.body.innerHTML = `
            <div><input type="file" id="file_report"><input id="value_file_report"></div>`;

        selectFile(document.getElementById('file_report'), '<b>report</b>.pdf');

        const container = document.getElementById(
            'tempFile_content_file_report'
        );
        expect(container.textContent).toBe('<b>report</b>.pdf');
        expect(container.querySelector('b')).toBeNull();
    });

    test("leaves FilePond's internal file input alone", () => {
        document.body.innerHTML = `
            <div class="filepond--root"><input type="file" id="filepond--browser-abc"></div>`;

        selectFile(document.getElementById('filepond--browser-abc'), 'a.pdf');

        expect(
            document.getElementById('tempFile_content_filepond--browser-abc')
        ).toBeNull();
    });

    test('the cancel icon clears the file, its paired value and the label', () => {
        document.body.innerHTML = `
            <div><input type="file" id="file_report"><input id="value_file_report" value="report.pdf"></div>`;
        const input = document.getElementById('file_report');
        selectFile(input, 'report.pdf');

        const onChange = jest.fn();
        input.addEventListener('change', onChange);
        Object.defineProperty(input, 'files', {
            configurable: true,
            value: [],
        });

        document.querySelector('.glyphicon-remove-circle').click();

        expect(document.getElementById('value_file_report').value).toBe('');
        expect(onChange).toHaveBeenCalled();
        expect(
            document.getElementById('tempFile_content_file_report').textContent
        ).toBe('');
    });

    test('clearFile ignores an unknown input', () => {
        expect(() => clearFile('missing')).not.toThrow();
    });
});
