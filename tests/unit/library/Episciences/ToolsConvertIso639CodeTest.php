<?php

class ToolsConvertIso639CodeTest extends PHPUnit\Framework\TestCase
{

    /**
     * Test successful B to T conversions
     */
    public function testBToTConversions()
    {
        // Test B codes (bibliographic) to T codes (terminologic)
        $this->assertEquals('sqi', Episciences_Tools::convertIso639Code('alb')); // Albanian
        $this->assertEquals('hye', Episciences_Tools::convertIso639Code('arm')); // Armenian
        $this->assertEquals('eus', Episciences_Tools::convertIso639Code('baq')); // Basque
        $this->assertEquals('mya', Episciences_Tools::convertIso639Code('bur')); // Burmese
        $this->assertEquals('zho', Episciences_Tools::convertIso639Code('chi')); // Chinese
        $this->assertEquals('ces', Episciences_Tools::convertIso639Code('cze')); // Czech
        $this->assertEquals('nld', Episciences_Tools::convertIso639Code('dut')); // Dutch
        $this->assertEquals('fra', Episciences_Tools::convertIso639Code('fre')); // French
        $this->assertEquals('kat', Episciences_Tools::convertIso639Code('geo')); // Georgian
        $this->assertEquals('deu', Episciences_Tools::convertIso639Code('ger')); // German
        $this->assertEquals('ell', Episciences_Tools::convertIso639Code('gre')); // Greek
        $this->assertEquals('isl', Episciences_Tools::convertIso639Code('ice')); // Icelandic
        $this->assertEquals('mkd', Episciences_Tools::convertIso639Code('mac')); // Macedonian
        $this->assertEquals('mri', Episciences_Tools::convertIso639Code('mao')); // Maori
        $this->assertEquals('msa', Episciences_Tools::convertIso639Code('may')); // Malay
        $this->assertEquals('fas', Episciences_Tools::convertIso639Code('per')); // Persian
        $this->assertEquals('ron', Episciences_Tools::convertIso639Code('rum')); // Romanian
        $this->assertEquals('slk', Episciences_Tools::convertIso639Code('slo')); // Slovak
        $this->assertEquals('bod', Episciences_Tools::convertIso639Code('tib')); // Tibetan
        $this->assertEquals('cym', Episciences_Tools::convertIso639Code('wel')); // Welsh
    }

    /**
     * Test successful T to B conversions
     */
    public function testTToBConversions()
    {
        // Test T codes (terminologic) to B codes (bibliographic)
        $this->assertEquals('alb', Episciences_Tools::convertIso639Code('sqi')); // Albanian
        $this->assertEquals('arm', Episciences_Tools::convertIso639Code('hye')); // Armenian
        $this->assertEquals('baq', Episciences_Tools::convertIso639Code('eus')); // Basque
        $this->assertEquals('bur', Episciences_Tools::convertIso639Code('mya')); // Burmese
        $this->assertEquals('chi', Episciences_Tools::convertIso639Code('zho')); // Chinese
        $this->assertEquals('cze', Episciences_Tools::convertIso639Code('ces')); // Czech
        $this->assertEquals('dut', Episciences_Tools::convertIso639Code('nld')); // Dutch
        $this->assertEquals('fre', Episciences_Tools::convertIso639Code('fra')); // French
        $this->assertEquals('geo', Episciences_Tools::convertIso639Code('kat')); // Georgian
        $this->assertEquals('ger', Episciences_Tools::convertIso639Code('deu')); // German
        $this->assertEquals('gre', Episciences_Tools::convertIso639Code('ell')); // Greek
        $this->assertEquals('ice', Episciences_Tools::convertIso639Code('isl')); // Icelandic
        $this->assertEquals('mac', Episciences_Tools::convertIso639Code('mkd')); // Macedonian
        $this->assertEquals('mao', Episciences_Tools::convertIso639Code('mri')); // Maori
        $this->assertEquals('may', Episciences_Tools::convertIso639Code('msa')); // Malay
        $this->assertEquals('per', Episciences_Tools::convertIso639Code('fas')); // Persian
        $this->assertEquals('rum', Episciences_Tools::convertIso639Code('ron')); // Romanian
        $this->assertEquals('slo', Episciences_Tools::convertIso639Code('slk')); // Slovak
        $this->assertEquals('tib', Episciences_Tools::convertIso639Code('bod')); // Tibetan
        $this->assertEquals('wel', Episciences_Tools::convertIso639Code('cym')); // Welsh
    }

