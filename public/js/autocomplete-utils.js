/**
 * Modern autocomplete utility for user selection with improved performance and UX
 * Features: debouncing, loading states, keyboard navigation, error handling
 * Maintains XMLHttpRequest headers for server-side compatibility
 */
class ModernUserAutocomplete {
    constructor(options = {}) {
        this.config = {
            inputId: 'autocompletedUserSelection',
            selectedUserIdField: 'selectedUserId',
            selectButtonId: 'select_user',
            appendTo: null,
            url: '/user/findcasusers',
            minLength: 2,
            debounceDelay: 300,
            maxResults: 100,
            onSelectCallback: null,
            onErrorCallback: null,
            ...options,
        };

        this.cache = new Map();
        this.currentXhr = null;
        this.selectedIndex = -1;
        this.isOpen = false;
        this.lastValue = '';

        this.init();
    }

    init() {
        this.inputElement = document.getElementById(this.config.inputId);
        this.selectedUserIdElement = document.getElementById(
            this.config.selectedUserIdField
        );
        this.selectButton = document.getElementById(this.config.selectButtonId);

        if (!this.inputElement) {
            console.error(
                `Autocomplete input element with id "${this.config.inputId}" not found`
            );
            return;
        }

        this.createDropdown();
        this.bindEvents();
        this.setupStyles();
    }

    createDropdown() {
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'modern-autocomplete-dropdown';
        this.dropdown.style.cssText = `
            position: absolute;
            z-index: 1000;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            min-width: 100%;
        `;

        // Position dropdown relative to input
        this.inputElement.style.position = 'relative';
        this.inputElement.parentNode.style.position = 'relative';
        this.inputElement.parentNode.appendChild(this.dropdown);
    }

    setupStyles() {
        if (document.getElementById('modern-autocomplete-styles')) return;

        const style = document.createElement('style');
        style.id = 'modern-autocomplete-styles';
        style.textContent = `
            .modern-autocomplete-item {
                padding: 6px 10px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
                transition: background-color 0.15s ease;
                line-height: 1.3;
            }
            .modern-autocomplete-item:last-child {
                border-bottom: none;
            }
            .modern-autocomplete-item:hover,
            .modern-autocomplete-item.highlighted {
                background-color: #f5f5f5;
            }
            .modern-autocomplete-item.selected {
                background-color: #007bff;
                color: white;
            }
            .modern-autocomplete-name {
                font-weight: 500;
                color: #333;
                font-size: 0.9em;
            }
            .modern-autocomplete-item.selected .modern-autocomplete-name {
                color: white;
            }
            .modern-autocomplete-details {
                font-size: 0.75em;
                color: #888;
                margin-top: 1px;
            }
            .modern-autocomplete-item.selected .modern-autocomplete-details {
                color: #cce7ff;
            }
            .modern-autocomplete-loading {
                padding: 8px;
                text-align: center;
                color: #666;
                font-style: italic;
                font-size: 0.85em;
            }
            .modern-autocomplete-empty {
                padding: 8px;
                text-align: center;
                color: #999;
                font-size: 0.85em;
            }
            .modern-autocomplete-error {
                padding: 8px;
                text-align: center;
                color: #dc3545;
                background-color: #f8d7da;
                font-size: 0.85em;
            }
            .loading,
            input.loading {
                --spinner-svg: url("data:image/svg+xml,%3Csvg fill='hsl(228, 97%25, 42%25)' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M2,12A10.94,10.94,0,0,1,5,4.65c-.21-.19-.42-.36-.62-.55h0A11,11,0,0,0,12,23c.34,0,.67,0,1-.05C6,23,2,17.74,2,12Z'%3E%3CanimateTransform attributeName='transform' type='rotate' dur='0.6s' values='0 12 12;360 12 12' repeatCount='indefinite'/%3E%3C/path%3E%3C/svg%3E");
                background-image: var(--spinner-svg);
                background-repeat: no-repeat;
                background-position: right 8px center;
                background-size: 16px 16px;
                padding-right: 30px !important;
            }
        `;
        document.head.appendChild(style);
    }

