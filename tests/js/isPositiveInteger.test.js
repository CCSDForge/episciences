// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the isPositiveInteger function to avoid jQuery dependencies
const isPositiveIntegerFunctionMatch = functionsJs.match(/function isPositiveInteger\(s\) \{[\s\S]*?\n\}/);
if (isPositiveIntegerFunctionMatch) {
    eval(isPositiveIntegerFunctionMatch[0]);
}

describe('isPositiveInteger', function () {
    it('should return true for positive integer strings', function () {
        expect(isPositiveInteger('1')).toBe(true);
        expect(isPositiveInteger('10')).toBe(true);
        expect(isPositiveInteger('123')).toBe(true);
        expect(isPositiveInteger('999999')).toBe(true);
    });

    it('should return false for zero', function () {
        expect(isPositiveInteger('0')).toBe(false);
    });

    it('should return false for negative numbers', function () {
        expect(isPositiveInteger('-1')).toBe(false);
        expect(isPositiveInteger('-10')).toBe(false);
        expect(isPositiveInteger('-123')).toBe(false);
    });

    it('should return false for decimal numbers', function () {
        expect(isPositiveInteger('1.5')).toBe(false);
        expect(isPositiveInteger('10.0')).toBe(false);
        expect(isPositiveInteger('0.1')).toBe(false);
        expect(isPositiveInteger('123.456')).toBe(false);
    });

    it('should return false for non-numeric strings', function () {
        expect(isPositiveInteger('abc')).toBe(false);
        expect(isPositiveInteger('1a')).toBe(false);
        expect(isPositiveInteger('a1')).toBe(false);
        expect(isPositiveInteger('1.2.3')).toBe(false);
        expect(isPositiveInteger('text')).toBe(false);
    });

    it('should return false for empty string', function () {
        expect(isPositiveInteger('')).toBe(false);
    });

    it('should return false for whitespace only', function () {
        expect(isPositiveInteger('   ')).toBe(false);
        expect(isPositiveInteger('\t')).toBe(false);
        expect(isPositiveInteger('\n')).toBe(false);
    });

    it('should handle strings with leading/trailing whitespace', function () {
        expect(isPositiveInteger(' 1 ')).toBe(true);
        expect(isPositiveInteger('  123  ')).toBe(true);
        expect(isPositiveInteger('\t10\t')).toBe(true);
        expect(isPositiveInteger('\n5\n')).toBe(true);
    });

    it('should return false for strings with leading zeros', function () {
        expect(isPositiveInteger('01')).toBe(false);
        expect(isPositiveInteger('001')).toBe(false);
        expect(isPositiveInteger('0123')).toBe(false);
    });

    it('should return false for non-string types', function () {
        expect(isPositiveInteger(1)).toBe(false);
        expect(isPositiveInteger(123)).toBe(false);
        expect(isPositiveInteger(null)).toBe(false);
        expect(isPositiveInteger(undefined)).toBe(false);
        expect(isPositiveInteger(true)).toBe(false);
        expect(isPositiveInteger(false)).toBe(false);
        expect(isPositiveInteger([])).toBe(false);
        expect(isPositiveInteger({})).toBe(false);
    });

    it('should return false for scientific notation', function () {
        expect(isPositiveInteger('1e5')).toBe(false);
        expect(isPositiveInteger('1E5')).toBe(false);
        expect(isPositiveInteger('2.5e3')).toBe(false);
    });

    it('should return false for hexadecimal numbers', function () {
        expect(isPositiveInteger('0x10')).toBe(false);
        expect(isPositiveInteger('0xFF')).toBe(false);
    });

    it('should return false for special numeric values', function () {
        expect(isPositiveInteger('Infinity')).toBe(false);
        expect(isPositiveInteger('-Infinity')).toBe(false);
        expect(isPositiveInteger('NaN')).toBe(false);
    });

    it('should return false for numbers with plus sign', function () {
        expect(isPositiveInteger('+1')).toBe(false);
        expect(isPositiveInteger('+123')).toBe(false);
    });
});
