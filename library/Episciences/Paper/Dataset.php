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

    /** @var string|null */
    protected ?string $_relationship = null;

    /**
     * @var int|null
     */
    protected ?int $_idPaperDatasetsMeta = null;


    /** @var DateTime */
    protected $_time = 'CURRENT_TIMESTAMP';

    public const HAL_LINKED_DATA_DOI_CODE = 'researchData_s';
    public const HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE = 'swhidId_s';
    public const DOI_CODE = 'doi';
    public const URL_CODE = 'url';
    public const SOFTWARE_CODE = 'software';
    public const PUBLICATION = 'publication';
    public const DATASET = 'dataset';
    public const UNDEFINED_CODE = 'undefined';
    public static array $_datasetsLabel = [

        self::HAL_LINKED_DATA_DOI_CODE => self::DOI_CODE,
        self::HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE => self::SOFTWARE_CODE,
        self::URL_CODE => self::URL_CODE,
        self::SOFTWARE_CODE => self::SOFTWARE_CODE,
        self::DATASET => self::DATASET,
        self::PUBLICATION => self::PUBLICATION,
        self::DOI_CODE => self::DOI_CODE,
        self::UNDEFINED_CODE => self::UNDEFINED_CODE,
        'journal-article' => self::PUBLICATION,
        'article' => self::PUBLICATION,
        'proceedings' => 'proceedings',
        'report' => 'report',
        'article-journal' => self::PUBLICATION,
    ];

    public static array $_datasetsLink = [
        self::HAL_LINKED_DATA_DOI_CODE => self::DOI_CODE,
        self::DOI_CODE => self::DOI_CODE,
        self::HAL_LINKED_DATA_SOFTWARE_HERITAGE_CODE => 'SWHID'
    ];

    protected $_metatext;


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
            'relationship' => $this->getRelationship(),
            'idPaperDatasetsMeta' => $this->getIdPaperDatasetsMeta(),
            'time' => $this->getTime()
        ];
    }

    public function getMetatext(): ?string
    {
        return $this->_metatext;
    }

    public function setMetatext($metatext)
    {
        return $this->_metatext = $metatext;
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
    public function getRelationship(): ?string
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

    /**
     * get metadata sources from T_PAPER_METADATA_SOURCES table
     * @param int $sourcesId
     * @return string
     * @throws Zend_Exception
     */
    public function getSourceLabel(int $sourcesId): string
    {

        $metadataSources = Zend_Registry::get('metadataSources');

        if (!$metadataSources || !array_key_exists($sourcesId, $metadataSources)) {
            return 'Undefined';
        }

        /** @var Episciences_Paper_MetaDataSource $metaDataSource */
        $metaDataSource = new Episciences_Paper_MetaDataSource($metadataSources[$sourcesId]);

        return $metaDataSource->getName();
    }

}