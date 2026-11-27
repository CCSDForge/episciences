<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Auth;
use Episciences_User;
use Episciences_View_Helper_WebpackAssets;
use PHPUnit\Framework\TestCase;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Registry;
use Zend_View;

/**
 * Rendering tests for paper/view_scripts.phtml: TinyMCE, FilePond and the other editor-only
 * scripts are only queued for logged-in users, while biblioRef.js (public references/citations
 * panel) is queued for anonymous visitors too.
 */
final class PaperViewScriptsTest extends TestCase
{
    private const JQUERY_CONTAINER_KEY = 'Episciences_View_Helper_JQuery';

    private const EDITOR_ONLY_SCRIPTS = [
        '/js/common/visualDeleteFile.js',
        '/js/paper/copy_editing_form.js',
        '/js/paper/updateOrcidAuthors.js',
        '/js/common/updateMetaData.js',
        '/js/user/affiliations.js',
        '/js/components/collapsible-message.js',
        '/build/filepond.js',
    ];

    private Zend_Auth_Storage_Interface $previousAuthStorage;
    private mixed $previousJQueryContainer = null;
    private string $entrypointsFile = '';

    protected function setUp(): void
    {
        // Script URLs of the editor, defined by public/const.php outside of the tests
        foreach (['VENDOR_TINYMCE', 'TINYMCE_DIR'] as $constant) {
            if (!defined($constant)) {
                define($constant, '');
            }
        }

        $this->previousAuthStorage = Episciences_Auth::getInstance()->getStorage();
        Episciences_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        // The jQuery helper keeps its queue in the registry: start from an empty one
        if (Zend_Registry::isRegistered(self::JQUERY_CONTAINER_KEY)) {
            $this->previousJQueryContainer = Zend_Registry::get(self::JQUERY_CONTAINER_KEY);
            Zend_Registry::getInstance()->offsetUnset(self::JQUERY_CONTAINER_KEY);
        }

        $this->entrypointsFile = (string)tempnam(sys_get_temp_dir(), 'ep_webpack_');
        file_put_contents($this->entrypointsFile, json_encode(['entrypoints' => [
            'filepond' => ['js' => ['/build/filepond.js'], 'css' => ['/build/filepond.css']],
        ]], JSON_THROW_ON_ERROR));
        Episciences_View_Helper_WebpackAssets::setEntrypointsPath($this->entrypointsFile);
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->setStorage($this->previousAuthStorage);

        Zend_Registry::getInstance()->offsetUnset(self::JQUERY_CONTAINER_KEY);
        if ($this->previousJQueryContainer !== null) {
            Zend_Registry::set(self::JQUERY_CONTAINER_KEY, $this->previousJQueryContainer);
        }

        Episciences_View_Helper_WebpackAssets::setEntrypointsPath('');
        if (is_file($this->entrypointsFile)) {
            unlink($this->entrypointsFile);
        }
    }

    /**
     * @return array{scripts: list<string>, stylesheets: list<string>}
     */
    private function renderQueuedAssets(): array
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');

        $view->render('paper/view_scripts.phtml');

        return [
            'scripts' => array_values($view->jQuery()->getJavascriptFiles()),
            'stylesheets' => array_values($view->jQuery()->getStylesheets()),
        ];
    }

    public function testAnonymousVisitorOnlyGetsThePublicReferencesScript(): void
    {
        $assets = $this->renderQueuedAssets();

        self::assertSame(['/js/paper/biblioRef.js'], $assets['scripts']);
        self::assertSame([], $assets['stylesheets']);
    }

    public function testLoggedInUserGetsTheEditorScripts(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->createMock(Episciences_User::class));

        $assets = $this->renderQueuedAssets();

        foreach (self::EDITOR_ONLY_SCRIPTS as $script) {
            self::assertContains($script, $assets['scripts']);
        }
        self::assertContains(VENDOR_TINYMCE, $assets['scripts']);
        self::assertContains(TINYMCE_DIR . 'tinymce_patch.js', $assets['scripts']);
        self::assertContains('/js/paper/biblioRef.js', $assets['scripts']);
        self::assertSame(['/build/filepond.css'], $assets['stylesheets']);
    }
}