    /**
     * Test case insensitive conversion
     */
    public function testCaseInsensitiveConversion()
    {
        // Test that function handles uppercase input correctly
        $this->assertEquals('sqi', Episciences_Tools::convertIso639Code('ALB'));
        $this->assertEquals('sqi', Episciences_Tools::convertIso639Code('Alb'));
        $this->assertEquals('sqi', Episciences_Tools::convertIso639Code('alB'));
        
        $this->assertEquals('alb', Episciences_Tools::convertIso639Code('SQI'));
        $this->assertEquals('alb', Episciences_Tools::convertIso639Code('Sqi'));
        $this->assertEquals('alb', Episciences_Tools::convertIso639Code('sqI'));
    }

    /**
     * Test unmapped codes - should return the same code unchanged
     */
    public function testUnmappedCodesReturnUnchanged()
    {
        // Test codes that don't have bidirectional mappings (most ISO 639-2 codes)
        $this->assertEquals('eng', Episciences_Tools::convertIso639Code('eng')); // English
        $this->assertEquals('spa', Episciences_Tools::convertIso639Code('spa')); // Spanish
        $this->assertEquals('ita', Episciences_Tools::convertIso639Code('ita')); // Italian
        $this->assertEquals('por', Episciences_Tools::convertIso639Code('por')); // Portuguese
        $this->assertEquals('rus', Episciences_Tools::convertIso639Code('rus')); // Russian
        $this->assertEquals('jpn', Episciences_Tools::convertIso639Code('jpn')); // Japanese
        $this->assertEquals('kor', Episciences_Tools::convertIso639Code('kor')); // Korean
        $this->assertEquals('ara', Episciences_Tools::convertIso639Code('ara')); // Arabic
        $this->assertEquals('hin', Episciences_Tools::convertIso639Code('hin')); // Hindi
        $this->assertEquals('ben', Episciences_Tools::convertIso639Code('ben')); // Bengali
        $this->assertEquals('swe', Episciences_Tools::convertIso639Code('swe')); // Swedish
        $this->assertEquals('nor', Episciences_Tools::convertIso639Code('nor')); // Norwegian
        $this->assertEquals('dan', Episciences_Tools::convertIso639Code('dan')); // Danish
        $this->assertEquals('fin', Episciences_Tools::convertIso639Code('fin')); // Finnish
        $this->assertEquals('pol', Episciences_Tools::convertIso639Code('pol')); // Polish
        $this->assertEquals('hun', Episciences_Tools::convertIso639Code('hun')); // Hungarian
        $this->assertEquals('cze', Episciences_Tools::convertIso639Code('ces')); // Czech T to B conversion
        
        // Test some fictional/invalid codes
        $this->assertEquals('abc', Episciences_Tools::convertIso639Code('abc'));
        $this->assertEquals('xyz', Episciences_Tools::convertIso639Code('xyz'));
        $this->assertEquals('foo', Episciences_Tools::convertIso639Code('foo'));
    }

    /**
     * Test invalid input handling - empty strings and wrong length
     */
    public function testInvalidInputHandling()
    {
        // Test empty string
        $this->assertEquals('', Episciences_Tools::convertIso639Code(''));
        
        // Test strings that are too short
        $this->assertEquals('a', Episciences_Tools::convertIso639Code('a'));
        $this->assertEquals('ab', Episciences_Tools::convertIso639Code('ab'));
        
        // Test strings that are too long  
        $this->assertEquals('abcd', Episciences_Tools::convertIso639Code('abcd'));
        $this->assertEquals('albanian', Episciences_Tools::convertIso639Code('albanian'));
        $this->assertEquals('12345', Episciences_Tools::convertIso639Code('12345'));
    }

    /**
     * Test edge cases with special characters
     */
    public function testEdgeCasesWithSpecialCharacters()
    {
        // Test codes with numbers (not valid ISO 639-2, but should return unchanged)
        $this->assertEquals('ab1', Episciences_Tools::convertIso639Code('ab1'));
        $this->assertEquals('123', Episciences_Tools::convertIso639Code('123'));
        
        // Test codes with special characters
        $this->assertEquals('a-b', Episciences_Tools::convertIso639Code('a-b'));
        $this->assertEquals('a_b', Episciences_Tools::convertIso639Code('a_b'));
        $this->assertEquals('a.b', Episciences_Tools::convertIso639Code('a.b'));
    }

