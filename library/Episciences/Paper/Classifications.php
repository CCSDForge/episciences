<?php


class Episciences_Paper_Classifications
{

    /**
     * @var int
     */
    protected int $id;

    /**
     * @var string
     */
    protected string $classificationCode;

    /**
     * @var string
     */
    protected string $classificationName;

    /**
     * @var int
     */
    protected int $docid;

    /**
     * @var int
     */
    protected int $sourceId;

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
     * @return int
     */
    public function getDocid(): int
    {
        return $this->docid;
    }

    /**
     * @param int $paperId
     */
    public function setDocid(int $paperId): void
    {
        $this->docid = $paperId;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'docid' => $this->getDocid(),
            'classificationCode' => $this->getClassificationCode(),
            'classificationName' => $this->getClassificationName(),
            'sourceId' => $this->getSourceId()
        ];
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getClassificationCode(): string
    {
        return $this->classificationCode;
    }

    public function setClassificationCode(string $classification): self
    {
        $this->classificationCode = $classification;
        return $this;
    }

    public function getClassificationName(): string
    {
        return $this->classificationName;
    }

    public function setClassificationName(string $type): self
    {
        $this->classificationName = $type;
        return $this;
    }

    public function getSourceId(): int
    {

        return $this->sourceId;

    }


    public function setSourceId(int $sourceId): self
    {
        $this->sourceId = $sourceId;
        return $this;
    }


    /**
     * @throws Zend_Exception
     */
    public function checkClassificationCode(string $code, array $availableClassificationCodes = []): void
    {
        if (!in_array($code, $availableClassificationCodes, true)) {
            throw new Zend_Exception(sprintf('[%s] code not found in %s classifications table', $code, strtoupper($this->classificationName)));
        }

    }
}