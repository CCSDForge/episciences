/**
 * Security tests for HTML sanitization
 * @jest-environment jsdom
 */

// Mock DOMPurify
global.DOMPurify = {
    sanitize: jest.fn((dirty, config) => {
        // Mock: remove script tags, event handlers, and javascript: URIs
        let clean = dirty
            // Remove script tags
            .replace(
                /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
                ''
            )
            // Remove inline event handlers (onload, onerror, etc.)
            .replace(/on\w+\s*=\s*["'][^"']*["']/gi, '')
            .replace(/on\w+\s*=\s*\S+/gi, '')
            // Remove javascript: URIs in attributes
            .replace(/\bhref\s*=\s*["']javascript:[^"']*["']/gi, '')
            .replace(/\bsrc\s*=\s*["']javascript:[^"']*["']/gi, '')
            // Remove javascript: in style attributes
            .replace(/style\s*=\s*["'][^"']*javascript:[^"']*["']/gi, '')
            // Remove iframes with javascript: src
            .replace(/<iframe\s+src\s*=\s*javascript:[^>]*>/gi, '');
        return clean;
    }),
};

// Load sanitizer module
require('../../../public/js/utils/sanitizer.js');

describe('XSS Sanitization Security Tests', () => {
    describe('sanitizeHTML function', () => {
        test('should be globally accessible', () => {
            expect(typeof window.sanitizeHTML).toBe('function');
        });

        test('should remove script tags', () => {
            const dirty = '<p>Hello</p><script>alert("XSS")</script>';
            const clean = window.sanitizeHTML(dirty);
            expect(clean).not.toContain('<script>');
            expect(clean).not.toContain('alert');
        });

        test('should remove inline event handlers', () => {
            const dirty = '<img src=x onerror="alert(1)">';
            const clean = window.sanitizeHTML(dirty);
            expect(clean).not.toContain('onerror');
            expect(clean).not.toContain('alert');
        });

        test('should preserve safe HTML', () => {
            const dirty = '<p>Hello <strong>world</strong></p>';
            const clean = window.sanitizeHTML(dirty);
            expect(clean).toContain('<p>');
            expect(clean).toContain('<strong>');
            expect(clean).toContain('world');
        });

        test('should handle empty input', () => {
            const clean = window.sanitizeHTML('');
            expect(clean).toBe('');
        });

        test('should call DOMPurify.sanitize', () => {
            const dirty = '<p>Test</p>';
            window.sanitizeHTML(dirty);
            expect(DOMPurify.sanitize).toHaveBeenCalled();
        });
    });

    describe('sanitizeHTMLStrict function', () => {
        test('should be globally accessible', () => {
            expect(typeof window.sanitizeHTMLStrict).toBe('function');
        });

        test('should remove script tags strictly', () => {
            const dirty = '<p>Hello</p><script>alert("XSS")</script>';
            const clean = window.sanitizeHTMLStrict(dirty);
            expect(clean).not.toContain('<script>');
            expect(clean).not.toContain('alert');
        });
    });

    describe('Fallback when DOMPurify is not loaded', () => {
        let originalDOMPurify;

        beforeEach(() => {
            originalDOMPurify = global.DOMPurify;
            global.DOMPurify = undefined;
            // Suppress console.error for this test
            jest.spyOn(console, 'error').mockImplementation(() => {});
        });

        afterEach(() => {
            global.DOMPurify = originalDOMPurify;
            console.error.mockRestore();
        });

        test('should fallback to textContent extraction', () => {
            const dirty = '<p>Hello</p><script>alert("XSS")</script>';
            const clean = window.sanitizeHTML(dirty);

            // Fallback should strip ALL HTML and return only text
            expect(clean).not.toContain('<p>');
            expect(clean).not.toContain('<script>');
        });

        test('should log error when DOMPurify is missing', () => {
            window.sanitizeHTML('<p>Test</p>');
            expect(console.error).toHaveBeenCalledWith(
                'DOMPurify is not loaded! HTML sanitization failed.'
            );
        });
    });
});

describe('Integration with modified files', () => {
    describe('es.contacts-list.js patterns', () => {
        test('should safely handle server response HTML', () => {
            const serverResponse =
                '<div class="contact"><span>John Doe</span></div><script>stealCookies()</script>';
            const safe = window.sanitizeHTML(serverResponse);

            expect(safe).not.toContain('<script>');
            expect(safe).not.toContain('stealCookies');
            expect(safe).toContain('John Doe');
        });
    });

    describe('paperAffiAuthors.js patterns', () => {
        test('should safely handle affiliation form HTML', () => {
            const formHTML =
                '<input type="text" name="affiliation"><script>malicious()</script>';
            const safe = window.sanitizeHTML(formHTML);

            expect(safe).not.toContain('<script>');
            expect(safe).not.toContain('malicious');
            expect(safe).toContain('input');
        });
    });

    describe('Common XSS attack vectors', () => {
        const xssPayloads = [
            '<img src=x onerror=alert(1)>',
            '<svg onload=alert(1)>',
            '<iframe src=javascript:alert(1)>',
            '<body onload=alert(1)>',
            '<input onfocus=alert(1) autofocus>',
            '<select onfocus=alert(1) autofocus>',
            '<textarea onfocus=alert(1) autofocus>',
            '<marquee onstart=alert(1)>',
            '<div style="background:url(javascript:alert(1))">',
        ];

        xssPayloads.forEach((payload, index) => {
            test(`should block XSS payload #${index + 1}: ${payload}`, () => {
                const clean = window.sanitizeHTML(payload);
                expect(clean).not.toContain('alert');
                expect(clean).not.toContain('javascript:');
            });
        });
    });
});