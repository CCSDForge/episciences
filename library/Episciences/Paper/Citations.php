<?php


class Episciences_Paper_Citations
{
    protected int $_id;
    protected string $_citation;
    protected int $_docId;
    protected int $_sourceId;
    protected ?DateTime $_updatedAt = null;

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

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

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'citation' => $this->getCitation(),
            'docId' => $this->getDocId(),
            'sourceId' => $this->getSourceId(),
            'updatedAt' => $this->getUpdatedAt(),
        ];
    }

    public function getId(): int
    {
        return $this->_id;
    }

    public function setId(int $id): self
    {
        $this->_id = $id;
        return $this;
    }

    public function getDocId(): ?int
    {
        return $this->_docId;
    }

    public function setDocId(int $docId): self
    {
        $this->_docId = $docId;
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

    public function getUpdatedAt(): ?DateTime
    {
        return $this->_updatedAt;
    }

    /**
     * @throws Exception
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        $this->_updatedAt = new DateTime($updatedAt);
        return $this;
    }

    public function getCitation(): ?string
    {
        return $this->_citation;
    }

    public function setCitation(string $citation): self
    {
        $this->_citation = $citation;
        return $this;
    }
}
