// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the readableBytes function to avoid jQuery dependencies
const readableBytesFunctionMatch = functionsJs.match(/function readableBytes\(bytes, locale\) \{[\s\S]*?\n\}/);
if (readableBytesFunctionMatch) {
    eval(readableBytesFunctionMatch[0]);
}

// Test suite
describe('readableBytes function', function () {
    describe('Input validation', function () {
        it('should handle null input', function () {
            expect(readableBytes(null, 'en')).toBe('0 bytes');
        });

        it('should handle undefined input', function () {
            expect(readableBytes(undefined, 'en')).toBe('0 bytes');
        });

        it('should convert valid string numbers to numbers', function () {
            expect(readableBytes('123', 'en')).toBe('123 bytes');
            expect(readableBytes('1024', 'en')).toBe('1 KB');
            expect(readableBytes('1536', 'en')).toBe('1.5 KB');
        });

        it('should handle invalid string input', function () {
            expect(readableBytes('abc', 'en')).toBe('0 bytes');
            expect(readableBytes('12abc', 'en')).toBe('0 bytes');
            expect(readableBytes('', 'en')).toBe('0 bytes');
            expect(readableBytes('   ', 'en')).toBe('0 bytes');
        });

        it('should handle NaN input', function () {
            expect(readableBytes(NaN, 'en')).toBe('0 bytes');
        });

        it('should handle negative numbers', function () {
            expect(readableBytes(-100, 'en')).toBe('0 bytes');
            expect(readableBytes(-1024, 'fr')).toBe('0 bytes');
        });

        it('should handle Infinity', function () {
            expect(readableBytes(Infinity, 'en')).toBe('0 bytes');
        });
    });

    describe('Zero bytes handling', function () {
        it('should handle zero bytes in English', function () {
            expect(readableBytes(0, 'en')).toBe('0 bytes');
        });

        it('should handle zero bytes in French', function () {
            expect(readableBytes(0, 'fr')).toBe('0 octet');
        });

        it('should handle zero bytes with no locale (defaults to English)', function () {
            expect(readableBytes(0)).toBe('0 bytes');
        });
    });

    describe('Byte unit (English)', function () {
        it('should handle singular byte', function () {
            expect(readableBytes(1, 'en')).toBe('1 byte');
        });

        it('should handle plural bytes', function () {
            expect(readableBytes(2, 'en')).toBe('2 bytes');
            expect(readableBytes(500, 'en')).toBe('500 bytes');
            expect(readableBytes(1023, 'en')).toBe('1023 bytes');
        });
    });

    describe('Byte unit (French)', function () {
        it('should handle singular octet', function () {
            expect(readableBytes(1, 'fr')).toBe('1 octet');
        });

        it('should handle plural octets', function () {
            expect(readableBytes(2, 'fr')).toBe('2 octets');
            expect(readableBytes(500, 'fr')).toBe('500 octets');
            expect(readableBytes(1023, 'fr')).toBe('1023 octets');
        });
    });

    describe('Kilobyte conversions (English)', function () {
        it('should convert 1024 bytes to 1 KB', function () {
            expect(readableBytes(1024, 'en')).toBe('1 KB');
        });

        it('should convert 1536 bytes to 1.5 KB', function () {
            expect(readableBytes(1536, 'en')).toBe('1.5 KB');
        });

        it('should convert 10240 bytes to 10 KB', function () {
            expect(readableBytes(10240, 'en')).toBe('10 KB');
        });

        it('should convert 102400 bytes to 100 KB', function () {
            expect(readableBytes(102400, 'en')).toBe('100 KB');
        });
    });

    describe('Kilobyte conversions (French)', function () {
        it('should convert 1024 bytes to 1 Ko', function () {
            expect(readableBytes(1024, 'fr')).toBe('1 Ko');
        });

        it('should convert 1536 bytes to 1.5 Ko', function () {
            expect(readableBytes(1536, 'fr')).toBe('1.5 Ko');
        });
    });

    describe('Megabyte conversions', function () {
        it('should convert 1048576 bytes to 1 MB (English)', function () {
            expect(readableBytes(1048576, 'en')).toBe('1 MB');
        });

        it('should convert 1048576 bytes to 1 Mo (French)', function () {
            expect(readableBytes(1048576, 'fr')).toBe('1 Mo');
        });

        it('should convert 5242880 bytes to 5 MB', function () {
            expect(readableBytes(5242880, 'en')).toBe('5 MB');
        });

        it('should handle fractional MB values', function () {
            expect(readableBytes(1572864, 'en')).toBe('1.5 MB'); // 1.5 MB
        });
    });

    describe('Gigabyte conversions', function () {
        it('should convert 1073741824 bytes to 1 GB (English)', function () {
            expect(readableBytes(1073741824, 'en')).toBe('1 GB');
        });

        it('should convert 1073741824 bytes to 1 Go (French)', function () {
            expect(readableBytes(1073741824, 'fr')).toBe('1 Go');
        });

        it('should handle large GB values', function () {
            expect(readableBytes(10737418240, 'en')).toBe('10 GB'); // 10 GB
        });
    });

    describe('Terabyte conversions', function () {
        it('should convert 1099511627776 bytes to 1 TB (English)', function () {
            expect(readableBytes(1099511627776, 'en')).toBe('1 TB');
        });

        it('should convert 1099511627776 bytes to 1 To (French)', function () {
            expect(readableBytes(1099511627776, 'fr')).toBe('1 To');
        });
    });

    describe('Petabyte conversions', function () {
        it('should convert 1125899906842624 bytes to 1 PB (English)', function () {
            expect(readableBytes(1125899906842624, 'en')).toBe('1 PB');
        });

        it('should convert 1125899906842624 bytes to 1 Po (French)', function () {
            expect(readableBytes(1125899906842624, 'fr')).toBe('1 Po');
        });
    });

    describe('Precision handling', function () {
        it('should round values >= 100 to whole numbers', function () {
            expect(readableBytes(104857600, 'en')).toBe('100 MB'); // 100 MB exactly
            expect(readableBytes(125829120, 'en')).toBe('120 MB'); // 120 MB
        });

        it('should show 1 decimal place for values >= 10 and < 100', function () {
            expect(readableBytes(52428800, 'en')).toBe('50 MB'); // 50 MB
            expect(readableBytes(62914560, 'en')).toBe('60 MB'); // 60 MB
        });

        it('should show 2 decimal places for values < 10', function () {
            expect(readableBytes(1050000, 'en')).toBe('1 MB'); // ~1.00 MB (rounds to 1)
            expect(readableBytes(5242880, 'en')).toBe('5 MB'); // 5 MB exactly
        });
    });

    describe('Edge cases', function () {
        it('should handle very large numbers (beyond PB)', function () {
            const veryLarge = Math.pow(1024, 7); // Exabyte range
            const result = readableBytes(veryLarge, 'en');
            expect(result).toContain('PB'); // Should clamp to PB
        });

        it('should handle decimal input', function () {
            expect(readableBytes(1024.5, 'en')).toBe('1 KB');
        });

        it('should default to English when locale is not fr', function () {
            expect(readableBytes(1024, 'es')).toBe('1 KB');
            expect(readableBytes(1024, 'de')).toBe('1 KB');
            expect(readableBytes(1024, null)).toBe('1 KB');
        });
    });

    describe('Common file sizes', function () {
        it('should handle typical document sizes', function () {
            expect(readableBytes(50000, 'en')).toBe('48.8 KB'); // ~50KB document
            expect(readableBytes(2097152, 'en')).toBe('2 MB'); // 2MB image
            expect(readableBytes(10485760, 'en')).toBe('10 MB'); // 10MB file
        });

        it('should handle typical video sizes', function () {
            expect(readableBytes(734003200, 'en')).toBe('700 MB'); // ~700MB video
            expect(readableBytes(4294967296, 'en')).toBe('4 GB'); // 4GB video
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { readableBytes };
}
