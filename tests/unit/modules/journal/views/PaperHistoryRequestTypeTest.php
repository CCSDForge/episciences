<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Paper_Logger;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Paper history (partials/paper_history_log_body.phtml): a reviewer invitation shows the review request type recorded
 * at the time of the invitation (#319).
 */
final class PaperHistoryRequestTypeTest extends TestCase
{
    private bool $localeRegistered = false;

    protected function setUp(): void
    {
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
            $this->localeRegistered = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->localeRegistered) {
            \Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function render(array $detail): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        return $view->partial('partials/paper_history_log_body.phtml', [
            'log' => ['ACTION' => Episciences_Paper_Logger::CODE_REVIEWER_INVITATION],
            'detail' => $detail,
            'status' => '',
            'fullName' => 'Editor Name',
            'tag' => '',
        ]);
    }

    public function testTheRequestTypeOfTheInvitationIsShownEscaped(): void
    {
        $html = $this->render([
            'user' => ['fullname' => 'Reviewer'],
            'request_type' => ['id' => 11, 'code' => 'phase_two', 'labels' => ['en' => 'Phase <two>']],
        ]);

        self::assertStringContainsString('Editor Name', $html);
        self::assertStringContainsString('Phase &lt;two&gt;', $html);
        self::assertStringNotContainsString('Phase <two>', $html);
    }

    public function testAnInvitationRecordedBeforeTheTypesShowsNoType(): void
    {
        $html = $this->render(['user' => ['fullname' => 'Reviewer']]);

        self::assertStringContainsString('Editor Name', $html);
        self::assertStringNotContainsString('<small>', $html);
    }
}
