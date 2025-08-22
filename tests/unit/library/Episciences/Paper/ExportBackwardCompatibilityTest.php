<?php

/**
 * Backward compatibility tests to ensure refactored methods behave identically to original implementation
 */
class ExportBackwardCompatibilityTest extends PHPUnit\Framework\TestCase
{
    
    protected function setUp(): void
    {
        parent::setUp();
        Episciences_Language_LanguageCodeProcessor::clearCaches();
    }
    
    protected function tearDown(): void
    {
        Episciences_Language_LanguageCodeProcessor::clearCaches();
        parent::tearDown();
    }
    
    /**
     * Test that getPaperLanguageCode maintains exact backward compatibility
     */
    public function testGetPaperLanguageCodeBackwardCompatibility()
    {
        $testCases = [
            // [inputLanguage, targetLength, default, convertIso639, expectedOutput, description]
            ['en', 2, '', false, 'en', 'Valid 2-char language, no conversion needed'],
            ['eng', 2, '', false, 'en', 'Convert 3-char to 2-char'],
            ['en', 3, '', false, 'eng', 'Convert 2-char to 3-char'],
            ['', 2, 'en', false, 'en', 'Empty language should return default'],
            ['invalid', 2, 'en', false, 'en', 'Invalid language should return default'],
            ['ger', 3, 'eng', true, 'deu', 'ISO 639 B to T conversion'],
            ['deu', 3, 'eng', false, 'deu', 'Valid T code without conversion'],
            ['fr', 2, '', false, 'fr', 'Valid French code'],
            ['fra', 2, '', false, 'fr', 'Convert French 3-char to 2-char'],
        ];
        
        foreach ($testCases as [$inputLang, $targetLength, $default, $convertIso639, $expected, $description]) {
            $mockPaper = $this->createMock(Episciences_Paper::class);
            $mockPaper->method('getMetadata')
                     ->with('language')
                     ->willReturn($inputLang);
            $mockPaper->method('getDocid')
                     ->willReturn('12345');
            
            $result = Export::getPaperLanguageCode(
                $mockPaper, 
                $targetLength, 
                $default, 
                $convertIso639
            );
            
            $this->assertEquals(
                $expected, 
                $result, 
                "Failed test case: {$description} (input: '{$inputLang}', target: {$targetLength}, default: '{$default}', convert: " . ($convertIso639 ? 'true' : 'false') . ")"
            );
        }
    }
    
    /**
     * Test that error handling behavior remains consistent
     */
    public function testErrorHandlingBackwardCompatibility()
    {
        // Capture triggered errors
        $errors = [];
        set_error_handler(function($errno, $errstr) use (&$errors) {
            $errors[] = $errstr;
            return true; // Don't execute PHP's internal error handler
        }, E_USER_WARNING);
        
        try {
            // Test invalid language triggers error
            $mockPaper = $this->createMock(Episciences_Paper::class);
            $mockPaper->method('getMetadata')
                     ->with('language')
                     ->willReturn('invalid');
            $mockPaper->method('getDocid')
                     ->willReturn('12345');
            
            $result = Export::getPaperLanguageCode($mockPaper, 2, 'en');
            
            $this->assertEquals('en', $result); // Should fallback to default
            $this->assertNotEmpty($errors); // Should have triggered an error
            
            // Clear errors for next test
            $errors = [];
            
            // Test invalid length triggers error
            $mockPaper = $this->createMock(Episciences_Paper::class);
            $mockPaper->method('getMetadata')
                     ->with('language')
                     ->willReturn('a'); // Too short
            $mockPaper->method('getDocid')
                     ->willReturn('12345');
            
            $result = Export::getPaperLanguageCode($mockPaper, 2, 'en');
            
            $this->assertEquals('en', $result); // Should fallback to default
            $this->assertNotEmpty($errors); // Should have triggered an error
            
        } finally {
            restore_error_handler();
        }
    }
    
