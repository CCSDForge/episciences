<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the invitation refusal flow (ReviewerController::answerProcess)
 * and for the output escaping of the paper log template.
 *
 * ZF1 controllers and view scripts cannot be rendered without the full request
 * stack, so, as in the other controller tests of this suite, the source is analysed.
 */
final class ReviewerControllerRefusalFormTest extends TestCase
{
    private string $controller;
    private string $responseView;
    private string $logView;

    protected function setUp(): void
    {
        $this->controller = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php'
        );
        $this->responseView = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/views/scripts/reviewer/invitation_response_form.phtml'
        );
        $this->logView = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/views/scripts/administratepaper/log.phtml'
        );
    }

    private function extractAnswerProcess(): string
    {
        $start = strpos($this->controller, 'function answerProcess(');
        self::assertNotFalse($start);
        $end = strpos($this->controller, "\n    private function ", (int) $start + 1);

        return substr($this->controller, (int) $start, ($end === false ? strlen($this->controller) : $end) - (int) $start);
    }

    public function testRefusalFormIsValidatedBeforeSaving(): void
    {
        $method = $this->extractAnswerProcess();
        self::assertStringContainsString('$refuse_form->isValid($request->getPost())', $method);
        self::assertStringContainsString('$refuse_form->getValues()', $method);
    }

    public function testInvalidRefusalIsNotSavedAndReopensTheRefusalForm(): void
    {
        $method = $this->extractAnswerProcess();
        // a refusal is only saved when its form is valid
        self::assertMatchesRegularExpression('/\$refusedFormIsValid\s*\|\|/', $method);
        // an invalid refusal must not fall into the acceptance-form error branch
        self::assertMatchesRegularExpression(
            '/elseif\s*\(\s*\$refused\s*\)\s*\{\s*(\/\/[^\n]*\n\s*)?\$this->view->invalid_refuse_form\s*=\s*true;/',
            $method
        );
    }

    public function testViewReopensRefusalFormOnInvalidRefusal(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(\$this->invalid_refuse_form\)\s*:\s*\?>\s*<script>\s*refuseInvitation\(\);/',
            $this->responseView
        );
    }

    /**
     * Every user-controlled field of the paper log modal must be escaped.
     *
     * @return array<string, array{string}>
     */
    public static function unescapedUserFieldProvider(): array
    {
        return [
            'user fullname' => ["/<\?=\s*\\\$this->user\['fullname'\]\s*\?>/"],
            'logged user fullname' => ["/<\?=\s*\\\$this->log\['detail'\]\['user'\]\['fullname'\]\s*\?>/"],
            'logged user username' => ["/<\?=\s*\\\$this->log\['detail'\]\['user'\]\['username'\]\s*\?>/"],
            'logged user email' => ["/<\?=\s*\\\$this->log\['detail'\]\['user'\]\['email'\]\s*\?>/"],
            'reviewer suggestion' => ["/<\?=\s*\\\$this->log\['detail'\]\['reviewer_suggestion'\]\s*\?>/"],
            'refusal reason' => ["/<\?=\s*\\\$this->log\['detail'\]\['refusal_reason'\]\s*\?>/"],
        ];
    }

    /**
     * @dataProvider unescapedUserFieldProvider
     */
    public function testLogTemplateDoesNotOutputUserFieldsRaw(string $rawOutputPattern): void
    {
        self::assertDoesNotMatchRegularExpression($rawOutputPattern, $this->logView);
    }
}
