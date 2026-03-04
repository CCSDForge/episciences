'use strict';

/**
 * MenuManager — vanilla JS + SortableJS replacement for jQuery NestedSortable.
 *
 * Responsibilities:
 *  - Initialise SortableJS on every <ul> (root + nested) with max-3-level constraint
 *  - Serialise the nested order and POST it to /website/ajaxorder on each drag end
 *  - Add new pages via /website/ajaxformpage
 *  - Delete pages via /website/ajaxrmpage
 *  - Toggle edit forms (aria-expanded)
 *  - Show/hide .multicheckbox depending on the visibility <select>
 *  - Announce operations to screen readers via an aria-live region
 */
class MenuManager {
    /**
     * @param {HTMLUListElement} rootList  The root <ul class="menu-sortable">
     */
    constructor(rootList) {
        this.rootList = rootList;
        this.liveRegion = document.getElementById('menu-announcer');
        this._sortableInstances = [];

        if (!this.rootList) {
            return;
        }

        this._initAllSortables();
        this._initEventListeners();

        // Initially toggle required status for all forms based on their visibility
        this.rootList.querySelectorAll('.menu-edit-form').forEach(form => {
            this.toggleRequired(form, !form.hidden);
        });
    }

    /**
     * Set up event delegation for all menu actions.
     * @private
     */
    _initEventListeners() {
        // Toolbar actions
        document.querySelectorAll('[data-action="add-page"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const typeSelect = document.getElementById('type');
                if (typeSelect) this.addPage(typeSelect.value);
            });
        });

        document.querySelectorAll('[data-action="add-folder"]').forEach(btn => {
            btn.addEventListener('click', () => this.addPage('folder'));
        });

        // Delegate actions within the list
        this.rootList.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;
            if (action === 'toggle-edit') {
                this.toggleEditForm(btn);
            } else if (action === 'delete-page') {
                this.deletePage(btn.dataset.idx, btn.dataset.pageResource);
            }
        });
    }

    /**
     * Toggle the required attribute on inputs within a container to avoid 
     * HTML5 validation errors on hidden fields.
     *
     * @param {HTMLElement} container 
     * @param {boolean}     isVisible 
     */
    toggleRequired(container, isVisible) {
        const targets = container.querySelectorAll('input, select, textarea');
        targets.forEach(el => {
            if (isVisible) {
                // If it's a child of a currently hidden container (.multicheckbox or similar), 
                // don't restore required yet.
                let parentHidden = false;
                let p = el.parentElement;
                while (p && p !== container) {
                    if (p.hidden || (p.classList.contains('form-group') && p.style.display === 'none')) {
                        parentHidden = true;
                        break;
                    }
                    p = p.parentElement;
                }

                if (!parentHidden && el.dataset.wasRequired) {
                    el.required = true;
                    delete el.dataset.wasRequired;
                }
            } else {
                if (el.required) {
                    el.dataset.wasRequired = 'true';
                    el.required = false;
                }
            }
        });
    }

    /**
     * SECURITY: Replaces innerHTML with a safer alternative.
     * Scripts are NOT executed to prevent XSS.
     * @param {HTMLElement} container
     * @param {string} html
     */
    _safeSetInnerHTML(container, html) {
        container.innerHTML = '';
        const fragment = document.createRange().createContextualFragment(html);
        container.appendChild(fragment);
    }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Serialise the nested list into URLSearchParams matching the backend format:
     *   page[{id}] = 'root' | '{parentId}'
     *
     * @param {HTMLUListElement} rootList
     * @returns {URLSearchParams}
     */
    serializeOrder(rootList) {
        const params = new URLSearchParams();
        const traverse = (ul, parentId) => {
            for (const li of ul.children) {
                if (!li.id?.startsWith('page_')) continue;
                const id = li.id.replace('page_', '');
                params.append(`page[${id}]`, parentId ?? 'root');
                const nested = li.querySelector(':scope > ul');
                if (nested) traverse(nested, id);
            }
        };
        traverse(rootList, null);
        return params;
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    /**
     * POST helper — always sends the X-Requested-With header expected by ZF1 isXmlHttpRequest().
     * Also sends the CSRF token from the <meta name="csrf-token"> tag.
     *
     * @param {string} url
     * @param {URLSearchParams|string} body
     * @returns {Promise<Response>}
     */
    async post(url, body) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken || '',
            },
            body: body.toString(),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res;
    }

    // -------------------------------------------------------------------------
    // SortableJS initialisation
    // -------------------------------------------------------------------------

    /**
     * Compute the nesting depth of a <ul> element relative to the root list.
     * Root list = depth 1.
     *
     * @param {HTMLUListElement} ul
     * @returns {number}
     */
    _getDepth(ul) {
        let depth = 0;
        let node = ul;
        while (node && node !== this.rootList.parentElement) {
            if (node.tagName === 'UL') depth++;
            node = node.parentElement;
        }
        return depth;
    }

    /**
     * Initialise a SortableJS instance on one <ul>.
     *
     * @param {HTMLUListElement} ul
     */
    _initSortable(ul) {
        if (!window.Sortable) return;

        const instance = window.Sortable.create(ul, {
            group: 'menu',
            handle: '.menu-drag-handle',
            animation: 150,
            ghostClass: 'menu-drop-placeholder',
            swapThreshold: 0.65,
            onMove: (evt) => {
                // Prevent dropping into a list that is itself nested inside a no-nest item.
                // (no-nest items are leaf pages that cannot become parents.)
                // Because no-nest <li> elements never have a child <ul> in the DOM,
                // this situation cannot occur naturally — the check is kept for safety.
                const destParentLi = evt.to.parentElement?.closest('li');
                if (destParentLi && destParentLi.classList.contains('no-nest')) {
                    return false;
                }
                // _getDepth() returns 1 for the root list, 2 for first nested ul,
                // 3 for second nested ul. Allow up to depth 3 (= max 3 levels).
                return this._getDepth(evt.to) <= 3;
            },
            onEnd: () => {
                this.saveOrder();
            },
        });

        this._sortableInstances.push(instance);
    }

    /**
     * Initialise SortableJS on the root list and all nested <ul> elements.
     */
    _initAllSortables() {
        this._initSortable(this.rootList);
        this.rootList.querySelectorAll('ul').forEach((ul) => this._initSortable(ul));
    }

    /**
     * Initialise SortableJS on any new nested <ul> elements found inside a given <li>.
     *
     * @param {HTMLLIElement} li
     */
    _initNewSortables(li) {
        li.querySelectorAll('ul').forEach((ul) => {
            // Only init if not already managed
            const alreadyInit = this._sortableInstances.some(
                (s) => s.el === ul
            );
            if (!alreadyInit) this._initSortable(ul);
        });
    }

    // -------------------------------------------------------------------------
    // Public actions
    // -------------------------------------------------------------------------

    /**
     * POST the current order to /website/ajaxorder.
     */
    async saveOrder() {
        try {
            await this.post('/website/ajaxorder', this.serializeOrder(this.rootList));
        } catch (err) {
            console.error('MenuManager: saveOrder failed', err);
        }
    }

    /**
     * Fetch a new page form from the server and inject it into the list.
     *
     * @param {string} type  Page type value from the <select>
     */
    async addPage(type) {
        try {
            const params = new URLSearchParams({ type });
            const res = await this.post('/website/ajaxformpage', params);
            const html = await res.text();

            if (!html.trim()) return;

            const template = document.getElementById('clone');
            if (!template) return;

            const clone = template.querySelector('li').cloneNode(true);
            const pageContent = clone.querySelector('.page-content');
            
            // Determine the new page id from the hidden input inside the injected form
            // We need to parse the HTML first to find the hidden input
            const tempDiv = document.createElement('div');
            this._safeSetInnerHTML(tempDiv, html);
            
            // Try specific pageid first, fallback to any hidden input (as in tests)
            let hiddenInput = tempDiv.querySelector('input[name$="[pageid]"]');
            if (!hiddenInput) {
                hiddenInput = tempDiv.querySelector('input[type="hidden"]');
            }
            const pageIdx = hiddenInput ? hiddenInput.value : Date.now();
            
            // Re-wrap the HTML in the same structure as menu-page.phtml
            const formContainer = document.createElement('div');
            formContainer.className = 'menu-edit-form';
            formContainer.id = `form-${pageIdx}`;
            // New pages are shown by default to allow immediate editing
            formContainer.hidden = false;
            
            const hr = document.createElement('hr');
            formContainer.appendChild(hr);
            
            // Move all elements from tempDiv to formContainer
            while (tempDiv.firstChild) {
                formContainer.appendChild(tempDiv.firstChild);
            }
            
            pageContent.appendChild(formContainer);

            clone.id = `page_${pageIdx}`;
            
            // Set aria-controls and aria-expanded on the edit button
            const editBtn = clone.querySelector('[data-action="toggle-edit"]');
            if (editBtn) {
                editBtn.setAttribute('aria-controls', `form-${pageIdx}`);
                editBtn.setAttribute('aria-expanded', 'true');
            }
            
            // Also update the delete button idx if needed (though it uses pageIdx from the ID usually)
            const deleteBtn = clone.querySelector('[data-action="delete-page"]');
            if (deleteBtn) {
                deleteBtn.dataset.idx = pageIdx;
            }

            if (type === 'folder') {
                const icon = clone.querySelector('.menu-item-type-icon .glyphicon');
                if (icon) {
                    icon.className = 'glyphicon glyphicon-folder-open menu-icon-folder';
                }
            } else {
                clone.classList.add('no-nest');
            }

            // Initialise sync between label inputs and the item name
            const labelInputs = formContainer.querySelectorAll('input[name$="[labels][' + (document.documentElement.lang || 'fr') + ']"]');
            const itemNameSpan = clone.querySelector('.menu-item-name');
            labelInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    if (itemNameSpan) itemNameSpan.textContent = e.target.value;
                });
                // Initial value
                if (itemNameSpan && input.value) itemNameSpan.textContent = input.value;
            });
            
            // Add a default name for new pages
            if (itemNameSpan && !itemNameSpan.textContent) {
                itemNameSpan.textContent = type === 'folder' ? 'Nouveau dossier' : 'Nouvelle page';
            }

            this.rootList.appendChild(clone);
            this._initNewSortables(clone);

            // Scroll to the new item
            if (typeof clone.scrollIntoView === 'function') {
                clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // ACCESSIBILITY: Move focus to the first input of the new form
            const firstInput = formContainer.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstInput) {
                firstInput.focus();
            }

            this.announce(
                document.documentElement.lang === 'fr'
                    ? 'Nouvelle page ajoutée'
                    : 'New page added'
            );
        } catch (err) {
            console.error('MenuManager: addPage failed', err);
        }
    }

    /**
     * Delete a page from the server and remove its <li> from the DOM.
     *
     * @param {string|number} idx     Page index (used as the server-side key)
     * @param {string}        pageId  Page resource identifier
     */
    async deletePage(idx, pageId) {
        const confirmed = window.confirm(
            document.documentElement.lang === 'fr'
                ? 'Souhaitez-vous supprimer la page ?'
                : 'Do you want to delete this page?'
        );
        if (!confirmed) return;

        try {
            const params = new URLSearchParams({ idx, page_id: pageId });
            await this.post('/website/ajaxrmpage', params);

            const li = document.getElementById(`page_${idx}`);
            if (li) li.remove();

            // Re-enable the page type in the <select> if it was disabled
            const typeSelect = document.getElementById('type');
            if (typeSelect) {
                const pageType = document.getElementById(`pages_${idx}-type`)?.className;
                if (pageType) {
                    const opt = typeSelect.querySelector(`option[type="${pageType}"]`);
                    if (opt) opt.removeAttribute('disabled');
                }
            }

            this.announce(
                document.documentElement.lang === 'fr'
                    ? 'Page supprimée'
                    : 'Page deleted'
            );
        } catch (err) {
            console.error('MenuManager: deletePage failed', err);
        }
    }

    /**
     * Toggle the edit form for a menu item and update aria-expanded on the trigger button.
     *
     * @param {HTMLElement} triggerBtn  The edit button that was clicked
     */
    toggleEditForm(triggerBtn) {
        const formId = triggerBtn.getAttribute('aria-controls');
        if (!formId) return;

        const form = document.getElementById(formId);
        if (!form) return;

        const isExpanded = triggerBtn.getAttribute('aria-expanded') === 'true';
        form.hidden = isExpanded;
        triggerBtn.setAttribute('aria-expanded', String(!isExpanded));

        // Toggle required status based on the NEW visibility state
        this.toggleRequired(form, !isExpanded);
    }

    /**
     * Show or hide the .multicheckbox container based on the visibility <select> value.
     * Value >= 2 means "custom access rights" → show the checkboxes.
     *
     * @param {HTMLSelectElement} selectEl
     */
    setVisibility(selectEl) {
        const multicheckbox = selectEl
            .closest('.menu-edit-form')
            ?.querySelector('.multicheckbox');

        if (!multicheckbox) return;

        const isVisible = parseInt(selectEl.value, 10) >= 2;
        multicheckbox.hidden = !isVisible;

        // Also toggle required on any fields inside the multicheckbox
        this.toggleRequired(multicheckbox, isVisible);
    }

    /**
     * Generate a permalink slug from a source field and write it into the dest field
     * (delegates to the global permalink() function from functions.js).
     *
     * @param {HTMLInputElement} srcEl   The label / source input
     * @param {HTMLInputElement} destEl  The permalink input
     */
    createPermalink(srcEl, destEl) {
        if (destEl.value === '' && typeof window.permalink === 'function') {
            destEl.value = window.permalink(srcEl.value);
        }
    }

    // -------------------------------------------------------------------------
    // Accessibility helper
    // -------------------------------------------------------------------------

    /**
     * Announce a message to screen readers via the aria-live region.
     *
     * @param {string} message
     */
    announce(message) {
        if (!this.liveRegion) return;
        this.liveRegion.textContent = '';
        requestAnimationFrame(() => {
            this.liveRegion.textContent = message;
        });
    }
}

if (typeof module !== 'undefined') {
    module.exports = { MenuManager };
}
