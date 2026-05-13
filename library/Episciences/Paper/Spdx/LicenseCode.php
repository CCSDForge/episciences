<?php

namespace Episciences\Paper\Spdx;

use Episciences\common\AbstractCommon;

class LicenseCode extends AbstractCommon
{

    private int $id;
    private int $docid;
    private string $code;
    private ?string $name = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getDocid(): int
    {
        return $this->docid;
    }

    public function setDocid(int $docid): void
    {
        $this->docid = $docid;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        if ($this->name === null) {

            $name = LicenseManager::getNameByIdentifier($this->code);

            if ($name) {
                $this->name = $name;
            }
        }

        return $this->name;
    }

    public function setName(?string $name = null): void
    {
        $this->name = $name;
    }

    public function save(): self
    {
        LicenseCodeManager::save($this);
        return $this;
    }

    /**
     * Code to spdx URL
     * @return string
     */

    public function getReference(): string
    {
        if($this->getCode()){
            return sprintf('%s%s.html', LicenseSpdxResolver::SPDX_LICENSE_LIST_URL, $this->getCode());
        }

        return LicenseSpdxResolver::NO_ASSERTION;
    }

}