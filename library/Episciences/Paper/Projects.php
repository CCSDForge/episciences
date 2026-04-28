<?php

declare(strict_types=1);

class Episciences_Paper_Projects
{
    protected ?int      $_idproject   = null;
    protected ?string   $_funding     = null;
    protected ?int      $_paperId     = null;
    protected int       $_sourceId;
    protected ?DateTime $_dateUpdated = null;

    public function __construct(?array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Case-insensitive method matching so that DB column names like 'paperid'
     * resolve to the correctly-cased setter 'setPaperId' without aliases.
     * PHP method calls are already case-insensitive at runtime; only the
     * in_array check needed to be relaxed.
     */
    public function setOptions(array $options): void
    {
        $classMethods      = get_class_methods($this);
        $classMethodsLower = array_map(strtolower(...), $classMethods);
        foreach ($options as $key => $value) {
            $method = 'set' . Episciences_Tools::convertToCamelCase($key, '_', true);
            if (in_array(strtolower($method), $classMethodsLower, true)) {
                $this->$method($value);
            }
        }
    }

    public function toArray(): array
    {
        return [
            'idproject'   => $this->getProjectId(),
            'funding'     => $this->getFunding(),
            'paperId'     => $this->getPaperId(),
            'dateUpdated' => $this->_dateUpdated?->format('Y-m-d H:i:s'),
        ];
    }

    public function getProjectId(): ?int
    {
        return $this->_idproject;
    }

    public function setProjectId(int $idProject): self
    {
        $this->_idproject = $idProject;
        return $this;
    }

    /** Maps DB column 'idproject' → setProjectId() */
    public function setIdproject(int $id): self
    {
        return $this->setProjectId($id);
    }

    public function getFunding(): ?string
    {
        return $this->_funding;
    }

    public function setFunding(string $funding): self
    {
        $this->_funding = $funding;
        return $this;
    }

    public function getPaperId(): ?int
    {
        return $this->_paperId;
    }

    public function setPaperId(int $paperId): self
    {
        $this->_paperId = $paperId;
        return $this;
    }

    public function getSourceId(): ?int
    {
        return $this->_sourceId;
    }

    public function setSourceId(int $sourceId): self
    {
        $this->_sourceId = $sourceId;
        return $this;
    }

    public function getDateUpdated(): ?DateTime
    {
        return $this->_dateUpdated;
    }

    /**
     * @throws Exception
     */
    public function setDateUpdated(string $dateUpdated): self
    {
        $this->_dateUpdated = new DateTime($dateUpdated);
        return $this;
    }
}
