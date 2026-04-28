// Load the functions.js file and extract only the in_array function
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the in_array function to avoid jQuery dependencies
const in_arrayFunctionMatch = functionsJs.match(
    /function in_array\(needle, haystack, strict = false\) \{[\s\S]*?\n\}/
);
if (in_arrayFunctionMatch) {
    eval(in_arrayFunctionMatch[0]);
}

describe('in_array', function () {
    describe('Input validation', function () {
        it('should return -1 for non-array haystack', function () {
            expect(in_array('test', null)).toBe(-1);
            expect(in_array('test', undefined)).toBe(-1);
            expect(in_array('test', 'string')).toBe(-1);
            expect(in_array('test', 123)).toBe(-1);
            expect(in_array('test', true)).toBe(-1);
            expect(in_array('test', {})).toBe(-1);
        });

        it('should return -1 for empty arrays', function () {
            expect(in_array('test', [])).toBe(-1);
            expect(in_array(1, [])).toBe(-1);
            expect(in_array(null, [])).toBe(-1);
        });
    });

    describe('Strict comparison (strict = true)', function () {
        it('should find exact matches with strict comparison', function () {
            const arr = ['apple', 'banana', 'cherry'];
            expect(in_array('apple', arr, true)).toBe(0);
            expect(in_array('banana', arr, true)).toBe(1);
            expect(in_array('cherry', arr, true)).toBe(2);
        });

        it('should not match different types with strict comparison', function () {
            const arr = [1, 2, 3, '4', '5'];
            expect(in_array('1', arr, true)).toBe(-1); // String '1' != number 1
            expect(in_array('2', arr, true)).toBe(-1); // String '2' != number 2
            expect(in_array(4, arr, true)).toBe(-1); // Number 4 != string '4'
            expect(in_array('4', arr, true)).toBe(3); // String '4' == string '4'
        });

        it('should handle null and undefined strictly', function () {
            const arr = [null, undefined, 0, false, ''];
            expect(in_array(null, arr, true)).toBe(0);
            expect(in_array(undefined, arr, true)).toBe(1);
            expect(in_array(0, arr, true)).toBe(2);
            expect(in_array(false, arr, true)).toBe(3);
            expect(in_array('', arr, true)).toBe(4);
        });

        it('should handle objects and arrays strictly', function () {
            const obj1 = { name: 'test' };
            const obj2 = { name: 'test' };
            const arr1 = [1, 2, 3];
            const arr2 = [1, 2, 3];
            const haystack = [obj1, arr1];

            expect(in_array(obj1, haystack, true)).toBe(0);
            expect(in_array(obj2, haystack, true)).toBe(-1); // Different object reference
            expect(in_array(arr1, haystack, true)).toBe(1);
            expect(in_array(arr2, haystack, true)).toBe(-1); // Different array reference
        });
    });

    describe('Loose comparison (strict = false, default)', function () {
        it('should find matches with type coercion', function () {
            const arr = [1, 2, 3, '4', '5'];
            expect(in_array('1', arr)).toBe(0); // String '1' == number 1
            expect(in_array('2', arr)).toBe(1); // String '2' == number 2
            expect(in_array(4, arr)).toBe(3); // Number 4 == string '4'
            expect(in_array('4', arr)).toBe(3); // String '4' == string '4'
        });

        it('should handle falsy values with loose comparison', function () {
            const arr = [null, undefined, 0, false, ''];
            expect(in_array(null, arr)).toBe(0);
            expect(in_array(undefined, arr)).toBe(0); // undefined == null (first match)
            expect(in_array(0, arr)).toBe(2);
            expect(in_array(false, arr)).toBe(2); // false == 0
            expect(in_array('', arr)).toBe(2); // '' == 0
        });

        it('should handle boolean coercion', function () {
            const arr = [true, false, 1, 0, 'yes', ''];
            expect(in_array(1, arr)).toBe(0); // 1 == true
            expect(in_array(0, arr)).toBe(1); // 0 == false
            expect(in_array(true, arr)).toBe(0); // true == true
            expect(in_array(false, arr)).toBe(1); // false == false
        });

        it('should handle string-number coercion', function () {
            const arr = ['10', '20', '30'];
            expect(in_array(10, arr)).toBe(0); // 10 == '10'
            expect(in_array(20, arr)).toBe(1); // 20 == '20'
            expect(in_array('10', arr)).toBe(0); // '10' == '10'
        });
    });

    describe('Return value behavior', function () {
        it('should return the first matching index', function () {
            const arr = ['a', 'b', 'a', 'c', 'a'];
            expect(in_array('a', arr)).toBe(0); // First occurrence
            expect(in_array('b', arr)).toBe(1);
            expect(in_array('c', arr)).toBe(3);
        });

        it('should return -1 when element is not found', function () {
            const arr = ['apple', 'banana', 'cherry'];
            expect(in_array('orange', arr)).toBe(-1);
            expect(in_array('APPLE', arr)).toBe(-1); // Case sensitive
            expect(in_array(null, arr)).toBe(-1);
        });

        it('should return correct indices for various positions', function () {
            const arr = ['zero', 'one', 'two', 'three', 'four'];
            expect(in_array('zero', arr)).toBe(0);
            expect(in_array('two', arr)).toBe(2);
            expect(in_array('four', arr)).toBe(4);
        });
    });

    describe('Edge cases and special values', function () {
        it('should handle NaN values', function () {
            const arr = [NaN, 1, 2, 3];
            // NaN == NaN is false, and NaN === NaN is also false in JavaScript
            expect(in_array(NaN, arr, true)).toBe(-1); // Strict: NaN !== NaN
            expect(in_array(NaN, arr, false)).toBe(-1); // Loose: NaN != NaN
        });

        it('should handle Infinity values', function () {
            const arr = [Infinity, -Infinity, 1, 2];
            expect(in_array(Infinity, arr, true)).toBe(0);
            expect(in_array(-Infinity, arr, true)).toBe(1);
            expect(in_array(Infinity, arr, false)).toBe(0);
            expect(in_array(-Infinity, arr, false)).toBe(1);
        });

        it('should handle zero variants', function () {
            const arr = [0, -0, +0];
            expect(in_array(0, arr, true)).toBe(0);
            expect(in_array(-0, arr, true)).toBe(0); // -0 === 0 is true
            expect(in_array(+0, arr, true)).toBe(0); // +0 === 0 is true
        });

        it('should handle large arrays efficiently', function () {
            const largeArray = Array.from({ length: 10000 }, (_, i) => i);
            expect(in_array(5000, largeArray, true)).toBe(5000);
            expect(in_array(9999, largeArray, true)).toBe(9999);
            expect(in_array(10000, largeArray, true)).toBe(-1);
        });
    });

    describe('Mixed data types', function () {
        it('should handle arrays with mixed types', function () {
            const mixedArray = [
                1,
                'two',
                true,
                null,
                undefined,
                { key: 'value' },
                [1, 2, 3],
            ];
            expect(in_array(1, mixedArray)).toBe(0);
            expect(in_array('two', mixedArray)).toBe(1);
            expect(in_array(true, mixedArray)).toBe(0); // true == 1 (first match)
            expect(in_array(null, mixedArray)).toBe(3);
            expect(in_array(undefined, mixedArray)).toBe(3); // undefined == null (first match)
        });

        it('should handle type coercion with mixed types', function () {
            const mixedArray = [1, '2', true, false, null, undefined];
            expect(in_array('1', mixedArray)).toBe(0); // '1' == 1
            expect(in_array(2, mixedArray)).toBe(1); // 2 == '2'
            expect(in_array(1, mixedArray)).toBe(0); // 1 == true would be index 2, but 1 is found first at index 0
        });
    });

    describe('Performance and reliability', function () {
        it('should handle various input types without throwing errors', function () {
            const testCases = [
                [null, []],
                [undefined, [1, 2, 3]],
                ['', ['a', 'b', 'c']],
                [0, [false, true, 1]],
                [[], [[1], [2], [3]]],
                [{}, [{ a: 1 }, { b: 2 }]],
            ];

            testCases.forEach(([needle, haystack]) => {
                expect(() => in_array(needle, haystack)).not.toThrow();
                expect(() => in_array(needle, haystack, true)).not.toThrow();
            });
        });

        it('should be consistent with repeated calls', function () {
            const arr = ['a', 'b', 'c'];
            for (let i = 0; i < 10; i++) {
                expect(in_array('b', arr)).toBe(1);
                expect(in_array('d', arr)).toBe(-1);
            }
        });

        it('should handle empty string vs zero correctly', function () {
            const arr = [0, '', false, null];
            expect(in_array('', arr, true)).toBe(1); // Strict: '' === ''
            expect(in_array('', arr, false)).toBe(0); // Loose: '' == 0 (first match)
            expect(in_array(0, arr, true)).toBe(0); // Strict: 0 === 0
            expect(in_array(0, arr, false)).toBe(0); // Loose: 0 == 0 (first match)
        });
    });

    describe('PHP in_array compatibility', function () {
        it('should mimic PHP in_array loose comparison behavior', function () {
            // These tests verify compatibility with PHP's in_array function
            const arr = [1, 2, 3, '4', '5'];
            expect(in_array('1', arr, false)).toBe(0); // PHP: in_array('1', [1,2,3,'4','5'], false) = true
            expect(in_array('4', arr, false)).toBe(3); // PHP: in_array('4', [1,2,3,'4','5'], false) = true
            expect(in_array(4, arr, false)).toBe(3); // PHP: in_array(4, [1,2,3,'4','5'], false) = true
        });

        it('should mimic PHP in_array strict comparison behavior', function () {
            const arr = [1, 2, 3, '4', '5'];
            expect(in_array('1', arr, true)).toBe(-1); // PHP: in_array('1', [1,2,3,'4','5'], true) = false
            expect(in_array('4', arr, true)).toBe(3); // PHP: in_array('4', [1,2,3,'4','5'], true) = true
            expect(in_array(4, arr, true)).toBe(-1); // PHP: in_array(4, [1,2,3,'4','5'], true) = false
        });

        it('should handle boolean comparisons like PHP', function () {
            const arr = [true, false, 1, 0];
            expect(in_array(1, arr, false)).toBe(0); // 1 == true (first match)
            expect(in_array(0, arr, false)).toBe(1); // 0 == false (first match)
            expect(in_array(1, arr, true)).toBe(2); // 1 === 1
            expect(in_array(0, arr, true)).toBe(3); // 0 === 0
        });
    });

    describe('Default parameter behavior', function () {
        it('should use loose comparison when strict parameter is omitted', function () {
            const arr = [1, 2, 3];
            expect(in_array('1', arr)).toBe(0); // Default to loose comparison
            expect(in_array('2', arr)).toBe(1); // Default to loose comparison
        });

        it('should use loose comparison when strict is explicitly false', function () {
            const arr = [1, 2, 3];
            expect(in_array('1', arr, false)).toBe(0);
            expect(in_array('2', arr, false)).toBe(1);
        });

        it('should use strict comparison when strict is explicitly true', function () {
            const arr = [1, 2, 3];
            expect(in_array('1', arr, true)).toBe(-1);
            expect(in_array(1, arr, true)).toBe(0);
        });
    });
});
