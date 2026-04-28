// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract both isValidDate and isISOdate functions to avoid jQuery dependencies
const isValidDateFunctionMatch = functionsJs.match(
    /function isValidDate\(input, separator\) \{[\s\S]*?\n\}/
);
if (isValidDateFunctionMatch) {
    eval(isValidDateFunctionMatch[0]);
}

const isISOdateFunctionMatch = functionsJs.match(
    /function isISOdate\(input, pattern, strict = false\) \{[\s\S]*?\n\}/
);
if (isISOdateFunctionMatch) {
    eval(isISOdateFunctionMatch[0]);
}

// Test suite
describe('isValidDate function', function () {
    describe('Input validation', function () {
        it('should handle null input', function () {
            expect(isValidDate(null)).toBe(false);
        });

        it('should handle undefined input', function () {
            expect(isValidDate(undefined)).toBe(false);
        });

        it('should handle non-string input', function () {
            expect(isValidDate(123)).toBe(false);
            expect(isValidDate(true)).toBe(false);
            expect(isValidDate({})).toBe(false);
            expect(isValidDate([])).toBe(false);
            expect(isValidDate(new Date())).toBe(false);
        });

        it('should handle empty string', function () {
            expect(isValidDate('')).toBe(false);
        });

        it('should handle whitespace-only string', function () {
            expect(isValidDate('   ')).toBe(false);
            expect(isValidDate('\t')).toBe(false);
            expect(isValidDate('\n')).toBe(false);
        });
    });

    describe('Default separator (-) validation', function () {
        it('should accept valid ISO dates with default separator', function () {
            expect(isValidDate('2023-01-01')).toBe(true);
            expect(isValidDate('2023-12-31')).toBe(true);
            expect(isValidDate('2020-02-29')).toBe(true); // Leap year
            expect(isValidDate('2023-02-28')).toBe(true);
        });

        it('should reject invalid dates with default separator', function () {
            expect(isValidDate('2023-02-30')).toBe(false); // Feb 30th doesn't exist
            expect(isValidDate('2021-02-29')).toBe(false); // Not a leap year
            expect(isValidDate('2023-04-31')).toBe(false); // April only has 30 days
            expect(isValidDate('2023-13-01')).toBe(false); // Month 13 doesn't exist
            expect(isValidDate('2023-00-01')).toBe(false); // Month 0 doesn't exist
            expect(isValidDate('2023-01-32')).toBe(false); // Jan 32nd doesn't exist
            expect(isValidDate('2023-01-00')).toBe(false); // Day 0 doesn't exist
        });

        it('should reject malformed dates with default separator', function () {
            expect(isValidDate('2023-1-1')).toBe(false); // Missing leading zeros
            expect(isValidDate('23-01-01')).toBe(false); // 2-digit year
            expect(isValidDate('2023-123-01')).toBe(false); // 3-digit month
            expect(isValidDate('2023-01-123')).toBe(false); // 3-digit day
        });
    });

    describe('Custom separator validation', function () {
        it('should accept valid dates with forward slash separator', function () {
            expect(isValidDate('2023/01/01', '/')).toBe(true);
            expect(isValidDate('2023/12/31', '/')).toBe(true);
            expect(isValidDate('2020/02/29', '/')).toBe(true); // Leap year
        });

        it('should reject invalid dates with forward slash separator', function () {
            expect(isValidDate('2023/02/30', '/')).toBe(false); // Feb 30th doesn't exist
            expect(isValidDate('2021/02/29', '/')).toBe(false); // Not a leap year
            expect(isValidDate('2023/13/01', '/')).toBe(false); // Month 13 doesn't exist
        });

        it('should accept valid dates with dot separator', function () {
            expect(isValidDate('2023.01.01', '.')).toBe(true);
            expect(isValidDate('2023.12.31', '.')).toBe(true);
            expect(isValidDate('2020.02.29', '.')).toBe(true); // Leap year
        });

        it('should reject invalid dates with dot separator', function () {
            expect(isValidDate('2023.02.30', '.')).toBe(false);
            expect(isValidDate('2023.13.01', '.')).toBe(false);
        });

        it('should accept valid dates with space separator', function () {
            expect(isValidDate('2023 01 01', ' ')).toBe(true);
            expect(isValidDate('2023 12 31', ' ')).toBe(true);
        });

        it('should reject invalid dates with space separator', function () {
            expect(isValidDate('2023 02 30', ' ')).toBe(false);
            expect(isValidDate('2023 13 01', ' ')).toBe(false);
        });

        it('should handle custom separators with format validation', function () {
            expect(isValidDate('2023|01|01', '|')).toBe(true);
            expect(isValidDate('2023_01_01', '_')).toBe(true);
            expect(isValidDate('2023@01@01', '@')).toBe(true);
        });
    });

    describe('Format validation for custom separators', function () {
        it('should require exactly 3 parts', function () {
            expect(isValidDate('2023/01', '/')).toBe(false); // Only 2 parts
            expect(isValidDate('2023/01/01/extra', '/')).toBe(false); // 4 parts
            expect(isValidDate('2023', '/')).toBe(false); // Only 1 part
        });

        it('should require 4-digit year', function () {
            expect(isValidDate('23/01/01', '/')).toBe(false); // 2-digit year
            expect(isValidDate('123/01/01', '/')).toBe(false); // 3-digit year
            expect(isValidDate('12345/01/01', '/')).toBe(false); // 5-digit year
        });

        it('should require 2-digit month', function () {
            expect(isValidDate('2023/1/01', '/')).toBe(false); // 1-digit month
            expect(isValidDate('2023/123/01', '/')).toBe(false); // 3-digit month
        });

        it('should require 2-digit day', function () {
            expect(isValidDate('2023/01/1', '/')).toBe(false); // 1-digit day
            expect(isValidDate('2023/01/123', '/')).toBe(false); // 3-digit day
        });

        it('should only accept YYYY-MM-DD order', function () {
            // The function enforces YYYY-MM-DD order, so other orders should fail
            expect(isValidDate('01/01/2023', '/')).toBe(false); // DD/MM/YYYY or MM/DD/YYYY
            expect(isValidDate('01/2023/01', '/')).toBe(false); // MM/YYYY/DD
        });
    });

    describe('Leap year validation', function () {
        it('should accept February 29th in leap years', function () {
            expect(isValidDate('2020-02-29')).toBe(true); // Divisible by 4
            expect(isValidDate('2000-02-29')).toBe(true); // Divisible by 400
            expect(isValidDate('1600-02-29')).toBe(true); // Divisible by 400
        });

        it('should reject February 29th in non-leap years', function () {
            expect(isValidDate('2021-02-29')).toBe(false); // Not divisible by 4
            expect(isValidDate('2022-02-29')).toBe(false); // Not divisible by 4
            expect(isValidDate('2023-02-29')).toBe(false); // Not divisible by 4
            expect(isValidDate('1900-02-29')).toBe(false); // Divisible by 100 but not 400
            expect(isValidDate('2100-02-29')).toBe(false); // Divisible by 100 but not 400
        });

        it('should handle leap year validation with custom separators', function () {
            expect(isValidDate('2020/02/29', '/')).toBe(true); // Leap year
            expect(isValidDate('2021/02/29', '/')).toBe(false); // Not leap year
            expect(isValidDate('2020.02.29', '.')).toBe(true); // Leap year
            expect(isValidDate('2021.02.29', '.')).toBe(false); // Not leap year
        });
    });

    describe('Month-specific day validation', function () {
        it('should validate 31-day months', function () {
            const thirtyOneDayMonths = [
                '01',
                '03',
                '05',
                '07',
                '08',
                '10',
                '12',
            ];
            thirtyOneDayMonths.forEach(month => {
                expect(isValidDate(`2023-${month}-31`)).toBe(true);
                expect(isValidDate(`2023/${month}/31`, '/')).toBe(true);
            });
        });

        it('should validate 30-day months', function () {
            const thirtyDayMonths = ['04', '06', '09', '11'];
            thirtyDayMonths.forEach(month => {
                expect(isValidDate(`2023-${month}-30`)).toBe(true);
                expect(isValidDate(`2023-${month}-31`)).toBe(false); // Should reject 31st
            });
        });

        it('should validate February in non-leap years', function () {
            expect(isValidDate('2023-02-28')).toBe(true); // Last day of Feb in non-leap year
            expect(isValidDate('2023-02-29')).toBe(false); // Feb 29th in non-leap year
        });

        it('should validate February in leap years', function () {
            expect(isValidDate('2020-02-28')).toBe(true); // Feb 28th in leap year
            expect(isValidDate('2020-02-29')).toBe(true); // Feb 29th in leap year
            expect(isValidDate('2020-02-30')).toBe(false); // Feb 30th doesn't exist
        });
    });

    describe('Integration with isISOdate function', function () {
        it('should use isISOdate for validation with default separator', function () {
            // These should match isISOdate behavior exactly
            expect(isValidDate('2023-01-01')).toBe(true);
            expect(isValidDate('2023-02-30')).toBe(false);
            expect(isValidDate('2023-13-01')).toBe(false);
        });

        it('should convert custom separator formats to ISO format', function () {
            // Custom separators should be converted to ISO format internally
            expect(isValidDate('2023/01/01', '/')).toBe(
                isValidDate('2023-01-01')
            );
            expect(isValidDate('2023.01.01', '.')).toBe(
                isValidDate('2023-01-01')
            );
            expect(isValidDate('2023 01 01', ' ')).toBe(
                isValidDate('2023-01-01')
            );
        });

        it('should maintain consistency between separators for same date', function () {
            const validDate = '2023-01-15';
            const invalidDate = '2023-02-30';

            expect(isValidDate(validDate)).toBe(true);
            expect(isValidDate('2023/01/15', '/')).toBe(true);
            expect(isValidDate('2023.01.15', '.')).toBe(true);

            expect(isValidDate(invalidDate)).toBe(false);
            expect(isValidDate('2023/02/30', '/')).toBe(false);
            expect(isValidDate('2023.02.30', '.')).toBe(false);
        });
    });

    describe('Real-world date scenarios', function () {
        it('should validate common publication dates', function () {
            expect(isValidDate('2023-01-15')).toBe(true);
            expect(isValidDate('2022-12-25')).toBe(true);
            expect(isValidDate('2021-07-04')).toBe(true);
        });

        it('should validate historical dates', function () {
            expect(isValidDate('1969-07-20')).toBe(true); // Moon landing
            expect(isValidDate('1945-05-08')).toBe(true); // VE Day
            expect(isValidDate('1776-07-04')).toBe(true); // US Independence
        });

        it('should validate future dates', function () {
            expect(isValidDate('2030-01-01')).toBe(true);
            expect(isValidDate('2050-12-31')).toBe(true);
        });

        it('should handle edge case years', function () {
            expect(isValidDate('0001-01-01')).toBe(true); // Year 1 AD
            expect(isValidDate('1970-01-01')).toBe(true); // Unix epoch
            expect(isValidDate('2038-01-19')).toBe(true); // Near 32-bit Unix timestamp limit
            expect(isValidDate('9999-12-31')).toBe(true); // Far future
        });
    });

    describe('Error handling and edge cases', function () {
        it('should handle dates with leading/trailing whitespace', function () {
            expect(isValidDate(' 2023-01-01 ')).toBe(true);
            expect(isValidDate('  2023/01/01  ', '/')).toBe(true);
            expect(isValidDate('\t2023.01.01\n', '.')).toBe(true);
        });

        it('should reject dates with wrong separators', function () {
            expect(isValidDate('2023-01-01', '/')).toBe(false); // Expects / but has -
            expect(isValidDate('2023/01/01', '-')).toBe(false); // Expects - but has /
            expect(isValidDate('2023.01.01', '/')).toBe(false); // Expects / but has .
        });

        it('should handle non-numeric parts', function () {
            expect(isValidDate('abcd/01/01', '/')).toBe(false); // Non-numeric year
            expect(isValidDate('2023/ab/01', '/')).toBe(false); // Non-numeric month
            expect(isValidDate('2023/01/ab', '/')).toBe(false); // Non-numeric day
        });

        it('should reject dates with mixed separators', function () {
            expect(isValidDate('2023-01/01')).toBe(false); // Mixed - and /
            expect(isValidDate('2023/01.01', '/')).toBe(false); // Mixed / and .
            expect(isValidDate('2023.01-01', '.')).toBe(false); // Mixed . and -
        });
    });

    describe('Performance considerations', function () {
        it('should be reasonably fast for validation', function () {
            const validDates = [
                '2023-01-01',
                '2023-02-28',
                '2023-12-31',
                '2023/01/01',
                '2023/02/28',
                '2023/12/31',
            ];
            const separators = ['-', '/', '/', '/', '/', '/'];

            const start = Date.now();

            for (let i = 0; i < 100; i++) {
                validDates.forEach((date, index) => {
                    isValidDate(
                        date,
                        separators[index] === '-'
                            ? undefined
                            : separators[index]
                    );
                });
            }

            const end = Date.now();
            expect(end - start).toBeLessThan(1000);
        });
    });

    describe('Backward compatibility', function () {
        it('should maintain compatibility with original function signature', function () {
            // Original function: isValidDate(input, separator)
            expect(isValidDate('2023-01-01')).toBe(true);
            expect(isValidDate('2023/01/01', '/')).toBe(true);
        });

        it('should work with existing code patterns', function () {
            // Common usage patterns that should still work
            expect(isValidDate('2023-01-01', '-')).toBe(true);
            expect(isValidDate('2023-01-01', undefined)).toBe(true);
        });
    });

    describe('Integration with existing codebase', function () {
        it('should work with updateDeadlineTag function expectations', function () {
            // This function is used in updateDeadlineTag, so it should handle typical inputs
            expect(isValidDate('2023-01-01')).toBe(true);
            expect(isValidDate('2023-12-31')).toBe(true);
            expect(isValidDate('2023-02-30')).toBe(false); // Should catch invalid dates
        });

        it('should handle form input validation scenarios', function () {
            // Typical form inputs
            expect(isValidDate('2023-01-01')).toBe(true); // Valid input
            expect(isValidDate('2023-1-1')).toBe(false); // User forgot leading zeros
            expect(isValidDate('01-01-2023')).toBe(false); // Wrong format order
            expect(isValidDate('')).toBe(false); // Empty input
            expect(isValidDate('invalid')).toBe(false); // Invalid input
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { isValidDate, isISOdate };
}
