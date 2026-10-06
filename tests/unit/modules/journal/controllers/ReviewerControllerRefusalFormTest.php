<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper_Logger;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rendering of the paper log modal (every user-controlled field must be escaped) and of the
 * invitation response form (an invalid refusal reopens the refusal form).
 */
final class ReviewerControllerRefusalFormTest extends TestCase
{
    private const SCRIPTS = '/modules/journal/views/scripts/';

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private mixed $previousLocale = null;

    protected function setUp(): void
    {
        $this->previousLocale = \Zend_Registry::isRegistered('Zend_Locale') ? \Zend_Registry::get('Zend_Locale') : null;
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set(
            'Zend_Translate',
            new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr'])
        );
    }

    protected function tearDown(): void
    {
        if ($this->previousLocale !== null) {
            \Zend_Registry::set('Zend_Locale', $this->previousLocale);
        }
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPTS);
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        return $view;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function loggedActionProvider(): array
    {
        return [
            'invitation' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION],
            'invitation accepted' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED],
            'invitation declined' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED],
            'abandon' => [Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS],
            'continue' => [Episciences_Paper_Logger::CODE_CONTINUE_PUBLICATION_PROCESS],
        ];
    }

    /**
     * @dataProvider loggedActionProvider
     */
    public function testLogModalEscapesEveryUserControlledField(string $action): void
    {
        $view = $this->view();
        $view->user = ['fullname' => self::PAYLOAD];
        $view->log = [
            'action' => $action,
            'uid' => 1,
            'date' => '2026-01-01 10:00:00',
            'detail' => [
                'user' => ['fullname' => self::PAYLOAD, 'username' => self::PAYLOAD, 'email' => self::PAYLOAD],
                'reviewer_suggestion' => self::PAYLOAD,
                'refusal_reason' => self::PAYLOAD,
                'lastStatus' => 0,
            ],
        ];

        $html = $view->render('administratepaper/log.phtml');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, 'the values are displayed, escaped');
    }

    private function responseForm(bool $invalidRefusal): string
    {
        $invitation = $this->createMock(\Episciences_User_Invitation::class);
        $invitation->method('hasExpired')->willReturn(false);
        $invitation->method('isAnswered')->willReturn(false);
        $invitation->method('isCancelled')->willReturn(false);

        $view = $this->view();
        $view->invitation = $invitation;
        $view->user_form = null;
        $view->refuse_form = '<form id="refuse"></form>';
        $view->invalid_refuse_form = $invalidRefusal;

        return $view->render('reviewer/invitation_response_form.phtml');
    }

    public function testInvalidRefusalReopensTheRefusalForm(): void
    {
        self::assertStringContainsString('refuseInvitation();', $this->responseForm(true));
    }

    public function testRefusalFormStaysClosedByDefault(): void
    {
        $html = $this->responseForm(false);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('id="refuse_form" style="display: none"', $html);
    }
}
