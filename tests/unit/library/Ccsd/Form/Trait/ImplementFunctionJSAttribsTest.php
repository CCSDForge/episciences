<?php

namespace unit\library\Ccsd\Form\Trait;

use Ccsd_Form_Decorator_Referentiel;
use Ccsd_Form_Element_Date;
use Ccsd_Form_Element_Liste;
use Ccsd_Form_Element_MultiReferentiel;
use Ccsd_Form_Element_MultiTextArea;
use Ccsd_Form_Element_MultiTextSimple;
use Ccsd_Form_Element_MultiTextSimpleLang;
use Ccsd_Form_Element_Referentiel;
use PHPUnit\Framework\TestCase;
use Zend_Form_Element;
use Zend_View;

/**
 * The location of the JavaScript templates is internal: it must not be rendered as HTML
 * attributes of the form fields, while the decorators must still find the templates.
 */
class ImplementFunctionJSAttribsTest extends TestCase
{
    /**
     * Options formerly accepted by the elements, which must still be ignored
     */
    private const LEGACY_OPTIONS = ['pathDir' => '/legacy/path', 'relPublicDirPath' => 'legacy/public'];

    /**
     * Elements rendered without any external service, with the templates their decorators must load
     * @return array<string, array{callable(array<string, mixed>): Zend_Form_Element, string, list<string>}>
     */
    public static function renderableElementProvider(): array
    {
        return [
            'multi text' => [
                static fn (array $options) => new Ccsd_Form_Element_MultiTextSimple(
                    'keywords',
                    $options + ['value' => ['first', 'second']]
                ),
                'first',
                ['multi/text/simple/add', 'multi/text/simple/delete'],
            ],
            'multi text with languages' => [
                static fn (array $options) => new Ccsd_Form_Element_MultiTextSimpleLang('title', $options + [
                    'languages' => ['en' => 'English', 'fr' => 'French'],
                    'value' => ['en' => 'A title', 'fr' => 'Un titre'],
                ]),
                'Un titre',
                ['lang/init', 'multi/text/simple/lang/add', 'multi/text/simple/lang/delete'],
            ],
            'multi text area' => [
                static fn (array $options) => new Ccsd_Form_Element_MultiTextArea(
                    'abstracts',
                    $options + ['value' => ['An abstract']]
                ),
                'An abstract',
                ['multi/area/simple/add', 'multi/area/simple/delete'],
            ],
            'date' => [
                static fn (array $options) => new Ccsd_Form_Element_Date('date', $options + [
                    'prefixPath' => ['decorator' => ['Ccsd_Form_Decorator' => 'Ccsd/Form/Decorator']],
                    'value' => '2026-10-22',
                ]),
                '2026-10-22',
                ['date/init'],
            ],
        ];
    }

    /**
     * Every element using the trait, including those whose rendering needs external services
     * @return array<string, array{callable(array<string, mixed>): Zend_Form_Element}>
     */
    public static function allElementProvider(): array
    {
        $elements = array_map(
            static fn (array $case): array => [$case[0]],
            self::renderableElementProvider()
        );
        $elements['referentiel'] = [static fn (array $options) => new Ccsd_Form_Element_Referentiel('journal', $options)];
        $elements['multi referentiel'] = [
            static fn (array $options) => new Ccsd_Form_Element_MultiReferentiel('projects', $options),
        ];
        // Thesaurus (Liste's parent) is deprecated and announces it at construction time
        $elements['liste'] = [
            static fn (array $options) => self::ignoringDeprecations(
                static fn () => new Ccsd_Form_Element_Liste('domains', $options)
            ),
        ];

        return $elements;
    }

    /**
     * Elements whose referential decorator loads its templates as soon as it is attached
     * @return array<string, array{callable(): Zend_Form_Element}>
     */
    public static function referentielElementProvider(): array
    {
        $options = ['prefixPath' => ['decorator' => ['Ccsd_Form_Decorator' => 'Ccsd/Form/Decorator']]];

        return [
            'referentiel' => [static fn () => new Ccsd_Form_Element_Referentiel('journal', $options)],
            'multi referentiel' => [static fn () => new Ccsd_Form_Element_MultiReferentiel('projects', $options)],
        ];
    }

