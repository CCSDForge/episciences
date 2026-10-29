<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use DOMDocument;
use DOMXPath;
use Episciences_Acl;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Form token check of the public resources page (publicAction of WebsiteDefaultController).
 *
 * A file removal without the per-session token must leave the file in place and tell the user
 * the session has expired. The action is called for real on a file of the journal public directory.
 */
final class WebsiteDefaultControllerCsrfTest extends TestCase
{
    use WebsiteControllerTestTrait;

    private const EXPIRED = 'Votre session a expiré, veuillez réessayer.';

    private string $fileName;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        // The public resources are managed by the secretaries and the webmasters
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);

        Episciences_Csrf_Helper::getSessionToken();

        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->fileName = uniqid('csrf-test-', true) . '.txt';
        file_put_contents($dir . $this->fileName, 'test');
    }

    protected function tearDown(): void
    {
        $file = REVIEW_PATH . 'public/' . $this->fileName;
        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function postRemoval(array $post): Zend_Controller_Response_HttpTestCase
    {
        $post += ['method' => 'remove', 'name' => $this->fileName];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $request->setRequestUri('/website/public');
        $_POST = $post;

        $response = new Zend_Controller_Response_HttpTestCase();
        $controller = new WebsiteDefaultController($request, $response);
        $controller->publicAction();

        return $response;
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function refusedTokenProvider(): array
    {
        return [
            'without token' => [[]],
            'with a wrong token' => [['csrf_token' => 'forged']],
        ];
    }

    /**
     * @param array<string, string> $token
     */
    #[DataProvider('refusedTokenProvider')]
    public function testFileIsNotRemovedWhenTheTokenIsRefused(array $token): void
    {
        $response = $this->postRemoval($token);

        self::assertFileExists(REVIEW_PATH . 'public/' . $this->fileName);
        self::assertSame([self::EXPIRED], $this->errorMessages());
        self::assertTrue($response->isRedirect());
    }

    public function testFileIsRemovedWithAValidToken(): void
    {
        $this->postRemoval(['csrf_token' => Episciences_Csrf_Helper::getSessionToken()]);

        self::assertFileDoesNotExist(REVIEW_PATH . 'public/' . $this->fileName);
        self::assertSame([], $this->errorMessages());
    }

    public function testPageFormSendsTheSessionToken(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/common/views/scripts');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Ccsd/View/Helper', 'Ccsd_View_Helper_');
        $view->csrfToken = Episciences_Csrf_Helper::getSessionToken();
        $view->files = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $view->render('website/public.phtml'));
        libxml_clear_errors();

        $inputs = (new DOMXPath($dom))->query('//form[@id="form"]//input[@name="csrf_token"]');

        self::assertNotFalse($inputs);
        self::assertSame(1, $inputs->length);
        $input = $inputs->item(0);
        self::assertInstanceOf(\DOMElement::class, $input);
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $input->getAttribute('value'));
    }
}
