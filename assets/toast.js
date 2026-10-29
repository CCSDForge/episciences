// Bundled by Webpack Encore (stubbed by Jest's moduleNameMapper)
require('./toast.css');

/**
 * Episciences Modern Toast Component
 * Lightweight Vanilla JS Toast notification system with auto-dismiss,
 * pause-on-hover/focus, a11y live regions, and stacking support.
 */
(function (global) {
    'use strict';

    var ICONS = {
        success:
            '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />' +
            '</svg>',
        error:
            '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />' +
            '</svg>',
        warning:
            '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
            '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />' +
            '</svg>',
        info:
            '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
            '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />' +
            '</svg>',
        close:
            '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<line x1="2" y1="2" x2="12" y2="12"></line>' +
            '<line x1="12" y1="2" x2="2" y2="12"></line>' +
            '</svg>',
    };

    // Delay between a live region update and its announcement, so that screen readers
    // pick up regions created in the same tick (e.g. flash messages on page load).
    var ANNOUNCE_DELAY = 100;

    var container = null;
    var announcers = {};
    var activeToasts = [];
    // Timer synchronizers of the active toasts, see syncAllTimers()
    var timerSyncs = [];
    // Hovering or focusing the stack expands it: every toast is paused meanwhile
    var stackHovered = false;
    var stackFocused = false;
    // Element focused before keyboard focus entered the stack, restored on dismiss
    var focusBeforeStack = null;

    function updateStack() {
        var total = activeToasts.length;
        for (var i = 0; i < total; i++) {
            var el = activeToasts[i];
            var indexFromFront = total - 1 - i; // 0 = frontmost / newest
            el.setAttribute('data-index', String(indexFromFront));
            el.style.zIndex = String(1000 + i);
        }
    }

    function isStackActive() {
        return stackHovered || stackFocused;
    }

    function syncAllTimers() {
        timerSyncs.forEach(function (sync) {
            sync();
        });
    }

    function bindContainerEvents(el) {
        el.addEventListener('mouseenter', function () {
            stackHovered = true;
            el.classList.add('toast-expanded');
            syncAllTimers();
        });
        el.addEventListener('mouseleave', function () {
            stackHovered = false;
            el.classList.remove('toast-expanded');
            syncAllTimers();
        });
        el.addEventListener('focusin', function (e) {
            if (
                !stackFocused &&
                e.relatedTarget &&
                !el.contains(e.relatedTarget)
            ) {
                focusBeforeStack = e.relatedTarget;
            }
            stackFocused = true;
            syncAllTimers();
        });
        el.addEventListener('focusout', function (e) {
            stackFocused = el.contains(e.relatedTarget);
            syncAllTimers();
        });
    }

    /**
     * The layout provides an empty #toast-container where flash messages used to be
     * rendered, so that toasts keep that position in the DOM (keyboard and reading order)
     * while being displayed as a fixed overlay. Pages without it get one appended to <body>.
     */
    function getOrCreateContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }
            // Toasts of a previous container are gone with it
            activeToasts = [];
            timerSyncs = [];
            stackHovered = false;
            stackFocused = false;
            focusBeforeStack = null;
            bindContainerEvents(container);
        }
        return container;
    }

    /**
     * Visually hidden live regions used for screen reader announcements.
     * Toast elements themselves carry no live role, to avoid nested live regions
     * and double announcements.
     */
    function getOrCreateAnnouncer(politeness) {
        var announcer = announcers[politeness];
        if (!announcer || !document.body.contains(announcer)) {
            var id = 'toast-announcer-' + politeness;
            announcer = document.getElementById(id);
            if (!announcer) {
                announcer = document.createElement('div');
                announcer.id = id;
                announcer.className = 'toast-sr-only';
                announcer.setAttribute('aria-live', politeness);
                announcer.setAttribute('aria-atomic', 'true');
                document.body.appendChild(announcer);
            }
            announcers[politeness] = announcer;
        }
        return announcer;
    }

    /**
     * Messages announced within the same delay are queued and announced together,
     * so that toasts shown in the same tick do not overwrite each other.
     */
    function announce(text, politeness) {
        var announcer = getOrCreateAnnouncer(politeness);
        if (announcer.toastPending) {
            announcer.toastPending.push(text);
            return;
        }
        announcer.toastPending = [text];
        // Clear first so that a repeated identical message is announced again
        announcer.textContent = '';
        setTimeout(function () {
            announcer.textContent = announcer.toastPending.join(' ');
            announcer.toastPending = null;
        }, ANNOUNCE_DELAY);
    }

    function getCloseLabel(options) {
        if (options.closeAriaLabel) {
            return options.closeAriaLabel;
        }
        if (typeof global.translate === 'function') {
            return global.translate('Fermer');
        }
        return 'Close';
    }

    function normalizeType(type) {
        if (!type) return 'info';
        var t = String(type)
            .toLowerCase()
            .replace(/^alert-/, '');
        if (t === 'danger' || t === 'error') return 'error';
        if (t === 'warning') return 'warning';
        if (t === 'success') return 'success';
        return 'info';
    }

    function createToastElement(messageHtml, type, options, duration) {
        var normalizedType = normalizeType(type);
        var toastEl = document.createElement('div');
        toastEl.className =
            'toast-item toast-' +
            (normalizedType === 'error' ? 'danger' : normalizedType);
        if (duration <= 0) {
            // Persistent toasts are never hidden by the collapsed stack
            toastEl.classList.add('toast-persistent');
        }

        // Icon
        var iconEl = document.createElement('div');
        iconEl.className = 'toast-icon';
        iconEl.innerHTML = ICONS[normalizedType] || ICONS.info;
        toastEl.appendChild(iconEl);

        // Content
        var contentEl = document.createElement('div');
        contentEl.className = 'toast-content';
        contentEl.innerHTML = messageHtml;
        toastEl.appendChild(contentEl);

        // Dismiss button
        if (options.dismissible !== false) {
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'toast-close-btn';
            closeBtn.setAttribute('aria-label', getCloseLabel(options));
            closeBtn.innerHTML = ICONS.close;
            toastEl.appendChild(closeBtn);
        }

        // Progress bar (enabled by default for auto-dismissing toasts)
        var showProgressBar = options.progressBar !== false && duration > 0;
        if (showProgressBar) {
            toastEl.style.setProperty('--toast-duration', duration + 'ms');
            var progressContainer = document.createElement('div');
            progressContainer.className = 'toast-progress';
            var progressBar = document.createElement('div');
            progressBar.className = 'toast-progress-bar';
            progressContainer.appendChild(progressBar);
            toastEl.appendChild(progressContainer);
        }

        return toastEl;
    }

    /**
     * Keyboard focus must not be lost when the focused toast is removed:
     * move it to the newest remaining toast, or back to where it came from.
     */
    function moveFocusOutOf() {
        var next = activeToasts.length
            ? activeToasts[activeToasts.length - 1].querySelector(
                  '.toast-close-btn'
              )
            : null;
        if (
            !next &&
            focusBeforeStack &&
            document.body.contains(focusBeforeStack)
        ) {
            next = focusBeforeStack;
        }
        if (next && typeof next.focus === 'function') {
            next.focus();
        } else {
            // Nowhere to go: release focus now rather than when the element is removed
            document.activeElement.blur();
        }
    }

    function show(message, type, options) {
        options = options || {};
        var normalizedType = normalizeType(type);
        var defaultDuration =
            normalizedType === 'error' || normalizedType === 'warning'
                ? 8000
                : 5000;
        var duration =
            typeof options.duration === 'number'
                ? options.duration
                : defaultDuration;
        if (options.autoclose === false) {
            duration = 0;
        }

        var parentContainer = getOrCreateContainer();
        var toastEl = createToastElement(
            message,
            normalizedType,
            options,
            duration
        );
        parentContainer.appendChild(toastEl);
        activeToasts.push(toastEl);
        updateStack();

        // Force reflow to trigger CSS enter transition
        void toastEl.offsetHeight;
        toastEl.classList.add('toast-visible');

        announce(
            toastEl.querySelector('.toast-content').textContent,
            normalizedType === 'error' ? 'assertive' : 'polite'
        );

        var timeoutId = null;
        var remainingTime = duration;
        var startTime = Date.now();

        function dismiss() {
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            var syncIdx = timerSyncs.indexOf(syncTimer);
            if (syncIdx !== -1) {
                timerSyncs.splice(syncIdx, 1);
            }
            var idx = activeToasts.indexOf(toastEl);
            if (idx !== -1) {
                activeToasts.splice(idx, 1);
            }
            updateStack();

            if (toastEl.contains(document.activeElement)) {
                moveFocusOutOf();
            }
            if (!activeToasts.length && container) {
                // No mouseleave fires when the last toast disappears under the pointer
                stackHovered = false;
                container.classList.remove('toast-expanded');
            }

            toastEl.classList.remove('toast-visible');
            toastEl.classList.add('toast-hiding');
            setTimeout(function () {
                if (toastEl.parentNode) {
                    toastEl.parentNode.removeChild(toastEl);
                }
            }, 250);
        }

        function startTimer() {
            if (duration <= 0 || timeoutId) {
                return;
            }
            toastEl.classList.remove('toast-paused');
            startTime = Date.now();
            timeoutId = setTimeout(dismiss, remainingTime);
        }

        function pauseTimer() {
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
                toastEl.classList.add('toast-paused');
                remainingTime -= Date.now() - startTime;
                if (remainingTime < 500) {
                    remainingTime = 500;
                }
            }
        }

        // Hook close button
        var closeBtn = toastEl.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dismiss();
            });
        }

        // Paused while the stack is hovered or focused; resumed only once neither applies
        function syncTimer() {
            if (isStackActive()) {
                pauseTimer();
            } else {
                startTimer();
            }
        }

        timerSyncs.push(syncTimer);
        syncTimer();

        return {
            element: toastEl,
            dismiss: dismiss,
        };
    }

    var toastAPI = {
        show: show,
        success: function (message, options) {
            return show(message, 'success', options);
        },
        error: function (message, options) {
            return show(message, 'error', options);
        },
        warning: function (message, options) {
            return show(message, 'warning', options);
        },
        info: function (message, options) {
            return show(message, 'info', options);
        },
        getContainer: getOrCreateContainer,
    };

    // Export globally
    global.toast = toastAPI;

    // CommonJS / Node support for Jest
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = toastAPI;
    }
})(typeof window !== 'undefined' ? window : this);
