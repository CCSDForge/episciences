/**
 * Test suite for isISOdate function
 * Tests the improved version with proper input validation, pattern handling, and strict date validation
 */

// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the isISOdate function to avoid jQuery dependencies
const isISOdateFunctionMatch = functionsJs.match(/function isISOdate\(input, pattern, strict = false\) \{[\s\S]*?\n\}/);
if (isISOdateFunctionMatch) {
    eval(isISOdateFunctionMatch[0]);
}

// Test suite
describe('isISOdate function', function () {
    describe('Input validation', function () {
        it('should handle null input', function () {
            expect(isISOdate(null)).toBe(false);
        });

        it('should handle undefined input', function () {
            expect(isISOdate(undefined)).toBe(false);
        });

        it('should handle non-string input', function () {
            expect(isISOdate(123)).toBe(false);
            expect(isISOdate(true)).toBe(false);
            expect(isISOdate({})).toBe(false);
            expect(isISOdate([])).toBe(false);
        });

        it('should handle empty string', function () {
            expect(isISOdate('')).toBe(false);
        });

        it('should handle whitespace-only string', function () {
            expect(isISOdate('   ')).toBe(false);
            expect(isISOdate('\t')).toBe(false);
            expect(isISOdate('\n')).toBe(false);
        });
    });

    describe('Default pattern validation (YYYY-MM-DD)', function () {
        it('should accept valid ISO date format', function () {
            expect(isISOdate('2023-01-01')).toBe(true);
            expect(isISOdate('2023-12-31')).toBe(true);
            expect(isISOdate('1900-01-01')).toBe(true);
            expect(isISOdate('2100-12-31')).toBe(true);
        });

        it('should require 4-digit year', function () {
            expect(isISOdate('23-01-01')).toBe(false);
            expect(isISOdate('123-01-01')).toBe(false);
            expect(isISOdate('12345-01-01')).toBe(false);
        });

        it('should require 2-digit month', function () {
            expect(isISOdate('2023-1-01')).toBe(false);
            expect(isISOdate('2023-123-01')).toBe(false);
        });

        it('should require 2-digit day', function () {
            expect(isISOdate('2023-01-1')).toBe(false);
            expect(isISOdate('2023-01-123')).toBe(false);
        });

        it('should require hyphens as separators', function () {
            expect(isISOdate('2023/01/01')).toBe(false);
            expect(isISOdate('2023.01.01')).toBe(false);
            expect(isISOdate('20230101')).toBe(false);
            expect(isISOdate('2023 01 01')).toBe(false);
        });

        it('should reject additional characters', function () {
            expect(isISOdate('2023-01-01T00:00:00')).toBe(false);
            expect(isISOdate('2023-01-01 00:00:00')).toBe(false);
            expect(isISOdate('a2023-01-01')).toBe(false);
            expect(isISOdate('2023-01-01a')).toBe(false);
        });

        it('should handle leap years correctly in pattern validation', function () {
            expect(isISOdate('2020-02-29')).toBe(true); // Pattern only
            expect(isISOdate('2021-02-29')).toBe(true); // Pattern only - strict mode needed for actual validation
        });
    });

    describe('Custom pattern validation', function () {
        it('should accept custom patterns', function () {
            const customPattern = /^\d{4}\/\d{2}\/\d{2}$/;
            expect(isISOdate('2023/01/01', customPattern)).toBe(true);
            expect(isISOdate('2023-01-01', customPattern)).toBe(false);
        });

        it('should work with flexible day/month patterns', function () {
            const flexiblePattern = /^\d{4}-\d{1,2}-\d{1,2}$/;
            expect(isISOdate('2023-1-1', flexiblePattern)).toBe(true);
            expect(isISOdate('2023-01-01', flexiblePattern)).toBe(true);
            expect(isISOdate('2023-12-31', flexiblePattern)).toBe(true);
        });

        it('should work with year-only patterns', function () {
            const yearPattern = /^\d{4}$/;
            expect(isISOdate('2023', yearPattern)).toBe(true);
            expect(isISOdate('23', yearPattern)).toBe(false);
        });

        it('should work with different date formats', function () {
            const ddmmyyyyPattern = /^\d{2}\/\d{2}\/\d{4}$/;
            expect(isISOdate('01/01/2023', ddmmyyyyPattern)).toBe(true);
            expect(isISOdate('2023-01-01', ddmmyyyyPattern)).toBe(false);
        });
    });

    describe('Non-strict validation (pattern only)', function () {
        it('should accept dates that match pattern but may not exist', function () {
            expect(isISOdate('2023-02-30')).toBe(true); // Feb 30th doesn't exist, but pattern matches
            expect(isISOdate('2023-13-01')).toBe(true); // Month 13 doesn't exist, but pattern matches
            expect(isISOdate('2023-00-01')).toBe(true); // Month 0 doesn't exist, but pattern matches
            expect(isISOdate('2023-01-32')).toBe(true); // Jan 32nd doesn't exist, but pattern matches
        });

        it('should be fast for pattern-only validation', function () {
            const start = Date.now();
            for (let i = 0; i < 1000; i++) {
                isISOdate('2023-01-01');
            }
            const end = Date.now();
            expect(end - start).toBeLessThan(100); // Should be very fast
        });
    });

    describe('Strict validation (pattern + date existence)', function () {
        it('should accept valid existing dates', function () {
            expect(isISOdate('2023-01-01', null, true)).toBe(true);
            expect(isISOdate('2023-12-31', null, true)).toBe(true);
            expect(isISOdate('2020-02-29', null, true)).toBe(true); // Leap year
            expect(isISOdate('2023-02-28', null, true)).toBe(true);
        });

        it('should reject non-existing dates', function () {
            expect(isISOdate('2023-02-30', null, true)).toBe(false); // Feb 30th doesn't exist
            expect(isISOdate('2021-02-29', null, true)).toBe(false); // Not a leap year
            expect(isISOdate('2023-04-31', null, true)).toBe(false); // April only has 30 days
            expect(isISOdate('2023-13-01', null, true)).toBe(false); // Month 13 doesn't exist
            expect(isISOdate('2023-00-01', null, true)).toBe(false); // Month 0 doesn't exist
            expect(isISOdate('2023-01-32', null, true)).toBe(false); // Jan 32nd doesn't exist
            expect(isISOdate('2023-01-00', null, true)).toBe(false); // Day 0 doesn't exist
        });

        it('should handle leap years correctly', function () {
            // Leap years (divisible by 4, except century years unless divisible by 400)
            expect(isISOdate('2020-02-29', null, true)).toBe(true); // Leap year
            expect(isISOdate('2000-02-29', null, true)).toBe(true); // Leap year (divisible by 400)
            expect(isISOdate('1600-02-29', null, true)).toBe(true); // Leap year (divisible by 400)

            // Non-leap years
            expect(isISOdate('2021-02-29', null, true)).toBe(false); // Not a leap year
            expect(isISOdate('1900-02-29', null, true)).toBe(false); // Not a leap year (century, not divisible by 400)
            expect(isISOdate('2100-02-29', null, true)).toBe(false); // Not a leap year (century, not divisible by 400)
        });

        it('should handle different month lengths', function () {
            // 31-day months
            expect(isISOdate('2023-01-31', null, true)).toBe(true);
            expect(isISOdate('2023-03-31', null, true)).toBe(true);
            expect(isISOdate('2023-05-31', null, true)).toBe(true);
            expect(isISOdate('2023-07-31', null, true)).toBe(true);
            expect(isISOdate('2023-08-31', null, true)).toBe(true);
            expect(isISOdate('2023-10-31', null, true)).toBe(true);
            expect(isISOdate('2023-12-31', null, true)).toBe(true);

            // 30-day months
            expect(isISOdate('2023-04-30', null, true)).toBe(true);
            expect(isISOdate('2023-06-30', null, true)).toBe(true);
            expect(isISOdate('2023-09-30', null, true)).toBe(true);
            expect(isISOdate('2023-11-30', null, true)).toBe(true);

            // Invalid days for 30-day months
            expect(isISOdate('2023-04-31', null, true)).toBe(false);
            expect(isISOdate('2023-06-31', null, true)).toBe(false);
            expect(isISOdate('2023-09-31', null, true)).toBe(false);
            expect(isISOdate('2023-11-31', null, true)).toBe(false);
        });

        it('should handle edge case years', function () {
            expect(isISOdate('0001-01-01', null, true)).toBe(true); // Year 1 AD
            expect(isISOdate('1970-01-01', null, true)).toBe(true); // Unix epoch
            expect(isISOdate('2038-01-19', null, true)).toBe(true); // Near 32-bit Unix timestamp limit
            expect(isISOdate('9999-12-31', null, true)).toBe(true); // Far future
        });
    });

    describe('Strict validation with custom patterns', function () {
        it('should work with custom patterns in strict mode', function () {
            const customPattern = /^\d{4}\/\d{2}\/\d{2}$/;
            // This should fail because the pattern doesn't match ISO format expected by Date constructor
            expect(isISOdate('2023/02/29', customPattern, true)).toBe(false);
        });

        it('should require ISO format for strict validation even with custom pattern', function () {
            const customPattern = /^\d{4}-\d{2}-\d{2}$/; // Same as default but explicit
            expect(isISOdate('2023-02-29', customPattern, true)).toBe(false);
            expect(isISOdate('2020-02-29', customPattern, true)).toBe(true);
        });
    });

    describe('Real-world date scenarios', function () {
        it('should validate common publication dates', function () {
            expect(isISOdate('2023-01-15', null, true)).toBe(true);
            expect(isISOdate('2022-12-25', null, true)).toBe(true);
            expect(isISOdate('2021-07-04', null, true)).toBe(true);
        });

        it('should validate historical dates', function () {
            expect(isISOdate('1969-07-20', null, true)).toBe(true); // Moon landing
            expect(isISOdate('1945-05-08', null, true)).toBe(true); // VE Day
            expect(isISOdate('1776-07-04', null, true)).toBe(true); // US Independence
        });

        it('should validate future dates', function () {
            expect(isISOdate('2030-01-01', null, true)).toBe(true);
            expect(isISOdate('2050-12-31', null, true)).toBe(true);
        });

        it('should handle common user input errors', function () {
            expect(isISOdate('2023-1-1')).toBe(false); // Missing leading zeros
            expect(isISOdate('23-01-01')).toBe(false); // 2-digit year
            expect(isISOdate('2023/01/01')).toBe(false); // Wrong separators
            expect(isISOdate('01-01-2023')).toBe(false); // Wrong order
            expect(isISOdate('2023-01-01 ')).toBe(false); // Trailing space
            expect(isISOdate(' 2023-01-01')).toBe(false); // Leading space
        });
    });

    describe('Performance considerations', function () {
        it('should be fast for non-strict validation', function () {
            const dates = [
                '2023-01-01',
                '2023-02-29',
                '2023-13-01',
                '2023-01-32',
            ];
            const start = Date.now();

            for (let i = 0; i < 1000; i++) {
                dates.forEach(date => isISOdate(date));
            }

            const end = Date.now();
            expect(end - start).toBeLessThan(100);
        });

        it('should be reasonably fast for strict validation', function () {
            const validDates = ['2023-01-01', '2023-02-28', '2023-12-31'];
            const start = Date.now();

            for (let i = 0; i < 100; i++) {
                validDates.forEach(date => isISOdate(date, null, true));
            }

            const end = Date.now();
            expect(end - start).toBeLessThan(1000);
        });
    });

    describe('Backward compatibility', function () {
        it('should maintain compatibility with original function signature', function () {
            // Original function: isISOdate(input, pattern)
            expect(isISOdate('2023-01-01')).toBe(true);
            expect(isISOdate('2023-01-01', /^\d{4}-\d{2}-\d{2}$/)).toBe(true);
        });

        it('should work with original patterns', function () {
            const originalPattern = /(^\d{4}-\d{1,2}-\d{1,2}$)/g;
            expect(isISOdate('2023-1-1', originalPattern)).toBe(true);
            expect(isISOdate('2023-01-01', originalPattern)).toBe(true);
        });
    });

    describe('Integration with existing codebase', function () {
        it('should work with updateDeadlineTag function expectations', function () {
            // Common dates that might be used in the application
            expect(isISOdate('2023-01-01')).toBe(true);
            expect(isISOdate('2023-12-31')).toBe(true);

            // Strict validation for actual date checking
            expect(isISOdate('2023-02-30', null, true)).toBe(false);
            expect(isISOdate('2023-02-28', null, true)).toBe(true);
        });

        it('should handle form input validation scenarios', function () {
            // Typical form inputs that should be rejected
            expect(isISOdate('')).toBe(false);
            expect(isISOdate('invalid')).toBe(false);
            expect(isISOdate('2023-1-1')).toBe(false); // User forgot leading zeros

            // Valid inputs
            expect(isISOdate('2023-01-01')).toBe(true);
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { isISOdate };
}
