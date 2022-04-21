<?php


class Episciences_Paper_Licence
{

    /**
     * @var int
     */
    protected $_id;

    /**
     * @var string
     */
    protected $_licence;

    /**
     * @var int
     */
    protected $_docId;

    /**
     * @var int
     */
    protected $_sourceId;

    /**
     * @var datetime
     */
    protected $_updatedAt = 'CURRENT_TIMESTAMP';

    /**
     * Episciences_Paper_Licence constructor.
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
            'licence'=> $this->getLicence(),
            'docId' => $this->getDocId(),
            'sourceId' => $this->getSourceId(),
            'updatedAt' => $this->getUpdatedAt(),
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
    public function getDocId(): ?int
    {
        return $this->_docId;
    }

    /**
     * @param int $docId
     */
    public function setDocId(int $docId): void
    {
        $this->_docId = $docId;
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
     * @return DateTime
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->_updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     * @return Episciences_Paper_Dataset
     * @throws Exception
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        $this->_updatedAt = new DateTime($updatedAt);
        return $this;
    }

    /**
     * @return string
     */

    public function getLicence(): ?string
    {
        return $this->_licence;
    }

    /**
     * @param string $licence
     * @return Episciences_Paper_Licence
     */
    public function setLicence(string $licence): self
    {
        $this->_licence = $licence;
        return $this;
    }



}