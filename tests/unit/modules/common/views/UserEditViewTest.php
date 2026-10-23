<?php

declare(strict_types=1);

namespace unit\modules\common\views;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Zend_Form;
use Zend_View;

/**
 * Rendering tests for user/edit.phtml: the profile form keeps the action set by
 * UserDefaultController::editAction(), which includes the edited account identifier.
 */
final class UserEditViewTest extends TestCase
{
    public function testFormIsPostedToTheActionNamingTheEditedAccount(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/common/views/scripts');

        $form = new Zend_Form();
        $form->setView($view);
        $form->setAction('/user/edit/userid/1788032');
        $form->addElement('submit', 'submit');
        $view->form = $form;

        $html = $view->render('user/edit.phtml');

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        $forms = (new DOMXPath($document))->query('//form');

        self::assertNotFalse($forms);
        self::assertSame(1, $forms->length);
        $formElement = $forms->item(0);
        self::assertInstanceOf(\DOMElement::class, $formElement);
        self::assertSame('/user/edit/userid/1788032', $formElement->getAttribute('action'));
    }
}
