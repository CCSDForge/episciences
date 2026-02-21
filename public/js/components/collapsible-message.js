/**
 * Collapsible Message Component
 * Makes long text content expandable/collapsible with accessibility support
 *
 * Features:
 * - Automatically detects if content exceeds threshold
 * - ARIA attributes for screen reader support
 * - Smooth CSS transitions
 * - Keyboard accessible
 */

(function () {
    'use strict';

    const MAX_HEIGHT = 200; // Maximum height in pixels before collapse
    const TRANSITION_DURATION = 300; // Animation duration in ms

    /**
     * Generate unique ID for message
     * @returns {string} Unique ID
     */
    function generateId() {
        return 'message-' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Check if element is already initialized
     * @param {Element} element - Element to check
     * @returns {boolean}
     */
    function isInitialized(element) {
        return element.hasAttribute('data-collapsible-initialized');
    }

    /**
     * Mark element as initialized
     * @param {Element} element - Element to mark
     */
    function markAsInitialized(element) {
        element.setAttribute('data-collapsible-initialized', 'true');
    }

    /**
     * Setup collapsible behavior for a message element
     * SECURITY FIX: Uses DOM manipulation instead of innerHTML concatenation to prevent XSS
     * @param {Element} message - Message element to make collapsible
     * @param {number} actualHeight - Actual content height
     */
    function setupCollapsible(message, actualHeight) {
        var messageId = generateId();

        // SECURITY FIX: Use DOM manipulation instead of innerHTML concatenation
        // This prevents XSS by avoiding HTML string concatenation with untrusted content

        // Create content wrapper
        var contentDiv = document.createElement('div');
        contentDiv.className = 'collapsible-content is-collapsed';
        contentDiv.id = messageId;
        contentDiv.setAttribute('data-full-height', String(actualHeight));

        // Move existing content into the wrapper (preserves existing DOM)
        while (message.firstChild) {
            contentDiv.appendChild(message.firstChild);
        }

        // Create toggle button
        var button = document.createElement('button');
        button.className = 'btn btn-default collapsible-toggle';
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', messageId);
        button.type = 'button';

        // Create "Show more" text span
        var textCollapsed = document.createElement('span');
        textCollapsed.className = 'toggle-text-collapsed';
        var iconDown = document.createElement('i');
        iconDown.className = 'fas fa-chevron-down';
        iconDown.setAttribute('aria-hidden', 'true');
        textCollapsed.appendChild(iconDown);
        textCollapsed.appendChild(
            document.createTextNode('\u00A0' + translate('Voir plus'))
        );

        // Create "Show less" text span
        var textExpanded = document.createElement('span');
        textExpanded.className = 'toggle-text-expanded';
        textExpanded.style.display = 'none';
        var iconUp = document.createElement('i');
        iconUp.className = 'fas fa-chevron-up';
        iconUp.setAttribute('aria-hidden', 'true');
        textExpanded.appendChild(iconUp);
        textExpanded.appendChild(
            document.createTextNode('\u00A0' + translate('Voir moins'))
        );

        // Assemble button
        button.appendChild(textCollapsed);
        button.appendChild(textExpanded);

        // Assemble final structure
        message.appendChild(contentDiv);
        message.appendChild(button);

        // Add event listener
        button.addEventListener('click', function (e) {
            e.preventDefault();
            handleToggle(messageId, button);
        });

        markAsInitialized(message);
    }

    /**
     * Handle toggle button click
     * @param {string} messageId - ID of content element
     * @param {Element} button - Toggle button element
     */
    function handleToggle(messageId, button) {
        var content = document.getElementById(messageId);
        var isExpanded = button.getAttribute('aria-expanded') === 'true';

        toggleCollapse(content, button, !isExpanded);
    }

    /**
     * Toggle collapse state with smooth animation
     * @param {Element} content - Content element
     * @param {Element} button - Toggle button
     * @param {boolean} expand - Whether to expand (true) or collapse (false)
     */
    function toggleCollapse(content, button, expand) {
        var textCollapsed = button.querySelector('.toggle-text-collapsed');
        var textExpanded = button.querySelector('.toggle-text-expanded');

        if (expand) {
            // Expand
            content.classList.remove('is-collapsed');
            content.classList.add('is-expanding');
            button.setAttribute('aria-expanded', 'true');

            // Show "Show less" text, hide "Show more" text
            textCollapsed.style.display = 'none';
            textExpanded.style.display = '';

            // After transition, remove expanding class
            setTimeout(function () {
                content.classList.remove('is-expanding');
                content.classList.add('is-expanded');
            }, TRANSITION_DURATION);
        } else {
            // Collapse
            content.classList.remove('is-expanded');
            content.classList.add('is-collapsing');
            button.setAttribute('aria-expanded', 'false');

            // Show "Show more" text, hide "Show less" text
            textExpanded.style.display = 'none';
            textCollapsed.style.display = '';

            // After transition, set collapsed state
            setTimeout(function () {
                content.classList.remove('is-collapsing');
                content.classList.add('is-collapsed');

                // Scroll button into view if needed
                var rect = button.getBoundingClientRect();
                if (rect.top < 0) {
                    button.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                    });
                }
            }, TRANSITION_DURATION);
        }
    }

    /**
     * Initialize collapsible message behavior
     */
    function initCollapsibleMessages() {
        var messages = document.querySelectorAll(
            '.timeline-item-message, .timeline-comment-message'
        );

        for (var i = 0; i < messages.length; i++) {
            var message = messages[i];

            // Skip if already initialized
            if (isInitialized(message)) {
                continue;
            }

            // Check if content is tall enough to require collapsing
            var actualHeight = message.scrollHeight;

            if (actualHeight > MAX_HEIGHT) {
                setupCollapsible(message, actualHeight);
            }
        }
    }

    /**
     * Initialize on DOM ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                initCollapsibleMessages
            );
        } else {
            // DOM is already loaded
            initCollapsibleMessages();
        }

        // Re-initialize when new content is added (e.g., after AJAX)
        document.addEventListener('contentLoaded', initCollapsibleMessages);
    }

    // Start initialization
    init();
})();
