/**
 * Test suite for collapsible-message component
 * Tests the expandable/collapsible behavior for long messages
 */

const fs = require('fs');
const path = require('path');

// Fix for JSDOM TextEncoder requirement
const { TextEncoder, TextDecoder } = require('util');
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

// Setup JSDOM environment
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.document = dom.window.document;
global.window = dom.window;

// Mock translate function
global.translate = jest.fn(key => {
    const translations = {
        'Voir plus': 'Show more',
        'Voir moins': 'Show less',
    };
    return translations[key] || key;
});

// Mock requestAnimationFrame and timers
global.requestAnimationFrame = jest.fn(cb => setTimeout(cb, 0));

describe('Collapsible Message Component', function () {
    let collapsibleMessageJs;

    beforeAll(function () {
        // Load the collapsible-message.js file
        collapsibleMessageJs = fs.readFileSync(
            path.join(
                __dirname,
                '../../public/js/components/collapsible-message.js'
            ),
            'utf8'
        );
    });

    beforeEach(function () {
        // Clear the document
        document.body.innerHTML = '';

        // Clear all timers
        jest.clearAllTimers();
        jest.clearAllMocks();
    });

    describe('Initialization', function () {
        it('should not initialize short messages', function () {
            // Create a short message
            document.body.innerHTML = `
                <div class="timeline-item-message" style="height: 100px;">
                    <p>This is a short message.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 100,
                writable: true,
            });

            // Execute the script
            eval(collapsibleMessageJs);

            // Manually trigger initialization
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should not have collapsible structure
            expect(message.querySelector('.collapsible-toggle')).toBeNull();
            expect(message.hasAttribute('data-collapsible-initialized')).toBe(
                false
            );
        });

        it('should initialize long messages with collapsible structure', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>This is a very long message that exceeds the maximum height threshold.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should have collapsible structure
            expect(
                message.querySelector('.collapsible-content')
            ).not.toBeNull();
            expect(message.querySelector('.collapsible-toggle')).not.toBeNull();
            expect(message.hasAttribute('data-collapsible-initialized')).toBe(
                true
            );
        });

        it('should initialize timeline-comment-message elements', function () {
            document.body.innerHTML = `
                <div class="timeline-comment-message">
                    <p>Long comment content.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-comment-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            expect(message.querySelector('.collapsible-toggle')).not.toBeNull();
        });

        it('should not re-initialize already initialized messages', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message" data-collapsible-initialized="true">
                    <div class="collapsible-content">Content</div>
                    <button class="collapsible-toggle">Toggle</button>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            const originalHTML = message.innerHTML;

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // HTML should remain unchanged
            expect(message.innerHTML).toBe(originalHTML);
        });
    });

    describe('HTML Structure', function () {
        beforeEach(function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Original content that is very long and needs to be collapsed.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
        });

        it('should create content wrapper with correct classes', function () {
            const content = document.querySelector('.collapsible-content');

            expect(content).not.toBeNull();
            expect(content.classList.contains('is-collapsed')).toBe(true);
        });

        it('should preserve original content', function () {
            const content = document.querySelector('.collapsible-content');

            expect(content.innerHTML).toContain(
                'Original content that is very long'
            );
        });

        it('should create toggle button with correct attributes', function () {
            const button = document.querySelector('.collapsible-toggle');

            expect(button).not.toBeNull();
            expect(button.getAttribute('type')).toBe('button');
            expect(button.getAttribute('aria-expanded')).toBe('false');
            expect(button.getAttribute('aria-controls')).toBeTruthy();
        });

        it('should generate unique IDs for each message', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message msg1">Content 1</div>
                <div class="timeline-item-message msg2">Content 2</div>
            `;

            const messages = document.querySelectorAll(
                '.timeline-item-message'
            );
            messages.forEach(msg => {
                Object.defineProperty(msg, 'scrollHeight', {
                    value: 300,
                    writable: true,
                });
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const content1 = document.querySelector(
                '.msg1 .collapsible-content'
            );
            const content2 = document.querySelector(
                '.msg2 .collapsible-content'
            );

            expect(content1.id).not.toBe(content2.id);
            expect(content1.id).toMatch(/^message-/);
            expect(content2.id).toMatch(/^message-/);
        });

        it('should have both collapsed and expanded text in button', function () {
            const button = document.querySelector('.collapsible-toggle');
            const collapsedText = button.querySelector(
                '.toggle-text-collapsed'
            );
            const expandedText = button.querySelector('.toggle-text-expanded');

            expect(collapsedText).not.toBeNull();
            expect(expandedText).not.toBeNull();
            expect(collapsedText.textContent).toContain('Show more');
            expect(expandedText.textContent).toContain('Show less');
        });

        it('should store original height in data attribute', function () {
            const content = document.querySelector('.collapsible-content');

            expect(content.getAttribute('data-full-height')).toBe('300');
        });
    });

    describe('Toggle Functionality', function () {
        beforeEach(function () {
            jest.useFakeTimers();

            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Long content for testing toggle functionality.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
        });

        afterEach(function () {
            jest.useRealTimers();
        });

        it('should expand when toggle button is clicked', function () {
            const button = document.querySelector('.collapsible-toggle');
            const content = document.querySelector('.collapsible-content');

            expect(content.classList.contains('is-collapsed')).toBe(true);

            button.click();

            expect(content.classList.contains('is-collapsed')).toBe(false);
            expect(content.classList.contains('is-expanding')).toBe(true);
            expect(button.getAttribute('aria-expanded')).toBe('true');
        });

        it('should show/hide correct toggle text when expanding', function () {
            const button = document.querySelector('.collapsible-toggle');
            const collapsedText = button.querySelector(
                '.toggle-text-collapsed'
            );
            const expandedText = button.querySelector('.toggle-text-expanded');

            // Initially collapsed text should be visible
            expect(collapsedText.style.display).not.toBe('none');

            button.click();

            // After click, expanded text should be visible
            expect(collapsedText.style.display).toBe('none');
            expect(expandedText.style.display).toBe('');
        });

        it('should transition to expanded state after animation', function () {
            const button = document.querySelector('.collapsible-toggle');
            const content = document.querySelector('.collapsible-content');

            button.click();

            expect(content.classList.contains('is-expanding')).toBe(true);
            expect(content.classList.contains('is-expanded')).toBe(false);

            // Fast-forward time
            jest.advanceTimersByTime(300);

            expect(content.classList.contains('is-expanding')).toBe(false);
            expect(content.classList.contains('is-expanded')).toBe(true);
        });

        it('should collapse when toggle button is clicked again', function () {
            const button = document.querySelector('.collapsible-toggle');
            const content = document.querySelector('.collapsible-content');

            // First expand
            button.click();
            jest.advanceTimersByTime(300);

            expect(content.classList.contains('is-expanded')).toBe(true);

            // Then collapse
            button.click();

            expect(content.classList.contains('is-expanded')).toBe(false);
            expect(content.classList.contains('is-collapsing')).toBe(true);
            expect(button.getAttribute('aria-expanded')).toBe('false');
        });

        it('should show/hide correct toggle text when collapsing', function () {
            const button = document.querySelector('.collapsible-toggle');
            const collapsedText = button.querySelector(
                '.toggle-text-collapsed'
            );
            const expandedText = button.querySelector('.toggle-text-expanded');

            // First expand
            button.click();

            // Then collapse
            button.click();

            expect(expandedText.style.display).toBe('none');
            expect(collapsedText.style.display).toBe('');
        });

        it('should transition to collapsed state after animation', function () {
            const button = document.querySelector('.collapsible-toggle');
            const content = document.querySelector('.collapsible-content');

            // First expand
            button.click();
            jest.advanceTimersByTime(300);

            // Then collapse
            button.click();

            expect(content.classList.contains('is-collapsing')).toBe(true);
            expect(content.classList.contains('is-collapsed')).toBe(false);

            // Fast-forward time
            jest.advanceTimersByTime(300);

            expect(content.classList.contains('is-collapsing')).toBe(false);
            expect(content.classList.contains('is-collapsed')).toBe(true);
        });

        it('should prevent default on button click', function () {
            const button = document.querySelector('.collapsible-toggle');
            const clickEvent = new Event('click', { cancelable: true });

            button.dispatchEvent(clickEvent);

            expect(clickEvent.defaultPrevented).toBe(true);
        });
    });

    describe('Accessibility', function () {
        beforeEach(function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content for accessibility testing.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
        });

        it('should have aria-expanded attribute on button', function () {
            const button = document.querySelector('.collapsible-toggle');

            expect(button.hasAttribute('aria-expanded')).toBe(true);
        });

        it('should have aria-controls linking button to content', function () {
            const button = document.querySelector('.collapsible-toggle');
            const content = document.querySelector('.collapsible-content');

            expect(button.getAttribute('aria-controls')).toBe(content.id);
        });

        it('should update aria-expanded when toggling', function () {
            const button = document.querySelector('.collapsible-toggle');

            expect(button.getAttribute('aria-expanded')).toBe('false');

            button.click();

            expect(button.getAttribute('aria-expanded')).toBe('true');

            button.click();

            expect(button.getAttribute('aria-expanded')).toBe('false');
        });

        it('should have aria-hidden on icon elements', function () {
            const button = document.querySelector('.collapsible-toggle');
            const icons = button.querySelectorAll('i');

            icons.forEach(icon => {
                expect(icon.getAttribute('aria-hidden')).toBe('true');
            });
        });

        it('should have type="button" to prevent form submission', function () {
            const button = document.querySelector('.collapsible-toggle');

            expect(button.getAttribute('type')).toBe('button');
        });
    });

    describe('Dynamic Content', function () {
        it('should initialize on contentLoaded event', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Dynamically loaded content.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            // Trigger DOMContentLoaded first
            const domEvent = new Event('DOMContentLoaded');
            document.dispatchEvent(domEvent);

            // Add new content
            const newMessage = document.createElement('div');
            newMessage.className = 'timeline-item-message';
            newMessage.innerHTML = '<p>New dynamically added message.</p>';
            document.body.appendChild(newMessage);

            Object.defineProperty(newMessage, 'scrollHeight', {
                value: 350,
                writable: true,
            });

            // Trigger contentLoaded event
            const contentEvent = new Event('contentLoaded');
            document.dispatchEvent(contentEvent);

            expect(
                newMessage.querySelector('.collapsible-toggle')
            ).not.toBeNull();
        });
    });

    describe('Edge Cases', function () {
        beforeEach(function () {
            jest.useFakeTimers();
        });

        afterEach(function () {
            jest.useRealTimers();
        });

        it('should handle message exactly at threshold height', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content at exact threshold.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 200, // Exactly at MAX_HEIGHT
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should not be collapsible (only > MAX_HEIGHT)
            expect(message.querySelector('.collapsible-toggle')).toBeNull();
        });

        it('should handle message just above threshold', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content just above threshold.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 201, // Just above MAX_HEIGHT
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Should be collapsible
            expect(message.querySelector('.collapsible-toggle')).not.toBeNull();
        });

        it('should handle empty messages', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message"></div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 0,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            expect(message.querySelector('.collapsible-toggle')).toBeNull();
        });

        it('should handle multiple messages with different heights', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message" id="msg1">Short</div>
                <div class="timeline-item-message" id="msg2">Long content here</div>
                <div class="timeline-item-message" id="msg3">Another short</div>
            `;

            const msg1 = document.getElementById('msg1');
            const msg2 = document.getElementById('msg2');
            const msg3 = document.getElementById('msg3');

            Object.defineProperty(msg1, 'scrollHeight', {
                value: 100,
                writable: true,
            });
            Object.defineProperty(msg2, 'scrollHeight', {
                value: 300,
                writable: true,
            });
            Object.defineProperty(msg3, 'scrollHeight', {
                value: 150,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            expect(msg1.querySelector('.collapsible-toggle')).toBeNull();
            expect(msg2.querySelector('.collapsible-toggle')).not.toBeNull();
            expect(msg3.querySelector('.collapsible-toggle')).toBeNull();
        });

        it('should handle scroll into view when button is above viewport', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content for scroll test.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const button = document.querySelector('.collapsible-toggle');

            // Mock getBoundingClientRect to simulate button above viewport
            button.getBoundingClientRect = jest.fn(() => ({
                top: -100, // Negative means above viewport
                bottom: -50,
                left: 0,
                right: 100,
            }));

            // Mock scrollIntoView
            button.scrollIntoView = jest.fn();

            // Expand then collapse
            button.click();
            jest.advanceTimersByTime(300);
            button.click();
            jest.advanceTimersByTime(300);

            // Should call scrollIntoView when collapsing if button is above viewport
            expect(button.scrollIntoView).toHaveBeenCalledWith({
                behavior: 'smooth',
                block: 'nearest',
            });
        });

        it('should not scroll when button is visible in viewport', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content for scroll test.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            const button = document.querySelector('.collapsible-toggle');

            // Mock getBoundingClientRect to simulate button in viewport
            button.getBoundingClientRect = jest.fn(() => ({
                top: 100, // Positive means in viewport
                bottom: 150,
                left: 0,
                right: 100,
            }));

            // Mock scrollIntoView
            button.scrollIntoView = jest.fn();

            // Expand then collapse
            button.click();
            jest.advanceTimersByTime(300);
            button.click();
            jest.advanceTimersByTime(300);

            // Should not call scrollIntoView
            expect(button.scrollIntoView).not.toHaveBeenCalled();
        });
    });

    describe('Translation', function () {
        it('should call translate function for button text', function () {
            document.body.innerHTML = `
                <div class="timeline-item-message">
                    <p>Content for translation test.</p>
                </div>
            `;

            const message = document.querySelector('.timeline-item-message');
            Object.defineProperty(message, 'scrollHeight', {
                value: 300,
                writable: true,
            });

            global.translate.mockClear();

            eval(collapsibleMessageJs);

            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            expect(global.translate).toHaveBeenCalledWith('Voir plus');
            expect(global.translate).toHaveBeenCalledWith('Voir moins');
        });
    });
});
