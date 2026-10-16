<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Links to the files of a comment in the paper history (administratepaper/new_comment_log_details.phtml):
 * the files of a temporary version are stored by paper, the other comment files by version.
 */
final class CommentLogDetailsAttachmentLinksTest extends TestCase
{
    private const PAPERID = 10;
    private const DOCID = 15;

    private mixed $previousLocale = null;

    protected function setUp(): void
    {
        // The template formats the comment date with the locale of the request
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

    private function render(int $commentType): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $view->log = [
            'paperid' => self::PAPERID,
            'docid' => self::DOCID,
            'date' => '2026-10-16 10:00:00',
            'detail' => [
                'comment' => [
                    'type' => $commentType,
                    'docid' => self::DOCID,
                    'file' => json_encode(['answer.pdf']),
                ],
            ],
        ];
        $view->user = ['fullname' => 'Jane Doe', 'email' => 'jane.doe@example.org'];

        return $view->render('administratepaper/new_comment_log_details.phtml');
    }

    public function testTemporaryVersionFileIsLinkedWithThePaperId(): void
    {
        $html = $this->render(Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION);

        self::assertStringContainsString('href="/tmp_files/' . self::PAPERID . '/answer.pdf"', $html);
    }

    public function testOtherCommentFileIsLinkedWithTheDocId(): void
    {
        $html = $this->render(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT);

        self::assertStringContainsString('href="/docfiles/comments/' . self::DOCID . '/answer.pdf"', $html);
    }
}
