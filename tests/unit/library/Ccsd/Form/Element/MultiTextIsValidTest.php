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

    public function testErrorsOfAPreviousValidationAreNotKept(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]));
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        self::assertTrue($element->isValid(['123456']));
        self::assertFalse($element->hasErrors());
        self::assertSame([], $element->getGroupErrors());
    }

    public function testErrorsAreNotDuplicatedByAnotherFailingValidation(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['abc']));
        $groupErrors = $element->getGroupErrors();

        self::assertFalse($element->isValid(['abc']));
        self::assertSame($groupErrors, $element->getGroupErrors());
    }

    public function testEveryValidatorValidatesAnEmptyRequiredList(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->setRequired(true);
        $element->setAutoInsertNotEmptyValidator(false);
        $element->addValidator(new Zend_Validate_StringLength(['min' => 5]), true);
        $element->addValidator(new Zend_Validate_Digits());

        self::assertFalse($element->isValid(['']));
        self::assertSame([Zend_Validate_StringLength::TOO_SHORT], array_keys($element->getMessages()));
    }

    public function testCustomErrorMessagesAreReportedOnFailure(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimple('keywords');
        $element->addValidator(new Zend_Validate_Digits());
        $element->setErrorMessages(['Invalid keyword']);

        self::assertFalse($element->isValid(['abc']));
        self::assertContains('Invalid keyword', $element->getMessages());
    }

    public function testCustomErrorMessagesAreReportedForAMultiValueLanguage(): void
    {
        $element = new Ccsd_Form_Element_MultiTextSimpleLang('keywords');
        $element->setLanguages(['fr' => 'French']);
        $element->setPluriValues(true);
        $element->addValidator(new Zend_Validate_Digits());
        $element->setErrorMessages(['Invalid keyword: %value%']);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING);
        try {
            self::assertFalse($element->isValid(['fr' => ['abc', 'def']]));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertContains('Invalid keyword: abc; Invalid keyword: def', $element->getMessages());
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
