'use strict';

/**
 * HeaderManager — vanilla JS + SortableJS replacement for jQuery.
 *
 * Responsibilities:
 *  - Initialise SortableJS on the list of logos
 *  - Add new logos via /website/ajaxheader
 *  - Delete logos
 *  - Toggle edit forms (aria-expanded)
 *  - Announce operations to screen readers via an aria-live region
 */
class HeaderManager {
    /**
     * @param {HTMLUListElement} rootList  The root <ul class="menu-sortable">
     * @param {number} uniq                Initial counter for unique IDs
     */
    constructor(rootList, uniq) {
        this.rootList = rootList;
        this.uniq = uniq;
        this.liveRegion = document.getElementById('header-announcer');

        if (!this.rootList) {
            return;
        }

        this._initSortable();
        this._initEventListeners();

        // Initially toggle required status for all forms based on their visibility
        this.rootList.querySelectorAll('.menu-edit-form').forEach(form => {
            this.toggleRequired(form, !form.hidden);
        });
    }

    /**
     * Set up event delegation for all header actions.
     * @private
     */
    _initEventListeners() {
        // Toolbar actions
        document.querySelectorAll('[data-action="add-logo"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.addLogo();
            });
        });

        // Delegate actions within the list
        this.rootList.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;
            if (action === 'toggle-edit') {
                this.toggleEditForm(btn);
            } else if (action === 'delete-logo') {
                this.deleteLogo(btn.dataset.logoId);
            }
        });

        // Initial setup for existing forms
        this.rootList.querySelectorAll('.logo').forEach(li => {
            const form = li.querySelector('.menu-edit-form');
            if (form) {
                this.setDisplayElements(form);
                this._syncLogoItem(li);
            }
            
            // Rename input file for existing items (workaround for Zend_Form_Element_File belongsTo)
            const match = li.id.match(/^logo-(logo_\d+)$/);
            if (match) {
                const belongsTo = match[1];
                this.renameInputFile(li.id, belongsTo + "[img]");
            }
        });
    }

    /**
     * Synchronize logo item display (icon and title) with form values.
     * @private
     * @param {HTMLLIElement} li
     */
    _syncLogoItem(li) {
        const typeSelect = li.querySelector('select[elem="type"]');
        const itemNameSpan = li.querySelector('.menu-item-name');
        const iconSpan = li.querySelector('.menu-item-type-icon .glyphicon');
        const lang = document.documentElement.lang || 'fr';
        
        const updateDisplay = () => {
            if (!typeSelect) return;
            const type = typeSelect.value;
            // Update icon
            if (iconSpan) {
                iconSpan.className = `glyphicon glyphicon-${type === 'img' ? 'picture' : 'font'} active`;
            }
            
            // Update title
            if (type === 'img') {
                const imgTmp = li.querySelector('input[name$="[img_tmp]"]');
                const fileInput = li.querySelector('input[type="file"]');
                // Use temp filename or new selected filename
                if (fileInput && fileInput.files.length > 0) {
                    if (itemNameSpan) itemNameSpan.textContent = fileInput.files[0].name;
                } else if (imgTmp) {
                    if (itemNameSpan) itemNameSpan.textContent = imgTmp.value;
                }
            } else {
                const labelInput = li.querySelector(`input[name$="[text][${lang}]"]`);
                if (labelInput && itemNameSpan) {
                    itemNameSpan.textContent = labelInput.value;
                }
            }
        };

        if (typeSelect) {
            typeSelect.addEventListener('change', updateDisplay);
        }

        li.querySelectorAll('.header-label-input').forEach(input => {
            input.addEventListener('input', updateDisplay);
        });

        const fileInput = li.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', updateDisplay);
        }
        
        // Initial call
        updateDisplay();
    }

    /**
     * Set up conditional display for form elements.
     * Vanilla JS replacement for public/js/form.js logic.
     * 
     * @param {HTMLElement} container
     */
    setDisplayElements(container) {
        container.querySelectorAll('.elem-link').forEach(link => {
            this.displayElements(link);
            link.addEventListener('change', () => {
                this.displayElements(link);
            });
        });
    }

    /**
     * Show/hide elements based on the value of a controlling element.
     * 
     * @param {HTMLElement} parent
     */
    displayElements(parent) {
        const closestForm = parent.closest('.menu-edit-form') || parent.closest('form');
        if (!closestForm) return;

        // Try to get the control ID from 'elem' attribute, fallback to data-elem, name or id
        const elemId = parent.getAttribute('elem') || parent.getAttribute('data-elem') || parent.name?.split('[').pop().replace(']', '') || parent.id?.split('-').pop();
        if (!elemId) return;

        // Find all elements or containers linked to this control (using standard or data attributes)
        const targets = closestForm.querySelectorAll(`[elem-link="${elemId}"], [data-elem-link="${elemId}"]`);
        
        targets.forEach(el => {
            const elValue = el.getAttribute('elem-value') || el.getAttribute('data-elem-value');
            const matchesValue = elValue === parent.value;
            const shouldBeVisible = matchesValue;

            // Find the container to hide (form-group for Bootstrap, or DT/DD pair for standard Zend)
            let container = el.closest('.form-group') || (el.tagName === 'FIELDSET' ? el : null);
            
            if (!container) {
                // Handle standard Zend Framework dt/dd structure
                const dd = el.closest('dd');
                const dt = dd ? dd.previousElementSibling : null;
                if (dt && dt.tagName === 'DT') {
                    dt.hidden = !shouldBeVisible;
                    dd.hidden = !shouldBeVisible;
                } else if (dd) {
                    dd.hidden = !shouldBeVisible;
                } else {
                    el.hidden = !shouldBeVisible;
                }
            } else {
                container.hidden = !shouldBeVisible;
            }

            // Toggle required attribute to avoid blocking submission when hidden
            // Also handle all children inputs that might be required
            const toggleRequired = (element, visible) => {
                if (element.hasAttribute('required') || element.hasAttribute('data-was-required')) {
                    if (visible) {
                        if (element.hasAttribute('data-was-required')) {
                            element.required = true;
                            element.removeAttribute('data-was-required');
                        }
                    } else {
                        if (element.required) {
                            element.setAttribute('data-was-required', 'true');
                            element.required = false;
                        }
                    }
                }
            };

            toggleRequired(el, shouldBeVisible);
            el.querySelectorAll('input[required], input[data-was-required], select[required], select[data-was-required]').forEach(child => {
                toggleRequired(child, shouldBeVisible);
            });

            // Recursive call for nested conditional elements
            if (el.classList.contains('elem-link')) {
                this.displayElements(el);
            }
        });
    }

    /**
     * SECURITY: Replaces innerHTML with a safer alternative that also executes scripts.
     * @param {HTMLElement} container 
     * @param {string} html 
     */
    _safeSetInnerHTML(container, html) {
        container.innerHTML = '';
        const fragment = document.createRange().createContextualFragment(html);
        const scripts = fragment.querySelectorAll('script');
        container.appendChild(fragment);

        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // Initialize conditional display for new form
        this.setDisplayElements(container);
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    /**
     * POST helper — always sends the X-Requested-With header expected by ZF1 isXmlHttpRequest().
     *
     * @param {string} url
     * @param {URLSearchParams|string} body
     * @returns {Promise<Response>}
     */
    async post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body ? body.toString() : null,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res;
    }

    // -------------------------------------------------------------------------
    // SortableJS initialisation
    // -------------------------------------------------------------------------

    /**
     * Initialise a SortableJS instance on the root list.
     */
    _initSortable() {
        if (!window.Sortable) return;

        window.Sortable.create(this.rootList, {
            handle: '.menu-drag-handle',
            animation: 150,
            ghostClass: 'menu-drop-placeholder',
        });
    }

    // -------------------------------------------------------------------------
    // Public actions
    // -------------------------------------------------------------------------

    /**
     * Fetch a new logo form from the server and inject it into the list.
     */
    async addLogo() {
        try {
            const id = 'logo_' + this.uniq;
            const res = await fetch("/website/ajaxheader/id/" + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await res.text();

            if (!html.trim()) return;

            const template = document.getElementById('logo-template');
            if (!template) return;

            const clone = template.content.querySelector('li').cloneNode(true);
            const divForm = clone.querySelector('.menu-edit-form');
            const logoId = 'logo-' + this.uniq;

            clone.id = logoId;
            divForm.id = 'form-' + logoId;
            this._safeSetInnerHTML(divForm, html);

            const removeBtn = clone.querySelector('[data-action="delete-logo"]');
            if (removeBtn) removeBtn.dataset.logoId = logoId;

            const editBtn = clone.querySelector('[data-action="toggle-edit"]');
            if (editBtn) {
                editBtn.setAttribute('aria-controls', 'form-' + logoId);
                editBtn.setAttribute('aria-expanded', 'true');
            }

            // Set initial title (it's empty for new logo)
            const titleElement = clone.querySelector('.menu-item-name');
            if (titleElement) titleElement.textContent = '...';

            this.rootList.appendChild(clone);

            // Rename input file if needed (as done in original)
            this.renameInputFile(logoId, id + "[img]");

            // Synchronize logo display
            this._syncLogoItem(clone);

            // Open form by default
            divForm.hidden = false;

            // Scroll to the new item
            if (typeof clone.scrollIntoView === 'function') {
                clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            this.uniq++;

            this.announce(
                document.documentElement.lang === 'fr'
                    ? 'Nouveau logo ajouté'
                    : 'New logo added'
            );
        } catch (err) {
            console.error('HeaderManager: addLogo failed', err);
        }
    }

    /**
     * Renommage de l'élément input type file (pb Zend File)
     */
    renameInputFile(id, newName) {
        const li = document.getElementById(id);
        if (!li) return;
        const fileInput = li.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.name = newName;
        }
    }

    /**
     * Delete a logo from the DOM.
     * Note: Deletion is only persisted when the form is saved.
     *
     * @param {string} logoId
     */
    deleteLogo(logoId) {
        const confirmed = window.confirm(
            document.documentElement.lang === 'fr'
                ? 'Souhaitez-vous supprimer le logo ?'
                : 'Do you want to delete this logo?'
        );
        if (!confirmed) return;

        const li = document.getElementById(logoId);
        if (li) li.remove();

        this.announce(
            document.documentElement.lang === 'fr'
                ? 'Logo supprimé'
                : 'Logo deleted'
        );
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
                // If it's a child of a currently hidden container (.form-group or similar), 
                // don't restore required yet — let displayElements handle it.
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
     * Toggle the edit form for a logo and update aria-expanded on the trigger button.
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

        // When becoming visible, re-run display logic
        if (!isExpanded) {
            // Re-find all controlling elements in the form and trigger their logic
            form.querySelectorAll('.elem-link').forEach(link => {
                this.displayElements(link);
            });
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
    module.exports = { HeaderManager };
}
