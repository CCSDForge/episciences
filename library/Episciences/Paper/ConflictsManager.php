<?php

class Episciences_Paper_ConflictsManager
{
    public const TABLE = T_PAPER_CONFLICTS;
    public const DEFAULT_MODE = 'object';

    /**
     * @param int $paperId
     * @return  Episciences_Paper_Conflict []
     */
    public static function findByPaperId(int $paperId): array
    {

        $oResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['c' => self::TABLE])
            ->join(['u' => T_USERS], 'u.UID = c.by', ['SCREEN_NAME'])
            ->where('paper_id = ?', $paperId)
            ->order('date DESC');

        $rows = $db->fetchAssoc($sql);

        foreach ($rows as $value) {
            $oResult[] = new Episciences_Paper_Conflict($value);
        }

        return $oResult;
    }

    /**
     * @param int $uid
     * @param string|null $answer
     * @param string $mode
     * @param string $mode
     * @return  array | Episciences_Paper_Conflict[]
     */
    public static function findByUidAndAnswer(int $uid, string $answer = null, string $mode = self::DEFAULT_MODE): array
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(self::TABLE)
            ->where("`by` = ?", $uid);

        if ($answer) {
            $sql->where('answer = ?', $answer);
        }

        $rows = $db->fetchAll(self::findByUidAndAnswerQuery($uid, $answer));

        if ($mode !== self::DEFAULT_MODE) {
            return $rows;
        }

        $oConflicts = [];

        foreach ($rows as $row) {
            $oConflicts [] = new Episciences_Paper_Conflict($row);
        }

        return $oConflicts;
    }

    /**
     * @return array [Episciences_Paper_Conflict]
     */
    public static function all(int $rvId = null): array
    {
        $conflicts = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db
            ->select()
            ->from(['pc' => self::TABLE])
            ->joinLeft(['p' => T_PAPERS], 'pc.paper_id = p.PAPERID', ['RVID'])
            ->where('p.RVID = ?', $rvId);

        $rows = $db->fetchAll($sql);

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            $oConflict = new Episciences_Paper_Conflict($row);
            $conflicts [$row['RVID']][$oConflict->getPaperId()][] = $oConflict;
        }

        return !$rvId ? $conflicts : $conflicts[$rvId];
    }


    /**
     * @param int $uid
     * @param int $paperId
     * @return bool
     */
    public static function deleteByUidAndPaperId(int $uid, int $paperId): bool
    {
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(self::TABLE, ['paper_id = ?' => $paperId, 'by' => $uid]) > 0);

    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteById(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(self::TABLE, ['cid = ?' => $id]) > 0);

    }

    /**
     * @param string $col
     * @param bool $distinct
     * @param array $option // default: answer = no (without conflicts)
     * @return array
     */
    public static function fetchSelectedCol(string $col, array $option = ['answer' => 'no'], bool $distinct = true): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(self::TABLE, $col);

        if ($distinct) {
            $sql->distinct();
        }

        foreach ($option as $key => $val) {

            if (in_array($key, Episciences_Paper_Conflict::TABLE_COLONES)) {
                $sql->where("$key = ?", $val);
            }

        }

        return $db->fetchCol($sql);

    }

    /**
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getCoiForm(): \Ccsd_Form
    {

        $form = new Ccsd_Form();

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/coi/userconfirmation.phtml'
            ]],
            $form->getDecorator('FormRequired'),
        ]);


        $form->addElement('textarea', 'message', [
            'id' => 'coi-message',
            'label' => 'Commentaire facultatif',
            'tiny' => true,
            'class' => 'form-control',
            'rows' => '3',
            'validators' => [['StringLength', false, ['max' => MAX_INPUT_TEXTAREA]]]
        ]);


        $form->addElement(new Zend_Form_Element_Button([
            'name' => 'yes',
            'type' => 'submit'
        ]));

        return $form;


    }

    /**
     * @param int $cId : conflict ID
     * @return Episciences_Paper_Conflict|null
     */
    public static function findById(int $cId): ?Episciences_Paper_Conflict
    {

        $oResult = null;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = self::findQuery()->where('cid = ?', $cId);

        $row = $db->fetchRow($sql);

        if (!empty($row)) {
            $oResult = new Episciences_Paper_Conflict($row);
        }

        return $oResult;
    }

    /**
     * @return Zend_Db_Select
     */
    private static function findQuery(): \Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->select()
            ->from(['c' => self::TABLE])
            ->join(['u' => T_USERS], 'u.UID = c.by', ['SCREEN_NAME']);
    }

    /**
     * @param int $uid
     * @param string|null $answer
     * @return Zend_Db_Select
     */
    public static function findByUidAndAnswerQuery(int $uid, string $answer = null): Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(self::TABLE)
            ->where("`by` = ?", $uid);

        if ($answer) {
            $sql->where('answer = ?', $answer);
        }

        return $sql;
    }

    /**
     * @param int $oldUid  // keeper UID
     * @param int $newUid // merger UID
     * @return int // affected rows
     */
    public static function updateRegistrant(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }

        $values = [];
        $updatedRows = 0;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $mergerConflicts = self::findByUidAndAnswer($oldUid, null, 'array');

        foreach ($mergerConflicts as $row) {
            $values[] = '(' . $row['cid'] . ',' . $row['paper_id'] . ',' . $newUid . ',' . $db->quote($row['answer']) . ',' . $db->quote($row['message']) . ',' . $db->quote($row['date']) . ')';
        }

        if (!empty($values)) {

            $where['`by` = ?'] = $oldUid;
            $db->delete(self::TABLE, $where);

            $sql = 'INSERT INTO ';
            $sql .= $db->quoteIdentifier(self::TABLE);
            $sql .= ' (';
            $sql .= $db->quoteIdentifier('cid');
            $sql .= ', ';
            $sql .= $db->quoteIdentifier('paper_id');
            $sql .= ', ';
            $sql .= $db->quoteIdentifier('by');
            $sql .= ', ';
            $sql .= $db->quoteIdentifier('answer');
            $sql .= ', ';
            $sql .= $db->quoteIdentifier('message');
            $sql .= ', ';
            $sql .= $db->quoteIdentifier('date');
            $sql .= ') VALUES ';
            $sql .= implode(',', $values);
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            $sql .= $db->quoteIdentifier('by');
            $sql .= ' = VALUES(';
            $sql .= $db->quoteIdentifier('by');
            $sql .= ')';


            $statement = $db->prepare($sql);

            try {
                if ($statement->execute()) {
                    $updatedRows = $statement->rowCount();
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }


        return $updatedRows;
    }

}