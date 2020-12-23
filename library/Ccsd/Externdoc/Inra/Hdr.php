<?php


class Ccsd_Externdoc_Inra_Hdr extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'HDR';

    protected $_specific_wantedTags = [
        self::META_HOSTLABORATORY_INRA,
        self::META_DATEDEFENDED,
        self::META_JURYCHAIR,
        self::META_JURYCOMPOSITION,
        self::META_NBPAGES_INRA,
        self::META_AUTHORITYINSTITUTION,
        self::META_SPECIALITY_INRA,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Hdr
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Hdr($id);
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
                case self::META_HOSTLABORATORY_INRA :
                    $meta = $this->getHostLaboratory();
                    break;
                case self::META_DATEDEFENDED :
                    $meta = $this->getDefenseDate();
                    break;
                case self::META_JURYCHAIR :
                    $meta = $this->getJuryChair();
                    break;
                case self::META_JURYCOMPOSITION :
                    $meta = $this->getJuryComposition();
                    break;
                case self::META_NBPAGES_INRA :
                    $meta = $this->getNbPage();
                    break;
                case self::META_AUTHORITYINSTITUTION :
                    $meta = $this->getRecordOrganizationDegreeName();
                    break;
                case self::META_SPECIALITY_INRA :
                    $meta = $this->getSpeciality();
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

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:hdr']", "Ccsd_Externdoc_Inra_Hdr");
