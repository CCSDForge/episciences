<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Acl;
use Episciences_Csrf_Helper;
use Episciences_Website_Navigation_Page;
use Episciences_Website_Navigation_Page_About;
use Episciences_Website_Navigation_Page_Custom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use unit\modules\common\controllers\support\WebsiteControllerTestTrait;
use WebsiteDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Form;

require_once __DIR__ . '/support/WebsiteControllerTestTrait.php';

/**
 * Menu pages submitted to WebsiteDefaultController::menuAction(): the checks of a page depend on the
 * class of the page kept in session, never on the "type" field sent by the browser.
 */
final class WebsiteDefaultControllerMenuPageTypeTest extends TestCase
{
    use WebsiteControllerTestTrait;

    private const RESERVED = "Le permalien '%s' est réservé aux pages prédéfinies. Veuillez choisir un autre permalien.";

    private Zend_Db_Adapter_Abstract $previousAdapter;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll', 'fetchPairs', 'fetchRow', 'fetchCol', 'insert', 'update', 'delete'])
            ->getMock();
        // No page saved yet for the journal
        $adapter->method('fetchAll')->willReturn([]);
        $adapter->method('fetchPairs')->willReturn([]);
        $adapter->method('fetchRow')->willReturn(false);
        $adapter->method('fetchCol')->willReturn([]);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * Posts one page of the menu, kept in session as $page, and returns the options given to setPage()
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function postPage(Episciences_Website_Navigation_Page $page, array $options): array
    {
        $website = new class ($page) {
            /** @var array<string, mixed> */
            public array $savedOptions = [];

            public function __construct(private readonly Episciences_Website_Navigation_Page $page)
            {
            }

            public function getPage(mixed $pageId): Episciences_Website_Navigation_Page
            {
                return $this->page;
            }

            /**
             * @param array<string, mixed> $options
             */
            public function setPage(mixed $pageId, array $options): void
            {
                $this->savedOptions = $options;
            }

            /**
             * @param array<int, mixed> $arguments
             */
            public function __call(string $name, array $arguments): mixed
            {
                return null;
            }
        };

        $post = ['pages_1' => $options, 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;
        $controller = new WebsiteDefaultController($request, new Zend_Controller_Response_HttpTestCase());

        // A plain holder: a real session namespace would try to serialize the anonymous double
        $sessionProperty = new \ReflectionProperty(WebsiteDefaultController::class, '_session');
        $sessionProperty->setAccessible(true);
        $sessionProperty->setValue($controller, (object)['website' => $website]);

        $controller->menuAction();

        return $website->savedOptions;
    }

    /**
     * @return MockObject&Zend_Form
     */
    private function validForm(): MockObject
    {
        $form = $this->createMock(Zend_Form::class);
        $form->method('isValid')->willReturn(true);

        return $form;
    }

    public function testReservedPermalinkOfACustomPageIsRefusedWhateverTheTypeSent(): void
    {
        $page = $this->createMock(Episciences_Website_Navigation_Page_Custom::class);
        $page->method('getPermalien')->willReturn('my-page');
        $page->method('getForm')->willReturn($this->validForm());

        $saved = $this->postPage($page, [
            'type' => 'Episciences_Website_Navigation_Page_File',
            'permalien' => 'about',
        ]);

        self::assertArrayNotHasKey('permalien', $saved);
        self::assertSame([sprintf(self::RESERVED, 'about')], $this->errorMessages());
    }

    public function testLabelsOfAPredefinedPageAreIgnoredWhateverTheTypeSent(): void
    {
        $page = $this->createMock(Episciences_Website_Navigation_Page_About::class);
        // Predefined page titles come from the translations: their form is not validated
        $page->expects(self::never())->method('getForm');

        $saved = $this->postPage($page, [
            'type' => 'Episciences_Website_Navigation_Page_Custom',
            'labels' => ['en' => 'Forged title'],
        ]);

        self::assertArrayNotHasKey('labels', $saved);
        self::assertSame([], $this->errorMessages());
    }

    public function testCustomPageSentAsPredefinedKeepsItsLabelsAndIsValidated(): void
    {
        $page = $this->createMock(Episciences_Website_Navigation_Page_Custom::class);
        $page->method('getPermalien')->willReturn('my-page');
        $page->expects(self::once())->method('getForm')->willReturn($this->validForm());

        $saved = $this->postPage($page, [
            'type' => 'Episciences_Website_Navigation_Page_About',
            'labels' => ['en' => 'My page'],
        ]);

        self::assertSame(['en' => 'My page'], $saved['labels']);
    }
}
