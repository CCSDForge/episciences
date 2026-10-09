<?php


use Episciences\common\AbstractCommon;

class Episciences_Paper_Licence extends AbstractCommon
{

    /**
     * @var int
     */
    protected int $_id;

    /**
     * @var string
     */
    protected string $_licence;

    /**
     * @var int
     */
    protected int $_docId;

    /**
     * @var int
     */
    protected int $_sourceId;

    /**
     * @var datetime|null
     */
    protected ?datetime $_updatedAt = null;

    protected $_uid;

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'licence'=> $this->getLicence(),
            'docId' => $this->getDocid(),
            'sourceId' => $this->getSourceId(),
            'updatedAt' => $this->getUpdatedAt(),
            'uid' => $this->getUid()
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
     * @return int|null
     */
    public function getDocid(): ?int
    {
        return $this->_docId;
    }

    /**
     * @param int $docId
     */
    public function setDocid(int $docId): void
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
        return $this->_updatedAt ?? new DateTime();
    }

    /**
     * @param string|null $updatedAt
     * @return $this
     * @throws Exception
     */

    public function setUpdatedAt(?string $updatedAt = null): self
    {
        $this->_updatedAt = !$updatedAt ? new DateTime() : new DateTime($updatedAt);
        return $this;
    }

    /**
     * @return string|null
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

    /**
     * @return int|null
     */
    public function getUid() : ?int
    {
        return $this->_uid;
    }

    /**
     * @param mixed $uid
     */
    public function setUid(int $uid = null): self
    {
        $this->_uid = $uid;
        return $this;
    }

    public function save(): int
    {
        return Episciences_Paper_LicenceManager::insert($this);
    }
}