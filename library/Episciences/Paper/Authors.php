<?php
declare(strict_types=1);

class Episciences_Paper_Authors
{

    /**
     * @var int
     */
    protected $_idauthors;

    /**
     * @var string
     */
    protected $_authors;

    /**
     * @var int
     */
    protected $_paperId;

    /**
     * @var datetime
     */
    protected $_dateUpdated = 'CURRENT_TIMESTAMP';

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


    public function toArray(): array
    {
        return [
            'idAuthors' => $this->getAuthorId(),
            'authors'=> $this->getAuthors(),
            'paperId' => $this->getPaperId(),
            'dateUpdated' => $this->getDateUpdated(),
        ];
    }

    /**
     * @return int
     */
    public function getAuthorId(): ?int
    {
        return $this->_idauthors;
    }

    /**
     * @return $this
     */
    public function setAuthorsId(int $idAuthors): self
    {
        $this->_idauthors = $idAuthors;
        return $this;
    }

    /**
     * @return string
     */
    public function getAuthors(): ?string
    {
        return $this->_authors;
    }

    public function setAuthors(string $authors): void
    {
        $this->_authors = $authors;
    }

    public function getPaperId(): ?int
    {

        return $this->_paperId;

    }

    /**
     * @return $this
     */
    public function setPaperId(int $paperId): self
    {
        $this->_paperId = $paperId;
        return $this;
    }


    public function getDateUpdated(): DateTime
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