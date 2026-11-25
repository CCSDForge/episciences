/**
 * @jest-environment jsdom
 */

const fs = require('fs');
const path = require('path');
const toast = require('../../assets/toast');

describe('Episciences Modern Toast Component', () => {
    beforeEach(() => {
        jest.useFakeTimers();
        document.body.innerHTML = '';
        // Reset container reference
        const existing = document.getElementById('toast-container');
        if (existing) {
            existing.remove();
        }
    });

    afterEach(() => {
        jest.clearAllTimers();
        jest.useRealTimers();
    });

    test('creates #toast-container without nesting live regions', () => {
        toast.info('Hello world');
        const container = document.getElementById('toast-container');
        expect(container).not.toBeNull();
        expect(container.hasAttribute('aria-live')).toBe(false);
        expect(container.querySelector('[role], [aria-live]')).toBeNull();
    });

    test('announces info toasts politely after a delay', () => {
        toast.info('Hello world');
        const announcer = document.getElementById('toast-announcer-polite');
        expect(announcer.getAttribute('aria-live')).toBe('polite');
        expect(announcer.textContent).toBe('');

        jest.advanceTimersByTime(100);
        expect(announcer.textContent).toBe('Hello world');
    });

    test('announces error toasts assertively', () => {
        toast.error('Erreur de validation');
        jest.advanceTimersByTime(100);
        const announcer = document.getElementById('toast-announcer-assertive');
        expect(announcer.getAttribute('aria-live')).toBe('assertive');
        expect(announcer.textContent).toBe('Erreur de validation');
    });

    test('renders a success toast with icon', () => {
        toast.success('Opération réussie');
        const container = document.getElementById('toast-container');
        const toastEl = container.querySelector('.toast-item.toast-success');

        expect(toastEl).not.toBeNull();
        expect(toastEl.textContent).toContain('Opération réussie');
        expect(toastEl.querySelector('.toast-icon svg')).not.toBeNull();
    });

    test('renders an error toast with danger styling', () => {
        toast.error('Erreur de validation');
        const container = document.getElementById('toast-container');
        const toastEl = container.querySelector('.toast-item.toast-danger');

        expect(toastEl).not.toBeNull();
        expect(toastEl.textContent).toContain('Erreur de validation');
    });

    test('auto-dismisses toast after default duration', () => {
        toast.info('Message temporaire', { duration: 3000 });
        const container = document.getElementById('toast-container');
        expect(container.querySelectorAll('.toast-item').length).toBe(1);

        // Advance timer to trigger dismiss animation
        jest.advanceTimersByTime(3000);
        const toastEl = container.querySelector('.toast-item');
        expect(toastEl.classList.contains('toast-hiding')).toBe(true);

        // Advance 250ms for removal transition
        jest.advanceTimersByTime(250);
        expect(container.querySelectorAll('.toast-item').length).toBe(0);
    });

    test('pauses dismissal on mouseenter and resumes on mouseleave', () => {
        toast.info('Message avec pause', { duration: 2000 });
        const container = document.getElementById('toast-container');
        const toastEl = container.querySelector('.toast-item');

        // Advance 1000ms
        jest.advanceTimersByTime(1000);

        // Hover over the stack
        container.dispatchEvent(new Event('mouseenter'));
        expect(toastEl.classList.contains('toast-paused')).toBe(true);

        // Advance past original expiration
        jest.advanceTimersByTime(3000);
        expect(container.querySelectorAll('.toast-item').length).toBe(1);
        expect(toastEl.classList.contains('toast-hiding')).toBe(false);

        // Leave hover
        container.dispatchEvent(new Event('mouseleave'));
        expect(toastEl.classList.contains('toast-paused')).toBe(false);

        // Advance remaining time (1000ms) + 250ms transition
        jest.advanceTimersByTime(1000);
        expect(toastEl.classList.contains('toast-hiding')).toBe(true);
        jest.advanceTimersByTime(250);
        expect(container.querySelectorAll('.toast-item').length).toBe(0);
    });

    test('renders a progress bar by default on auto-dismissing success toast', () => {
        toast.success('Succès avec progression');
        const container = document.getElementById('toast-container');
        const progressBar = container.querySelector('.toast-progress-bar');
        expect(progressBar).not.toBeNull();
        const toastEl = container.querySelector('.toast-item');
        expect(toastEl.style.getPropertyValue('--toast-duration')).toBe(
            '5000ms'
        );
    });

    test('closes immediately when clicking the close button', () => {
        toast.info('Dismissible test');
        const container = document.getElementById('toast-container');
        const closeBtn = container.querySelector('.toast-close-btn');
        expect(closeBtn).not.toBeNull();

        closeBtn.click();
        const toastEl = container.querySelector('.toast-item');
        expect(toastEl.classList.contains('toast-hiding')).toBe(true);

        jest.advanceTimersByTime(250);
        expect(container.querySelectorAll('.toast-item').length).toBe(0);
    });

    test('does not auto-dismiss when autoclose is false', () => {
        toast.show('Permanent message', 'warning', { autoclose: false });
        const container = document.getElementById('toast-container');
        expect(container.querySelectorAll('.toast-item').length).toBe(1);

        jest.advanceTimersByTime(60000);
        expect(container.querySelectorAll('.toast-item').length).toBe(1);
    });

    test('stacks multiple toasts with Sonner data-index attributes and expands on container hover', () => {
        toast.info('Premier toast');
        toast.success('Deuxième toast');
        const container = document.getElementById('toast-container');
        const items = container.querySelectorAll('.toast-item');
        expect(items.length).toBe(2);

        // The first (older) toast has data-index="1", the second (newer) one data-index="0"
        expect(items[0].getAttribute('data-index')).toBe('1');
        expect(items[1].getAttribute('data-index')).toBe('0');

        // Hover container -> adds toast-expanded
        container.dispatchEvent(new Event('mouseenter'));
        expect(container.classList.contains('toast-expanded')).toBe(true);

        container.dispatchEvent(new Event('mouseleave'));
        expect(container.classList.contains('toast-expanded')).toBe(false);
    });

    test('does not dismiss a toast that is still focused after mixed hover and focus events', () => {
        toast.info('Mixed interactions', { duration: 2000 });
        const container = document.getElementById('toast-container');
        const toastEl = document.querySelector('.toast-item');
        const closeBtn = toastEl.querySelector('.toast-close-btn');

        container.dispatchEvent(new Event('mouseenter'));
        closeBtn.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        container.dispatchEvent(new Event('mouseleave'));
        // Still focused: the timer must stay paused
        jest.advanceTimersByTime(5000);
        expect(toastEl.classList.contains('toast-hiding')).toBe(false);

        closeBtn.dispatchEvent(
            new FocusEvent('focusout', { bubbles: true, relatedTarget: null })
        );
        container.dispatchEvent(new Event('mouseenter'));
        // Hovered again: no leftover timer may fire
        jest.advanceTimersByTime(5000);
        expect(toastEl.classList.contains('toast-hiding')).toBe(false);

        container.dispatchEvent(new Event('mouseleave'));
        jest.advanceTimersByTime(2000);
        expect(toastEl.classList.contains('toast-hiding')).toBe(true);
    });

    test('marks non auto-dismissing toasts as persistent', () => {
        toast.error('Persistent error', { autoclose: false });
        toast.info('Transient info');
        const items = document.querySelectorAll('.toast-item');
        expect(items[0].classList.contains('toast-persistent')).toBe(true);
        expect(items[1].classList.contains('toast-persistent')).toBe(false);
    });

    test('translates the close button label when translate() is available', () => {
        window.translate = jest.fn(() => 'Close');
        try {
            toast.info('Translated label');
            const closeBtn = document.querySelector('.toast-close-btn');
            expect(window.translate).toHaveBeenCalledWith('Fermer');
            expect(closeBtn.getAttribute('aria-label')).toBe('Close');
        } finally {
            delete window.translate;
        }
    });

    test('announces toasts shown in the same tick together', () => {
        toast.success('Premier message');
        toast.info('Second message');
        jest.advanceTimersByTime(100);
        const announcer = document.getElementById('toast-announcer-polite');
        expect(announcer.textContent).toBe('Premier message Second message');

        // A later toast starts a new announcement
        toast.info('Troisième message');
        expect(announcer.textContent).toBe('');
        jest.advanceTimersByTime(100);
        expect(announcer.textContent).toBe('Troisième message');
    });

    test('uses the container provided by the page and keeps its DOM position', () => {
        document.body.innerHTML =
            '<a id="before" href="#">before</a><div id="toast-container"></div><a href="#">after</a>';
        const provided = document.getElementById('toast-container');

        toast.info('In place', { duration: 2000 });

        expect(provided.querySelector('.toast-item')).not.toBeNull();
        expect(document.querySelectorAll('#toast-container').length).toBe(1);
        expect(document.body.firstElementChild.nextElementSibling).toBe(
            provided
        );

        // Hover handling is bound on a provided container too
        provided.dispatchEvent(new Event('mouseenter'));
        expect(provided.classList.contains('toast-expanded')).toBe(true);
        jest.advanceTimersByTime(5000);
        expect(provided.querySelector('.toast-hiding')).toBeNull();
    });

    test('pauses every toast while the stack is hovered', () => {
        toast.info('Ancien', { duration: 2000 });
        toast.info('Récent', { duration: 4000 });
        const container = document.getElementById('toast-container');
        const items = container.querySelectorAll('.toast-item');

        container.dispatchEvent(new Event('mouseenter'));
        expect(items[0].classList.contains('toast-paused')).toBe(true);
        expect(items[1].classList.contains('toast-paused')).toBe(true);
        jest.advanceTimersByTime(10000);
        expect(container.querySelector('.toast-hiding')).toBeNull();

        container.dispatchEvent(new Event('mouseleave'));
        jest.advanceTimersByTime(2000);
        expect(items[0].classList.contains('toast-hiding')).toBe(true);
        expect(items[1].classList.contains('toast-hiding')).toBe(false);
    });

    test('does not leave the stack paused when the last hovered toast is dismissed', () => {
        toast.info('Survolé');
        const container = document.getElementById('toast-container');
        container.dispatchEvent(new Event('mouseenter'));
        container.querySelector('.toast-close-btn').click();
        expect(container.classList.contains('toast-expanded')).toBe(false);

        toast.info('Suivant', { duration: 2000 });
        jest.advanceTimersByTime(2000);
        expect(container.querySelector('.toast-hiding')).not.toBeNull();
    });

    test('moves focus to the remaining toast when the focused one is closed', () => {
        toast.error('Première erreur', { autoclose: false });
        toast.error('Seconde erreur', { autoclose: false });
        const buttons = document.querySelectorAll('.toast-close-btn');

        buttons[1].focus();
        buttons[1].click();

        expect(document.activeElement).toBe(buttons[0]);
    });

    test('restores focus to the element focused before the stack when the last toast is closed', () => {
        document.body.innerHTML =
            '<button id="origin" type="button">origin</button>';
        const origin = document.getElementById('origin');
        toast.error('Erreur', { autoclose: false });
        const closeBtn = document.querySelector('.toast-close-btn');

        origin.focus();
        closeBtn.focus();
        closeBtn.click();

        expect(document.activeElement).toBe(origin);
    });

    describe('message()', () => {
        const functionsJs = fs.readFileSync(
            path.join(__dirname, '../../public/js/functions.js'),
            'utf8'
        );
        const htmlEntitiesSrc = functionsJs.match(
            /function htmlEntities\(str\) \{[\s\S]*?\n\}/
        )[0];
        const messageSrc = functionsJs.match(
            /function message\(text, type\) \{[\s\S]*?\n\}/
        )[0];
        const message = new Function(
            htmlEntitiesSrc + messageSrc + 'return message;'
        )();

        test('escapes its text before handing it to the toast', () => {
            message('<img src=x onerror="alert(1)">', 'alert-danger');

            const content = document.querySelector('.toast-content');
            expect(content.querySelector('img')).toBeNull();
            expect(content.textContent).toBe('<img src=x onerror="alert(1)">');
        });

        test('falls back to a native alert when the toast bundle is not loaded', () => {
            const loadedToast = window.toast;
            const alertSpy = jest
                .spyOn(window, 'alert')
                .mockImplementation(() => {});
            delete window.toast;
            try {
                message('Mise à jour impossible', 'alert-danger');
                expect(alertSpy).toHaveBeenCalledWith('Mise à jour impossible');
            } finally {
                window.toast = loadedToast;
                alertSpy.mockRestore();
            }
        });
    });
});
