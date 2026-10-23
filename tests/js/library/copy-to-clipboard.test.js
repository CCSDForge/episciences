const CopyToClipboard = require('../../../public/js/library/episciences.copy-to-clipboard.js');

describe('CopyToClipboard', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="container">
                <button class="copy-btn" data-copy-value="secret123">Copy</button>
                <span class="copy-feedback" style="display: none;">Copié !</span>
            </div>
        `;
        document.execCommand = jest.fn(() => true);
    });

    describe('init', () => {
        test('does nothing when container is null', () => {
            expect(() => CopyToClipboard.init(null)).not.toThrow();
        });

        test('does nothing when container is undefined', () => {
            expect(() => CopyToClipboard.init(undefined)).not.toThrow();
        });

        test('copies text via fallback when navigator.clipboard is unavailable', () => {
            delete navigator.clipboard;

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            expect(document.execCommand).toHaveBeenCalledWith('copy');
        });

        test('shows feedback after successful fallback copy', () => {
            delete navigator.clipboard;
            const feedback = document.querySelector('.copy-feedback');

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            expect(feedback.style.display).toBe('inline-block');
        });

        test('hides feedback after 2 seconds', () => {
            jest.useFakeTimers();
            delete navigator.clipboard;
            const feedback = document.querySelector('.copy-feedback');

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            jest.advanceTimersByTime(2000);
            expect(feedback.style.display).toBe('none');
            jest.useRealTimers();
        });

        test('ignores clicks that are not on a .copy-btn', () => {
            delete navigator.clipboard;
            const container = document.getElementById('container');

            CopyToClipboard.init(container);
            container.click();

            expect(document.execCommand).not.toHaveBeenCalled();
        });

        test('ignores .copy-btn elements with no data-copy-value attribute', () => {
            delete navigator.clipboard;
            document.body.innerHTML = `
                <div id="container">
                    <button class="copy-btn">Copy</button>
                    <span class="copy-feedback" style="display: none;">Copié !</span>
                </div>
            `;

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            expect(document.execCommand).not.toHaveBeenCalled();
        });

        test('uses navigator.clipboard.writeText when available', async () => {
            const writeText = jest.fn(() => Promise.resolve());
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText },
                configurable: true,
            });

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            expect(writeText).toHaveBeenCalledWith('secret123');

            delete navigator.clipboard;
        });

        test('shows feedback after successful navigator.clipboard copy', async () => {
            const writeText = jest.fn(() => Promise.resolve());
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText },
                configurable: true,
            });
            const feedback = document.querySelector('.copy-feedback');

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            await Promise.resolve();
            expect(feedback.style.display).toBe('inline-block');

            delete navigator.clipboard;
        });

        test('falls back to execCommand when navigator.clipboard.writeText rejects', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: jest.fn(() => Promise.reject(new Error('denied'))) },
                configurable: true,
            });

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            await new Promise(process.nextTick);
            expect(document.execCommand).toHaveBeenCalledWith('copy');

            delete navigator.clipboard;
        });

        test('finds feedback within the same table row as the button', () => {
            delete navigator.clipboard;
            document.body.innerHTML = `
                <table>
                    <tbody id="container">
                        <tr>
                            <td>
                                <button class="copy-btn" data-copy-value="rowcode">Copy</button>
                                <span class="copy-feedback" style="display: none;">Copié !</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            `;

            CopyToClipboard.init(document.getElementById('container'));
            document.querySelector('.copy-btn').click();

            expect(document.querySelector('.copy-feedback').style.display).toBe('inline-block');
        });
    });

    describe('copyText', () => {
        test('is a public function', () => {
            expect(typeof CopyToClipboard.copyText).toBe('function');
        });

        test('calls fallbackCopy when clipboard API is unavailable', () => {
            delete navigator.clipboard;
            const feedback = document.querySelector('.copy-feedback');

            CopyToClipboard.copyText('hello', feedback);

            expect(document.execCommand).toHaveBeenCalledWith('copy');
        });

        test('shows feedback even when feedbackEl is null', () => {
            delete navigator.clipboard;

            expect(() => CopyToClipboard.copyText('hello', null)).not.toThrow();
        });
    });
});