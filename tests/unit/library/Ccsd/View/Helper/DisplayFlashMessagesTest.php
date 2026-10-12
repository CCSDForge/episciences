<?php

declare(strict_types=1);

namespace tests\unit\library\Ccsd\View\Helper;

use Ccsd_View_Helper_DisplayFlashMessages;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_HelperBroker;
use Zend_View;

class DisplayFlashMessagesTest extends TestCase
{
    /** @var \ArrayObject<string, array<int, mixed>> */
    private \ArrayObject $messagesStorage;
    private Ccsd_View_Helper_DisplayFlashMessages $helper;
    private Zend_View $view;
    private ?Zend_Controller_Action_Helper_Abstract $originalFlashMessenger = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messagesStorage = new \ArrayObject();

        // Extends the real helper so the view helper's type is honoured, without touching the session
        $flashMessenger = new class($this->messagesStorage) extends Zend_Controller_Action_Helper_FlashMessenger {
            /** @var \ArrayObject<string, array<int, mixed>> */
            private \ArrayObject $storage;

            /** @param \ArrayObject<string, array<int, mixed>> $storage */
            public function __construct(\ArrayObject $storage)
            {
                $this->storage = $storage;
            }

            /** @param string $namespace */
            public function setNamespace($namespace = 'default'): self
            {
                $this->_namespace = $namespace;
                return $this;
            }

            /**
             * @param mixed $message
             * @param string|null $namespace
             */
            public function addMessage($message, $namespace = null): self
            {
                $this->storage[$namespace ?? $this->_namespace][] = $message;
                return $this;
            }

            /** @param string|null $namespace */
            public function hasMessages($namespace = null): bool
            {
                return !empty($this->storage[$namespace ?? $this->_namespace]);
            }

            /** @param string|null $namespace */
            public function hasCurrentMessages($namespace = null): bool
            {
                return false;
            }

            /**
             * @param string|null $namespace
             * @return array<int, mixed>
             */
            public function getMessages($namespace = null): array
            {
                return $this->storage[$namespace ?? $this->_namespace] ?? [];
            }

            /**
             * @param string|null $namespace
             * @return array<int, mixed>
             */
            public function getCurrentMessages($namespace = null): array
            {
                return [];
            }

            /** @param string|null $namespace */
            public function clearMessages($namespace = null): bool
            {
                $this->storage[$namespace ?? $this->_namespace] = [];
                return true;
            }

            /** @param string|null $namespace */
            public function clearCurrentMessages($namespace = null): bool
            {
                return true;
            }
        };

        $this->originalFlashMessenger = Zend_Controller_Action_HelperBroker::hasHelper('FlashMessenger')
            ? Zend_Controller_Action_HelperBroker::getExistingHelper('FlashMessenger')
            : null;

        Zend_Controller_Action_HelperBroker::removeHelper('FlashMessenger');
        Zend_Controller_Action_HelperBroker::addHelper($flashMessenger);

