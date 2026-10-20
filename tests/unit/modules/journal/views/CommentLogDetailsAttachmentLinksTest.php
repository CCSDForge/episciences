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

    private const REGISTRY_KEYS = ['Zend_Locale', 'Zend_Translate'];

    /** @var array<string, mixed> registry entries present before the test */
    private array $previousRegistry = [];

    protected function setUp(): void
    {
        $this->previousRegistry = [];

        foreach (self::REGISTRY_KEYS as $key) {
            if (\Zend_Registry::isRegistered($key)) {
                $this->previousRegistry[$key] = \Zend_Registry::get($key);
            }
        }

        // The template formats the comment date with the locale of the request
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set(
            'Zend_Translate',
            new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr'])
        );
    }

    protected function tearDown(): void
    {
        foreach (self::REGISTRY_KEYS as $key) {
            if (array_key_exists($key, $this->previousRegistry)) {
                \Zend_Registry::set($key, $this->previousRegistry[$key]);
            } elseif (\Zend_Registry::isRegistered($key)) {
                \Zend_Registry::getInstance()->offsetUnset($key);
            }
        }
    }

    /**
     * @param string[] $files
     */
    private function render(int $commentType, array $files = ['answer.pdf'], ?int $paperId = self::PAPERID): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $view->log = [
            'paperid' => $paperId,
            'docid' => self::DOCID,
            'date' => '2026-10-16 10:00:00',
            'detail' => [
                'comment' => [
                    'type' => $commentType,
                    'docid' => self::DOCID,
                    'file' => json_encode($files),
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

    public function testTemporaryVersionFileIsNotLinkedWithoutPaperId(): void
    {
        // The docid would only match the paper id for the first version: no link rather than another paper's file
        $html = $this->render(Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, ['answer.pdf'], null);

        self::assertStringNotContainsString('tmp_files/', $html);
        self::assertMatchesRegularExpression('/>\s*answer\.pdf\s*</u', $html);
    }

    public function testOtherCommentFileIsLinkedWithTheDocId(): void
    {
        $html = $this->render(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT);

        self::assertStringContainsString('href="/docfiles/comments/' . self::DOCID . '/answer.pdf"', $html);
    }

    public function testFileNameIsEncodedInTheLink(): void
    {
        $html = $this->render(Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, ['réponse #2?.pdf']);

        self::assertStringContainsString('href="/tmp_files/' . self::PAPERID . '/r%C3%A9ponse%20%232%3F.pdf"', $html);
        self::assertMatchesRegularExpression('/>\s*réponse #2\?\.pdf\s*</u', $html);
    }
}
