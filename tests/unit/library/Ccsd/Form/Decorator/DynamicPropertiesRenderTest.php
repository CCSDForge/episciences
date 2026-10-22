<?php

namespace unit\library\Ccsd\Form\Decorator;

use Ccsd_Form_Element_Date;
use Ccsd_Form_Element_MultiTextArea;
use Ccsd_Form_Element_MultiTextSimple;
use Ccsd_Form_Element_MultiTextSimpleLang;
use PHPUnit\Framework\TestCase;
use Zend_Form_Element;
use Zend_View;

/**
 * Rendering the multi-value and date elements must not raise any deprecation, such as
 * the creation of dynamic properties on their decorators (deprecated since PHP 8.2).
 */
class DynamicPropertiesRenderTest extends TestCase
{
    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        // Record every deprecation, not only the dynamic property ones: an error handler
        // returning false would hand them to the PHP handler, which never fails the test
        set_error_handler(function (int $errno, string $errstr): bool {
            $this->deprecations[] = $errstr;
            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    /**
     * @return array<string, array{callable(): Zend_Form_Element, string}>
     */
    public static function elementProvider(): array
    {
        return [
            'multi text' => [
                static fn () => new Ccsd_Form_Element_MultiTextSimple('keywords', ['value' => ['first', 'second']]),
                'first',
            ],
            'multi text with languages' => [
                static fn () => new Ccsd_Form_Element_MultiTextSimpleLang('title', [
                    'languages' => ['en' => 'English', 'fr' => 'French'],
                    'value' => ['en' => 'A title', 'fr' => 'Un titre'],
                ]),
                'Un titre',
            ],
            'multi text area' => [
                static fn () => new Ccsd_Form_Element_MultiTextArea('abstracts', ['value' => ['An abstract']]),
                'An abstract',
            ],
            'date' => [
                static fn () => new Ccsd_Form_Element_Date('date', [
                    'prefixPath' => ['decorator' => ['Ccsd_Form_Decorator' => 'Ccsd/Form/Decorator']],
                    'value' => '2026-10-22',
                ]),
                '2026-10-22',
            ],
        ];
    }

    /**
     * @dataProvider elementProvider
     * @param callable(): Zend_Form_Element $factory
     */
    public function testRenderingRaisesNoDeprecation(callable $factory, string $expectedValue): void
    {
        $element = $factory();
        $element->setView(new Zend_View());

        $html = $element->render();

        self::assertStringContainsString($expectedValue, $html);
        self::assertSame([], array_values(array_unique($this->deprecations)));
    }
}
