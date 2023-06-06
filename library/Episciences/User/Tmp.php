<?php

class Episciences_User_Tmp extends Episciences_User
{
    protected $_status;
    private $_id;
    private $_lang;

    public function find($id, $key = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(T_TMP_USER)
            ->where('ID = ?', $id);
        if ($key) {
            $sql->where('MD5(EMAIL) = ? ', $key);
        }

        $data = $db->fetchRow($sql);

        if ($data) {
            $this->setOptions($data);
        }

        return (array)$data;
    }

    /**
     * @param bool $forceInsert
     * @param bool $isCasRecording
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(bool $forceInsert = false, bool $isCasRecording = false): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [
            'EMAIL' => $this->getEmail(),
            'FIRSTNAME' => $this->getFirstname(),
            'LASTNAME' => $this->getLastname(),
            'LANG' => $this->getLangueid(true),
        ];

        $db->insert(T_TMP_USER, $values);
        $id = $db->lastInsertId();
        $this->setId($id);

        return true;
    }

    public function getLangueid($forceResult = false)
    {
        return parent::getLangueid($this->setLangueid($this->_lang));
    }

    public function delete()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete(T_TMP_USER, ['ID = ?' => $this->getId()]);

        return true;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function getUid()
    {
        return null;
    }

    public function setLang($lang)
    {
        $this->_lang = $lang;
    }

    public function generateScreen_name()
    {
        $screen_name = sprintf("%s %s", $this->getFirstname(), $this->getLastname());
        $this->setScreenName(trim($screen_name));
    }

    public function availableUsername($username)
    {
        $db = Ccsd_Db_Adapter_Cas::getAdapter();
        $sql = $db->select()->from(T_CAS_USERS, ['UID'])->where('USERNAME = ?', $username);
        return !$db->fetchOne($sql);
    }

    public function toArray()
    {
        $result['id'] = $this->getId();
        $result['email'] = $this->getEmail();
        $result['username'] = $this->getUsername();
        $result['firstname'] = $this->getFirstname();
        $result['lastname'] = $this->getLastname();
        $result['fullname'] = $this->getFullname();
        $result['screen_name'] = $this->getScreenName();
        $result['lang'] = $this->getLangueid(true);
        $result['status'] = $this->getStatus();

        return $result;
    }
    
    public function getStatus()
    {
        return $this->_status;
    }

    public function setStatus($status)
    {
        $this->_status = $status;
    }

}
