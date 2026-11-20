<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use DateTimeImmutable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * Answers given without an account (table review_light_answer, #319)
 */
class LightAnswerRepository
{
    public const TABLE_NAME = 'review_light_answer';
    private const INVITATIONS_TABLE = 'USER_INVITATION';

    private ?Zend_Db_Adapter_Abstract $db;

    /**
     * @param ?Zend_Db_Adapter_Abstract $db defaults to the application database
     */
    public function __construct(?Zend_Db_Adapter_Abstract $db = null)
    {
        $this->db = $db;
    }

    /**
     * The answer of an invitation, by its original assignment (USER_INVITATION.AID)
     */
    public function findByAid(int $aid): ?LightAnswer
    {
        $db = $this->getDb();
        $row = $db->fetchRow($db->select()->from(self::TABLE_NAME)->where('aid = ?', $aid));

        return $row ? LightAnswer::fromRow($row) : null;
    }

    /**
     * Answers given on a paper version, by original assignment
     * @return array<int, LightAnswer>
     */
    public function findByDocid(int $docid): array
    {
        $db = $this->getDb();
        $answers = [];

        foreach ($db->fetchAll($db->select()->from(self::TABLE_NAME)->where('docid = ?', $docid)->order('created_at ASC')) as $row) {
            $answer = LightAnswer::fromRow($row);
            $answers[$answer->getAid()] = $answer;
        }

        return $answers;
    }

    /**
     * Answers of an account in a journal, given by the invited account or associated with it, most recent first, with
     * the id of the first row of their invitation (the one of the invitation link)
     * @return list<array{answer: LightAnswer, invitation_id: int}>
     */
    public function findForAccount(int $uid, int $rvid): array
    {
        $db = $this->getDb();
        $firstInvitationRow = $db->select()
            ->from(['i' => self::INVITATIONS_TABLE], [new Zend_Db_Expr('MIN(i.ID)')])
            ->where('i.AID = a.aid');
        $select = $db->select()
            ->from(['a' => self::TABLE_NAME])
            ->columns(['invitation_id' => new Zend_Db_Expr('(' . $firstInvitationRow . ')')])
            ->where('a.rvid = ?', $rvid)
            ->where('(a.tmp_user = 0 AND a.uid = ' . $db->quote($uid, 'INTEGER') . ') OR a.linked_uid = ?', $uid)
            ->order('a.created_at DESC');
        $answers = [];

        foreach ($db->fetchAll($select) as $row) {
            if (!empty($row['invitation_id'])) {
                $answers[] = ['answer' => LightAnswer::fromRow($row), 'invitation_id' => (int)$row['invitation_id']];
            }
        }

        return $answers;
    }

    /**
     * Associates an answer with an account (see LightAnswer::linkedTo())
     */
    public function link(LightAnswer $answer, int $uid, DateTimeImmutable $now): LightAnswer
    {
        $linked = $answer->linkedTo($uid, $now);
        $row = $linked->toRow();
        $this->getDb()->update(self::TABLE_NAME, [
            'linked_uid' => $row['linked_uid'],
            'linked_at' => $row['linked_at'],
        ], ['id = ?' => $answer->getId(), 'aid = ?' => $answer->getAid()]);

        return $linked;
    }

    /**
     * Inserts the first answer of an invitation, or updates it
     * @return LightAnswer the saved answer, with its id
     */
    public function save(LightAnswer $answer): LightAnswer
    {
        $db = $this->getDb();
        $row = $answer->toRow();

        if ($answer->getId() === null) {
            $db->insert(self::TABLE_NAME, $row);
            return $answer->withId((int)$db->lastInsertId(self::TABLE_NAME, 'id'));
        }

        // the invitation, the paper and the person never change; the linked account is saved by link()
        $db->update(self::TABLE_NAME, [
            'updated_at' => $row['updated_at'],
        ], ['id = ?' => $answer->getId(), 'aid = ?' => $answer->getAid()]);

        return $answer;
    }

    private function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
    }
}
