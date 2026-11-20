<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Invitation page: the guidelines follow the current way of choosing whom to invite (git #320, #1387, #900).
 */
final class InviteReviewerGuidelinePartialTest extends TestCase
{
    private ?Zend_Translate $previousTranslate = null;

    protected function setUp(): void
    {
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => require APPLICATION_PATH . '/languages/en/views.php',
            'locale' => 'en',
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->previousTranslate !== null) {
            Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    private function render(): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        return $view->render('partials/invitereviewer_guideline.phtml');
    }

    public function testEachGuidelineIsTranslatedWithItsLead(): void
    {
        $html = $this->render();

        self::assertSame(5, substr_count($html, '<li>'));
        foreach ([
            'Search for the person first.',
            'One account per person.',
            'Share out the requests.',
            'On a new version, think of the reviewers of the previous versions.',
            'Check the invitation.',
        ] as $lead) {
            self::assertStringContainsString('<strong>' . $lead . '</strong>', $html);
        }
    }

    public function testTheGuidelinesNameTheLabelsOfThePage(): void
    {
        $html = $this->render();

        // the labels of the page, as the user sees them; the quotes are escaped
        self::assertStringContainsString('&quot;Requests in the journal&quot;', $html);
        self::assertStringContainsString('&quot;Least requested first&quot;', $html);
        self::assertStringContainsString('Check the review due date and adapt the text if needed.', $html);
    }

    public function testNoMoreWordOfTheFormerSelection(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('namesake', $html);
        self::assertStringNotContainsString('guest account', $html);
    }
}
