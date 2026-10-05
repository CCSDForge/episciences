<?php

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_Form;
use Zend_Form_Element_Multiselect;
use Zend_View;

/**
 * Layout tests for paper/filters.phtml
 *
 * The decision-suggestion field only exists on the paper management lists
 * (Episciences_PapersManager::isSuggestionFilterAllowed()). Where it is absent, the row must
 * not be left with a four-column hole.
 */
class PaperFiltersLayoutTest extends TestCase
{
    private const VIEW_SCRIPT = 'paper/filters.phtml';

    private Zend_View $view;

    private ?\Zend_Controller_Request_Abstract $previousRequest = null;

    protected function setUp(): void
    {
        $this->view = new Zend_View();
        $this->view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');

        // The view script reads the current action name
        $front = \Zend_Controller_Front::getInstance();
        $this->previousRequest = $front->getRequest();
        $request = new \Zend_Controller_Request_Http('http://localhost/');
        $request->setActionName('list');
        $front->setRequest($request);
    }

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(\Zend_Controller_Front::class, '_request');
        $property->setAccessible(true);
        $property->setValue(\Zend_Controller_Front::getInstance(), $this->previousRequest);
    }

    /**
     * @param string[] $elementNames
     */
    private function render(array $elementNames): string
    {
        $form = new Zend_Form();
        $form->setAction('/administratepaper/list');
        $form->setMethod('get');

        foreach ($elementNames as $name) {
            $form->addElement(new Zend_Form_Element_Multiselect([
                'name' => $name,
                'multiOptions' => ['' => 'Tous'],
                'value' => '',
            ]));
        }

        $this->view->element = $form;
        $this->view->open = true;

        return $this->view->render(self::VIEW_SCRIPT);
    }

    public function testSectionFieldSpansHalfTheRowWhenTheSuggestionFilterIsShown(): void
    {
        $output = $this->render(['sid', 'suggestion', 'repositories']);

        self::assertStringContainsString('col-sm-4', $output);
        self::assertStringNotContainsString('col-sm-8', $output);
    }

    public function testSectionFieldTakesBackTheWidthWhenTheSuggestionFilterIsHidden(): void
    {
        $output = $this->render(['sid', 'repositories']);

        // 8 + 4 columns: no hole left where the suggestion field would have been
        self::assertStringContainsString('col-sm-8', $output);
    }

    public function testSuggestionFieldIsOnlyRenderedWhenPresentOnTheForm(): void
    {
        self::assertStringContainsString('name="suggestion', $this->render(['sid', 'suggestion']));
        self::assertStringNotContainsString('name="suggestion', $this->render(['sid']));
    }
}
