<?php

namespace unit\library\Ccsd\Form\Element;

use Ccsd_Form_Element_MultiTextSimple;
use Ccsd_Form_Element_MultiTextSimpleLang;
use PHPUnit\Framework\TestCase;
use Zend_Validate_Digits;
use Zend_Validate_Regex;
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
        self::assertSame(
            [Zend_Validate_StringLength::TOO_SHORT, Zend_Validate_Digits::NOT_DIGITS],
            array_keys($element->getMessages())
        );
    }

    public function testValidationStopsAtAValidatorBreakingTheChainOnFailureOnARequiredField(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->setRequired(true);
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]), true);
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        self::assertSame([Zend_Validate_StringLength::TOO_SHORT], array_keys($element->getMessages()));
    }

    public function testValidatorBreakingTheChainDoesNotStopItWhenItSucceedsAfterAFailure(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimpleLang('titles');
        $element->setLanguages(['fr' => 'French']);
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]));
        $element->addValidator(new Zend_Validate_Regex('/^[a-z]+$/'), true);
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['fr' => 'abc']));
        self::assertSame(
            [Zend_Validate_StringLength::TOO_SHORT, Zend_Validate_Digits::NOT_DIGITS],
            $element->getErrors()['fr']
        );
    }

    public function testAFailureDoesNotChangeHowTheNextValidationsAreRun(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]));
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        self::assertTrue($element->isValid(['123456']));
    }

    public function testCustomErrorMessagesAreReportedOnFailure(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_Digits());
        $element->setErrorMessages(['Invalid keyword']);

        self::assertFalse($element->isValid(['abc']));
        self::assertContains('Invalid keyword', $element->getMessages());
    }

    public function testMessagesOfEveryFailingValidatorAreKeptForALanguage(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimpleLang('titles');
        $element->setLanguages(['fr' => 'French']);
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]));
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['fr' => 'abc']));
        self::assertSame(
            [Zend_Validate_StringLength::TOO_SHORT, Zend_Validate_Digits::NOT_DIGITS],
            array_keys($element->getMessages()['fr'])
        );
    }
}
