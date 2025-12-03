// Load the functions.js file
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the isValidHttpUrl function to avoid jQuery dependencies
const isValidHttpUrlFunctionMatch = functionsJs.match(
    /function isValidHttpUrl\(string\) \{[\s\S]*?\n\}/
);
if (isValidHttpUrlFunctionMatch) {
    eval(isValidHttpUrlFunctionMatch[0]);
}

describe('isValidHttpUrl', function () {
    describe('Input validation', function () {
        it('should return false for non-string inputs', function () {
            expect(isValidHttpUrl(null)).toBe(false);
            expect(isValidHttpUrl(undefined)).toBe(false);
            expect(isValidHttpUrl(123)).toBe(false);
            expect(isValidHttpUrl(true)).toBe(false);
            expect(isValidHttpUrl(false)).toBe(false);
            expect(isValidHttpUrl([])).toBe(false);
            expect(isValidHttpUrl({})).toBe(false);
        });

        it('should return false for empty or whitespace-only strings', function () {
            expect(isValidHttpUrl('')).toBe(false);
            expect(isValidHttpUrl('   ')).toBe(false);
            expect(isValidHttpUrl('\t')).toBe(false);
            expect(isValidHttpUrl('\n')).toBe(false);
            expect(isValidHttpUrl('\r\n')).toBe(false);
        });

        it('should handle strings with leading/trailing whitespace', function () {
            expect(isValidHttpUrl('  https://example.com  ')).toBe(true);
            expect(isValidHttpUrl('\t\nhttps://example.com\t\n')).toBe(true);
        });

        it('should return false for excessively long URLs', function () {
            const longUrl = 'https://' + 'a'.repeat(2048) + '.com';
            expect(isValidHttpUrl(longUrl)).toBe(false);
        });
    });

    describe('Protocol validation', function () {
        it('should accept HTTP and HTTPS protocols', function () {
            expect(isValidHttpUrl('http://example.com')).toBe(true);
            expect(isValidHttpUrl('https://example.com')).toBe(true);
        });

        it('should reject other protocols', function () {
            expect(isValidHttpUrl('ftp://example.com')).toBe(false);
            expect(isValidHttpUrl('file://path/to/file')).toBe(false);
            expect(isValidHttpUrl('mailto:user@example.com')).toBe(false);
            expect(isValidHttpUrl('tel:+1234567890')).toBe(false);
            expect(isValidHttpUrl('javascript:alert("xss")')).toBe(false);
            expect(isValidHttpUrl('data:text/plain;base64,SGVsbG8=')).toBe(
                false
            );
        });

        it('should normalize protocols to lowercase (URL constructor behavior)', function () {
            // Note: URL constructor normalizes protocols to lowercase
            expect(isValidHttpUrl('HTTP://example.com')).toBe(true);
            expect(isValidHttpUrl('HTTPS://example.com')).toBe(true);
            expect(isValidHttpUrl('Http://example.com')).toBe(true);
            expect(isValidHttpUrl('Https://example.com')).toBe(true);
        });
    });

    describe('Hostname validation', function () {
        it('should accept valid domain names', function () {
            expect(isValidHttpUrl('https://example.com')).toBe(true);
            expect(isValidHttpUrl('https://www.example.com')).toBe(true);
            expect(isValidHttpUrl('https://subdomain.example.com')).toBe(true);
            expect(
                isValidHttpUrl('https://test-site.example-domain.co.uk')
            ).toBe(true);
        });

        it('should accept localhost', function () {
            expect(isValidHttpUrl('http://localhost')).toBe(true);
            expect(isValidHttpUrl('https://localhost')).toBe(true);
            expect(isValidHttpUrl('http://localhost:8080')).toBe(true);
        });

        it('should accept IP addresses', function () {
            expect(isValidHttpUrl('http://192.168.1.1')).toBe(true);
            expect(isValidHttpUrl('https://127.0.0.1')).toBe(true);
            expect(isValidHttpUrl('http://10.0.0.1:3000')).toBe(true);
        });

        it('should accept IPv6 addresses', function () {
            expect(isValidHttpUrl('http://[::1]')).toBe(true);
            expect(isValidHttpUrl('https://[2001:db8::1]')).toBe(true);
            expect(isValidHttpUrl('http://[fe80::1]:8080')).toBe(true);
        });

        it('should reject malformed hostnames', function () {
            expect(isValidHttpUrl('https://')).toBe(false);
            expect(isValidHttpUrl('https://.')).toBe(false);
            expect(isValidHttpUrl('https://.com')).toBe(false);
            expect(isValidHttpUrl('https://example.')).toBe(false);
            expect(isValidHttpUrl('https://example..com')).toBe(false);
            expect(isValidHttpUrl('https://example .com')).toBe(false);
            // Note: URL constructor may handle some special characters differently
        });

        it('should handle URLs with special characters in hostnames', function () {
            // Some URLs with special characters may be parsed by URL constructor but rejected by our validation
            expect(isValidHttpUrl('https://example$.com')).toBe(false);
            expect(isValidHttpUrl('https://example@.com')).toBe(false);
            // Note: # in hostname creates a fragment, so URL constructor may handle it differently
            expect(isValidHttpUrl('https://example%.com')).toBe(false);
        });
    });

    describe('Complete URL validation', function () {
        it('should accept URLs with paths', function () {
            expect(isValidHttpUrl('https://example.com/path')).toBe(true);
            expect(isValidHttpUrl('https://example.com/path/to/resource')).toBe(
                true
            );
            expect(isValidHttpUrl('https://example.com/path/file.html')).toBe(
                true
            );
        });

        it('should accept URLs with query parameters', function () {
            expect(isValidHttpUrl('https://example.com?param=value')).toBe(
                true
            );
            expect(
                isValidHttpUrl('https://example.com/search?q=test&type=web')
            ).toBe(true);
        });

        it('should accept URLs with fragments', function () {
            expect(isValidHttpUrl('https://example.com#section')).toBe(true);
            expect(isValidHttpUrl('https://example.com/page#top')).toBe(true);
        });

        it('should accept URLs with ports', function () {
            expect(isValidHttpUrl('http://example.com:8080')).toBe(true);
            expect(isValidHttpUrl('https://example.com:443')).toBe(true);
            expect(isValidHttpUrl('http://localhost:3000')).toBe(true);
        });

        it('should accept URLs with authentication', function () {
            expect(isValidHttpUrl('https://user:pass@example.com')).toBe(true);
            expect(isValidHttpUrl('http://user@example.com')).toBe(true);
        });
    });

    describe('Real-world URLs', function () {
        it('should accept common website URLs', function () {
            expect(isValidHttpUrl('https://www.google.com')).toBe(true);
            expect(isValidHttpUrl('https://github.com')).toBe(true);
            expect(isValidHttpUrl('https://stackoverflow.com')).toBe(true);
            expect(
                isValidHttpUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ).toBe(true);
        });

        it('should accept API endpoints', function () {
            expect(isValidHttpUrl('https://api.example.com/v1/users')).toBe(
                true
            );
            expect(
                isValidHttpUrl('https://jsonplaceholder.typicode.com/posts/1')
            ).toBe(true);
        });

        it('should accept development URLs', function () {
            expect(isValidHttpUrl('http://localhost:3000/api/test')).toBe(true);
            expect(isValidHttpUrl('https://dev.example.com')).toBe(true);
            expect(isValidHttpUrl('http://192.168.1.100:8080/admin')).toBe(
                true
            );
        });

        it('should accept international domain names', function () {
            expect(isValidHttpUrl('https://example.co.uk')).toBe(true);
            expect(isValidHttpUrl('https://test.example.org')).toBe(true);
            expect(isValidHttpUrl('https://site.example.net')).toBe(true);
        });
    });

    describe('Edge cases and security', function () {
        it('should reject URLs with no hostname', function () {
            expect(isValidHttpUrl('https://')).toBe(false);
            expect(isValidHttpUrl('http://')).toBe(false);
        });

        it('should reject malformed URLs that URL constructor cannot parse', function () {
            expect(isValidHttpUrl('not-a-url')).toBe(false);
            expect(isValidHttpUrl('https//example.com')).toBe(false);
            // Note: Some malformed URLs might be parsed by URL constructor with unexpected results
        });

        it('should validate based on what URL constructor actually parses', function () {
            // These tests reflect the actual behavior of URL constructor
            // URL constructor may interpret some inputs differently than expected
            expect(isValidHttpUrl('http://256.256.256.256')).toBe(false);
            // Note: URL constructor behavior for incomplete IP addresses may vary
        });

        it('should handle URLs with special characters correctly', function () {
            expect(isValidHttpUrl('https://example.com/path with spaces')).toBe(
                true
            ); // URL constructor handles encoding
            expect(
                isValidHttpUrl(
                    'https://example.com/path?query=value%20with%20spaces'
                )
            ).toBe(true);
        });
    });

    describe('Performance and reliability', function () {
        it('should handle empty inputs gracefully', function () {
            expect(isValidHttpUrl('')).toBe(false);
            expect(isValidHttpUrl('   ')).toBe(false);
        });

        it('should be consistent with repeated calls', function () {
            const testUrl = 'https://example.com';
            for (let i = 0; i < 10; i++) {
                expect(isValidHttpUrl(testUrl)).toBe(true);
            }
        });

        it('should handle various edge cases without throwing errors', function () {
            const edgeCases = [
                'https://.',
                'https://..',
                'https://...',
                'https://a',
                'https://a.b',
                'https://1',
                'https://1.2.3.4.5',
                'http://[invalid:ipv6]',
            ];

            edgeCases.forEach(testCase => {
                expect(() => isValidHttpUrl(testCase)).not.toThrow();
            });
        });
    });
});
