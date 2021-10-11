<?php

class Episciences_Paper_Conflict
{
    public const AVAILABLE_ANSWER = [
        'yes' => 'yes',
        'no' => 'no',
        'later' => 'later'
    ];

    public const TABLE = T_PAPER_CONFLICTS;

    public const TABLE_COLONES = ['cid', 'paper_id', 'by', 'answer', 'message', 'date'];

    /**
     * @var int
     */
    protected $_cid;

    /**
     * @var int
     */
    protected $_paperId;

    /**
     * @var int
     */
    protected $_by; // uid

    /**
     * @var string
     */
    protected $_answer;

    /**
     * @var string
     */
    protected $_message;

    /**
     * @var DateTime
     */
    protected $_date = 'CURRENT_TIMESTAMP';

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * set paper options
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }


    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cid' => $this->getCid(),
            'paperId' => $this->getPaperId(),
            'by' => $this->getBy(),
            'answer' => $this->getAnswer(),
            'message' => $this->getMessage(),
            'date' => $this->getDate()
        ];
    }

    /**
     * @return int|null
     */
    public function getCid(): ?int
    {
        return $this->_cid;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function setCid(int $id = null): self
    {
        $this->_cid = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getPaperId(): ?int
    {
        return $this->_paperId;
    }

    /**
     * @param int|null $paperId
     * @return Episciences_Paper_Conflict
     */
    public function setPaperId(int $paperId = null): self
    {
        $this->_paperId = $paperId;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getBY(): ?int
    {
        return $this->_by;
    }

    /**
     * @param int|null $uid
     * @return $this
     */
    public function setBy(int $uid = null): self
    {
        $this->_by = $uid;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAnswer(): ?string
    {
        return $this->_answer;
    }

    /**
     * @param string|null $answer
     * @return $this
     */
    public function setAnswer(string $answer = null): self
    {
        $this->_answer = $answer;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): ?string
    {
        return $this->_message;
    }

    /**
     * @param string|null $message
     * @return Episciences_Paper_Conflict
     */
    public function setMessage(string $message = null): self
    {
        $this->_message = $message;
        return $this;
    }

    /**
     * @return DateTime|string
     */
    public function getDate()
    {
        return $this->_date;
    }

    /**
     * @param string|null $date
     * @return Episciences_Paper_Conflict
     * @throws Exception
     */
    public function setDate(string $date = null): self
    {
        $this->_date = new DateTime($date);
        return $this;
    }


    /**
     * @return int number of affected rows
     */
    protected function insert(): int
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $latestId = 0;

        $values = [
            'paper_id' => $this->getPaperId(),
            'by' => $this->getbY(),
            'answer' => $this->getAnswer(),
            'message' => $this->getMessage()
        ];

        try {
            $db->insert(self::TABLE, $values);
            $latestId = (int)$db->lastInsertId();
        } catch (Zend_Db_Adapter_Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $latestId;

    }

    /**
     * @return int  last ID generated
     */
    protected function update(): int
    {

        if (!$this->getCid()) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['cid = ?'] = $this->getCid();

        $values = [
            'paperId' => $this->getPaperId(),
            'by' => $this->getBy(),
            'answer' => $this->getAnswer(),
            'message' => $this->getMessage()
        ];

        try {
            $resUpdate = $db->update(self::TABLE, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
        return $resUpdate;
    }

    /**
     * Save conflict
     * return latest insert ID if insert query;
     * @return int  last ID generated for INSERT, number of affected rows for UPDATE
     */
    public function save(): int
    {

        if ($this->getCid()) {
            return $this->update();
        }

        return $this->insert();

    }

    /**
     * Remove paper conflict
     * @return bool
     */
    public function remove(): bool
    {
        $id = $this->getCid();

        if (!$id) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(self::TABLE, ['cid = ?' => $id]) > 0);

    }

}