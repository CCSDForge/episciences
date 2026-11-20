<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Invitation page, step 1 in one search (git #900): the search, the browsable list and the new person form.
 */
final class ReviewerSearchPartialTest extends TestCase
{
    private ?Zend_Translate $previousTranslate = null;
    private ?Zend_Locale $previousLocale = null;

    protected function setUp(): void
    {
        $this->previousLocale = Zend_Registry::isRegistered('Zend_Locale') ? Zend_Registry::get('Zend_Locale') : null;
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => require APPLICATION_PATH . '/languages/en/views.php',
            'locale' => 'en',
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->previousLocale !== null) {
            Zend_Registry::set('Zend_Locale', $this->previousLocale);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }

        if ($this->previousTranslate !== null) {
            Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    private function render(): string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(12);
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        return $view->partial('partials/reviewer_search.phtml', ['paper' => $paper]);
    }

    public function testOneSearchFieldWithItsLabelAndHelp(): void
    {
        $html = $this->render();

        self::assertStringContainsString('data-docid="12"', $html);
        self::assertStringContainsString('<label for="reviewer-search-term">Search for a person: last name, first name or e-mail address</label>', $html);
        self::assertMatchesRegularExpression('/id="reviewer-search-term"[^>]*aria-describedby="reviewer-search-help"/s', $html);
        // the results are announced to screen readers
        self::assertMatchesRegularExpression('/id="reviewer-search-status"[^>]*role="status"[^>]*aria-live="polite"/s', $html);
    }

    public function testTheReviewersOfTheJournalCanBeBrowsedInThreeOrders(): void
    {
        $html = $this->render();

        self::assertStringContainsString('<summary>Browse the reviewers of the journal</summary>', $html);
        foreach (['name', 'least_requested', 'last_invitation'] as $order) {
            self::assertStringContainsString('<option value="' . $order . '">', $html);
        }
    }

    public function testANewPersonHasAnOptionalFirstNameAndRequiredAddressAndName(): void
    {
        $html = $this->render();

        self::assertMatchesRegularExpression('/id="new-person-toggle"[^>]*aria-expanded="false"[^>]*aria-controls="new-person"/s', $html);
        self::assertMatchesRegularExpression('/id="new-person-email"[^>]*required/s', $html);
        self::assertMatchesRegularExpression('/id="new-person-lastname"[^>]*required/s', $html);
        self::assertDoesNotMatchRegularExpression('/id="new-person-firstname"[^>]*required/s', $html);
        self::assertStringContainsString('<label for="new-person-firstname"', $html);
        self::assertMatchesRegularExpression('/id="new-person-errors"[^>]*role="alert"/s', $html);
    }
}