    /**
     * @dataProvider renderableElementProvider
     * @param callable(array<string, mixed>): Zend_Form_Element $factory
     */
    public function testInternalPropertiesAreNotRenderedAsAttributes(callable $factory, string $expectedValue): void
    {
        $element = $factory(self::LEGACY_OPTIONS);
        $html = self::render($element);

        self::assertStringContainsString($expectedValue, $html);
        foreach (array_keys(self::LEGACY_OPTIONS) as $property) {
            self::assertStringNotContainsStringIgnoringCase($property, $html);
        }
    }

    /**
     * @dataProvider allElementProvider
     * @param callable(array<string, mixed>): Zend_Form_Element $factory
     */
    public function testInternalPropertiesAreNotAttributes(callable $factory): void
    {
        foreach ([[], self::LEGACY_OPTIONS] as $options) {
            $element = $factory($options);
            $attribs = $element->getAttribs();

            foreach (array_keys(self::LEGACY_OPTIONS) as $property) {
                self::assertArrayNotHasKey($property, $attribs);
                self::assertNull($element->getAttrib($property));
            }
        }
    }

    /**
     * @dataProvider allElementProvider
     * @param callable(array<string, mixed>): Zend_Form_Element $factory
     */
    public function testJavascriptTemplateDirectoryExists(callable $factory): void
    {
        self::assertDirectoryExists($factory([])->getJsTemplateDir());
    }

    /**
     * @dataProvider renderableElementProvider
     * @param callable(array<string, mixed>): Zend_Form_Element $factory
     * @param list<string> $templates
     */
    public function testJavascriptFunctionsAreGeneratedFromTemplates(
        callable $factory,
        string $expectedValue,
        array $templates
    ): void {
        $element = $factory([]);
        self::render($element);

        self::assertFunctionsGeneratedFrom($templates, $element);
    }

    /**
     * @dataProvider referentielElementProvider
     * @param callable(): Zend_Form_Element $factory
     */
    public function testReferentielDecoratorGeneratesFunctionsFromTemplates(callable $factory): void
    {
        $element = $factory();
        $decorator = $element->getDecorator('Referentiel');
        self::assertInstanceOf(Ccsd_Form_Decorator_Referentiel::class, $decorator);

        // Attaching the element is what render() does before rendering, which needs the referential services.
        // The decorator may raise PHP 8.2+ dynamic property deprecations, out of the scope of this test
        self::ignoringDeprecations(static fn () => $decorator->setElement($element));

        self::assertFunctionsGeneratedFrom(
            ['referentiel/add', 'referentiel/delete', 'referentiel/edit'],
            $element
        );
    }

    /**
     * Each template must have produced exactly one function, its %%XXX%% placeholders being substituted or not
     * @param list<string> $templates
     */
    private static function assertFunctionsGeneratedFrom(array $templates, Zend_Form_Element $element): void
    {
        // getJavascript('function') returns the whole array, whatever the templates: read the functions themselves
        $functions = $element->getJavascript()['function'];
        self::assertCount(count($templates), $functions);

        foreach ($templates as $template) {
            $file = $element->getJsTemplateDir() . '/' . $template . '.js';
            self::assertFileExists($file);

            $pattern = '/^' . implode('.*?', array_map(
                static fn (string $part): string => preg_quote($part, '/'),
                preg_split('/%%[^%]+%%/', (string) file_get_contents($file))
            )) . '$/s';

            self::assertNotEmpty(
                preg_grep($pattern, $functions),
                sprintf('No JavaScript function generated from the "%s" template', $template)
            );
        }
    }

    private static function render(Zend_Form_Element $element): string
    {
        $element->setView(new Zend_View());

        // The decorators may raise PHP 8.2+ dynamic property deprecations, out of the scope of this test
        return self::ignoringDeprecations(static fn () => $element->render());
    }

    /**
     * Runs $callback silencing deprecations only: any other notice or warning still fails the test
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private static function ignoringDeprecations(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true, E_DEPRECATED | E_USER_DEPRECATED);
        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
