/**
 * Test suite for isEmptyData function
 * Tests the improved version with comprehensive empty data detection
 */

// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the isEmptyData function to avoid jQuery dependencies
const isEmptyDataFunctionMatch = functionsJs.match(
    /function isEmptyData\(value, visited = new WeakSet\(\)\) \{[\s\S]*?\n\}/
);
if (isEmptyDataFunctionMatch) {
    eval(isEmptyDataFunctionMatch[0]);
}

describe('isEmptyData', function () {
    describe('null and undefined values', function () {
        it('should return true for null', function () {
            expect(isEmptyData(null)).toBe(true);
        });

        it('should return true for undefined', function () {
            expect(isEmptyData(undefined)).toBe(true);
        });
    });

    describe('arrays', function () {
        it('should return true for empty array', function () {
            expect(isEmptyData([])).toBe(true);
        });

        it('should return true for array with only null values', function () {
            expect(isEmptyData([null, null, null])).toBe(true);
        });

        it('should return true for array with only undefined values', function () {
            expect(isEmptyData([undefined, undefined])).toBe(true);
        });

        it('should return true for array with only zeros', function () {
            expect(isEmptyData([0, 0, 0])).toBe(true);
        });

        it('should return true for array with only empty strings', function () {
            expect(isEmptyData(['', '   ', '\t\n'])).toBe(true);
        });

        it('should return true for array with mixed empty values', function () {
            expect(isEmptyData([null, undefined, 0, '', '  '])).toBe(true);
        });

        it('should return true for nested empty arrays', function () {
            expect(isEmptyData([[], [null, 0], ['', undefined]])).toBe(true);
        });

        it('should return false for array with at least one non-empty value', function () {
            expect(isEmptyData([0, 0, 1])).toBe(false);
            expect(isEmptyData([null, 'test'])).toBe(false);
            expect(isEmptyData(['', 'hello'])).toBe(false);
        });

        it('should return false for array with boolean false', function () {
            expect(isEmptyData([false])).toBe(false);
        });

        it('should return false for array with boolean true', function () {
            expect(isEmptyData([true])).toBe(false);
        });
    });

    describe('objects', function () {
        it('should return true for empty object', function () {
            expect(isEmptyData({})).toBe(true);
        });

        it('should return true for object with only empty values', function () {
            expect(isEmptyData({ a: null, b: undefined, c: 0, d: '' })).toBe(
                true
            );
        });

        it('should return true for nested empty objects', function () {
            expect(isEmptyData({ a: {}, b: { c: null }, d: { e: '' } })).toBe(
                true
            );
        });

        it('should return false for object with at least one non-empty value', function () {
            expect(isEmptyData({ a: null, b: 'test' })).toBe(false);
            expect(isEmptyData({ a: 0, b: 1 })).toBe(false);
        });

        it('should return false for object with boolean false value', function () {
            expect(isEmptyData({ flag: false })).toBe(false);
        });

        it('should return false for Date objects', function () {
            expect(isEmptyData(new Date())).toBe(false);
        });

        it('should return false for RegExp objects', function () {
            expect(isEmptyData(/test/)).toBe(false);
        });

        it('should return false for function objects', function () {
            expect(isEmptyData(function () {})).toBe(false);
        });
    });

    describe('strings', function () {
        it('should return true for empty string', function () {
            expect(isEmptyData('')).toBe(true);
        });

        it('should return true for whitespace-only strings', function () {
            expect(isEmptyData('   ')).toBe(true);
            expect(isEmptyData('\t\n\r')).toBe(true);
            expect(isEmptyData('  \t  \n  ')).toBe(true);
        });

        it('should return false for non-empty strings', function () {
            expect(isEmptyData('test')).toBe(false);
            expect(isEmptyData('0')).toBe(false);
            expect(isEmptyData('false')).toBe(false);
        });

        it('should return false for strings with actual content after trimming', function () {
            expect(isEmptyData('  hello  ')).toBe(false);
            expect(isEmptyData('\t\ntest\r\n')).toBe(false);
        });
    });

    describe('numbers', function () {
        it('should return true for zero', function () {
            expect(isEmptyData(0)).toBe(true);
        });

        it('should return true for negative zero', function () {
            expect(isEmptyData(-0)).toBe(true);
        });

        it('should return false for positive numbers', function () {
            expect(isEmptyData(1)).toBe(false);
            expect(isEmptyData(0.1)).toBe(false);
            expect(isEmptyData(Infinity)).toBe(false);
        });

        it('should return false for negative numbers', function () {
            expect(isEmptyData(-1)).toBe(false);
            expect(isEmptyData(-0.1)).toBe(false);
            expect(isEmptyData(-Infinity)).toBe(false);
        });

        it('should return false for NaN', function () {
            expect(isEmptyData(NaN)).toBe(false);
        });
    });

    describe('booleans', function () {
        it('should return false for boolean true', function () {
            expect(isEmptyData(true)).toBe(false);
        });

        it('should return false for boolean false', function () {
            expect(isEmptyData(false)).toBe(false);
        });
    });

    describe('other types', function () {
        it('should return false for functions', function () {
            expect(isEmptyData(function () {})).toBe(false);
            expect(isEmptyData(() => {})).toBe(false);
        });

        it('should return false for symbols', function () {
            expect(isEmptyData(Symbol('test'))).toBe(false);
        });
    });

    describe('chart data use cases', function () {
        // Based on the usage in stats/submissions.js
        it('should correctly handle chart dataset data arrays', function () {
            // Empty chart data - should be true
            expect(isEmptyData([0, 0, 0, 0])).toBe(true);
            expect(isEmptyData([])).toBe(true);

            // Chart data with values - should be false
            expect(isEmptyData([0, 1, 2, 0])).toBe(false);
            expect(isEmptyData([5, 10, 15])).toBe(false);
        });

        it('should handle mixed empty chart data', function () {
            // Chart dataset might have null/undefined for missing data
            expect(isEmptyData([null, 0, undefined, 0])).toBe(true);
            expect(isEmptyData([null, 0, undefined, 1])).toBe(false);
        });
    });

    describe('enrichment data use cases', function () {
        // Based on the usage in submit/functions.js
        it('should correctly handle enrichment objects', function () {
            // Empty enrichment data - should be true
            expect(isEmptyData({})).toBe(true);
            expect(isEmptyData({ field1: null, field2: '' })).toBe(true);

            // Enrichment with data - should be false
            expect(isEmptyData({ title: 'Test Title' })).toBe(false);
            expect(isEmptyData({ authors: ['Author 1'] })).toBe(false);
        });
    });

    describe('edge cases and recursion', function () {
        it('should handle deeply nested empty structures', function () {
            const deepEmpty = {
                level1: {
                    level2: {
                        level3: [null, '', 0, undefined],
                    },
                },
            };
            expect(isEmptyData(deepEmpty)).toBe(true);
        });

        it('should handle deeply nested structures with content', function () {
            const deepWithContent = {
                level1: {
                    level2: {
                        level3: [null, '', 0, 'content'],
                    },
                },
            };
            expect(isEmptyData(deepWithContent)).toBe(false);
        });

        it('should handle circular references gracefully', function () {
            const circular = {};
            circular.self = circular;
            // Should not cause infinite recursion - will return false for circular refs
            expect(isEmptyData(circular)).toBe(false);
        });
    });
});
