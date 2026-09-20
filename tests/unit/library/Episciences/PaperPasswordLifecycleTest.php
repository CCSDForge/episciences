<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaperPasswordLifecycleTest extends TestCase
{
    /** @dataProvider statuses */
    public function testPersistedPasswordIsClearedOnPublication(int $status, ?string $expected): void
    {
        $previousDb = Zend_Db_Table_Abstract::getDefaultAdapter();
        $cache = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $cache->setAccessible(true);
        $previousCache = $cache->getValue();
        $cache->setValue(null, ['rvid_' . RVID => false]);
        $db = $this->createMock(Zend_Db_Adapter_Pdo_Mysql::class);
        $db->expects(self::once())->method('update')->with(T_PAPERS, self::callback(
            static fn($data) => $data['PASSWORD'] === $expected && $data['STATUS'] === $status
        ), ['DOCID = ?' => 123])->willReturn(1);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);
        $paper = $this->getMockBuilder(Episciences_Paper::class)->disableOriginalConstructor()
            ->onlyMethods(['toJson', 'applyPositioningStrategy'])->getMock();
        $paper->setDocid(123)->setVersion(2)->setIdentifier('1234.5678')->setRvid(RVID)->setStatus($status)->setPassword('encrypted-password');
        try {
            self::assertTrue($paper->save());
            self::assertSame($expected, $paper->getPassword());
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousDb);
            $cache->setValue(null, $previousCache);
        }
    }

    public static function statuses(): iterable
    {
        yield 'published' => [Episciences_Paper::STATUS_PUBLISHED, null];
        yield 'awaiting publication' => [Episciences_Paper::STATUS_ALT_AWAITING_PUBLICATION, 'encrypted-password'];
    }

    /** @dataProvider viewers */
    public function testPasswordPanelEscapesValueAndShowsDecryptionFailureOnlyToManagers(bool $owner, bool $failed): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isOwner')->willReturn($owner);
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_ALT_FINAL_VERSION_SUBMITTED);
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->paper = $paper;
        $view->displayPaperPasswordBloc = true;
        $view->paperPassword = '\"><script>alert(1)</script>';
        $view->paperPasswordDecryptionFailed = $failed;
        $html = $view->render('partials/paper_password_form.phtml');
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertSame(!$owner && $failed, str_contains($html, 'alert alert-danger'));
    }

    public static function viewers(): iterable
    {
        yield 'author' => [true, true];
        yield 'manager, failure' => [false, true];
        yield 'manager, success' => [false, false];
    }
}
