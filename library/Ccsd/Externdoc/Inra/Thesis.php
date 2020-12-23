<?php


class Ccsd_Externdoc_Inra_Thesis extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'THESE';

    protected $_specific_wantedTags = [
        self::META_LINK,
        self::META_DATEDEFENDED,
        self::META_SPECIALITY_INRA,
        self::META_GRANT_INRA,
        self::META_HOSTLABORATORY_INRA,
        self::META_AUTHORITYINSTITUTION,
        self::META_JURYCHAIR,
        self::META_JURYCOMPOSITION,
        self::META_PAGE,
        self::META_THESISSCHOOL,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Thesis
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Thesis($id);
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
                case self::META_GRANT_INRA:
                    $meta = $this->getGrant();
                    break;
                case self::META_HOSTLABORATORY_INRA :
                    $meta = $this->getHostLaboratory();
                    break;
                case self::META_SPECIALITY_INRA :
                    $meta = $this->getSpeciality();
                    break;
                case self::META_LINK :
                    $meta = $this->getRecordLink();
                    break;
                case self::META_DATEDEFENDED :
                    $meta = $this->getDefenseDate();
                    break;
                case self::META_AUTHORITYINSTITUTION :
                    $meta = $this->getRecordOrganizationDegreeName();
                    break;
                case self::META_JURYCHAIR :
                    $meta = $this->getThesisDirector();
                    break;
                case self::META_JURYCOMPOSITION :
                    $meta = $this->getJuryComposition();
                    break;
                case self::META_PAGE :
                    $meta = $this->getNbPage();
                    break;
                case self::META_THESISSCHOOL :
                    $meta = $this->getAuthorityInstitution();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }

        return $this->_metas;
    }

    /**
     * @return array|string|null
     */
    public function getJuryComposition()
    {
        return $this->tryToExplodeString(parent::getJuryComposition());
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:thesis']", "Ccsd_Externdoc_Inra_Thesis");
