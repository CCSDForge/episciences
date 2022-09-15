<?php


class Episciences_Paper_Classifications
{

    /**
     * @var int
     */
    protected int $_id;

    /**
     * @var string
     */
    protected string $_classification;

    /**
     * @var string
     */
    protected string $_type;

    /**
     * @var int
     */
    protected int $_paperId;

    /**
     * @var int
     */
    protected int $_sourceId;

    /**
     * Episciences_Paper_Classification constructor.
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
            'id' => $this->getId(),
            'paperId'=> $this->getPaperId(),
            'classification' => $this->getClassification(),
            'sourceId' => $this->getSourceId(),
            'type' => $this->getType(),
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
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getPaperId(): ?int
    {
        return $this->_paperId;
    }

    /**
     * @param int $paperId
     */
    public function setPaperId(int $paperId): void
    {
        $this->_paperId = $paperId;
    }

    public function getSourceId(): ?int
    {

        return $this->_sourceId;

    }

    /**
     * @param int $sourceId
     * @return $this
     */

    public function setSourceId(int $sourceId): self
    {
        $this->_sourceId = $sourceId;
        return $this;
    }

    /**
     * @return string
     */

    public function getClassification(): ?string
    {
        return $this->_classification;
    }

    /**
     * @param string $classification
     * @return Episciences_Paper_Classifications
     */
    public function setClassification(string $classification): self
    {
        $this->_classification = $classification;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->_type;
    }
    /**
     * @param string $type
     * @return Episciences_Paper_Classifications
     */
    public function setType(string $type): self
    {
        $this->_type = $type;
        return $this;
    }


}