    /**
     * Test getMetaWithLanguagesCode backward compatibility
     */
    public function testGetMetaWithLanguagesCodeBackwardCompatibility()
    {
        $testCases = [
            [
                'input' => ['en' => 'English Title', 'fr' => 'Titre Français'],
                'targetLength' => 2,
                'default' => '',
                'convertIso639' => false,
                'expectedKeys' => ['en', 'fr'],
                'description' => 'Valid 2-char languages'
            ],
            [
                'input' => ['eng' => 'English Title', 'fra' => 'Titre Français'],
                'targetLength' => 2,
                'default' => '',
                'convertIso639' => false,
                'expectedKeys' => ['en', 'fr'],
                'description' => 'Convert 3-char to 2-char languages'
            ],
            [
                'input' => ['en' => 'English Title', 'fr' => 'Titre Français'],
                'targetLength' => 3,
                'default' => 'eng',
                'convertIso639' => false,
                'expectedKeys' => ['eng', 'fra'],
                'description' => 'Convert 2-char to 3-char languages'
            ],
            [
                'input' => ['en' => 'English Title', 'invalid' => 'Invalid Title'],
                'targetLength' => 2,
                'default' => '',
                'convertIso639' => false,
                'expectedKeys' => ['en'],
                'description' => 'Filter out invalid languages'
            ]
        ];
        
        foreach ($testCases as $testCase) {
            $mockPaper = $this->createMock(Episciences_Paper::class);
            $mockPaper->method('getAllTitles')
                     ->willReturn($testCase['input']);
            $mockPaper->method('getDocid')
                     ->willReturn('12345');
            
            // Use reflection to access private method
            $reflectionClass = new ReflectionClass(Export::class);
            $method = $reflectionClass->getMethod('getMetaWithLanguagesCode');
            $method->setAccessible(true);
            
            $result = $method->invokeArgs(null, [
                $mockPaper,
                'getAllTitles',
                $testCase['targetLength'],
                $testCase['default'],
                $testCase['convertIso639']
            ]);
            
            $this->assertIsArray($result, "Result should be array for: {$testCase['description']}");
            
            // Extract language codes from result
            $actualKeys = [];
            foreach ($result as $item) {
                if (is_array($item) && count($item) === 1) {
                    $actualKeys[] = array_keys($item)[0];
                }
            }
            
            // Sort for comparison
            sort($actualKeys);
            $expectedKeys = $testCase['expectedKeys'];
            sort($expectedKeys);
            
            $this->assertEquals(
                $expectedKeys,
                $actualKeys,
                "Language keys mismatch for: {$testCase['description']}. Expected: " . 
                json_encode($expectedKeys) . ", Got: " . json_encode($actualKeys)
            );
        }
    }
    
    /**
     * Test that specific CrossRef and DOAJ methods work correctly
     */
    public function testSpecificExportMethodsBackwardCompatibility()
    {
        // Test CrossRef titles (should be 2-character codes)
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getAllTitles')
                 ->willReturn(['eng' => 'English Title', 'fra' => 'Titre Français']);
        $mockPaper->method('getDocid')
                 ->willReturn('12345');
        
        $result = Export::crossrefGetTitlesWithLanguages($mockPaper);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Verify 2-character language codes
        foreach ($result as $item) {
            $languageCode = array_keys($item)[0];
            $this->assertEquals(2, strlen($languageCode), "CrossRef should use 2-character codes");
        }
        
        // Test CrossRef abstracts (should be 2-character codes)
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getAbstractsCleaned')
                 ->willReturn(['eng' => 'English Abstract', 'fra' => 'Résumé Français']);
        $mockPaper->method('getDocid')
                 ->willReturn('12345');
        
        $result = Export::crossrefGetAbstractsWithLanguages($mockPaper);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Verify 2-character language codes
        foreach ($result as $item) {
            $languageCode = array_keys($item)[0];
            $this->assertEquals(2, strlen($languageCode), "CrossRef should use 2-character codes");
        }
    }
    
    /**
     * Test data structure compatibility
     */
    public function testDataStructureBackwardCompatibility()
    {
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getAllTitles')
                 ->willReturn(['en' => 'English Title', 'fr' => 'Titre Français']);
        $mockPaper->method('getDocid')
                 ->willReturn('12345');
        
        $result = Export::crossrefGetTitlesWithLanguages($mockPaper);
        
        // Verify the exact data structure expected by calling code
        $this->assertIsArray($result);
        
        foreach ($result as $item) {
            $this->assertIsArray($item, "Each result item should be an array");
            $this->assertCount(1, $item, "Each result item should have exactly one key-value pair");
            
            $languageCode = array_keys($item)[0];
            $content = array_values($item)[0];
            
            $this->assertIsString($languageCode, "Language code should be string");
            $this->assertIsString($content, "Content should be string");
            $this->assertNotEmpty($languageCode, "Language code should not be empty");
        }
    }
    
    /**
     * Test performance remains acceptable after refactoring
     */
    public function testPerformanceBackwardCompatibility()
    {
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getMetadata')
                 ->with('language')
                 ->willReturn('en');
        $mockPaper->method('getDocid')
                 ->willReturn('12345');
        
        $start = microtime(true);
        
        // Run operations that would have been common in the original system
        for ($i = 0; $i < 100; $i++) {
            Export::getPaperLanguageCode($mockPaper, 2);
            Export::getPaperLanguageCode($mockPaper, 3);
        }
        
        $duration = microtime(true) - $start;
        
        // Should complete reasonably quickly (allow for some overhead)
        $this->assertLessThan(1.0, $duration, "Performance should remain acceptable: took {$duration} seconds for 200 operations");
    }
}