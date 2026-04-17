/**
 * Test suite for toggle-reply-form component
 * Covers: showReplyForm, hideReplyForm, cancel-author-form, init lifecycle,
 * edge cases, documented bugs, and security issues.
 */

const fs = require('fs');
const path = require('path');

describe('Toggle Reply Form Component', function () {
    let toggleReplyFormJs;

    beforeAll(function () {
        toggleReplyFormJs = fs.readFileSync(
            path.join(
                __dirname,
                '../../../public/js/components/toggle-reply-form.js'
            ),
            'utf8'
        );
    });

    beforeEach(function () {
        document.body.innerHTML = '';
        // restoreAllMocks restores original implementations (e.g. document.querySelector spies)
        jest.restoreAllMocks();
        // Safety net: always restore readyState to 'complete' before each test
        Object.defineProperty(document, 'readyState', {
            get: () => 'complete',
            configurable: true,
        });
    });

    /**
     * Build a standard DOM: one toggle button + one reply form div with textarea + cancel button.
     */
    function buildReplyDom(formId) {
        document.body.innerHTML = `
            <button class="toggle-reply-form" data-reply-form-id="${formId}">Reply</button>
            <div id="${formId}" style="display:none">
                <textarea></textarea>
                <button class="cancel-reply-form" data-reply-form-id="${formId}">Cancel</button>
            </div>
        `;
    }

    // -------------------------------------------------------------------------
    describe('showReplyForm (via .toggle-reply-form click)', function () {
        it('should show the form and hide the toggle button', function () {
            buildReplyDom('form-1');
            eval(toggleReplyFormJs);

            const button = document.querySelector('.toggle-reply-form');
            const form = document.getElementById('form-1');

            button.click();

            expect(form.style.display).toBe('block');
            expect(button.style.display).toBe('none');
        });

        it('should focus the textarea inside the form', function () {
            buildReplyDom('form-2');
            eval(toggleReplyFormJs);

            const textarea = document.querySelector('textarea');
            const focusSpy = jest.spyOn(textarea, 'focus');

            document.querySelector('.toggle-reply-form').click();

            expect(focusSpy).toHaveBeenCalled();
        });

        it('should not crash when the form element does not exist', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="nonexistent">Reply</button>
            `;
            eval(toggleReplyFormJs);

            // getElementById('nonexistent') returns null → if (form && button) short-circuits
            expect(() => {
                document.querySelector('.toggle-reply-form').click();
            }).not.toThrow();
        });

        it('should not crash when the form has no textarea', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="no-ta">Reply</button>
                <div id="no-ta" style="display:none"></div>
            `;
            eval(toggleReplyFormJs);

            expect(() => {
                document.querySelector('.toggle-reply-form').click();
            }).not.toThrow();

            expect(document.getElementById('no-ta').style.display).toBe(
                'block'
            );
        });

        it('should prevent the default action on click', function () {
            buildReplyDom('form-3');
            eval(toggleReplyFormJs);

            const event = new MouseEvent('click', {
                cancelable: true,
                bubbles: true,
            });
            document.querySelector('.toggle-reply-form').dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    describe('hideReplyForm (via .cancel-reply-form click)', function () {
        it('should hide the form and restore the toggle button', function () {
            buildReplyDom('form-4');
            eval(toggleReplyFormJs);

            const toggle = document.querySelector('.toggle-reply-form');
            const cancel = document.querySelector('.cancel-reply-form');
            const form = document.getElementById('form-4');

            toggle.click();
            expect(form.style.display).toBe('block');

            cancel.click();

            expect(form.style.display).toBe('none');
            expect(toggle.style.display).toBe('inline-block');
        });

        it('should clear the textarea value on cancel', function () {
            buildReplyDom('form-5');
            eval(toggleReplyFormJs);

            const textarea = document.querySelector('textarea');
            document.querySelector('.toggle-reply-form').click();
            textarea.value = 'typed reply content';

            document.querySelector('.cancel-reply-form').click();

            expect(textarea.value).toBe('');
        });

        it('should prevent the default action on cancel click', function () {
            buildReplyDom('form-6');
            eval(toggleReplyFormJs);

            const event = new MouseEvent('click', {
                cancelable: true,
                bubbles: true,
            });
            document.querySelector('.cancel-reply-form').dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
        });

        it('should be a no-op when the matching toggle button does not exist', function () {
            // A cancel button referencing a form that has no .toggle-reply-form counterpart.
            document.body.innerHTML = `
                <div id="orphan-form">
                    <textarea></textarea>
                    <button class="cancel-reply-form" data-reply-form-id="orphan-form">Cancel</button>
                </div>
            `;
            eval(toggleReplyFormJs);

            // querySelector for .toggle-reply-form returns null → if (form && button) fails
            expect(() => {
                document.querySelector('.cancel-reply-form').click();
            }).not.toThrow();
        });
    });

    // -------------------------------------------------------------------------
    describe('.cancel-author-form button', function () {
        function buildAuthorFormDom() {
            document.body.innerHTML = `
                <form>
                    <textarea name="comment">Hello world</textarea>
                    <input type="file">
                    <button class="cancel-author-form" type="button">Cancel</button>
                </form>
            `;
        }

        it('should clear the textarea[name="comment"] on click', function () {
            buildAuthorFormDom();
            eval(toggleReplyFormJs);

            expect(
                document.querySelector('textarea[name="comment"]').value
            ).toBe('Hello world');

            document.querySelector('.cancel-author-form').click();

            expect(
                document.querySelector('textarea[name="comment"]').value
            ).toBe('');
        });

        it('should reset the file input on click', function () {
            buildAuthorFormDom();
            eval(toggleReplyFormJs);

            document.querySelector('.cancel-author-form').click();

            expect(document.querySelector('input[type="file"]').value).toBe('');
        });

        it('should prevent the default action on click', function () {
            buildAuthorFormDom();
            eval(toggleReplyFormJs);

            const event = new MouseEvent('click', {
                cancelable: true,
                bubbles: true,
            });
            document.querySelector('.cancel-author-form').dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
        });

        it('should not crash when the form has no textarea or file input', function () {
            document.body.innerHTML = `
                <form>
                    <button class="cancel-author-form" type="button">Cancel</button>
                </form>
            `;
            eval(toggleReplyFormJs);

            expect(() => {
                document.querySelector('.cancel-author-form').click();
            }).not.toThrow();
        });

        it('should not crash when the button has no ancestor <form>', function () {
            document.body.innerHTML = `
                <div>
                    <button class="cancel-author-form" type="button">Cancel</button>
                </div>
            `;
            eval(toggleReplyFormJs);

            // btn.closest('form') returns null → if (form) guard prevents crash
            expect(() => {
                document.querySelector('.cancel-author-form').click();
            }).not.toThrow();
        });
    });

    // -------------------------------------------------------------------------
    describe('Initialization lifecycle', function () {
        it('should set up handlers immediately when DOM is already loaded', function () {
            // jsdom readyState is 'complete' by default → initToggleReplyForm() runs on eval
            buildReplyDom('form-immediate');
            eval(toggleReplyFormJs);

            document.querySelector('.toggle-reply-form').click();

            expect(
                document.getElementById('form-immediate').style.display
            ).toBe('block');
        });

        it('should defer initialization to DOMContentLoaded when readyState is loading', function () {
            buildReplyDom('form-defer');

            Object.defineProperty(document, 'readyState', {
                get: () => 'loading',
                configurable: true,
            });

            try {
                eval(toggleReplyFormJs);

                // Before DOMContentLoaded fires, click does nothing (no listener yet)
                document.querySelector('.toggle-reply-form').click();
                // form starts with style="display:none" from HTML — click was a no-op
                expect(
                    document.getElementById('form-defer').style.display
                ).toBe('none');

                // Restore readyState before dispatching DOMContentLoaded
                Object.defineProperty(document, 'readyState', {
                    get: () => 'complete',
                    configurable: true,
                });
                document.dispatchEvent(new Event('DOMContentLoaded'));

                // Now handlers are attached; click the (cloned) button
                document.querySelector('.toggle-reply-form').click();
                expect(
                    document.getElementById('form-defer').style.display
                ).toBe('block');
            } finally {
                // Always restore to avoid polluting subsequent tests on assertion failure
                Object.defineProperty(document, 'readyState', {
                    get: () => 'complete',
                    configurable: true,
                });
            }
        });

        it('should re-initialize on contentLoaded event (for AJAX-added content)', function () {
            eval(toggleReplyFormJs);

            // Simulate new content added after initial load
            buildReplyDom('form-dynamic');

            document.dispatchEvent(new Event('contentLoaded'));

            document.querySelector('.toggle-reply-form').click();
            expect(document.getElementById('form-dynamic').style.display).toBe(
                'block'
            );
        });

        it('should replace buttons with clones to avoid duplicate listeners on re-init', function () {
            buildReplyDom('form-clone');
            eval(toggleReplyFormJs);

            // Re-initialize via contentLoaded
            document.dispatchEvent(new Event('contentLoaded'));

            // Button was cloned twice, but each clone removes the previous listener
            // → clicking once should show the form exactly once (not toggle it back)
            document.querySelector('.toggle-reply-form').click();

            expect(document.getElementById('form-clone').style.display).toBe(
                'block'
            );
        });
    });

    // -------------------------------------------------------------------------
    describe('Multiple buttons and forms', function () {
        it('should handle multiple toggle buttons independently', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="form-a">Reply A</button>
                <button class="toggle-reply-form" data-reply-form-id="form-b">Reply B</button>
                <div id="form-a" style="display:none"><textarea></textarea></div>
                <div id="form-b" style="display:none"><textarea></textarea></div>
            `;
            eval(toggleReplyFormJs);

            const [btnA, btnB] =
                document.querySelectorAll('.toggle-reply-form');

            btnA.click();
            expect(document.getElementById('form-a').style.display).toBe(
                'block'
            );
            expect(document.getElementById('form-b').style.display).toBe(
                'none'
            );

            btnB.click();
            expect(document.getElementById('form-b').style.display).toBe(
                'block'
            );
        });

        it('should handle multiple cancel buttons independently', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="fa">Reply A</button>
                <button class="toggle-reply-form" data-reply-form-id="fb">Reply B</button>
                <div id="fa">
                    <textarea></textarea>
                    <button class="cancel-reply-form" data-reply-form-id="fa">Cancel A</button>
                </div>
                <div id="fb">
                    <textarea></textarea>
                    <button class="cancel-reply-form" data-reply-form-id="fb">Cancel B</button>
                </div>
            `;
            eval(toggleReplyFormJs);

            document.querySelectorAll('.toggle-reply-form')[0].click();
            document.querySelectorAll('.toggle-reply-form')[1].click();

            document.querySelectorAll('.cancel-reply-form')[0].click();
            expect(document.getElementById('fa').style.display).toBe('none');
            expect(document.getElementById('fb').style.display).toBe('block');
        });
    });

    // -------------------------------------------------------------------------
    describe('Edge cases', function () {
        it('should handle missing data-reply-form-id attribute gracefully', function () {
            // getAttribute returns null → getElementById(null) → getElementById('null') → null
            // querySelector('[data-reply-form-id="null"]') → no match → if (form && button) is false
            document.body.innerHTML = `
                <button class="toggle-reply-form">Reply (no id attr)</button>
            `;
            eval(toggleReplyFormJs);

            expect(() => {
                document.querySelector('.toggle-reply-form').click();
            }).not.toThrow();
        });

        it('should toggle a form that starts visible (already open)', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="form-open" style="display:none">Reply</button>
                <div id="form-open" style="display:block">
                    <textarea></textarea>
                    <button class="cancel-reply-form" data-reply-form-id="form-open">Cancel</button>
                </div>
            `;
            eval(toggleReplyFormJs);

            // Clicking cancel on an already-open form should close it
            document.querySelector('.cancel-reply-form').click();

            expect(document.getElementById('form-open').style.display).toBe(
                'none'
            );
            expect(
                document.querySelector('.toggle-reply-form').style.display
            ).toBe('inline-block');
        });
    });

    // -------------------------------------------------------------------------
    describe('Fixed behaviors', function () {
        /**
         * FIX verified: the dead `return false` was removed from the cancel-author-form handler.
         * The event still propagates (e.stopPropagation() was never intended here),
         * but the code no longer contains misleading dead code.
         */
        it('cancel-author-form click event propagates (e.stopPropagation not intended)', function () {
            document.body.innerHTML = `
                <form>
                    <textarea name="comment">text</textarea>
                    <button class="cancel-author-form" type="button">Cancel</button>
                </form>
            `;
            eval(toggleReplyFormJs);

            let propagated = false;
            document.body.addEventListener('click', () => {
                propagated = true;
            });

            document.querySelector('.cancel-author-form').click();

            expect(propagated).toBe(true);
        });

        /**
         * FIX verified: getElementById guard short-circuits before querySelector is called.
         * When the target form does not exist, querySelector is never reached → no SyntaxError risk.
         */
        it('click is a no-op when the form element does not exist (querySelector not reached)', function () {
            document.body.innerHTML = `
                <button class="toggle-reply-form" data-reply-form-id="missing">Reply</button>
            `;
            eval(toggleReplyFormJs);

            const querySelectorSpy = jest.spyOn(document, 'querySelector');

            document.querySelector('.toggle-reply-form').click();

            // querySelector should NOT have been called with the attribute selector
            // because the !form early-return fired first
            const attrSelectorCalls = querySelectorSpy.mock.calls
                .map(c => c[0])
                .filter(
                    s =>
                        typeof s === 'string' &&
                        s.includes('data-reply-form-id')
                );
            expect(attrSelectorCalls).toHaveLength(0);
        });
    });

    // -------------------------------------------------------------------------
    describe('Security: CSS selector injection fix via CSS.escape()', function () {
        /**
         * FIX: formId is now passed through CSS.escape() before interpolation into querySelector.
         * Special CSS characters like `"` and `]` are safely escaped → no SyntaxError.
         */

        it('should not throw when formId contains a double-quote (CSS.escape applied)', function () {
            // Build DOM programmatically to use special chars in attribute and id
            const btn = document.createElement('button');
            btn.className = 'toggle-reply-form';
            btn.setAttribute('data-reply-form-id', 'foo"bar');
            document.body.appendChild(btn);

            const form = document.createElement('div');
            form.setAttribute('id', 'foo"bar');
            form.style.display = 'none';
            document.body.appendChild(form);

            eval(toggleReplyFormJs);

            // CSS.escape('foo"bar') → 'foo\\22 bar' — valid selector, no SyntaxError
            expect(() => {
                document.querySelector('.toggle-reply-form').click();
            }).not.toThrow();

            // The form is shown because getElementById + escaped querySelector both succeed
            expect(form.style.display).toBe('block');
        });

        it('should not throw when formId contains a closing bracket (CSS.escape applied)', function () {
            const btn = document.createElement('button');
            btn.className = 'toggle-reply-form';
            btn.setAttribute('data-reply-form-id', 'foo]bar');
            document.body.appendChild(btn);

            const form = document.createElement('div');
            form.setAttribute('id', 'foo]bar');
            form.style.display = 'none';
            document.body.appendChild(form);

            eval(toggleReplyFormJs);

            expect(() => {
                document.querySelector('.toggle-reply-form').click();
            }).not.toThrow();

            expect(form.style.display).toBe('block');
        });

        it('CSS.escape is used: selector passed to querySelector contains escaped characters', function () {
            document.body.innerHTML =
                '<button class="toggle-reply-form">Reply</button>';
            document
                .querySelector('.toggle-reply-form')
                .setAttribute('data-reply-form-id', 'foo"bar');

            // Add a matching form so getElementById succeeds and querySelector is reached
            const form = document.createElement('div');
            form.setAttribute('id', 'foo"bar');
            document.body.appendChild(form);

            eval(toggleReplyFormJs);

            const btn = document.querySelector('.toggle-reply-form');
            const capturedSelectors = [];
            jest.spyOn(document, 'querySelector').mockImplementation(
                selector => {
                    capturedSelectors.push(selector);
                    return null;
                }
            );

            btn.click();

            const attrSelector = capturedSelectors.find(s =>
                s.includes('data-reply-form-id')
            );
            expect(attrSelector).toBeDefined();
            // CSS.escape('foo"bar') escapes the `"` with a backslash → foo\"bar
            // The buggy (unescaped) selector would be: [data-reply-form-id="foo"bar"]
            // The fixed (escaped) selector must contain \" (backslash + double-quote)
            expect(attrSelector).not.toBe(
                '.toggle-reply-form[data-reply-form-id="foo"bar"]'
            );
            expect(attrSelector).toContain('\\"'); // literal backslash + double-quote = CSS escape
        });

        it('should work correctly with a safe alphanumeric formId', function () {
            buildReplyDom('safe-form-123');
            eval(toggleReplyFormJs);

            document.querySelector('.toggle-reply-form').click();

            expect(document.getElementById('safe-form-123').style.display).toBe(
                'block'
            );
        });
    });
});
