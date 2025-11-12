<?php

// volume / papers relation (defines papers contained by a volume) 
class Episciences_Volume_Paper
{
    private $_id;
    private $_vid;
    private $_docid;

    public function __construct($data = array())
    {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    public function populate($data)
    {
        foreach ($data as $name => $value) {
            $method = 'set' . ucfirst(strtolower($name));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setVid($vid)
    {
        $this->_vid = (int)$vid;
    }

    public function getVid() : int
    {
        return $this->_vid;
    }

    public function setDocid($docid)
    {
        $this->_docid = $docid;
    }

    public function getDocid()
    {
        return $this->_docid;
    }

    public function save()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->getVid()) {
            throw new Zend_Exception('NO_VID');
        }
        if (!$this->getDocid()) {
            throw new Zend_Exception('NO_DOCID');
        }

        $values = [
            'VID' => $this->getVid(),
            'DOCID' => $this->getDocid()
        ];

        if ($this->getId()) {
            $db->update(T_VOLUME_PAPER, $values, ['ID = ?', $this->getId()]);
        } else {
            $db->insert(T_VOLUME_PAPER, $values);
        }
    }


    public function toArray(): array
    {
        return [

            'id' => $this->getId(),
            'vid' => $this->getVid(),
            'docid' => $this->getDocid()

        ];

    }

}
