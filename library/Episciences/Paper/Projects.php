<?php


class Episciences_Paper_Projects
{

    /**
     * @var int
     */
    protected $_idproject;

    /**
     * @var string
     */
    protected $_funding;

    /**
     * @var int
     */
    protected $_paperId;

    /**
     * @var int
     */
    protected int $_sourceId;

    /**
     * @var datetime
     */
    protected $_dateUpdated = 'CURRENT_TIMESTAMP';

    /**
     * Episciences_Paper_Projects constructor.
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
            'idproject' => $this->getProjectId(),
            'funding'=> $this->getFunding(),
            'paperId' => $this->getPaperid(),
            'dateUpdated' => $this->getDateUpdated(),
        ];
    }

    /**
     * @return int
     */
    public function getProjectId(): ?int
    {
        return $this->_idproject;
    }

    /**
     * @param int $idProject
     * @return $this
     */
    public function setProjectId(int $idProject): self
    {
        $this->_idproject = $idProject;
        return $this;
    }

    /**
     * @return string
     */
    public function getFunding(): ?string
    {
        return $this->_funding;
    }

    /**
     * @param string $funding
     */
    public function setFunding(string $funding): void
    {
        $this->_funding = $funding;
    }

    public function getPaperid(): ?int
    {

        return $this->_paperId;

    }

    /**
     * @param int $paperId
     * @return $this
     */

    public function setPaperid(int $paperId): self
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



    /**
     * @return DateTime
     */
    public function getDateUpdated(): DateTime
    {
        return $this->_dateUpdated;
    }

    /**
     * @param DateTime $dateUpdated
     * @return Episciences_Paper_Projects
     * @throws Exception
     */
    public function setDateUpdated(string $dateUpdated): self
    {
        $this->_dateUpdated = new DateTime($dateUpdated);
        return $this;
    }

}