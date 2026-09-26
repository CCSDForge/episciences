/**
 * Opens a user's profile card in a native <dialog> instead of navigating to /user/view.
 *
 * Progressive enhancement: triggers are plain links (`a[data-user-card]`, href = /user/view),
 * so a modified click (new tab/window), a missing dialog or a failed request falls back to
 * the regular profile page. The dialog markup and its translated labels are rendered by the
 * server (see user/profile_card_dialog.phtml); the card HTML comes from /user/card, already
 * escaped server-side.
 */
const ProfileCardDialog = {
    dialog: null,
    lastTrigger: null,

    init(root = document) {
        this.dialog = root.getElementById('user-card-dialog');
        if (!this.dialog || typeof this.dialog.showModal !== 'function') {
            return;
        }

        // Delegated: the user list is (re)rendered by DataTables
        root.addEventListener('click', event => this.onClick(event));

        this.dialog.addEventListener('click', event => {
            // a click on the backdrop targets the <dialog> itself
            if (event.target === this.dialog) {
                this.dialog.close();
            }
        });
        this.dialog.addEventListener('close', () => {
            if (this.lastTrigger) {
                this.lastTrigger.focus();
            }
        });
    },

    isModifiedClick(event) {
        return (
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        );
    },

    onClick(event) {
        const trigger = event.target.closest('a[data-user-card]');
        if (!trigger || this.isModifiedClick(event)) {
            return;
        }
        event.preventDefault();
        this.open(trigger);
    },

    async open(trigger) {
        this.lastTrigger = trigger;
        const body = this.dialog.querySelector('[data-user-card-body]');

        this.dialog.querySelector('[data-user-card-title]').textContent =
            trigger.dataset.userName || '';
        body.replaceChildren(
            this.dialog
                .querySelector('[data-user-card-loading]')
                .content.cloneNode(true)
        );
        this.dialog.setAttribute('aria-busy', 'true');
        this.dialog.showModal();

        try {
            const response = await fetch(
                '/user/card?userid=' +
                    encodeURIComponent(trigger.dataset.userCard),
                {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }
            );
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            body.innerHTML = await response.text();
        } catch (error) {
            this.dialog.close();
            window.location.assign(trigger.href);
            return;
        } finally {
            this.dialog.removeAttribute('aria-busy');
        }
    },
};

if (typeof document !== 'undefined' && typeof module === 'undefined') {
    document.addEventListener('DOMContentLoaded', () =>
        ProfileCardDialog.init()
    );
}

// CommonJS export for Jest testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProfileCardDialog;
}
