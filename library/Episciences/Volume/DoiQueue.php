<?php

class Episciences_Volume_DoiQueue
{
    public const DEFAULT_STATUS = self::STATUS_NOT_ASSIGNED;
    public const STATUS_MANUAL = 'manual';
    public const STATUS_PUBLIC = 'public';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_NOT_ASSIGNED = 'not-assigned';


    public static $htmlStatus = [
        self::STATUS_NOT_ASSIGNED => '<span class="label label-default">%s</span>',
        self::STATUS_MANUAL => '<span class="label label-default">%s</span>',
        self::STATUS_ASSIGNED => '<span class="label label-primary">%s</span>',
        self::STATUS_REQUESTED => '<span class="label label-warning">%s</span>',
        self::STATUS_PUBLIC => '<span class="label label-success">%s</span>'
    ];
    public static $statusList = [self::STATUS_NOT_ASSIGNED, self::STATUS_ASSIGNED, self::STATUS_REQUESTED, self::STATUS_PUBLIC, self::STATUS_MANUAL];


    /**
     * @var int
     */
    protected $_id;
    /**
     * @var int
     */
    protected $_vid;
    /**
     * @var string
     */
    protected $_doi_status;
    /**
     * @var string
     */
    protected $_date_init;
    /**
     * @var string
     */
    protected $_date_updated;

    /**
     *
     * @param array|null $options
     */
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
    public function setOptions(array $options)
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'vid' => $this->getVid(),
            'doi_status' => $this->getDoi_status(),
            'date_init' => $this->getDate_init(),
            'date_updated' => $this->getDate_updated()
        ];
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->_id = (int)$id;
    }

    /**
     * @return int
     */
    public function getVid(): int
    {
        return $this->_vid;
    }

    /**
     * @param int $vid
     */
    public function setVid(int $vid)
    {
        $this->_vid = $vid;
    }

    /**
     * @return string
     */
    public function getDoi_status(): string
    {
        if (!$this->_doi_status) {
            $this->setDoi_status(self::STATUS_NOT_ASSIGNED);
        }
        return $this->_doi_status;
    }

    /**
     * @param string $doi_status
     */
    public function setDoi_status(string $doi_status)
    {
        $this->_doi_status = $doi_status;
    }

    /**
     * @return string
     */
    public function getDate_init(): string
    {
        return $this->_date_init;
    }

    /**
     * @param string $date_init
     */
    public function setDate_init(string $date_init)
    {
        $this->_date_init = $date_init;
    }

    /**
     * @return string
     */
    public function getDate_updated(): string
    {
        return $this->_date_updated;
    }

    /**
     * @param string $date_updated
     */
    public function setDate_updated(string $date_updated)
    {
        $this->_date_updated = $date_updated;
    }


}