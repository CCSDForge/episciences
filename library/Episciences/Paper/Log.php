<?php

class Episciences_Paper_Log
{

    private $_logid;
    private $_paperid;
    private $_docid;
    private $_uid;
    private $_rvid;
    private $_action;
    private $_detail;
    private $_date;

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions($options = []): static
    {
        // Cache per concrete class: get_class_methods() is expensive; isset() is O(1) vs in_array() O(n)
        static $cache = [];
        $class = static::class;
        if (!isset($cache[$class])) {
            $cache[$class] = array_flip(get_class_methods($this));
        }
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst(strtolower((string)$key));
            if (isset($cache[$class][$method])) {
                $this->$method($value);
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        return [
            'logid' => $this->getLogid(),
            'paperid' => $this->getPaperid(),
            'docid' => $this->getDocid(),
            'uid' => $this->getUid(),
            'rvid' => $this->getRvid(),
            'action' => $this->getAction(),
            'detail' => $this->getDetail(),
            'date' => $this->getDate()
        ];
    }

    public function getLogid()
    {
        return $this->_logid;
    }

    public function setLogid($logid): static
    {
        $this->_logid = $logid;
        return $this;
    }

    public function getPaperid()
    {
        return $this->_paperid;
    }

    public function setPaperid($paperid): static
    {
        $this->_paperid = $paperid;
        return $this;
    }

    public function getDocid()
    {
        return $this->_docid;
    }

    public function setDocid($docid): static
    {
        $this->_docid = $docid;
        return $this;
    }

    public function getUid()
    {
        return $this->_uid;
    }

    public function setUid($uid): static
    {
        $this->_uid = $uid;
        return $this;
    }

    public function getRvid()
    {
        return $this->_rvid;
    }

    public function setRvid($rvid): static
    {
        $this->_rvid = $rvid;
        return $this;
    }

    public function getAction()
    {
        return $this->_action;
    }

    public function setAction($action): static
    {
        $this->_action = $action;
        return $this;
    }

    public function getDetail()
    {
        return (Episciences_Tools::isJson($this->_detail)) ? Zend_Json::decode($this->_detail) : $this->_detail;
    }

    public function setDetail($detail): static
    {
        $this->_detail = (Episciences_Tools::isJson($detail)) ? Zend_Json::decode($detail) : $detail;
        return $this;
    }

    public function getDate()
    {
        return $this->_date;
    }

    public function setDate($date): static
    {
        $this->_date = $date;
        return $this;
    }

    public function load($id): false|self
    {

        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_LOGS)->where('LOGID = ?', $id);
        $data = $db->fetchRow($sql);

        if (!$data) {
            return false;
        }

        $this->setOptions($data);
        return $this;
    }

    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $detail = $this->getDetail();
        $data = [
            'PAPERID' => $this->getPaperid(),
            'DOCID' => $this->getDocid(),
            'UID' => $this->getUid(),
            'RVID' => $this->getRvid(),
            'ACTION' => $this->getAction(),
            // setDetail() already decodes JSON, so $detail is either an array/object (re-encode) or a plain scalar
            'DETAIL' => (is_array($detail) || is_object($detail)) ? Zend_Json::encode($detail) : $detail,
            'DATE' => $this->getDate() ?: new Zend_Db_Expr('NOW()')
        ];
        return (bool)$db->insert(T_LOGS, $data);
    }
}