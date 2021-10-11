<?php

class Episciences_Paper_ConflictsManager
{
    public const TABLE = T_PAPER_CONFLICTS;

    /**
     * @param int $paperId
     * @return array [Episciences_Paper_Conflict]
     */
    public static function findByPaperId(int $paperId): array
    {

        $oResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(self::TABLE)
            ->where('paper_id = ?', $paperId);

        $rows = $db->fetchAssoc($sql);

        foreach ($rows as $value) {
            $oResult[] = new Episciences_Paper_Conflict($value);
        }

        return $oResult;
    }

    /**
     * @param int $uid
     * @param string|null $answer
     * @return array [Episciences_Paper_Conflict]
     */
    public static function findByUidAndAnswer(int $uid, string $answer = null): array
    {
        $oConflicts = [];

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(self::TABLE)
            ->where("`by` = ?", $uid);

        if ($answer) {
            $sql->where('answer = ?', $answer);
        }

        $rows = $db->fetchAll($sql);

        foreach ($rows as $row) {
            $oConflicts [] = new Episciences_Paper_Conflict($row);
        }

        return $oConflicts;
    }

    /**
     * @return array [Episciences_Paper_Conflict]
     */
    public static function all(): array
    {
        $conflicts = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()->from(self::TABLE);

        $rows = $db->fetchAll($sql);

        foreach ($rows as $row) {

            $oConflict = new Episciences_Paper_Conflict($row);

            $conflicts [$oConflict->getCid()] = $oConflict;

        }

        return $conflicts;
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
            'class'=> 'form-control',
            'rows' => '3',
            'validators' => [['StringLength', false, ['max' => MAX_INPUT_TEXTAREA]]]
        ]);


        $form->addElement(new Zend_Form_Element_Button([
            'name' => 'yes',
            'type' => 'submit'
        ]));

        return $form;


    }

}