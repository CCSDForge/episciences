/**
 * Test suite for getFirstOf function
 * Tests the improved version with proper input validation and array optimization
 */

// Load the functions.js file and extract only the getFirstOf function
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the getFirstOf function to avoid jQuery dependencies
const getFirstOfFunctionMatch = functionsJs.match(
    /function getFirstOf\(data\) \{[\s\S]*?\n\}/
);
if (getFirstOfFunctionMatch) {
    eval(getFirstOfFunctionMatch[0]);
}

// Test suite
describe('getFirstOf function', function () {
    describe('Input validation', function () {
        it('should handle null input', function () {
            expect(getFirstOf(null)).toBe(undefined);
        });

        it('should handle undefined input', function () {
            expect(getFirstOf(undefined)).toBe(undefined);
        });

        it('should handle string input', function () {
            expect(getFirstOf('not an object')).toBe(undefined);
        });

        it('should handle number input', function () {
            expect(getFirstOf(123)).toBe(undefined);
        });

        it('should handle boolean input', function () {
            expect(getFirstOf(true)).toBe(undefined);
            expect(getFirstOf(false)).toBe(undefined);
        });

        it('should handle function input', function () {
            expect(getFirstOf(function () {})).toBe(undefined);
        });
    });

    describe('Array handling', function () {
        it('should return first element of non-empty array', function () {
            expect(getFirstOf(['a', 'b', 'c'])).toBe('a');
            expect(getFirstOf([1, 2, 3])).toBe(1);
            expect(getFirstOf([true, false])).toBe(true);
        });

        it('should return undefined for empty array', function () {
            expect(getFirstOf([])).toBe(undefined);
        });

        it('should handle arrays with various data types', function () {
            expect(getFirstOf([null, 'second'])).toBe(null);
            expect(getFirstOf([undefined, 'second'])).toBe(undefined);
            expect(getFirstOf([0, 1, 2])).toBe(0);
            expect(getFirstOf(['', 'second'])).toBe('');
            expect(getFirstOf([false, true])).toBe(false);
        });

        it('should handle arrays with objects', function () {
            const obj1 = { a: 1 };
            const obj2 = { b: 2 };
            expect(getFirstOf([obj1, obj2])).toBe(obj1);
        });

        it('should handle nested arrays', function () {
            expect(getFirstOf([['nested'], 'second'])).toEqual(['nested']);
        });

        it('should handle sparse arrays', function () {
            const sparseArray = [];
            sparseArray[2] = 'third';
            sparseArray[0] = 'first';
            expect(getFirstOf(sparseArray)).toBe('first');
        });
    });

    describe('Object handling', function () {
        it('should return first property value of non-empty object', function () {
            expect(getFirstOf({ a: 1, b: 2 })).toBe(1);
            expect(getFirstOf({ name: 'John', age: 30 })).toBe('John');
        });

        it('should return undefined for empty object', function () {
            expect(getFirstOf({})).toBe(undefined);
        });

        it('should handle objects with various data types as values', function () {
            expect(getFirstOf({ a: null, b: 'second' })).toBe(null);
            expect(getFirstOf({ a: undefined, b: 'second' })).toBe(undefined);
            expect(getFirstOf({ a: 0, b: 1 })).toBe(0);
            expect(getFirstOf({ a: '', b: 'second' })).toBe('');
            expect(getFirstOf({ a: false, b: true })).toBe(false);
        });

        it('should handle objects with nested objects', function () {
            const nested = { x: 1 };
            expect(getFirstOf({ first: nested, second: { y: 2 } })).toBe(
                nested
            );
        });

        it('should handle objects with array values', function () {
            const arr = [1, 2, 3];
            expect(getFirstOf({ first: arr, second: 'value' })).toBe(arr);
        });

        it('should ignore inherited properties', function () {
            function Parent() {
                this.parentProp = 'parent';
            }
            Parent.prototype.inheritedProp = 'inherited';

            function Child() {
                Parent.call(this);
                this.childProp = 'child';
            }
            Child.prototype = Object.create(Parent.prototype);

            const child = new Child();
            // Should return own property, not inherited
            const result = getFirstOf(child);
            expect(result === 'parent' || result === 'child').toBe(true);
            expect(result).not.toBe('inherited');
        });
    });

    describe('Multilingual object handling (common use case)', function () {
        it('should handle language objects', function () {
            const langObj = {
                fr: 'Bonjour',
                en: 'Hello',
                es: 'Hola',
            };
            expect(getFirstOf(langObj)).toBe('Bonjour');
        });

        it('should handle title objects', function () {
            const titleObj = {
                fr: 'Titre français',
                en: 'English title',
            };
            expect(getFirstOf(titleObj)).toBe('Titre français');
        });

        it('should handle content objects', function () {
            const contentObj = {
                fr: 'Contenu en français',
                en: 'Content in English',
            };
            expect(getFirstOf(contentObj)).toBe('Contenu en français');
        });
    });

    describe('Edge cases', function () {
        it('should handle Date objects', function () {
            const date = new Date('2023-01-01');
            // Date is an object, so it should try to get first property
            const result = getFirstOf(date);
            expect(result).toBe(undefined); // Date objects don't have enumerable own properties
        });

        it('should handle RegExp objects', function () {
            const regex = /test/g;
            const result = getFirstOf(regex);
            expect(result).toBe(undefined); // RegExp objects don't have enumerable own properties typically
        });

        it('should handle objects with numeric keys', function () {
            expect(getFirstOf({ 0: 'zero', 1: 'one' })).toBe('zero');
            expect(getFirstOf({ 10: 'ten', 5: 'five' })).toBe('five'); // Numeric keys are enumerated in ascending order
        });

        it('should handle objects with mixed key types', function () {
            const mixedObj = {
                stringKey: 'string value',
                123: 'numeric value',
                anotherString: 'another value',
            };
            const result = getFirstOf(mixedObj);
            expect(typeof result).toBe('string');
            expect([
                'string value',
                'numeric value',
                'another value',
            ]).toContain(result);
        });

        it('should handle objects with symbol keys', function () {
            const sym = Symbol('test');
            const obj = {};
            obj[sym] = 'symbol value';
            obj.normalKey = 'normal value';

            // Symbols are not enumerable in for-in loops
            expect(getFirstOf(obj)).toBe('normal value');
        });
    });

    describe('Performance and consistency', function () {
        it('should return the same value for the same object', function () {
            const obj = { a: 1, b: 2, c: 3 };
            const first1 = getFirstOf(obj);
            const first2 = getFirstOf(obj);
            expect(first1).toBe(first2);
        });

        it('should be efficient with large arrays', function () {
            const largeArray = new Array(10000).fill(0).map((_, i) => i);
            largeArray[0] = 'first';

            const start = Date.now();
            const result = getFirstOf(largeArray);
            const end = Date.now();

            expect(result).toBe('first');
            expect(end - start).toBeLessThan(10); // Should be very fast
        });

        it('should handle objects with many properties efficiently', function () {
            const largeObj = {};
            for (let i = 0; i < 1000; i++) {
                largeObj[`key${i}`] = `value${i}`;
            }

            const start = Date.now();
            const result = getFirstOf(largeObj);
            const end = Date.now();

            expect(result).toBe('value0');
            expect(end - start).toBeLessThan(10); // Should be fast due to early return
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { getFirstOf };
}
