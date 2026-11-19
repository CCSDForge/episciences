<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Answers given without an account (table review_light_answer, #319)
 */
class LightAnswerRepository
{
    public const TABLE_NAME = 'review_light_answer';

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

        // the invitation, the paper and the person never change
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
