// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the nl2br function to avoid jQuery dependencies
const nl2brFunctionMatch = functionsJs.match(/function nl2br\(str\) \{[\s\S]*?\n\}/);
if (nl2brFunctionMatch) {
    eval(nl2brFunctionMatch[0]);
}

describe('nl2br', function () {
    it('should handle null input', function () {
        expect(nl2br(null)).toBe('');
    });

    it('should handle undefined input', function () {
        expect(nl2br(undefined)).toBe('');
    });

    it('should handle empty string', function () {
        expect(nl2br('')).toBe('');
    });

    it('should handle strings without line breaks', function () {
        expect(nl2br('Hello World')).toBe('Hello World');
        expect(nl2br('This is a test')).toBe('This is a test');
    });

    it('should convert Unix line breaks (\\n)', function () {
        expect(nl2br('Line 1\nLine 2')).toBe('Line 1<br>\nLine 2');
        expect(nl2br('First\nSecond\nThird')).toBe(
            'First<br>\nSecond<br>\nThird'
        );
    });

    it('should convert Windows line breaks (\\r\\n)', function () {
        expect(nl2br('Line 1\r\nLine 2')).toBe('Line 1<br>\r\nLine 2');
        expect(nl2br('First\r\nSecond\r\nThird')).toBe(
            'First<br>\r\nSecond<br>\r\nThird'
        );
    });

    it('should convert old Mac line breaks (\\r)', function () {
        expect(nl2br('Line 1\rLine 2')).toBe('Line 1<br>\rLine 2');
        expect(nl2br('First\rSecond\rThird')).toBe(
            'First<br>\rSecond<br>\rThird'
        );
    });

    it('should convert reverse Windows line breaks (\\n\\r)', function () {
        expect(nl2br('Line 1\n\rLine 2')).toBe('Line 1<br>\n\rLine 2');
    });

    it('should handle mixed line break types', function () {
        expect(nl2br('Unix\nWindows\r\nMac\rReverse\n\r')).toBe(
            'Unix<br>\nWindows<br>\r\nMac<br>\rReverse<br>\n\r'
        );
    });

    it('should handle multiple consecutive line breaks', function () {
        expect(nl2br('Line 1\n\nLine 2')).toBe('Line 1<br>\n<br>\nLine 2');
        expect(nl2br('Text\r\n\r\nMore text')).toBe(
            'Text<br>\r\n<br>\r\nMore text'
        );
    });

    it('should handle line breaks at the beginning', function () {
        expect(nl2br('\nStart with newline')).toBe('<br>\nStart with newline');
        expect(nl2br('\r\nWindows start')).toBe('<br>\r\nWindows start');
    });

    it('should handle line breaks at the end', function () {
        expect(nl2br('End with newline\n')).toBe('End with newline<br>\n');
        expect(nl2br('Windows end\r\n')).toBe('Windows end<br>\r\n');
    });

    it('should handle only line breaks', function () {
        expect(nl2br('\n')).toBe('<br>\n');
        expect(nl2br('\r\n')).toBe('<br>\r\n');
        expect(nl2br('\r')).toBe('<br>\r');
        expect(nl2br('\n\r')).toBe('<br>\n\r');
    });

    it('should convert numbers to strings and process', function () {
        expect(nl2br(123)).toBe('123');
        expect(nl2br(0)).toBe('0');
    });

    it('should convert booleans to strings and process', function () {
        expect(nl2br(true)).toBe('true');
        expect(nl2br(false)).toBe('false');
    });

    it('should preserve existing content while adding line breaks', function () {
        expect(nl2br('Hello\nWorld\nTest')).toBe('Hello<br>\nWorld<br>\nTest');
    });

    it('should handle HTML content with line breaks', function () {
        expect(nl2br('<p>Paragraph 1</p>\n<p>Paragraph 2</p>')).toBe(
            '<p>Paragraph 1</p><br>\n<p>Paragraph 2</p>'
        );
    });

    it('should handle text with special characters and line breaks', function () {
        expect(nl2br('Special chars: @#$%\nNext line')).toBe(
            'Special chars: @#$%<br>\nNext line'
        );
    });

    it('should handle multiline text block', function () {
        const input = 'Line 1\nLine 2\nLine 3\nLine 4';
        const expected = 'Line 1<br>\nLine 2<br>\nLine 3<br>\nLine 4';
        expect(nl2br(input)).toBe(expected);
    });

    it('should preserve content before line breaks', function () {
        expect(nl2br('Content before\nafter')).toBe(
            'Content before<br>\nafter'
        );
        expect(nl2br('Multiple words before\nafter break')).toBe(
            'Multiple words before<br>\nafter break'
        );
    });
});
