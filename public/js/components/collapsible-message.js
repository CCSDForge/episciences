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

(function() {
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
     * @param {Element} message - Message element to make collapsible
     * @param {number} actualHeight - Actual content height
     */
    function setupCollapsible(message, actualHeight) {
        var messageId = generateId();
        var content = message.innerHTML;

        // Build HTML structure
        var html =
            '<div class="collapsible-content is-collapsed" id="' +
            messageId +
            '" data-full-height="' +
            actualHeight +
            '">' +
            content +
            '</div>' +
            '<button class="btn btn-default collapsible-toggle" ' +
            'aria-expanded="false" ' +
            'aria-controls="' +
            messageId +
            '" ' +
            'type="button">' +
            '<span class="toggle-text-collapsed">' +
            '<i class="fas fa-chevron-down" aria-hidden="true"></i>&nbsp;' +
            translate('Voir plus') +
            '</span>' +
            '<span class="toggle-text-expanded" style="display: none;">' +
            '<i class="fas fa-chevron-up" aria-hidden="true"></i>&nbsp;' +
            translate('Voir moins') +
            '</span>' +
            '</button>';

        message.innerHTML = html;

        // Add event listener to button
        var button = message.querySelector('.collapsible-toggle');
        button.addEventListener('click', function(e) {
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
            setTimeout(function() {
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
            setTimeout(function() {
                content.classList.remove('is-collapsing');
                content.classList.add('is-collapsed');

                // Scroll button into view if needed
                var rect = button.getBoundingClientRect();
                if (rect.top < 0) {
                    button.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, TRANSITION_DURATION);
        }
    }

    /**
     * Initialize collapsible message behavior
     */
    function initCollapsibleMessages() {
        var messages = document.querySelectorAll('.timeline-item-message, .timeline-comment-message');

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
            document.addEventListener('DOMContentLoaded', initCollapsibleMessages);
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
