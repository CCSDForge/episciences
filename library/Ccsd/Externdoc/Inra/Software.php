<?php


class Ccsd_Externdoc_Inra_Software extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'SOFTWARE';

    protected $_specific_wantedTags = [
        self::META_COMMENT,
        self::META_VERSION,
        self::META_DEVELOPMENTSTATUS,
        self::META_LICENSE,
        self::META_PROGRAMMINGLANGUAGE,
        self::META_ENVIRONMENT,
        self::META_RELATEDSOFTWARE,
        self::META_DEVELOPMENTTYPE_INRA,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Software
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Software($id);
        $doc->setDomPath($domxpath);

        return $doc;
    }

    /**
     * @return array
     */
    public function getMetadatas()
    {
        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = parent::getMetadatas();

        foreach ($this->_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_COMMENT:
                    $meta = $this->getAdditionalComment();
                    break;
                case self::META_VERSION:
                    $meta = $this->getVersionNumber();
                    break;
                case self::META_DEVELOPMENTSTATUS: //FIXME: absent dans la TEI
                    $meta = $this->getMaturity();
                    break;
                case self::META_LICENSE: //FIXME: si pas de version, récupérer la plus récente
                    $meta = $this->getLicense();
                    break;
                case self::META_PROGRAMMINGLANGUAGE:
                    $meta = $this->getProgramLanguage();
                    break;
                case self::META_ENVIRONMENT:
                    $meta = $this->getEnvironment();
                    break;
                case self::META_RELATEDSOFTWARE: //FIXME: absent dans la TEI
                    $meta = $this->getPlatform();
                    break;
                case self::META_DEVELOPMENTTYPE_INRA:
                    $meta = $this->getSoftwareType();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }

        return $this->_metas;
    }

    public function getAdditionalComment()
    {
        $comment    = '';
        $glue       = "\r\n";

        // check if comment already has a value
        $meta = $this->getMetaValue(self::META, self::META_COMMENT);
        if (isset($meta) && !empty($meta)) {
            $comment .= $meta;
        }

        $firstVersionYear = $this->getFirstVersionYear();
        if (!empty($firstVersionYear)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'Année de la première version : '.$firstVersionYear;
        }

        $documentation = $this->getDocumentation();
        if (!empty($documentation)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'Documents associés disponibles : '.$documentation;
        }

        $userInterface = $this->getUserInterface();
        if (!empty($userInterface)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'Interface utilisateur : '.$userInterface;
        }

        $diffusionMode = $this->getDiffusionMode();
        if (!empty($diffusionMode)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'Mode de diffusion : '.$diffusionMode;
        }

        $appDepositNumber = $this->getAppDepositNumber();
        if (!empty($appDepositNumber)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'N° de dépôt APP : '.$appDepositNumber;
        }

        $prerequisites = $this->getPrerequisites();
        if (!empty($prerequisites)) {
            if (!empty($comment)) {
                $comment .= $glue;
            }

            $comment .= 'Prérequis : '.$prerequisites;
        }

        return $comment;
    }

    public function getPlatform()
    {
        $platform = '';

        $relatedSoftware = $this->getRelatedSoftware();
        if (!empty($relatedSoftware)) {
            $platform .= $relatedSoftware;
        }

        $relatedSoftwareLink = $this->getRelatedSoftwareLink();
        if (!empty($relatedSoftwareLink)) {
            if (!empty($relatedSoftwareLink)) {
                $platform .= ' ';
            }

            $platform .= '('.$relatedSoftwareLink.')';
        }

        return $platform;
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:software']", "Ccsd_Externdoc_Inra_Software");
