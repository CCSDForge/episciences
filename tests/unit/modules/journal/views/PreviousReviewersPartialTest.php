<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences\Reviewer\InvitationEligibility;
use Episciences\Reviewer\PreviousVersionReviewers;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Invitation page: table of the reviewers of the previous versions (git #1387).
 */
final class PreviousReviewersPartialTest extends TestCase
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

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function render(array $rows): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        return $view->partial('partials/previous_reviewers.phtml', ['previousReviewers' => $rows, 'reviewerWorkloads' => []]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function row(array $overrides = []): array
    {
        return $overrides + [
            'key' => 'r0', 'docid' => 20, 'version' => 2.0, 'tmp_version' => false, 'version_date' => '2026-04-01 10:00:00',
            'uid' => 1, 'tmp_user' => false, 'full_name' => 'Jane Doe', 'email' => 'jane@example.org',
            'status' => PreviousVersionReviewers::STATUS_REPORT_COMPLETED, 'date' => '2026-05-01 10:00:00',
            'can_reinvite' => true, 'refusal' => null,
        ];
    }

    public function testNothingWithoutPreviousReviewers(): void
    {
        self::assertSame('', trim($this->render([])));
    }

    public function testAReviewerWhoMayBeInvitedAgain(): void
    {
        $html = $this->render([self::row()]);

        self::assertStringContainsString('Reviewers of the previous versions', $html);
        self::assertStringContainsString('Version 2', $html);
        self::assertStringContainsString('Report submitted', $html);
        self::assertStringContainsString('data-reinvite="r0"', $html);
        self::assertStringContainsString('aria-label="Invite Jane Doe again"', $html);
        self::assertStringContainsString('href="/user/view/userid/1"', $html);
    }

    public function testTheReasonIsShownInsteadOfTheButton(): void
    {
        $html = $this->render([self::row(['can_reinvite' => false, 'refusal' => InvitationEligibility::REASON_ALREADY_INVITED])]);

        self::assertStringNotContainsString('data-reinvite', $html);
        self::assertStringContainsString('Already invited on this version', $html);
    }

    public function testAPersonWithoutAnAccountHasNoProfileAndATemporaryVersionIsNamedSo(): void
    {
        $html = $this->render([self::row(['tmp_user' => true, 'tmp_version' => true, 'status' => PreviousVersionReviewers::STATUS_DECLINED])]);

        self::assertStringNotContainsString('/user/view/userid/', $html);
        self::assertStringContainsString('Temporary version of', $html);
        self::assertStringContainsString('Invitation declined', $html);
    }

    public function testARequestAnsweredWithoutAnAccountSaysSo(): void
    {
        $html = $this->render([self::row(['light_answer' => true]), self::row(['key' => 'r1', 'uid' => 2, 'light_answer' => false])]);

        self::assertSame(1, substr_count($html, 'Without an account'));
    }

    public function testNamesAreEscaped(): void
    {
        $html = $this->render([self::row(['full_name' => '<b>Jane</b>', 'email' => 'a"b@example.org'])]);

        self::assertStringContainsString('&lt;b&gt;Jane&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Jane</b>', $html);
        self::assertStringContainsString('a&quot;b@example.org', $html);
    }
}
