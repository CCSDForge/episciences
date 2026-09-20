<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelineBlockingIssuesTest extends TestCase
{
    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
    }

    /** @dataProvider paperCounts */
    public function testPipelineCanOnlyBeDisabledWithoutArticlesInProgress(string $count, bool $allowed): void
    {
        $review = $this->getMockBuilder(Episciences_Review::class)
            ->disableOriginalConstructor()->onlyMethods(['getPapersCount'])->getMock();
        $review->expects(self::once())->method('getPapersCount')
            ->with(['is' => ['status' => [34, 35, 36, 37, 38, 39]]])->willReturn($count);

        self::assertSame($allowed, $review->canDisableAlternativePipeline());
    }

    public static function paperCounts(): iterable
    {
        yield 'empty' => ['0', true];
        yield 'one article' => ['1', false];
        yield 'several articles' => ['6', false];
    }

    /** @dataProvider menuCases */
    public function testRenderedStatusMenu(string $role, int $status, bool $enabled, bool $canPublish, array $targets): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn(42);
        $user->method('getRoles')->willReturn([$role]);
        $user->method('getAllRoles')->willReturn([RVID => [$role]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);

        $paper = $this->getMockBuilder(Episciences_Paper::class)->disableOriginalConstructor()
            ->onlyMethods(['getCopyEditors', 'getAcceptanceDate'])->getMock();
        $paper->setStatus($status);
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getAcceptanceDate')->willReturn(null);
        $review = $this->createMock(Episciences_Review::class);
        $review->method('isAlternativePipelineEnabled')->willReturn($enabled);
        $review->method('getSetting')->willReturnCallback(static fn($key) => $key === 'editorsCanPublishPapers' && $canPublish);
        $view = new class extends Zend_View {
            public function partial($name = null, $module = null, $model = null)
            {
                return isset($module['target']) ? '<div class="' . $module['target'] . '"></div>' : '';
            }
        };
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->paper = $paper;
        $view->review = $review;
        $view->altStartLayoutEditingForm = 'start form';
        $view->altSendProofToAuthorForm = 'proof form';
        $html = $view->render('partials/paper_status_button.phtml');
        preg_match_all('/data-target="(\.alt-[^"]+)"/', $html, $matches);
        self::assertSame($targets, $matches[1]);
        if (!$targets) {
            self::assertStringNotContainsString('status-menu', $html);
        }
        foreach ($targets as $target) {
            if ($role === Episciences_Acl::ROLE_COPY_EDITOR) {
                self::assertStringContainsString('class="' . substr($target, 1) . '"', $html);
            }
        }
    }

    public static function menuCases(): iterable
    {
        yield 'secretary, waiting for author' => ['secretary', 34, true, true, []];
        yield 'editor, waiting for proof approval' => ['editor', 37, true, true, []];
        yield 'editor, disabled pipeline' => ['editor', 35, false, true, []];
        yield 'editor, disabled publication' => ['editor', 38, true, false, []];
        yield 'editor, publication' => ['editor', 38, true, true, ['.alt-publish-modal']];
        yield 'editor, no duplicate actions' => ['editor', 35, true, true, [
            '.alt-start-layout-editing-modal', '.alt-incorrect-password-modal', '.alt-incorrect-latex-modal',
        ]];
        yield 'copy editor, start' => ['copyeditor', 35, true, false, ['.alt-start-layout-editing-modal']];
        yield 'copy editor, proof' => ['copyeditor', 36, true, false, ['.alt-send-proof-to-author-modal']];
        yield 'copy editor, disabled pipeline' => ['copyeditor', 35, false, false, []];
    }

    public function testCopyEditorAclIncludesOnlyTheTwoNewLayoutActions(): void
    {
        $acl = new Zend_Config_Ini(APPLICATION_PATH . '/configs/acl.ini', 'copyeditor');
        self::assertTrue(isset($acl->allow->{'administratepaper-altstartlayoutediting'}));
        self::assertTrue(isset($acl->allow->{'administratepaper-altsendprooftoauthor'}));
        self::assertFalse(isset($acl->allow->{'administratepaper-altpublish'}));
    }

    /** @dataProvider disabledSettings */
    public function testSettingsSaveCannotStrandArticles(array $repositories, string $enabled): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewController.php';
        $values = [Episciences_Review::SETTING_REPOSITORIES => $repositories,
            Episciences_Review::SETTING_ALTERNATIVE_PIPELINE => $enabled];
        $post = $values + ['submit' => 'Save'];
        foreach (['invitation_deadline', 'rating_deadline', 'rating_deadline_min', 'rating_deadline_max'] as $key) {
            $post[$key] = '1';
            $post[$key . '_unit'] = 'day';
        }
        $form = $this->getMockBuilder(Ccsd_Form::class)->onlyMethods(['isValid', 'getValues'])->getMock();
        $form->addElement('checkbox', Episciences_Review::SETTING_ALTERNATIVE_PIPELINE);
        $form->method('isValid')->willReturn(true);
        $form->method('getValues')->willReturn($values);
        $review = $this->createMock(Episciences_Review::class);
        $review->method('settingsForm')->willReturn($form);
        $review->method('getSettings')->willReturn([]);
        $review->method('getDoiSettings')->willReturn(new Episciences_Review_DoiSettings());
        $review->expects(self::once())->method('canDisableAlternativePipeline')->willReturn(false);
        $review->expects(self::never())->method('setOptions');
        $review->expects(self::never())->method('save');
        $cache = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $cache->setAccessible(true);
        $previousCache = $cache->getValue();
        $cache->setValue(null, ['rvid_' . RVID => $review]);
        $request = $this->getMockBuilder(Zend_Controller_Request_Http::class)->onlyMethods(['isPost'])->getMock();
        $request->method('isPost')->willReturn(true);
        $request->setPost($post);
        $controller = new ReviewController($request, new Zend_Controller_Response_Http());
        $controller->view = new Zend_View();
        try {
            $controller->settingsAction();
            self::assertNotEmpty($form->getElement(Episciences_Review::SETTING_ALTERNATIVE_PIPELINE)->getMessages());
            self::assertSame($form, $controller->view->form);
        } finally {
            $cache->setValue(null, $previousCache);
        }
    }

    public static function disabledSettings(): iterable
    {
        yield 'unchecked' => [[Episciences_Repositories::ARXIV_REPO_ID], '0'];
        yield 'second repository' => [[Episciences_Repositories::ARXIV_REPO_ID, 999], '1'];
        yield 'removed arxiv' => [[999], '1'];
    }

    public function testManagerRecipientsUseJournalNotificationsAndExcludeConflicts(): void
    {
        $review = $this->createMock(Episciences_Review::class);
        $review->expects(self::once())->method('getSetting')
            ->with(Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS)->willReturn([]);
        $cache = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $cache->setAccessible(true);
        $previousCache = $cache->getValue();
        $previousDb = Zend_Db_Table_Abstract::getDefaultAdapter();
        $previousSettings = Zend_Registry::isRegistered('reviewSettings') ? Zend_Registry::get('reviewSettings') : [];
        $cache->setValue(null, ['rvid_' . RVID => $review]);
        Zend_Registry::set('reviewSettings', [Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => 1]);
        $db = $this->getMockBuilder(Zend_Db_Adapter_Pdo_Mysql::class)->disableOriginalConstructor()
            ->onlyMethods(['fetchCol', '_quote', '_connect'])->getMock();
        $db->method('_quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $db->expects(self::once())->method('fetchCol')->willReturn([42]);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);
        $allowed = $this->createMock(Episciences_User::class);
        $allowed->method('getUid')->willReturn(42);
        $conflicted = $this->createMock(Episciences_User::class);
        $conflicted->method('getUid')->willReturn(43);
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(123);
        $paper->method('getCopyEditors')->willReturn([$allowed, $conflicted, $allowed]);
        $paper->expects(self::never())->method('getEditors');
        try {
            self::assertSame([$allowed], Episciences_PapersManager::getAlternativePipelineManagerRecipients($paper));
        } finally {
            $cache->setValue(null, $previousCache);
            Zend_Db_Table_Abstract::setDefaultAdapter($previousDb);
            Zend_Registry::set('reviewSettings', $previousSettings);
        }
    }

    public function testRejectedDepositDoesNotRetainPasswordInSession(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';
        $controller = (new ReflectionClass(PaperController::class))->newInstanceWithoutConstructor();
        $controller->view = new Zend_View();
        $redirector = $this->createMock(Zend_Controller_Action_Helper_Redirector::class);
        $redirector->method('getName')->willReturn('Redirector');
        $redirector->method('setActionController')->willReturnSelf();
        $redirector->expects(self::once())->method('gotoUrl')->with('/paper/finalversiondeposit/id/123');
        Zend_Controller_Action_HelperBroker::addHelper($redirector);
        $helper = new ReflectionProperty(Zend_Controller_Action::class, '_helper');
        $helper->setAccessible(true);
        $helper->setValue($controller, new Zend_Controller_Action_HelperBroker($controller));
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(123);
        $method = new ReflectionMethod(PaperController::class, 'redirectAltFinalVersionDepositWithError');
        $method->setAccessible(true);
        try {
            $method->invoke($controller, $paper, ['version' => ' 3 ', 'paperPassword' => 'secret'], 'Invalid version');
            $session = new Zend_Session_Namespace('AltFinalVersionDeposit_123');
            self::assertSame('3', $session->values['version']);
            self::assertArrayNotHasKey('paperPassword', $session->values);
            self::assertSame([], $session->values[Episciences_Mail_Send::ATTACHMENTS]);
        } finally {
            Zend_Controller_Action_HelperBroker::removeHelper('redirector');
            unset($session->values);
        }
    }
}
