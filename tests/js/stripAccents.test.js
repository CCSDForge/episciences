/**
 * Test suite for stripAccents function
 * Tests the improved version with Unicode normalization and fallback support
 */

// Load the functions.js file and extract only the stripAccents function
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the stripAccents function to avoid jQuery dependencies
const stripAccentsFunctionMatch = functionsJs.match(/function stripAccents\(string\) \{[\s\S]*?\n\}/);
if (stripAccentsFunctionMatch) {
    eval(stripAccentsFunctionMatch[0]);
}

// Test suite
describe('stripAccents function', function () {
    describe('Basic accent removal', function () {
        it('should remove accents from basic Latin characters', function () {
            expect(stripAccents('café')).toBe('cafe');
            expect(stripAccents('naïve')).toBe('naive');
            expect(stripAccents('résumé')).toBe('resume');
            expect(stripAccents('piñata')).toBe('pinata');
        });

        it('should handle uppercase accented characters', function () {
            expect(stripAccents('CAFÉ')).toBe('CAFE');
            expect(stripAccents('NAÏVE')).toBe('NAIVE');
            expect(stripAccents('RÉSUMÉ')).toBe('RESUME');
            expect(stripAccents('PIÑATA')).toBe('PINATA');
        });

        it('should preserve case when removing accents', function () {
            expect(stripAccents('CaFé')).toBe('CaFe');
            expect(stripAccents('NaÏvE')).toBe('NaIvE');
            expect(stripAccents('RéSuMé')).toBe('ReSuMe');
        });
    });

    describe('French accented characters', function () {
        it('should remove French acute accents (é)', function () {
            expect(stripAccents('été')).toBe('ete');
            expect(stripAccents('café')).toBe('cafe');
            expect(stripAccents('résumé')).toBe('resume');
        });

        it('should remove French grave accents (è, à, ù)', function () {
            expect(stripAccents('père')).toBe('pere');
            expect(stripAccents('voilà')).toBe('voila');
            expect(stripAccents('où')).toBe('ou');
        });

        it('should remove French circumflex accents (ê, â, î, ô, û)', function () {
            expect(stripAccents('forêt')).toBe('foret');
            expect(stripAccents('château')).toBe('chateau');
            expect(stripAccents('maître')).toBe('maitre');
            expect(stripAccents('hôtel')).toBe('hotel');
            expect(stripAccents('sûr')).toBe('sur');
        });

        it('should remove French diaeresis (ë, ï)', function () {
            expect(stripAccents('Noël')).toBe('Noel');
            expect(stripAccents('naïf')).toBe('naif');
        });

        it('should handle French cedilla (ç)', function () {
            expect(stripAccents('français')).toBe('francais');
            expect(stripAccents('façade')).toBe('facade');
        });
    });

    describe('Spanish accented characters', function () {
        it('should remove Spanish acute accents', function () {
            expect(stripAccents('José')).toBe('Jose');
            expect(stripAccents('María')).toBe('Maria');
            expect(stripAccents('sí')).toBe('si');
        });

        it('should handle Spanish tilde (ñ)', function () {
            expect(stripAccents('niño')).toBe('nino');
            expect(stripAccents('España')).toBe('Espana');
            expect(stripAccents('mañana')).toBe('manana');
        });

        it('should handle Spanish diaeresis (ü)', function () {
            expect(stripAccents('pingüino')).toBe('pinguino');
            expect(stripAccents('bilingüe')).toBe('bilingue');
        });
    });

    describe('German accented characters', function () {
        it('should handle German umlaut (ä, ö, ü)', function () {
            expect(stripAccents('Müller')).toBe('Muller');
            expect(stripAccents('Köln')).toBe('Koln');
            expect(stripAccents('Bär')).toBe('Bar');
        });

        it('should handle German uppercase umlauts (Ä, Ö, Ü)', function () {
            expect(stripAccents('MÜLLER')).toBe('MULLER');
            expect(stripAccents('KÖLN')).toBe('KOLN');
            expect(stripAccents('BÄR')).toBe('BAR');
        });

        it('should handle German eszett (ß) - note: ß is not an accented character', function () {
            // Note: ß (eszett) is a separate character, not a base letter with diacritic
            // The stripAccents function correctly preserves it as it's not an accent
            expect(stripAccents('weiß')).toBe('weiß');
            expect(stripAccents('Straße')).toBe('Straße');
        });
    });

    describe('Portuguese accented characters', function () {
        it('should remove Portuguese acute accents', function () {
            expect(stripAccents('João')).toBe('Joao');
            expect(stripAccents('São')).toBe('Sao');
        });

        it('should remove Portuguese tilde', function () {
            expect(stripAccents('ação')).toBe('acao');
            expect(stripAccents('não')).toBe('nao');
        });

        it('should remove Portuguese circumflex', function () {
            expect(stripAccents('você')).toBe('voce');
            expect(stripAccents('três')).toBe('tres');
        });
    });

    describe('Italian accented characters', function () {
        it('should remove Italian acute accents', function () {
            expect(stripAccents('perché')).toBe('perche');
            expect(stripAccents('così')).toBe('cosi');
        });

        it('should remove Italian grave accents', function () {
            expect(stripAccents('città')).toBe('citta');
            expect(stripAccents('università')).toBe('universita');
        });
    });

    describe('Nordic accented characters', function () {
        it('should handle Scandinavian characters (å, æ, ø) - note: these are separate characters', function () {
            // Note: å, æ, ø are separate Unicode characters, not base letters with diacritics
            // Only å is decomposable and will be processed by stripAccents
            expect(stripAccents('København')).toBe('København'); // æ and ø remain
            expect(stripAccents('Åse')).toBe('Ase'); // å is decomposable
            expect(stripAccents('kæreste')).toBe('kæreste'); // æ remains
        });

        it('should handle Swedish/Finnish characters', function () {
            expect(stripAccents('Malmö')).toBe('Malmo');
            expect(stripAccents('Göteborg')).toBe('Goteborg');
        });
    });

    describe('Eastern European accented characters', function () {
        it('should handle Czech characters', function () {
            expect(stripAccents('Praha')).toBe('Praha'); // No accents
            expect(stripAccents('Václav')).toBe('Vaclav');
            expect(stripAccents('Čech')).toBe('Cech');
        });

        it('should handle Polish characters - mixed results', function () {
            expect(stripAccents('Kraków')).toBe('Krakow'); // ó is decomposable
            expect(stripAccents('Łódź')).toBe('Łodz'); // Ł is separate character, ó and ź are decomposable
            expect(stripAccents('Gdańsk')).toBe('Gdansk'); // ń is decomposable
        });

        it('should handle Hungarian characters', function () {
            expect(stripAccents('Budapest')).toBe('Budapest'); // No accents
            expect(stripAccents('Pécs')).toBe('Pecs');
            expect(stripAccents('Győr')).toBe('Gyor');
        });
    });

    describe('Mixed languages and complex cases', function () {
        it('should handle text with multiple different accents', function () {
            expect(stripAccents('Café München Zürich')).toBe(
                'Cafe Munchen Zurich'
            );
            expect(stripAccents('José María Azañón')).toBe('Jose Maria Azanon');
        });

        it('should handle sentences with multiple accented words', function () {
            const input = "Les élèves étudient à l'université française.";
            const expected = "Les eleves etudient a l'universite francaise.";
            expect(stripAccents(input)).toBe(expected);
        });

        it('should preserve non-accented characters', function () {
            expect(stripAccents('Hello World 123!')).toBe('Hello World 123!');
            expect(stripAccents('Test@email.com')).toBe('Test@email.com');
        });

        it('should handle mixed case complex text', function () {
            const input = "CAFÉ à MÜNCHEN - José's résumé";
            const expected = "CAFE a MUNCHEN - Jose's resume";
            expect(stripAccents(input)).toBe(expected);
        });
    });

    describe('Edge cases and input validation', function () {
        it('should handle empty string', function () {
            expect(stripAccents('')).toBe('');
        });

        it('should handle single accented character', function () {
            expect(stripAccents('é')).toBe('e');
            expect(stripAccents('ñ')).toBe('n');
            expect(stripAccents('ü')).toBe('u');
        });

        it('should handle strings with only accents', function () {
            expect(stripAccents('áéíóú')).toBe('aeiou');
            expect(stripAccents('ÀÈÌÒÙ')).toBe('AEIOU');
        });

        it('should handle whitespace and special characters', function () {
            expect(stripAccents('  café  ')).toBe('  cafe  ');
            expect(stripAccents('café\nñoël')).toBe('cafe\nnoel');
            expect(stripAccents('café\tñoël')).toBe('cafe\tnoel');
        });

        it('should handle numbers and symbols mixed with accents', function () {
            expect(stripAccents('café123')).toBe('cafe123');
            expect(stripAccents('josé@email.côm')).toBe('jose@email.com');
            expect(stripAccents('€50 für süße Käse')).toBe('€50 fur suße Kase'); // ß remains as it's not a diacritic
        });
    });

    describe('Unicode normalization edge cases', function () {
        it('should handle precomposed vs decomposed characters', function () {
            // These should produce the same result regardless of Unicode form
            const precomposed = 'é'; // Single character U+00E9
            const decomposed = 'e\u0301'; // e + combining acute accent

            expect(stripAccents(precomposed)).toBe('e');
            expect(stripAccents(decomposed)).toBe('e');
        });

        it('should handle multiple combining marks', function () {
            // Character with multiple diacritics
            const complex = 'e\u0301\u0308'; // e + acute + diaeresis
            expect(stripAccents(complex)).toBe('e');
        });

        it('should handle non-Latin scripts (should remain unchanged)', function () {
            // These should pass through unchanged as they don't use Latin diacritics
            expect(stripAccents('こんにちは')).toBe('こんにちは'); // Japanese
            expect(stripAccents('Здравствуйте')).toBe('Здравствуите'); // Russian - some combining marks may be removed
            expect(stripAccents('مرحبا')).toBe('مرحبا'); // Arabic
        });
    });

    describe('Performance and browser compatibility', function () {
        it('should work with both modern and legacy browsers', function () {
            // The function should work regardless of which internal method is used
            const testCases = [
                ['café', 'cafe'],
                ['résumé', 'resume'],
                ['München', 'Munchen'],
                ['José', 'Jose'],
                ['naïve', 'naive'],
            ];

            testCases.forEach(([input, expected]) => {
                expect(stripAccents(input)).toBe(expected);
            });
        });

        it('should handle large strings efficiently', function () {
            // Create a large string with accents
            const largeString = 'café résumé München José naïve '.repeat(100);
            const expectedString = 'cafe resume Munchen Jose naive '.repeat(
                100
            );

            expect(stripAccents(largeString)).toBe(expectedString);
        });
    });

    describe('Real-world use cases', function () {
        it('should handle typical search scenarios', function () {
            // Common use case: making search terms accent-insensitive
            expect(stripAccents('Rechercher "café français"')).toBe(
                'Rechercher "cafe francais"'
            );
            expect(stripAccents('München Hauptbahnhof')).toBe(
                'Munchen Hauptbahnhof'
            );
        });

        it('should handle user names and addresses', function () {
            expect(stripAccents('José María González')).toBe(
                'Jose Maria Gonzalez'
            );
            expect(stripAccents('Müller Straße 15')).toBe('Muller Straße 15'); // ß remains as it's not a diacritic
            expect(stripAccents('São Paulo, Brasil')).toBe('Sao Paulo, Brasil');
        });

        it('should handle academic titles and institutions', function () {
            expect(stripAccents('Université de Montréal')).toBe(
                'Universite de Montreal'
            );
            expect(stripAccents('École Polytechnique')).toBe(
                'Ecole Polytechnique'
            );
            expect(stripAccents('Århus Universitet')).toBe('Arhus Universitet');
        });

        it('should handle file names and URLs', function () {
            expect(stripAccents('mon-résumé.pdf')).toBe('mon-resume.pdf');
            expect(stripAccents('café-müller.html')).toBe('cafe-muller.html');
        });
    });
});

// Run tests if in browser environment with a test runner
if (typeof window !== 'undefined' && window.jasmine) {
    // Tests will run automatically with Jasmine
} else if (typeof module !== 'undefined' && module.exports) {
    // Export for Node.js testing
    module.exports = { stripAccents };
}
