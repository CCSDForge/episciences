/**
 * Security utility: HTML sanitization to prevent XSS attacks
 * Requires: DOMPurify (loaded via CDN in layout.phtml)
 *
 * Usage:
 *   const clean = sanitizeHTML(dirtyHTML);
 *   element.innerHTML = clean;
 *
 * @see https://github.com/cure53/DOMPurify
 */

(function (window) {
    'use strict';

    /**
     * Sanitize HTML content to prevent XSS attacks
     * @param {string} dirty - Untrusted HTML string
     * @param {Object} config - Optional DOMPurify configuration
     * @returns {string} Sanitized HTML safe for innerHTML
     */
    window.sanitizeHTML = function (dirty, config) {
        if (typeof DOMPurify === 'undefined') {
            console.error('DOMPurify is not loaded! HTML sanitization failed.');
            console.error(
                'Falling back to textContent extraction (removes all HTML)'
            );

            // Fallback: extract text only (removes all HTML)
            const temp = document.createElement('div');
            temp.textContent = dirty;
            return temp.innerHTML;
        }

        const defaultConfig = {
            USE_PROFILES: { html: true },
            ALLOWED_TAGS: [
                'p',
                'br',
                'strong',
                'em',
                'u',
                'a',
                'ul',
                'ol',
                'li',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'div',
                'span',
                'img',
                'table',
                'thead',
                'tbody',
                'tr',
                'td',
                'th',
                'blockquote',
                'code',
                'pre',
                'hr',
                'i',
                'b',
                'small',
                'label',
                'input',
                'button',
                'form',
                'select',
                'option',
                'textarea',
            ],
            ALLOWED_ATTR: [
                'href',
                'src',
                'alt',
                'title',
                'class',
                'id',
                'style',
                'target',
                'rel',
                'data-*',
                'aria-*',
                'type',
                'name',
                'value',
                'placeholder',
                'disabled',
                'readonly',
                'checked',
                'selected',
                'for',
            ],
            ALLOW_DATA_ATTR: true,
        };

        const finalConfig = Object.assign({}, defaultConfig, config || {});
        return DOMPurify.sanitize(dirty, finalConfig);
    };

    /**
     * Sanitize HTML with strict configuration (removes most attributes)
     * Useful for user-generated content where minimal formatting is needed
     * @param {string} dirty - Untrusted HTML string
     * @returns {string} Strictly sanitized HTML
     */
    window.sanitizeHTMLStrict = function (dirty) {
        return window.sanitizeHTML(dirty, {
            ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'],
            ALLOWED_ATTR: ['href', 'class'],
        });
    };
})(window);
