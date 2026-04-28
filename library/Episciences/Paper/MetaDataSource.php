<?php
class Episciences_Paper_MetaDataSource
{


    protected int $_id;

    protected string $_name;

    protected string $_type;
    protected ?string $_identifier;
    protected ?string $_doiPrefix;
    protected ?string $_apiUrl;
    protected ?string $_docUrl;
    protected ?string $_paperUrl;

    protected bool $_status;


    /**
     * Episciences_Paper_Dataset constructor.
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
            'name' => $this->getName(),
            'code' => $this->getCode(),
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
     * @return string
     */
    public function getName(): string
    {
        return $this->_name;
    }

    /**
     * @param string $name
     * @return Episciences_Paper_MetaDataSource
     */
    public function setName(string $name): self
    {
        $this->_name = $name;
        return $this;
    }


    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->_type;
    }

    /**
     * @param string $type
     * @return Episciences_Paper_MetaDataSource
     */
    public function setType(string $type): self
    {
        $this->_type = $type;
        return $this;
    }

    public function getStatus(): bool
    {
        return $this->_status;
    }

    public function setStatus(bool $status): self
    {
        $this->_status = $status;
        return $this;
    }
    public function getIdentifier(): string
    {
        return $this->_oaiIdentifier;
    }

    public function setIdentifier(string $identifier = null): self
    {
        $this->_identifier = $identifier;
        return $this;
    }

    public function getDoiPrefix(): string
    {
        return $this->_oaiPrefix;
    }

    public function setDoiPrefix(string $doiPrefix = null): self
    {
        $this->_doirefix = $doiPrefix;
        return  $this;
    }

    public function getApiUrl(): string
    {
        return $this->_apiUrl;
    }

    public function setApiUrl(string $apiUrl = null): self
    {
        $this->_apiUrl = $apiUrl;
        return $this;
    }

    public function getDocUrl(): string
    {
        return $this->_docUrl;
    }

    public function setDocUrl(string $docUrl = null): self
    {
        $this->_docUrl = $docUrl;
        return $this;
    }

    public function getPaperUrl(): string
    {
        return $this->_paperUrl;
    }

    public function setPaperUrl(string $paperUrl = null): self
    {
        $this->_paperUrl = $paperUrl;
        return $this;
    }
}