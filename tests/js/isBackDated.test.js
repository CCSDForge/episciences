// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the isBackDated function to avoid jQuery dependencies
const isBackDatedFunctionMatch = functionsJs.match(/function isBackDated\(input\) \{[\s\S]*?\n\}/);
if (isBackDatedFunctionMatch) {
    eval(isBackDatedFunctionMatch[0]);
}

describe('isBackDated', function () {
    describe('Input validation', function () {
        it('should return false for null and undefined inputs', function () {
            expect(isBackDated(null)).toBe(false);
            expect(isBackDated(undefined)).toBe(false);
        });

        it('should return false for empty string', function () {
            expect(isBackDated('')).toBe(false);
        });

        it('should return false for invalid input types', function () {
            expect(isBackDated(true)).toBe(false);
            expect(isBackDated(false)).toBe(false);
            expect(isBackDated([])).toBe(false);
            expect(isBackDated({})).toBe(false);
            expect(isBackDated(function () {})).toBe(false);
        });

        it('should return false for invalid date strings', function () {
            expect(isBackDated('invalid-date')).toBe(false);
            expect(isBackDated('not a date')).toBe(false);
            expect(isBackDated('abc')).toBe(false);
            expect(isBackDated('2023-13-45')).toBe(false); // Invalid month/day
        });

        it('should return false for invalid numbers', function () {
            expect(isBackDated(NaN)).toBe(false);
            expect(isBackDated(Infinity)).toBe(false);
            expect(isBackDated(-Infinity)).toBe(false);
        });
    });

    describe('Date object inputs', function () {
        it('should return true for past Date objects', function () {
            const pastDate = new Date('2020-01-01');
            const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);

            expect(isBackDated(pastDate)).toBe(true);
            expect(isBackDated(yesterday)).toBe(true);
        });

        it('should return false for future Date objects', function () {
            const futureDate = new Date('2030-12-31');
            const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);

            expect(isBackDated(futureDate)).toBe(false);
            expect(isBackDated(tomorrow)).toBe(false);
        });

        it('should handle dates very close to now', function () {
            const fewSecondsAgo = new Date(Date.now() - 5000); // 5 seconds ago
            const fewSecondsLater = new Date(Date.now() + 5000); // 5 seconds later

            expect(isBackDated(fewSecondsAgo)).toBe(true);
            expect(isBackDated(fewSecondsLater)).toBe(false);
        });

        it('should return false for invalid Date objects', function () {
            const invalidDate = new Date('invalid');
            expect(isBackDated(invalidDate)).toBe(false);
        });
    });

    describe('String date inputs', function () {
        it('should return true for past date strings', function () {
            expect(isBackDated('2020-01-01')).toBe(true);
            expect(isBackDated('2020-12-31T23:59:59')).toBe(true);
            expect(isBackDated('January 1, 2020')).toBe(true);
            expect(isBackDated('01/01/2020')).toBe(true);
        });

        it('should return false for future date strings', function () {
            expect(isBackDated('2030-12-31')).toBe(false);
            expect(isBackDated('2030-01-01T00:00:00')).toBe(false);
            expect(isBackDated('December 31, 2030')).toBe(false);
            expect(isBackDated('12/31/2030')).toBe(false);
        });

        it('should handle ISO date strings', function () {
            const pastISO = '2020-06-15T10:30:00.000Z';
            const futureISO = '2030-06-15T10:30:00.000Z';

            expect(isBackDated(pastISO)).toBe(true);
            expect(isBackDated(futureISO)).toBe(false);
        });

        it('should handle various date string formats', function () {
            // Past dates in different formats (using formats that JavaScript Date constructor reliably parses)
            expect(isBackDated('2020/06/15')).toBe(true);
            expect(isBackDated('Jun 15, 2020')).toBe(true);
            expect(isBackDated('15 Jun 2020')).toBe(true);
            expect(isBackDated('2020-06-15')).toBe(true);

            // Future dates in different formats
            expect(isBackDated('2030/06/15')).toBe(false);
            expect(isBackDated('Jun 15, 2030')).toBe(false);
            expect(isBackDated('2030-06-15')).toBe(false);
        });

        it('should handle whitespace in date strings', function () {
            expect(isBackDated('  2020-01-01  ')).toBe(true);
            expect(isBackDated('\t2030-12-31\t')).toBe(false);
        });
    });

    describe('Numeric inputs', function () {
        it('should return true for past timestamps', function () {
            const pastTimestamp = new Date('2020-01-01').getTime();
            const pastTimestampNumber = Date.now() - 365 * 24 * 60 * 60 * 1000; // 1 year ago

            expect(isBackDated(pastTimestamp)).toBe(true);
            expect(isBackDated(pastTimestampNumber)).toBe(true);
        });

        it('should return false for future timestamps', function () {
            const futureTimestamp = new Date('2030-12-31').getTime();
            const futureTimestampNumber =
                Date.now() + 365 * 24 * 60 * 60 * 1000; // 1 year from now

            expect(isBackDated(futureTimestamp)).toBe(false);
            expect(isBackDated(futureTimestampNumber)).toBe(false);
        });

        it('should handle edge case timestamps', function () {
            expect(isBackDated(0)).toBe(true); // Unix epoch (1970-01-01)
            expect(isBackDated(1)).toBe(true); // 1 millisecond after epoch
        });
    });

    describe('Edge cases and timing', function () {
        it('should handle dates around current time consistently', function () {
            const now = new Date();
            const justBefore = new Date(now.getTime() - 1000); // 1 second ago
            const justAfter = new Date(now.getTime() + 1000); // 1 second later

            expect(isBackDated(justBefore)).toBe(true);
            expect(isBackDated(justAfter)).toBe(false);
        });

        it('should be consistent with repeated calls', function () {
            const testDate = new Date('2020-01-01');

            for (let i = 0; i < 5; i++) {
                expect(isBackDated(testDate)).toBe(true);
            }
        });

        it('should handle leap years correctly', function () {
            expect(isBackDated('2020-02-29')).toBe(true); // Valid leap year date in the past
            expect(isBackDated('2028-02-29')).toBe(false); // Future leap year date
        });

        it('should handle daylight saving time transitions', function () {
            // These dates might have DST transitions depending on timezone
            expect(isBackDated('2020-03-15T02:30:00')).toBe(true);
            expect(isBackDated('2020-11-01T01:30:00')).toBe(true);
        });
    });

    describe('Real-world scenarios', function () {
        it('should identify expired deadlines', function () {
            const expiredDeadline = new Date('2020-12-31T23:59:59');
            expect(isBackDated(expiredDeadline)).toBe(true);
        });

        it('should identify valid future deadlines', function () {
            const futureDeadline = new Date('2030-12-31T23:59:59');
            expect(isBackDated(futureDeadline)).toBe(false);
        });

        it('should handle publication dates', function () {
            expect(isBackDated('2020-06-15')).toBe(true); // Past publication
            expect(isBackDated('2030-06-15')).toBe(false); // Future publication
        });

        it('should handle event dates', function () {
            const pastEvent = '2020-03-15T14:30:00';
            const futureEvent = '2030-03-15T14:30:00';

            expect(isBackDated(pastEvent)).toBe(true);
            expect(isBackDated(futureEvent)).toBe(false);
        });
    });

    describe('Performance and reliability', function () {
        it('should handle various edge cases without throwing errors', function () {
            const edgeCases = [
                null,
                undefined,
                '',
                'invalid',
                NaN,
                Infinity,
                -Infinity,
                {},
                [],
                '2020-13-45', // Invalid date
                '32/12/2020', // Invalid format
            ];

            edgeCases.forEach(testCase => {
                expect(() => isBackDated(testCase)).not.toThrow();
            });
        });

        it('should be performant with multiple calls', function () {
            const testDate = new Date('2020-01-01');
            const start = Date.now();

            for (let i = 0; i < 1000; i++) {
                isBackDated(testDate);
            }

            const duration = Date.now() - start;
            expect(duration).toBeLessThan(100); // Should complete within 100ms
        });

        it('should handle different timezone inputs correctly', function () {
            // These should all represent the same moment in time
            expect(isBackDated('2020-01-01T00:00:00Z')).toBe(true);
            expect(isBackDated('2020-01-01T01:00:00+01:00')).toBe(true);
            expect(isBackDated('2019-12-31T19:00:00-05:00')).toBe(true);
        });
    });

    describe('Boundary testing', function () {
        it('should handle very old dates', function () {
            expect(isBackDated('1900-01-01')).toBe(true);
            expect(isBackDated('1970-01-01')).toBe(true); // Unix epoch
        });

        it('should handle very future dates', function () {
            expect(isBackDated('2100-12-31')).toBe(false);
            expect(isBackDated('3000-01-01')).toBe(false);
        });

        it('should handle the current year boundary', function () {
            const currentYear = new Date().getFullYear();
            const pastInCurrentYear = `${currentYear}-01-01`;
            const futureInCurrentYear = `${currentYear}-12-31`;

            // Note: These results depend on when the test is run
            expect(() => isBackDated(pastInCurrentYear)).not.toThrow();
            expect(() => isBackDated(futureInCurrentYear)).not.toThrow();
        });
    });
});
