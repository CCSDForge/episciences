<?php

class Episciences_JournalNews
{

    /**
     * @var int
     */
    protected int $_id;
    /**
     * @var int|null
     */
    protected ?int $_legacy_id;
    /**
     * @var string
     */
    protected string $_code;
    /**
     * @var int
     */
    protected int $_uid;
    /**
     * @var datetime
     */
    protected $_dateCreation;
    /**
     * @var datetime
     */
    protected $_dateUpdated = 'CURRENT_TIMESTAMP';
    /**
     * @var string
     */
    protected string $_title;
    /**
     * @var string
     */
    protected ?string $_content = '' ;
    /**
     * @var string
     */
    protected ?string $_link = '';
    /**
     * @var string
     */
    protected string $_visibility;

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
            'id' => $this->getId(),
            'legacyId' => $this->getLegacyId(),
            'code' => $this->getCode(),
            'uid' => $this->getUid(),
            'dateCreation' => $this->getDateCreation(),
            'dateUpdated' => $this->getDateUpdated(),
            'title' => $this->getTitle(),
            'content' => $this->getContent(),
            'link' => $this->getLink(),
            'visiblity' => $this->getVisibility(),
        ];
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->_id;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLegacyId(): ?int
    {
        return $this->_legacy_id;
    }

    /**
     * @param int $legacyId
     * @return $this
     */
    public function setLegacyId(int $legacyId): self
    {
        $this->_legacy_id = $legacyId;
        return $this;
    }

    public function getCode(): string
    {
        return $this->_code;
    }

    public function setCode(string $code): self
    {
        $this->_code = $code;
        return $this;
    }

    public function getUid(): ?int
    {
        return $this->_uid;
    }

    public function setUid(int $uid): self
    {
        $this->_uid = $uid;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateCreation(): string
    {
        return $this->_dateCreation;
    }


    public function setDateCreation(string $dateCreation): self
    {
        $this->_dateCreation = $dateCreation;
        return $this;
    }


    public function getDateUpdated(): string
    {
        return $this->_dateUpdated;
    }

    public function setDateUpdated(string $dateUpdated): self
    {
        $this->_dateUpdated = $dateUpdated;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->_title;
    }

    public function setTitle(string $title): self
    {
        $this->_title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        if (($this->_content === '') || is_null($this->_content)){
            $this->_content = null;
        }
        return $this->_content;
    }

    public function setContent(?string $content = ""): ?self
    {
        if ($content === ''){
            $content = null;
        }
        $this->_content = $content;
        return $this;
    }

    public function getLink(): ?string
    {
        if ($this->_link === null || $this->_link === ''){
            $this->_link = null;
        }
        return $this->_link;
    }

    public function setLink(?string $link = ""): ?self
    {
        if ($link === ''){
            $link = null;
        }
        $this->_link = $link;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->_visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->_visibility = $visibility;
        return $this;
    }

    public static function insert(array $news): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        try {
            $resUpdate = $db->insert(T_JOURNAL_NEWS, $news);
            return $resUpdate;
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }
        return $resUpdate;

    }

    public static function update(Episciences_JournalNews $journalNews): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['id = ?'] = $journalNews->getId();

        $values = [
            'uid' => $journalNews->getUid(),
            'title' => $journalNews->getTitle(),
            'content' => $journalNews->getContent(),
            'link' => $journalNews->getLink(),
            'date_updated' => new Zend_DB_Expr('NOW()'),
            'visibility' => $journalNews->getVisibility(),
        ];
        try {
            $resUpdate = $db->update(T_JOURNAL_NEWS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }
        return $resUpdate;
    }
    public static function findByLegacyId(int $legacyId): ?Episciences_JournalNews
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_JOURNAL_NEWS)
            ->where('legacy_id = ?', $legacyId);

        $res = $db->fetchRow($query);
        if (empty($res)) {
            $journalNew = null;
        } else {
            $journalNew = $res;
            return new Episciences_JournalNews($journalNew);
        }
        return $journalNew;
    }
    public static function deleteByLegacyId(int $legacyId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resDelete = $db->delete(T_JOURNAL_NEWS, ['legacy_id = ?' => $legacyId]);
        return $resDelete > 0;


    }
}