        $this->view = new Zend_View();
        $this->helper = new Ccsd_View_Helper_DisplayFlashMessages();
        $this->helper->setView($this->view);
    }

    protected function tearDown(): void
    {
        Zend_Controller_Action_HelperBroker::removeHelper('FlashMessenger');
        if ($this->originalFlashMessenger !== null) {
            Zend_Controller_Action_HelperBroker::addHelper($this->originalFlashMessenger);
        }
        parent::tearDown();
    }

    public function testReturnsEmptyStringWhenNoMessages(): void
    {
        $output = $this->helper->DisplayFlashMessages();
        $this->assertSame('', $output);
    }

    public function testRendersSuccessToastPayload(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)
              ->addMessage('Sauvegarde effectuée avec succès');

        $output = $this->helper->DisplayFlashMessages();

        $this->assertStringContainsString('window.toast', $output);
        $this->assertStringContainsString('Sauvegarde effectuée avec succès', $output);
        $this->assertStringContainsString('"type":"success"', $output);
    }

    public function testRendersServerSideAlertsUsableWithoutToastBundle(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
              ->addMessage('Merci de compléter le <a href="https://example.org">CAPTCHA</a>');

        $output = $this->helper->DisplayFlashMessages();

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $output . '</body>', LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);

        $alerts = $xpath->query('//div[contains(@class, "flash-messages-fallback")]/div[contains(@class, "alert-danger")]');
        $this->assertNotFalse($alerts);
        $this->assertSame(1, $alerts->length);
        // Allowed markup built by controllers (links, emphasis) is kept
        $this->assertSame(1, $xpath->query('.//a[@href="https://example.org"]', $alerts->item(0))->length);
        $this->assertSame(0, $xpath->query('//noscript')->length);
    }

    public function testPayloadCannotBreakOutOfTheInlineScript(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
              ->addMessage('Texte <strong>important</strong>');

        $output = $this->helper->DisplayFlashMessages();

        // The helper's own script block is the last one; the fallback alerts above it render the sanitized HTML
        $script = substr($output, (int) strrpos($output, '<script>' . PHP_EOL . '(function() {'));
        // Markup in the JSON payload is hex-escaped: the only tags left are the script's own
        $this->assertSame(2, substr_count($script, '<'));
        $this->assertStringContainsString('\u003Cstrong\u003Eimportant', $script);
        $this->assertStringContainsString('"type":"error"', $output);
    }

    public function testStripsUnsafeMarkupAndKeepsAllowedMarkup(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)
              ->addMessage(
                  '<strong>Erreur</strong> <img src=x onerror="alert(1)"><script>alert(2)</script>'
                  . '<a href="javascript:alert(3)">piège</a> <a href="/paper/view" target="_blank" onclick="alert(4)">article</a>'
              );

        $output = $this->helper->DisplayFlashMessages();

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $output . '</body>', LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $alert = $xpath->query('//div[contains(@class, "flash-messages-fallback")]/div')->item(0);
        $this->assertNotNull($alert);

        $this->assertSame(1, $xpath->query('.//strong', $alert)->length);
        $this->assertSame(0, $xpath->query('.//img | .//script | .//*[@onerror] | .//*[@onclick]', $alert)->length);
        $this->assertSame(0, $xpath->query('.//a[starts-with(@href, "javascript:")]', $alert)->length);
        $this->assertSame(1, $xpath->query('.//a[@href="/paper/view"][@target="_blank"]', $alert)->length);

        // The toast payload carries the same sanitized text
        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function testTranslatesPluralMessages(): void
    {
        $translator = new \Zend_Translate([
            'adapter' => 'array',
            'content' => ['%d fichier' => ['%d file', '%d files']],
            'locale' => 'en',
        ]);
        $this->view->registerHelper(
            (new \Zend_View_Helper_Translate($translator))->setView($this->view),
            'translate'
        );

        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_INFO)
              ->addMessage(['%d fichier', '%d fichiers', 2, 'plural' => true]);

        $output = $this->helper->DisplayFlashMessages();

        $this->assertStringContainsString('%d files', $output);
    }

    public function testRespectsAutocloseParameter(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_WARNING)
              ->addMessage('Attention message');

        $output = $this->helper->DisplayFlashMessages('', false);

        $this->assertStringContainsString('"autoclose":false', $output);
    }

    public function testDisplaysMessagesOfTheLegacyErrorNamespace(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace('error')->addMessage('Legacy error message');

        $output = $this->helper->DisplayFlashMessages();

        $this->assertStringContainsString('Legacy error message', $output);
        $this->assertStringContainsString('"type":"error"', $output);
        $this->assertStringContainsString('"autoclose":false', $output);
        $this->assertStringContainsString('alert alert-danger', $output);
    }

    public function testKeepsErrorsAndWarningsUntilDismissedByDefault(): void
    {
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_INFO)->addMessage('Info message');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_SUCCESS)->addMessage('Success message');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_WARNING)->addMessage('Warning message');
        $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->addMessage('Error message');

        $output = $this->helper->DisplayFlashMessages();

        $this->assertSame(1, preg_match('/var notifications = (\[.*\]);/', $output, $matches));
        $notifications = json_decode($matches[1], true);
        $this->assertSame(
            [
                ['error', 'Error message', false],
                ['warning', 'Warning message', false],
                ['success', 'Success message', true],
                ['info', 'Info message', true],
            ],
            array_map(static fn(array $n): array => [$n['type'], $n['text'], $n['autoclose']], $notifications)
        );
    }
}
