<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelineProxyUiTest extends TestCase
{
    /** @dataProvider availableForms */
    public function testActionsOnlyExistForAvailableForms(bool $approve, bool $reject): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->label = "Répondre au nom de l'auteur";
        if ($approve) {
            $view->approveForm = new Zend_Form();
        }
        if ($reject) {
            $view->rejectForm = new Zend_Form();
        }
        $html = $view->render('partials/author-proof-actions.phtml');
        self::assertSame($approve, str_contains($html, 'data-target=".alt-author-approve-proof-modal"'));
        self::assertSame($reject, str_contains($html, 'data-target=".alt-author-reject-proof-modal"'));
        self::assertSame($approve || $reject, str_contains($html, '<button'));
    }

    public static function availableForms(): iterable
    {
        yield [false, false];
        yield [true, false];
        yield [false, true];
        yield [true, true];
    }
}
