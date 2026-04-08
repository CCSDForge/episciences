<?php

class Episciences_Paper_DoiQueue
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
    protected $_id_doi_queue;
    /**
     * @var int
     */
    protected $_paperid;
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
     * @param $status
     * @return string
     */
    public static function getStatusHtmlTemplate($status): string
    {
        if (!in_array($status, self::$statusList, true)) {
            $status = self::DEFAULT_STATUS;
        }
        return self::getHtmlStatus()[$status];
    }

    /**
     * @return array[]
     */
    public static function getHtmlStatus(): array
    {
        return self::$htmlStatus;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id_doi_queue' => $this->getId_doi_queue(),
            'paperid' => $this->getPaperid(),
            'doi_status' => $this->getDoi_status(),
            'date_init' => $this->getDate_init(),
            'date_updated' => $this->getDate_updated()
        ];
    }

    /**
     * @return int
     */
    public function getId_doi_queue(): int
    {
        return (int) $this->_id_doi_queue;
    }

    /**
     * @param int $id_doi_queue
     */
    public function setId_doi_queue(int $id_doi_queue)
    {
        $this->_id_doi_queue = (int)$id_doi_queue;
    }

    /**
     * @return int
     */
    public function getPaperid(): int
    {
        return (int) $this->_paperid;
    }

    /**
     * @param int $paperid
     */
    public function setPaperid(int $paperid)
    {
        $this->_paperid = $paperid;
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