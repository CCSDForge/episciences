<?php

declare(strict_types=1);

namespace unit\library\Ccsd\Form\Validate;

use Ccsd_Form_Validate_NotSame;
use PHPUnit\Framework\TestCase;

class Ccsd_Form_Validate_NotSameTest extends TestCase
{
    private Ccsd_Form_Validate_NotSame $validator;

    protected function setUp(): void
    {
        $this->validator = new Ccsd_Form_Validate_NotSame();
    }

    /**
     * Test the default group value and set/get group.
     */
    public function testSetAndGetGroup(): void
    {
        $this->assertTrue($this->validator->isGroup());
        $this->validator->setGroup(false);
        $this->assertFalse($this->validator->isGroup());
        $this->validator->setGroup(true);
        $this->assertTrue($this->validator->isGroup());
    }

    /**
     * Test that the constructor option correctly sets group.
     */
    public function testConstructorSetsGroup(): void
    {
        $validatorFalse = new Ccsd_Form_Validate_NotSame(false);
        $this->assertFalse($validatorFalse->isGroup());

        $validatorTrue = new Ccsd_Form_Validate_NotSame(true);
        $this->assertTrue($validatorTrue->isGroup());
    }

    /**
     * Test validation succeeds with unique languages.
     */
    public function testIsValidWithValidUniqueLanguages(): void
    {
        $value = [
            'fr' => 'Bonjour',
            'en' => 'Hello',
            'es' => 'Hola',
        ];

        $this->assertTrue($this->validator->isValid($value));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test validation fails when duplicate languages are validated across sequential calls.
     */
    public function testIsValidWithDuplicateLanguagesConsecutively(): void
    {
        // First call with unique language
        $this->assertTrue($this->validator->isValid(['fr' => 'Bonjour']));

        // Second call with another language
        $this->assertTrue($this->validator->isValid(['en' => 'Hello']));

        // Third call with already seen language 'fr'
        $this->assertFalse($this->validator->isValid(['fr' => 'Salut']));

        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Ccsd_Form_Validate_NotSame::SAME, $messages);
        $this->assertSame(
            "Vous ne pouvez pas soumettre plus de deux valeurs pour une même langue",
            $messages[Ccsd_Form_Validate_NotSame::SAME]
        );
    }

    /**
     * Test validation fails with a non-array value.
     */
    public function testIsValidWithInvalidNonArrayValue(): void
    {
        $this->assertFalse($this->validator->isValid('not-an-array'));

        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Ccsd_Form_Validate_NotSame::MISSING_TOKEN, $messages);
        $this->assertSame(
            'Les valeurs passées ne sont pas valides',
            $messages[Ccsd_Form_Validate_NotSame::MISSING_TOKEN]
        );
    }

    /**
     * Test validation with an empty array.
     */
    public function testIsValidWithEmptyArray(): void
    {
        $this->assertTrue($this->validator->isValid([]));
        $this->assertEmpty($this->validator->getMessages());
    }
}
