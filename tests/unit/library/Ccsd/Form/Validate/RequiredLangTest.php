<?php

namespace unit\library\Ccsd\Form\Validate;

use Ccsd_Form_Validate_RequiredLang;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Form_Validate_RequiredLang validator
 */
class RequiredLangTest extends TestCase
{
    private Ccsd_Form_Validate_RequiredLang $validator;

    protected function setUp(): void
    {
        parent::setUp();
        // Ccsd_Form_Validate_RequiredLang expects 'langs' or 'populate' in constructor
        $this->validator = new Ccsd_Form_Validate_RequiredLang(['langs' => ['fr', 'en']]);
    }

    /**
     * Test validation with all required languages filled
     */
    public function testIsValidWithAllLangs(): void
    {
        $value = ['fr' => 'Bonjour', 'en' => 'Hello'];
        $this->assertTrue($this->validator->isValid($value));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test validation with missing language key
     */
    public function testIsInvalidWithMissingLang(): void
    {
        $value = ['fr' => 'Bonjour'];
        $this->assertFalse($this->validator->isValid($value));
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey(Ccsd_Form_Validate_RequiredLang::REQUIRED_LANG, $messages);
    }

    /**
     * Test validation with empty string value for a required language
     */
    public function testIsInvalidWithEmptyStringLang(): void
    {
        $value = ['fr' => 'Bonjour', 'en' => ''];
        $this->assertFalse($this->validator->isValid($value));
        
        $value = ['fr' => 'Bonjour', 'en' => '   '];
        $this->assertFalse($this->validator->isValid($value));
        
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey(Ccsd_Form_Validate_RequiredLang::REQUIRED_LANG, $messages);
    }

    /**
     * Test validation with min option (minimum number of languages regardless of which ones)
     */
    public function testIsValidWithMinOption(): void
    {
        // Require fr, en, but at least 3 languages in total
        $this->validator->setLangs(['fr', 'en']);
        $this->validator->setMin(3);
        
        // Fails: only 2 languages provided
        $value = ['fr' => 'Bonjour', 'en' => 'Hello'];
        $this->assertFalse($this->validator->isValid($value));
        
        // Success: 3 languages provided including required fr, en
        $value = ['fr' => 'Bonjour', 'en' => 'Hello', 'es' => 'Hola'];
        $this->assertTrue($this->validator->isValid($value));
    }
}
