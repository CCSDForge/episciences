const { isEmail } = require('./isEmail');

describe('isEmail function', () => {
    describe('Valid emails', () => {
        test('should accept standard email formats', () => {
            expect(isEmail('test@example.com')).toBe(true);
            expect(isEmail('user.name@domain.co.uk')).toBe(true);
            expect(isEmail('first.last@subdomain.example.org')).toBe(true);
        });

        test('should accept emails with numbers', () => {
            expect(isEmail('user123@example.com')).toBe(true);
            expect(isEmail('123user@example.com')).toBe(true);
            expect(isEmail('test@example123.com')).toBe(true);
        });

        test('should accept emails with special characters', () => {
            expect(isEmail('user+tag@example.com')).toBe(true);
            expect(isEmail('user_name@example.com')).toBe(true);
            expect(isEmail('user-name@example.com')).toBe(true);
            expect(isEmail('user.name+tag@example.com')).toBe(true);
        });

        test('should accept emails with various TLDs', () => {
            expect(isEmail('test@example.co')).toBe(true);
            expect(isEmail('test@example.info')).toBe(true);
            expect(isEmail('test@example.museum')).toBe(true);
        });
    });

    describe('Invalid emails', () => {
        test('should reject emails without @ symbol', () => {
            expect(isEmail('plainaddress')).toBe(false);
            expect(isEmail('user.domain.com')).toBe(false);
        });

        test('should reject emails without local part', () => {
            expect(isEmail('@example.com')).toBe(false);
            expect(isEmail('@')).toBe(false);
        });

        test('should reject emails without domain', () => {
            expect(isEmail('user@')).toBe(false);
            expect(isEmail('user@.')).toBe(false);
        });

        test('should reject emails with multiple @ symbols', () => {
            expect(isEmail('user@@example.com')).toBe(false);
            expect(isEmail('user@domain@example.com')).toBe(false);
        });

        test('should reject emails with invalid characters', () => {
            expect(isEmail('user name@example.com')).toBe(false);
            expect(isEmail('user@exam ple.com')).toBe(false);
            expect(isEmail('user<>@example.com')).toBe(false);
        });

        test('should reject malformed domains', () => {
            expect(isEmail('user@.example.com')).toBe(false);
            expect(isEmail('user@example..com')).toBe(false);
            expect(isEmail('user@example.')).toBe(false);
        });

        test('should reject empty or null inputs', () => {
            expect(isEmail('')).toBe(false);
            expect(isEmail(null)).toBe(false);
            expect(isEmail(undefined)).toBe(false);
        });
    });

    describe('Edge cases and security', () => {
        test('should handle very long emails efficiently', () => {
            const longLocal = 'a'.repeat(64);
            const longDomain = 'b'.repeat(63) + '.com';
            expect(isEmail(`${longLocal}@${longDomain}`)).toBe(true);
        });

        test('should handle potential ReDoS attack patterns efficiently', () => {
            // Test the specific pattern mentioned in the issue - this was causing exponential backtracking
            // in the old regex. The new regex should handle it efficiently regardless of result
            const maliciousPattern = 'a@a' + '0.0'.repeat(100);
            const startTime = Date.now();
            const result = isEmail(maliciousPattern);
            const endTime = Date.now();

            // Most importantly, should complete quickly (under 100ms) - no exponential backtracking
            expect(endTime - startTime).toBeLessThan(100);

            // The pattern is actually technically valid (a@a0.00.00.0...)
            // but the key is that it runs fast, not the result
        });

        test('should handle repeated patterns efficiently', () => {
            const patterns = [
                'a@' + 'b.'.repeat(50) + 'com',
                'a@b' + '.c'.repeat(50),
                'a' + '.b'.repeat(50) + '@example.com',
            ];

            patterns.forEach(pattern => {
                const startTime = Date.now();
                isEmail(pattern);
                const endTime = Date.now();
                expect(endTime - startTime).toBeLessThan(50);
            });
        });
    });

    describe('Real-world examples', () => {
        test('should accept common email providers', () => {
            expect(isEmail('user@gmail.com')).toBe(true);
            expect(isEmail('user@yahoo.co.uk')).toBe(true);
            expect(isEmail('user@hotmail.fr')).toBe(true);
            expect(isEmail('user@outlook.com')).toBe(true);
        });

        test('should accept academic emails', () => {
            expect(isEmail('student@university.edu')).toBe(true);
            expect(isEmail('professor@research.ac.uk')).toBe(true);
        });

        test('should accept business emails', () => {
            expect(isEmail('contact@company.co')).toBe(true);
            expect(isEmail('support@service.org')).toBe(true);
        });
    });
});
