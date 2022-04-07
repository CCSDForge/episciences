<?php

class Episciences_Paper_Dataset
{

    /**
     * @var int
     */
    protected $_id;
    /**
     * @var int
     */
    protected $_docId;
    /**
     * @var string
     */
    protected $_code;

    /**
     * @var string
     */
    protected $_name;

    /** @var string */
    protected $_value;

    /** @var string */
    protected $_link;

    /**
     * @var int
     */
    protected $_sourceId;

    /** @var string */
    protected $_relationship;

    /**
     * @var int
     */
    protected $_idPaperDatasetsMeta;


    /** @var DateTime */
    protected $_time = 'CURRENT_TIMESTAMP';

    public const HAL_LINKED_DATA_DOI_CODE = 'researchData_s';
    public const HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE = 'swhidId_s';
    public const DOI_CODE = 'doi';
    public const URL_CODE = 'url';
    public const LINK_CODE = 'link';
    public const SOFTWARE_CODE = 'software';


    public static $_datasetsLabel = [

        self::HAL_LINKED_DATA_DOI_CODE =>  self::DOI_CODE,
        self::HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE => self::SOFTWARE_CODE,
        self::URL_CODE => self::LINK_CODE
    ];

    public static $_datasetsLink = [
        self::HAL_LINKED_DATA_DOI_CODE => 'https://doi.org/',
        self::DOI_CODE => 'https://doi.org/',
        self::HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE => 'https://archive.softwareheritage.org/'
    ];



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
            'docId' => $this->getDocId(),
            'code' => $this->getCode(),
            'name' => $this->getName(),
            'value' => $this->getValue(),
            'link' => $this->getLink(),
            'sourceId' => $this->getSourceId(),
            'relationship'=> $this->getRelationship(),
            'idPaperDatasetsMeta'=> $this->getIdPaperDatasetsMeta(),
            'time' => $this->getTime()
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
    public function getDocId(): int
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

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->_code;
    }

    /**
     * @param string $code
     * @return Episciences_Paper_Dataset
     */
    public function setCode(string $code): self
    {

        $this->_code = $code;

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
     * @return Episciences_Paper_Dataset
     */
    public function setName(string $name): self
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->_value;
    }

    /**
     * @param string $value
     * @return Episciences_Paper_Dataset
     */
    public function setValue(string $value): self
    {
        $this->_value = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->_link;
    }

    /**
     * @param string $link
     * @return Episciences_Paper_Dataset
     */
    public function setLink(string $link): self
    {
        $this->_link = $link;
        return $this;
    }

    /**
     * @return int
     */

    public function getSourceId(): int
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
    public function getRelationship(): string
    {
        return $this->_relationship;
    }

    /**
     * @param null $relationship
     * @return Episciences_Paper_Dataset
     */
    public function setRelationship(string $relationship = null): self
    {

        $this->_relationship = $relationship;

        return $this;
    }

    /**
     * @return int
     */

    public function getIdPaperDatasetsMeta(): ?int
    {

        return $this->_idPaperDatasetsMeta;

    }

    /**
     * @param int|null $idPaperDatasetsMeta
     * @return $this
     */

    public function setIdPaperDatasetsMeta(int $idPaperDatasetsMeta = null): self
    {
        $this->_idPaperDatasetsMeta = $idPaperDatasetsMeta;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTime()
    {
        return $this->_time;
    }

    /**
     * @param string $time
     * @return Episciences_Paper_Dataset
     * @throws Exception
     */
    public function setTime(string $time): self
    {
        $this->_time = new DateTime($time);
        return $this;
    }

}