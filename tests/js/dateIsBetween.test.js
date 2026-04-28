/**
 * Test suite for dateIsBetween function
 * Tests the improved version with proper input validation and error handling
 */

// Load the functions.js file and extract only the dateIsBetween function
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the dateIsBetween function to avoid jQuery dependencies
const dateIsBetweenFunctionMatch = functionsJs.match(
    /function dateIsBetween\(input, min, max\) \{[\s\S]*?\n\}/
);
if (dateIsBetweenFunctionMatch) {
    eval(dateIsBetweenFunctionMatch[0]);
}

// Test suite
describe('dateIsBetween function', function () {
    describe('Basic date range validation', function () {
        it('should return true for date within range', function () {
            expect(
                dateIsBetween('2023-06-15', '2023-06-01', '2023-06-30')
            ).toBe(true);
            expect(
                dateIsBetween('2023-12-25', '2023-01-01', '2023-12-31')
            ).toBe(true);
        });

        it('should return false for date outside range', function () {
            expect(
                dateIsBetween('2023-05-31', '2023-06-01', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('2023-07-01', '2023-06-01', '2023-06-30')
            ).toBe(false);
        });

        it('should return true for date exactly at boundaries (inclusive)', function () {
            expect(
                dateIsBetween('2023-06-01', '2023-06-01', '2023-06-30')
            ).toBe(true);
            expect(
                dateIsBetween('2023-06-30', '2023-06-01', '2023-06-30')
            ).toBe(true);
        });

        it('should handle same min and max date', function () {
            expect(
                dateIsBetween('2023-06-15', '2023-06-15', '2023-06-15')
            ).toBe(true);
            expect(
                dateIsBetween('2023-06-14', '2023-06-15', '2023-06-15')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-16', '2023-06-15', '2023-06-15')
            ).toBe(false);
        });
    });

    describe('Missing boundary parameters', function () {
        it('should return true when min is missing', function () {
            expect(dateIsBetween('2023-06-15', null, '2023-06-30')).toBe(true);
            expect(dateIsBetween('2023-06-15', undefined, '2023-06-30')).toBe(
                true
            );
            expect(dateIsBetween('2023-06-15', '', '2023-06-30')).toBe(true);
        });

        it('should return true when max is missing', function () {
            expect(dateIsBetween('2023-06-15', '2023-06-01', null)).toBe(true);
            expect(dateIsBetween('2023-06-15', '2023-06-01', undefined)).toBe(
                true
            );
            expect(dateIsBetween('2023-06-15', '2023-06-01', '')).toBe(true);
        });

        it('should return true when both boundaries are missing', function () {
            expect(dateIsBetween('2023-06-15', null, null)).toBe(true);
            expect(dateIsBetween('2023-06-15', undefined, undefined)).toBe(
                true
            );
            expect(dateIsBetween('2023-06-15', '', '')).toBe(true);
        });
    });

    describe('Invalid date handling', function () {
        it('should return false for invalid input date', function () {
            expect(
                dateIsBetween('invalid-date', '2023-06-01', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('2023-13-45', '2023-06-01', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('not-a-date', '2023-06-01', '2023-06-30')
            ).toBe(false);
        });

        it('should return false for invalid min date', function () {
            expect(
                dateIsBetween('2023-06-15', 'invalid-date', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-15', '2023-13-45', '2023-06-30')
            ).toBe(false);
        });

        it('should return false for invalid max date', function () {
            expect(
                dateIsBetween('2023-06-15', '2023-06-01', 'invalid-date')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-15', '2023-06-01', '2023-13-45')
            ).toBe(false);
        });

        it('should return false when any date is invalid', function () {
            expect(dateIsBetween('invalid', 'invalid', 'invalid')).toBe(false);
            expect(dateIsBetween('2023-06-15', 'invalid', 'invalid')).toBe(
                false
            );
            expect(dateIsBetween('invalid', '2023-06-01', 'invalid')).toBe(
                false
            );
        });
    });

    describe('Date range validation', function () {
        it('should return false when min > max', function () {
            expect(
                dateIsBetween('2023-06-15', '2023-06-30', '2023-06-01')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-15', '2023-12-31', '2023-01-01')
            ).toBe(false);
        });

        it('should handle edge case where input is within inverted range', function () {
            // Even if input would be "between" the inverted values, it should return false
            expect(
                dateIsBetween('2023-06-15', '2023-06-30', '2023-06-01')
            ).toBe(false);
        });
    });

    describe('Different date formats', function () {
        it('should handle ISO date strings (YYYY-MM-DD)', function () {
            expect(
                dateIsBetween('2023-06-15', '2023-06-01', '2023-06-30')
            ).toBe(true);
            expect(
                dateIsBetween('2023-05-31', '2023-06-01', '2023-06-30')
            ).toBe(false);
        });

        it('should handle US date format (MM/DD/YYYY)', function () {
            expect(
                dateIsBetween('06/15/2023', '06/01/2023', '06/30/2023')
            ).toBe(true);
            expect(
                dateIsBetween('05/31/2023', '06/01/2023', '06/30/2023')
            ).toBe(false);
        });

        it('should handle full date strings', function () {
            expect(
                dateIsBetween('June 15, 2023', 'June 1, 2023', 'June 30, 2023')
            ).toBe(true);
            expect(
                dateIsBetween('May 31, 2023', 'June 1, 2023', 'June 30, 2023')
            ).toBe(false);
        });

        it('should handle mixed date formats', function () {
            expect(
                dateIsBetween('2023-06-15', 'June 1, 2023', '06/30/2023')
            ).toBe(true);
            expect(
                dateIsBetween('June 15, 2023', '2023-06-01', '06/30/2023')
            ).toBe(true);
        });
    });

    describe('Date object inputs', function () {
        it('should handle Date objects as input', function () {
            const inputDate = new Date('2023-06-15');
            const minDate = new Date('2023-06-01');
            const maxDate = new Date('2023-06-30');

            expect(dateIsBetween(inputDate, minDate, maxDate)).toBe(true);
        });

        it('should handle mixed Date objects and strings', function () {
            const inputDate = new Date('2023-06-15');
            expect(dateIsBetween(inputDate, '2023-06-01', '2023-06-30')).toBe(
                true
            );
            expect(
                dateIsBetween(
                    '2023-06-15',
                    new Date('2023-06-01'),
                    '2023-06-30'
                )
            ).toBe(true);
            expect(
                dateIsBetween(
                    '2023-06-15',
                    '2023-06-01',
                    new Date('2023-06-30')
                )
            ).toBe(true);
        });
    });

    describe('Time component handling', function () {
        it('should handle dates with time components', function () {
            expect(
                dateIsBetween('2023-06-15T10:30:00', '2023-06-01', '2023-06-30')
            ).toBe(true);
            expect(
                dateIsBetween(
                    '2023-06-15',
                    '2023-06-01T00:00:00',
                    '2023-06-30T23:59:59'
                )
            ).toBe(true);
        });

        it('should handle same date with different times', function () {
            expect(
                dateIsBetween(
                    '2023-06-15T10:30:00',
                    '2023-06-15T09:00:00',
                    '2023-06-15T11:00:00'
                )
            ).toBe(true);
            expect(
                dateIsBetween(
                    '2023-06-15T08:30:00',
                    '2023-06-15T09:00:00',
                    '2023-06-15T11:00:00'
                )
            ).toBe(false);
            expect(
                dateIsBetween(
                    '2023-06-15T12:30:00',
                    '2023-06-15T09:00:00',
                    '2023-06-15T11:00:00'
                )
            ).toBe(false);
        });
    });

    describe('Edge cases and special values', function () {
        it('should handle empty strings', function () {
            expect(dateIsBetween('', '2023-06-01', '2023-06-30')).toBe(false);
            expect(dateIsBetween('2023-06-15', '', '')).toBe(true); // Missing boundaries
        });

        it('should handle null values', function () {
            expect(dateIsBetween(null, '2023-06-01', '2023-06-30')).toBe(false);
            expect(dateIsBetween('2023-06-15', null, null)).toBe(true); // Missing boundaries
        });

        it('should handle undefined values', function () {
            expect(dateIsBetween(undefined, '2023-06-01', '2023-06-30')).toBe(
                false
            );
            expect(dateIsBetween('2023-06-15', undefined, undefined)).toBe(
                true
            ); // Missing boundaries
        });
    });

    describe('Year boundaries and long ranges', function () {
        it('should handle year boundaries', function () {
            expect(
                dateIsBetween('2023-01-01', '2022-12-31', '2023-01-02')
            ).toBe(true);
            expect(
                dateIsBetween('2022-12-31', '2022-12-30', '2023-01-01')
            ).toBe(true);
        });

        it('should handle multi-year ranges', function () {
            expect(
                dateIsBetween('2022-06-15', '2020-01-01', '2025-12-31')
            ).toBe(true);
            expect(
                dateIsBetween('2019-12-31', '2020-01-01', '2025-12-31')
            ).toBe(false);
            expect(
                dateIsBetween('2026-01-01', '2020-01-01', '2025-12-31')
            ).toBe(false);
        });

        it('should handle leap year dates', function () {
            expect(
                dateIsBetween('2024-02-29', '2024-02-01', '2024-03-01')
            ).toBe(true);
            // Note: JavaScript Date constructor "corrects" 2023-02-29 to 2023-03-01
            // So this becomes a valid date that falls within the range
            expect(
                dateIsBetween('2023-02-29', '2023-02-01', '2023-03-01')
            ).toBe(true); // Becomes March 1, 2023
        });
    });

    describe('Performance and practical use cases', function () {
        it('should handle typical date validation scenarios', function () {
            // Event date validation
            expect(
                dateIsBetween('2023-07-15', '2023-07-01', '2023-07-31')
            ).toBe(true);

            // Age validation (birth date range)
            const today = new Date();
            const hundredYearsAgo = new Date(
                today.getFullYear() - 100,
                today.getMonth(),
                today.getDate()
            );
            const eighteenYearsAgo = new Date(
                today.getFullYear() - 18,
                today.getMonth(),
                today.getDate()
            );

            expect(
                dateIsBetween(
                    '1990-06-15',
                    hundredYearsAgo.toISOString().split('T')[0],
                    eighteenYearsAgo.toISOString().split('T')[0]
                )
            ).toBe(true);
        });

        it('should handle business date ranges', function () {
            // Quarter validation
            expect(
                dateIsBetween('2023-05-15', '2023-04-01', '2023-06-30')
            ).toBe(true); // Q2
            expect(
                dateIsBetween('2023-03-31', '2023-04-01', '2023-06-30')
            ).toBe(false); // Before Q2
            expect(
                dateIsBetween('2023-07-01', '2023-04-01', '2023-06-30')
            ).toBe(false); // After Q2
        });

        it('should perform well with many date comparisons', function () {
            const start = Date.now();

            // Test 1000 date comparisons
            for (let i = 0; i < 1000; i++) {
                dateIsBetween('2023-06-15', '2023-06-01', '2023-06-30');
            }

            const duration = Date.now() - start;
            expect(duration).toBeLessThan(100); // Should complete in less than 100ms
        });
    });

    describe('Comparison with original implementation issues', function () {
        it('should not have variable name collision issues', function () {
            // The original had var min/max collision - this should work correctly
            const testMin = '2023-06-01';
            const testMax = '2023-06-30';

            expect(dateIsBetween('2023-06-15', testMin, testMax)).toBe(true);
            expect(testMin).toBe('2023-06-01'); // Should not be modified
            expect(testMax).toBe('2023-06-30'); // Should not be modified
        });

        it('should properly validate dates instead of accepting Invalid Date objects', function () {
            // Original would create Invalid Date objects but still compare them
            expect(
                dateIsBetween('invalid-date', '2023-06-01', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-15', 'invalid-min', '2023-06-30')
            ).toBe(false);
            expect(
                dateIsBetween('2023-06-15', '2023-06-01', 'invalid-max')
            ).toBe(false);
        });

        it('should handle min > max scenario properly', function () {
            // Original didn't check for this logical error
            expect(
                dateIsBetween('2023-06-15', '2023-06-30', '2023-06-01')
            ).toBe(false);
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { dateIsBetween };
}
