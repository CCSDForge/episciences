<?php

namespace unit\library\Ccsd\Form\Element;

use Ccsd_Form_Element_MultiTextSimple;
use PHPUnit\Framework\TestCase;
use Zend_Validate_Digits;
use Zend_Validate_StringLength;

/**
 * Unit tests for Ccsd_Form_Element_MultiText::isValid()
 */
class MultiTextIsValidTest extends TestCase
{
    public function testValidationStopsAtAValidatorBreakingTheChainOnFailure(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]), true);
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        self::assertSame([Zend_Validate_StringLength::TOO_SHORT], array_keys($element->getMessages()));
    }

    public function testValidationRunsEveryValidatorWithoutBreakChainOnFailure(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]));
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        self::assertArrayHasKey(Zend_Validate_StringLength::TOO_SHORT, $element->getMessages());
        self::assertCount(2, $element->getMessages());
    }
}
