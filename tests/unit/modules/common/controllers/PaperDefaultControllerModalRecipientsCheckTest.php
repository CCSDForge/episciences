<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Mail\RecipientListValidator;
use Episciences_Paper;
use Episciences_User;
use PaperDefaultController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use unit\modules\support\RecordingDbAdapter;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

/**
 * PaperDefaultController::areOtherRecipientsRefused(): the mails of the paper status modals get the checks of the
 * mailing form (number of recipients, anonymity of the reviewers).
 */
final class PaperDefaultControllerModalRecipientsCheckTest extends TestCase
{
    private const RVID = 3;
    private const PAPERID = 90;

    private PaperDefaultController $controller;
    private RecordingDbAdapter $db;
    private ?Zend_Db_Adapter_Abstract $previousAdapter;

    protected function setUp(): void
    {
        $this->controller = (new ReflectionClass(PaperDefaultController::class))->newInstanceWithoutConstructor();
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->db = new RecordingDbAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    private function isRefused(string $to, array $cc, array $bcc = []): bool
    {
        $user = new Episciences_User();
        $user->setEmail($to);
        $paper = new Episciences_Paper();
        $paper->setRvid(self::RVID);
        $paper->setPaperid(self::PAPERID);
        $paper->setDocid(12);

        $method = new ReflectionMethod(PaperDefaultController::class, 'areOtherRecipientsRefused');

        return $method->invoke($this->controller, $user, $paper, $cc, $bcc);
    }

    private function givenAuthorAndReviewer(): void
    {
        $this->db
            ->respondTo('/u\.EMAIL IN/', [
                ['UID' => 1, 'EMAIL' => 'author@example.org'],
                ['UID' => 2, 'EMAIL' => 'reviewer@example.org'],
            ])
            ->respondTo('/SELECT `PAPERS`\.`UID` FROM `PAPERS`/', [['UID' => 1]])
            ->respondTo('/`a`\.`STATUS` FROM `USER_ASSIGNMENT`/', [['UID' => 2, 'ROLEID' => 'reviewer', 'STATUS' => 'active']]);
    }

    public function testNoOtherRecipientIsNotChecked(): void
    {
        self::assertFalse($this->isRefused('author@example.org', []));
        self::assertSame([], $this->db->selects());
    }

    public function testReviewerVisibleToTheAuthorIsRefused(): void
    {
        $this->givenAuthorAndReviewer();

        self::assertTrue($this->isRefused('author@example.org', ['Reviewer <reviewer@example.org>']));
    }

    public function testReviewerInBccIsAccepted(): void
    {
        $this->givenAuthorAndReviewer();

        self::assertFalse($this->isRefused('author@example.org', [], ['reviewer@example.org']));
    }

    public function testTooManyRecipientsAreRefused(): void
    {
        $bcc = array_map(static fn(int $i): string => 'user' . $i . '@example.org', range(1, RecipientListValidator::MAX_TOTAL));

        self::assertTrue($this->isRefused('author@example.org', [], $bcc));
    }

    public function testInvalidAddressesAreSkippedNotRefused(): void
    {
        self::assertFalse($this->isRefused('author@example.org', ['not an address', 'editor@example.org']));
    }
}
