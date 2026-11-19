<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Reviewer\DefaultRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Mail_Reminder;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * "Not enough reviewers" reminder (#319): only the invitations of the request types whose reports count as review
 * reports are counted. Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Mail_Reminder::countedInvitations
 */
final class ReminderCountedInvitationsTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
        RequestTypeRepository::clearCache();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        RequestTypeRepository::clearCache();
    }

    public function testOnlyTheInvitationsOfTheTypesCountingAsReviewReportsAreKept(): void
    {
        $repository = new RequestTypeRepository($this->db);
        $repository->createDefaults(RVID);
        $fullReport = (int)$repository->findDefault(RVID, DefaultRequestType::FullReport)?->getId();
        $quickOpinion = (int)$repository->findDefault(RVID, DefaultRequestType::QuickOpinion)?->getId();
        $phase = (int)$repository->save(new RequestType(null, RVID, null, ['en' => 'Phase two'], false, null, 3, true, [], true))->getId();

        $invitations = [
            'report' => ['REQUEST_TYPE_ID' => $fullReport],
            'quick' => ['REQUEST_TYPE_ID' => $quickOpinion],
            'phase' => ['REQUEST_TYPE_ID' => $phase],
            // made before the request types: a review report
            'legacy' => ['REQUEST_TYPE_ID' => null],
        ];

        self::assertSame(['report', 'phase', 'legacy'], array_keys(Episciences_Mail_Reminder::countedInvitations($invitations)));
    }
}
