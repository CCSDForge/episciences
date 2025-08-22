<?php

class LanguageCodeTest extends PHPUnit\Framework\TestCase
{
    
    public function testValidLanguageCodes()
    {
        $code2 = new Episciences_Language_LanguageCode('en');
        $this->assertEquals('en', $code2->getCode());
        $this->assertEquals(2, $code2->getLength());
        $this->assertTrue($code2->isValid());
        $this->assertTrue($code2->isAlpha2());
        $this->assertFalse($code2->isAlpha3());
        $this->assertTrue($code2->hasValidLength());
        $this->assertFalse($code2->isEmpty());
        
        $code3 = new Episciences_Language_LanguageCode('eng');
        $this->assertEquals('eng', $code3->getCode());
        $this->assertEquals(3, $code3->getLength());
        $this->assertTrue($code3->isValid());
        $this->assertFalse($code3->isAlpha2());
        $this->assertTrue($code3->isAlpha3());
        $this->assertTrue($code3->hasValidLength());
        $this->assertFalse($code3->isEmpty());
    }
    
    public function testCaseInsensitive()
    {
        $code1 = new Episciences_Language_LanguageCode('EN');
        $code2 = new Episciences_Language_LanguageCode('en');
        $code3 = new Episciences_Language_LanguageCode('En');
        
        $this->assertEquals('en', $code1->getCode());
        $this->assertEquals('en', $code2->getCode());
        $this->assertEquals('en', $code3->getCode());
        $this->assertTrue($code1->equals($code2));
        $this->assertTrue($code2->equals($code3));
    }
    
    public function testWhitespaceHandling()
    {
        $code = new Episciences_Language_LanguageCode('  en  ');
        $this->assertEquals('en', $code->getCode());
        $this->assertTrue($code->isValid());
    }
    
    public function testInvalidCodes()
    {
        // Empty code
        $empty = new Episciences_Language_LanguageCode('');
        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isValid());
        
        // Too short
        $short = new Episciences_Language_LanguageCode('a');
        $this->assertFalse($short->hasValidLength());
        $this->assertFalse($short->isValid());
        
        // Too long
        $long = new Episciences_Language_LanguageCode('english');
        $this->assertFalse($long->hasValidLength());
        $this->assertFalse($long->isValid());
        
        // Contains numbers
        $numeric = new Episciences_Language_LanguageCode('en1');
        $this->assertFalse($numeric->isValid());
        
        // Contains special characters
        $special = new Episciences_Language_LanguageCode('e-n');
        $this->assertFalse($special->isValid());
    }
    
    public function testEquals()
    {
        $code1 = new Episciences_Language_LanguageCode('en');
        $code2 = new Episciences_Language_LanguageCode('en');
        $code3 = new Episciences_Language_LanguageCode('fr');
        
        $this->assertTrue($code1->equals($code2));
        $this->assertFalse($code1->equals($code3));
    }
    
    public function testToString()
    {
        $code = new Episciences_Language_LanguageCode('en');
        $this->assertEquals('en', (string)$code);
    }
    
    public function testConstants()
    {
        $this->assertEquals(2, Episciences_Language_LanguageCode::LENGTH_ALPHA2);
        $this->assertEquals(3, Episciences_Language_LanguageCode::LENGTH_ALPHA3);
        $this->assertEquals(2, Episciences_Language_LanguageCode::MIN_LENGTH);
        $this->assertEquals(3, Episciences_Language_LanguageCode::MAX_LENGTH);
    }
}