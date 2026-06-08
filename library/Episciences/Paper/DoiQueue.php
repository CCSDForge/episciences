<?php

class Episciences_Paper_DoiQueue
{
    public const DEFAULT_STATUS      = self::STATUS_NOT_ASSIGNED;
    public const STATUS_MANUAL       = 'manual';
    public const STATUS_PUBLIC       = 'public';
    public const STATUS_REQUESTED    = 'requested';
    public const STATUS_ASSIGNED     = 'assigned';
    public const STATUS_NOT_ASSIGNED = 'not-assigned';
    public const STATUS_UPDATE_PENDING = 'update-pending';

    /** @var array<string, string> */
    public static $htmlStatus = [
        self::STATUS_NOT_ASSIGNED   => '<span class="label label-default">%s</span>',
        self::STATUS_MANUAL         => '<span class="label label-default">%s</span>',
        self::STATUS_ASSIGNED       => '<span class="label label-primary">%s</span>',
        self::STATUS_REQUESTED      => '<span class="label label-warning">%s</span>',
        self::STATUS_UPDATE_PENDING => '<span class="label label-info">%s</span>',
        self::STATUS_PUBLIC         => '<span class="label label-success">%s</span>',
    ];

    /** @var list<string> */
    public static $statusList = [
        self::STATUS_NOT_ASSIGNED,
        self::STATUS_ASSIGNED,
        self::STATUS_REQUESTED,
        self::STATUS_UPDATE_PENDING,
        self::STATUS_PUBLIC,
        self::STATUS_MANUAL,
    ];

    protected int $_id_doi_queue;
    protected int $_paperid;
    protected string $_doi_status;
    protected string $_date_init;
    protected string $_date_updated;

    /** @param array<string, mixed>|null $options */
    public function __construct(?array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key    = strtolower((string) $key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

    public static function getStatusHtmlTemplate(string $status): string
    {
        if (!in_array($status, self::$statusList, true)) {
            $status = self::DEFAULT_STATUS;
        }
        return self::$htmlStatus[$status];
    }

    /** @return array<string, string> */
    public static function getHtmlStatus(): array
    {
        return self::$htmlStatus;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id_doi_queue' => $this->getId_doi_queue(),
            'paperid'      => $this->getPaperid(),
            'doi_status'   => $this->getDoi_status(),
            'date_init'    => $this->getDate_init(),
            'date_updated' => $this->getDate_updated(),
        ];
    }

    public function getId_doi_queue(): int
    {
        return (int) ($this->_id_doi_queue ?? 0);
    }

    public function setId_doi_queue(int $id_doi_queue): void
    {
        $this->_id_doi_queue = $id_doi_queue;
    }

    public function getPaperid(): int
    {
        return (int) ($this->_paperid ?? 0);
    }

    public function setPaperid(int $paperid): void
    {
        $this->_paperid = $paperid;
    }

    public function getDoi_status(): string
    {
        if (!isset($this->_doi_status) || $this->_doi_status === '') {
            $this->setDoi_status(self::STATUS_NOT_ASSIGNED);
        }
        return $this->_doi_status;
    }

    public function setDoi_status(string $doi_status): void
    {
        $this->_doi_status = $doi_status;
    }

    public function getDate_init(): string
    {
        return $this->_date_init ?? '';
    }

    public function setDate_init(string $date_init): void
    {
        $this->_date_init = $date_init;
    }

    public function getDate_updated(): string
    {
        return $this->_date_updated ?? '';
    }

    public function setDate_updated(string $date_updated): void
    {
        $this->_date_updated = $date_updated;
    }
}