    bindEvents() {
        this.debouncedSearch = this.debounce(
            this.search.bind(this),
            this.config.debounceDelay
        );

        this.inputElement.addEventListener(
            'input',
            this.handleInput.bind(this)
        );
        this.inputElement.addEventListener(
            'focus',
            this.handleFocus.bind(this)
        );
        this.inputElement.addEventListener('blur', this.handleBlur.bind(this));
        this.inputElement.addEventListener(
            'keydown',
            this.handleKeydown.bind(this)
        );

        // Close dropdown when clicking outside
        document.addEventListener('click', e => {
            if (
                !this.inputElement.contains(e.target) &&
                !this.dropdown.contains(e.target)
            ) {
                this.closeDropdown();
            }
        });
    }

    debounce(func, delay) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }

    handleInput(e) {
        const value = e.target.value.trim();

        if (value !== this.lastValue) {
            this.clearSelection();
            this.lastValue = value;

            if (value.length >= this.config.minLength) {
                this.debouncedSearch(value);
            } else {
                this.closeDropdown();
            }
        }
    }

    handleFocus() {
        const value = this.inputElement.value.trim();
        if (value.length >= this.config.minLength) {
            this.search(value);
        }
    }

    handleBlur() {
        // Delay to allow item selection
        setTimeout(() => this.closeDropdown(), 150);
    }

    handleKeydown(e) {
        if (!this.isOpen) return;

        const items = this.dropdown.querySelectorAll(
            '.modern-autocomplete-item:not(.modern-autocomplete-loading):not(.modern-autocomplete-empty):not(.modern-autocomplete-error)'
        );

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(
                    this.selectedIndex + 1,
                    items.length - 1
                );
                this.updateSelection(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelection(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    this.selectItem(items[this.selectedIndex]);
                }
                break;
            case 'Escape':
                this.closeDropdown();
                break;
        }
    }

    updateSelection(items) {
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });

        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    async search(term) {
        if (this.cache.has(term)) {
            this.displayResults(this.cache.get(term), term);
            return;
        }

        this.showLoading();

        // Cancel previous request
        if (this.currentXhr) {
            this.currentXhr.abort();
        }

        try {
            const results = await this.fetchUsers(term);
            this.cache.set(term, results);
            this.displayResults(results, term);
        } catch (error) {
            this.showError(error.message);
            if (this.config.onErrorCallback) {
                this.config.onErrorCallback(error);
            }
        }
    }

    fetchUsers(term) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            this.currentXhr = xhr;

            const url = `${this.config.url}?term=${encodeURIComponent(term)}`;

            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    this.inputElement.classList.remove('loading');
                    this.currentXhr = null;

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            resolve(
                                Array.isArray(data)
                                    ? data.slice(0, this.config.maxResults)
                                    : []
                            );
                        } catch (e) {
                            reject(new Error('Failed to parse response'));
                        }
                    } else if (xhr.status !== 0) {
                        // 0 means aborted
                        reject(new Error(`Request failed: ${xhr.status}`));
                    }
                }
            };

            xhr.onerror = () => {
                this.inputElement.classList.remove('loading');
                reject(new Error('Network error'));
            };

            this.inputElement.classList.add('loading');
            xhr.send();
        });
    }

    showLoading() {
        this.dropdown.innerHTML =
            '<div class="modern-autocomplete-loading">' +
            translate('Recherche...') +
            '</div>';
        this.openDropdown();
    }

    showError(message) {
        // Create DOM elements securely to prevent XSS
        const errorDiv = document.createElement('div');
        errorDiv.className = 'modern-autocomplete-error';
        errorDiv.textContent = 'Erreur: ' + message;
        this.dropdown.innerHTML = '';
        this.dropdown.appendChild(errorDiv);
        this.openDropdown();
    }

    displayResults(results, term) {
        if (!results || results.length === 0) {
            this.dropdown.innerHTML =
                '<div class="modern-autocomplete-empty">' +
                translate('Aucun résultat') +
                '</div>';
            this.openDropdown();
            return;
        }

        // Create DOM elements securely to prevent XSS
        this.dropdown.innerHTML = '';

        results.forEach(user => {
            const name =
                user.full_name ||
                `${user.firstname || ''} ${user.lastname || ''}`.trim();
            const email = user.email || user.EMAIL || '';
            const uid = user.id || user.UID || '';

            // Create item container
            const itemDiv = document.createElement('div');
            itemDiv.className = 'modern-autocomplete-item';
            itemDiv.dataset.id = uid;
            itemDiv.dataset.email = email;
            itemDiv.dataset.name = name;

            // Create name element with highlighted match
            const nameDiv = document.createElement('div');
            nameDiv.className = 'modern-autocomplete-name';
            this.highlightMatch(nameDiv, name, term);

            // Create details element
            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'modern-autocomplete-details';
            detailsDiv.textContent = `${email} (ID: ${uid})`;

            itemDiv.appendChild(nameDiv);
            itemDiv.appendChild(detailsDiv);
            this.dropdown.appendChild(itemDiv);
        });

        this.selectedIndex = -1;
        this.bindItemEvents();
        this.openDropdown();
    }

    highlightMatch(element, text, term) {
        // Create DOM elements securely to prevent XSS
        if (!term || !text) {
            element.textContent = text || '';
            return;
        }

        const regex = new RegExp(
            `(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`,
            'gi'
        );
        const parts = text.split(regex);

        parts.forEach((part, index) => {
            if (regex.test(part)) {
                const strong = document.createElement('strong');
                strong.textContent = part;
                element.appendChild(strong);
                regex.lastIndex = 0; // Reset regex for next iteration
            } else if (part) {
                element.appendChild(document.createTextNode(part));
            }
        });
    }

    bindItemEvents() {
        const items = this.dropdown.querySelectorAll(
            '.modern-autocomplete-item'
        );
        items.forEach(item => {
            item.addEventListener('click', () => this.selectItem(item));
            item.addEventListener('mouseenter', () => {
                items.forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                this.selectedIndex = Array.from(items).indexOf(item);
            });
        });
    }

    selectItem(item) {
        const id = item.dataset.id;
        const name = item.dataset.name;
        const email = item.dataset.email;

        this.inputElement.value = name;

        if (this.selectedUserIdElement) {
            this.selectedUserIdElement.value = id;
        }

        if (this.selectButton) {
            this.selectButton.removeAttribute('disabled');
        }

        this.closeDropdown();

        // Call custom callback if provided
        if (this.config.onSelectCallback) {
            this.config.onSelectCallback({
                id: id,
                name: name,
                email: email,
                full_name: name,
            });
        }
    }

    clearSelection() {
        if (this.selectedUserIdElement) {
            this.selectedUserIdElement.value = '0';
        }
        if (this.selectButton) {
            this.selectButton.setAttribute('disabled', 'disabled');
        }
    }

    openDropdown() {
        this.dropdown.style.display = 'block';
        this.isOpen = true;
        this.positionDropdown();
    }

    closeDropdown() {
        this.dropdown.style.display = 'none';
        this.isOpen = false;
        this.selectedIndex = -1;
        this.inputElement.classList.remove('loading');
    }

    positionDropdown() {
        const rect = this.inputElement.getBoundingClientRect();
        const dropdownRect = this.dropdown.getBoundingClientRect();

        this.dropdown.style.top = `${this.inputElement.offsetHeight}px`;
        this.dropdown.style.left = '0px';
        this.dropdown.style.width = `${this.inputElement.offsetWidth}px`;

        // Adjust if dropdown goes below viewport
        if (
            rect.bottom + dropdownRect.height > window.innerHeight &&
            rect.top > dropdownRect.height
        ) {
            this.dropdown.style.top = `-${dropdownRect.height}px`;
        }
    }

    // Public API methods
    clearCache() {
        this.cache.clear();
    }

    destroy() {
        if (this.currentXhr) {
            this.currentXhr.abort();
        }
        if (this.dropdown && this.dropdown.parentNode) {
            this.dropdown.parentNode.removeChild(this.dropdown);
        }
        this.inputElement.classList.remove('loading');
    }
}

// Backward compatibility function
function createUserAutocomplete(options = {}) {
    return new ModernUserAutocomplete(options);
}