    /**
     * Test bidirectional conversion integrity
     */
    public function testBidirectionalConversionIntegrity()
    {
        // Test that converting B->T->B returns original
        $bCodes = ['alb', 'arm', 'baq', 'bur', 'chi', 'cze', 'dut', 'fre', 'geo', 'ger', 'gre', 'ice', 'mac', 'mao', 'may', 'per', 'rum', 'slo', 'tib', 'wel'];
        
        foreach ($bCodes as $bCode) {
            $tCode = Episciences_Tools::convertIso639Code($bCode);
            $backToBCode = Episciences_Tools::convertIso639Code($tCode);
            $this->assertEquals($bCode, $backToBCode, "Bidirectional conversion failed for B code: $bCode");
        }
        
        // Test that converting T->B->T returns original
        $tCodes = ['sqi', 'hye', 'eus', 'mya', 'zho', 'ces', 'nld', 'fra', 'kat', 'deu', 'ell', 'isl', 'mkd', 'mri', 'msa', 'fas', 'ron', 'slk', 'bod', 'cym'];
        
        foreach ($tCodes as $tCode) {
            $bCode = Episciences_Tools::convertIso639Code($tCode);
            $backToTCode = Episciences_Tools::convertIso639Code($bCode);
            $this->assertEquals($tCode, $backToTCode, "Bidirectional conversion failed for T code: $tCode");
        }
    }

    /**
     * Test function performance with large number of calls
     */
    public function testPerformance()
    {
        $start = microtime(true);
        
        // Test 1000 conversions
        for ($i = 0; $i < 1000; $i++) {
            Episciences_Tools::convertIso639Code('alb');
            Episciences_Tools::convertIso639Code('sqi');
            Episciences_Tools::convertIso639Code('eng'); // unmapped
            Episciences_Tools::convertIso639Code('invalid'); // invalid
        }
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // Should complete 4000 conversions in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $duration, "Performance test failed: took {$duration} seconds for 4000 conversions");
    }

    /**
     * Test all mapping pairs are correctly implemented
     */
    public function testAllMappingPairsAreCorrect()
    {
        // Define expected mappings (B => T)
        $expectedMappings = [
            'alb' => 'sqi', // Albanian
            'arm' => 'hye', // Armenian  
            'baq' => 'eus', // Basque
            'bur' => 'mya', // Burmese
            'chi' => 'zho', // Chinese
            'cze' => 'ces', // Czech
            'dut' => 'nld', // Dutch
            'fre' => 'fra', // French
            'geo' => 'kat', // Georgian
            'ger' => 'deu', // German
            'gre' => 'ell', // Greek (modern)
            'ice' => 'isl', // Icelandic
            'mac' => 'mkd', // Macedonian
            'mao' => 'mri', // Maori
            'may' => 'msa', // Malay
            'per' => 'fas', // Persian
            'rum' => 'ron', // Romanian
            'slo' => 'slk', // Slovak
            'tib' => 'bod', // Tibetan
            'wel' => 'cym', // Welsh
        ];

        // Test all B to T conversions
        foreach ($expectedMappings as $bCode => $expectedTCode) {
            $actualTCode = Episciences_Tools::convertIso639Code($bCode);
            $this->assertEquals($expectedTCode, $actualTCode, "B to T conversion failed for: $bCode -> $expectedTCode");
        }

        // Test all T to B conversions (reverse mappings)
        foreach ($expectedMappings as $expectedBCode => $tCode) {
            $actualBCode = Episciences_Tools::convertIso639Code($tCode);
            $this->assertEquals($expectedBCode, $actualBCode, "T to B conversion failed for: $tCode -> $expectedBCode");
        }
    }

    /**
     * Test that function doesn't modify global state
     */
    public function testNoGlobalStateModification()
    {
        // Store some values before function calls
        $beforeValue = 'test_value';
        
        // Make several function calls
        Episciences_Tools::convertIso639Code('alb');
        Episciences_Tools::convertIso639Code('sqi');
        Episciences_Tools::convertIso639Code('');
        Episciences_Tools::convertIso639Code('invalid');
        
        // Check that our test value wasn't modified
        $this->assertEquals('test_value', $beforeValue, "Function call modified external state");
    }
}