<?php

class Episciences_Paper_DatasetMetadata
{

    /**
     * @var int
     */
    protected $_id;
    /**
     * @var string
     */
    protected $_metatext;

    /**
     * @var DateTime
     */
    protected $_updated = 'CURRENT_TIMESTAMP';


    /**
     * Episciences_Paper_DatasetMeta constructor.
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
            'metatext' => $this->getMetatext(),
            'updated' => $this->getUpdated()
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
     * @return null
     */
    public function getMetatext(): ?string
    {
        return $this->_metatext;
    }

    /**
     * @param string $metatext
     */
    public function setMetatext(string $metatext): void
    {
        $this->_metatext = $metatext;
    }


    /**
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->_updated;
    }

    /**
     * @param string $updated
     * @return Episciences_Paper_DatasetMetadata
     * @throws Exception
     */
    public function setUpdated(string $updated): self
    {
        $this->_updated = new DateTime($updated);
        return $this;
    }

}