<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Submit;
use ReflectionMethod;
use SubmitController;
use unit\modules\ControllerTestCase;
use unit\modules\support\ResponseSent;
use Zend_Controller_Front;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

/**
 * A saved submission whose files or linked data could not be retrieved from the repository:
 * the author gets a warning in place of the success message (the warning also says that the paper is saved).
 */
final class SubmitControllerRepositoryFailureTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'SubmitController');

        // The result is followed by a redirection to an action, which needs the default route
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
    }

    public function testRepositoryFailureIsReportedAsAWarning(): void
    {
        $controller = $this->handleSubmissionResult([
            'code' => 1,
            'docId' => 1234,
            Episciences_Submit::RESULT_POST_SAVE_FAILURE => true,
        ]);

        // The warning alone: it also says that the paper is saved
        self::assertSame([], $this->messages($controller, Ccsd_View_Helper_Message::MSG_SUCCESS));
        self::assertSame(
            [Episciences_Submit::POST_SAVE_FAILURE_MESSAGE],
            $this->messages($controller, Ccsd_View_Helper_Message::MSG_WARNING)
        );
    }

    public function testNoWarningWhenTheRepositoryDataIsRetrieved(): void
    {
        $controller = $this->handleSubmissionResult(['code' => 1, 'docId' => 1234]);

        self::assertSame(['saved'], $this->messages($controller, Ccsd_View_Helper_Message::MSG_SUCCESS));
        self::assertSame([], $this->messages($controller, Ccsd_View_Helper_Message::MSG_WARNING));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function handleSubmissionResult(array $result): SubmitController
    {
        $controller = new SubmitController($this->postRequest(), new Zend_Controller_Response_HttpTestCase());
        $controller->view = new Zend_View();
        foreach ([Ccsd_View_Helper_Message::MSG_SUCCESS, Ccsd_View_Helper_Message::MSG_WARNING] as $namespace) {
            $controller->getHelper('FlashMessenger')->clearCurrentMessages($namespace);
        }

        $method = new ReflectionMethod(SubmitController::class, 'handleSubmissionResult');
        $method->setAccessible(true);

        try {
            $method->invoke($controller, $result, 'saved');
        } catch (ResponseSent) {
            // The redirection to the paper page ends the request
        }

        return $controller;
    }

    /**
     * @return list<string>
     */
    private function messages(SubmitController $controller, string $namespace): array
    {
        return array_values($controller->getHelper('FlashMessenger')->getCurrentMessages($namespace));
    }
}
