<?php

class LanguageProcessorConfigTest extends PHPUnit\Framework\TestCase
{
    
    public function testDefaultConfiguration()
    {
        $config = new Episciences_Language_LanguageProcessorConfig();
        
        $this->assertEquals('lenient', $config->getErrorMode());
        $this->assertTrue($config->isCachingEnabled());
        $this->assertFalse($config->shouldConvertIso639Code());
        $this->assertFalse($config->isStrictMode());
        $this->assertEquals(1000, $config->getMaxCacheSize());
        
        $this->assertEquals('en', $config->getDefaultLanguage('paper'));
        $this->assertEquals('', $config->getDefaultLanguage('crossref'));
        $this->assertEquals('eng', $config->getDefaultLanguage('doaj'));
        $this->assertEquals('en', $config->getDefaultLanguage('default'));
    }
    
    public function testCustomConfiguration()
    {
        $customConfig = [
            'error_mode' => 'strict',
            'enable_caching' => false,
            'convert_iso639_code' => true,
            'max_cache_size' => 500,
            'default_languages' => [
                'default' => 'fr'
            ]
        ];
        
        $config = new Episciences_Language_LanguageProcessorConfig($customConfig);
        
        $this->assertEquals('strict', $config->getErrorMode());
        $this->assertFalse($config->isCachingEnabled());
        $this->assertTrue($config->shouldConvertIso639Code());
        $this->assertTrue($config->isStrictMode());
        $this->assertEquals(500, $config->getMaxCacheSize());
        $this->assertEquals('fr', $config->getDefaultLanguage('default'));
    }
    
    public function testNestedConfiguration()
    {
        $config = new Episciences_Language_LanguageProcessorConfig();
        
        $this->assertFalse($config->get('validation.allow_empty', false));
        $this->assertTrue($config->get('validation.require_alpha_only', false));
        $this->assertEquals(2, $config->get('validation.min_length', 0));
        $this->assertEquals(3, $config->get('validation.max_length', 0));
    }
    
    public function testGetWithDefault()
    {
        $config = new Episciences_Language_LanguageProcessorConfig();
        
        $this->assertEquals('default_value', $config->get('non.existent.key', 'default_value'));
        $this->assertNull($config->get('non.existent.key'));
    }
    
    public function testFactoryMethods()
    {
        // Test Crossref configuration
        $crossrefConfig = Episciences_Language_LanguageProcessorConfig::forCrossref();
        $this->assertEquals('', $crossrefConfig->getDefaultLanguage('default'));
        
        // Test DOAJ configuration
        $doajConfig = Episciences_Language_LanguageProcessorConfig::forDoaj();
        $this->assertTrue($doajConfig->shouldConvertIso639Code());
        $this->assertEquals('eng', $doajConfig->getDefaultLanguage('default'));
    }
    
    public function testConstants()
    {
        $this->assertEquals(2, Episciences_Language_LanguageProcessorConfig::ALPHA2_LENGTH);
        $this->assertEquals(3, Episciences_Language_LanguageProcessorConfig::ALPHA3_LENGTH);
        
        $this->assertEquals('', Episciences_Language_LanguageProcessorConfig::DEFAULT_CROSSREF_LANGUAGE);
        $this->assertEquals('eng', Episciences_Language_LanguageProcessorConfig::DEFAULT_DOAJ_LANGUAGE);
        $this->assertEquals('en', Episciences_Language_LanguageProcessorConfig::DEFAULT_PAPER_LANGUAGE);
        
        $this->assertEquals('strict', Episciences_Language_LanguageProcessorConfig::ERROR_MODE_STRICT);
        $this->assertEquals('lenient', Episciences_Language_LanguageProcessorConfig::ERROR_MODE_LENIENT);
    }
}