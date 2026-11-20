<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use AdministratepaperController;
use Episciences\Reviewer\InvitationEligibility;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Invitation page opened to invite again the reviewer of an expired invitation who may not be: step 1 tells why.
 */
final class InviteReviewerReinvitationRefusalPartialTest extends TestCase
{
    private ?Zend_Translate $previousTranslate = null;
    private ?Zend_Locale $previousLocale = null;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php';
    }

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
        foreach (['Zend_Locale' => $this->previousLocale, 'Zend_Translate' => $this->previousTranslate] as $key => $previous) {
            if ($previous !== null) {
                Zend_Registry::set($key, $previous);
            } else {
                Zend_Registry::getInstance()->offsetUnset($key);
            }
        }
    }

    private function render(?string $refusal): string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(12);
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        return $view->partial('partials/invitereviewer.phtml', [
            'paper' => $paper,
            'previousReviewers' => [],
            'reviewerWorkloads' => [],
            'reviewersCount' => 0,
            'invitation_form' => '',
            'referer' => '/administratepaper/list',
            'isExistingCriterionGrid' => true,
            'reinviteRefusal' => $refusal,
        ]);
    }

    public function testTheReasonIsShownAboveTheChoiceOfTheReviewer(): void
    {
        $html = $this->render(AdministratepaperController::invitationRefusalMessage(InvitationEligibility::REASON_CONFLICT));

        self::assertMatchesRegularExpression(
            '/<div id="reinvite-refusal" class="alert alert-warning" role="alert">\s*This person declared a conflict of interest with this article: they may not be invited\.\s*<\/div>/',
            $html
        );
        self::assertLessThan(strpos($html, 'id="reviewer-search"'), strpos($html, 'id="reinvite-refusal"'));
    }

    public function testNoReasonWhenTheReviewerMayBeInvitedAgain(): void
    {
        self::assertStringNotContainsString('reinvite-refusal', $this->render(null));
    }
}
