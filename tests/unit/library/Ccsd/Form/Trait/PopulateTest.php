<?php

namespace unit\library\Ccsd\Form\Trait;

use Ccsd_Form_Trait_Populate;
use PHPUnit\Framework\TestCase;
use Zend_Form;
use Zend_Translate;

/**
 * Unit tests for Ccsd_Form_Trait_Populate::setData() language resolution
 */
class PopulateTest extends TestCase
{
    protected function tearDown(): void
    {
        Zend_Form::setDefaultTranslator(null);
    }

    private function newSubject(): object
    {
        return new class {
            use Ccsd_Form_Trait_Populate;
        };
    }

    private function useTranslator(array $messages): void
    {
        Zend_Form::setDefaultTranslator(new Zend_Translate([
            'adapter' => 'array',
            'content' => $messages,
            'locale' => 'en',
        ]));
    }

    public function testLanguageCodeIsResolvedFromCldr(): void
    {
        $this->useTranslator(['unrelated' => 'x']);
        $subject = $this->newSubject()->setData(['fr']);
        $this->assertSame(['French'], $subject->getData());
    }

    public function testArbitraryValueIsKeptAsIs(): void
    {
        $this->useTranslator(['unrelated' => 'x']);
        $subject = $this->newSubject()->setData(['Some free text', '']);
        $this->assertSame(['Some free text', ''], $subject->getData());
    }

    public function testExistingTranslationKeyTakesPrecedence(): void
    {
        $this->useTranslator(['fr' => 'Custom label']);
        $subject = $this->newSubject()->setData(['fr']);
        $this->assertSame(['Custom label'], $subject->getData());
    }

    public function testNonStringValuesAreKept(): void
    {
        $this->useTranslator(['unrelated' => 'x']);
        $subject = $this->newSubject()->setData([null, 12]);
        $this->assertSame([null, 12], $subject->getData());
    }
}
