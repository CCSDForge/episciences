<?php


class Ccsd_Externdoc_Inra_Report extends Ccsd_Externdoc_Inra
{
    const META_ARRAY_OTHERTYPE_OTHER = [
        'Article de blog / Web'                         => 'BL',
        'Compte-rendu d\'ouvrage ou Note de lecture'    => 'CR',
        'Notice d\'encyclopédie ou de dictionnaire'     => 'NO',
        'Traduction'                                    => 'TR',
        'Illustration'                                  => 'IL',
        'Charte'                                        => 'CH',
        'Newsletter'                                    => 'NL',
        'Livret guide'                                  => 'LG',
        'Communiqué de presse'                          => 'CP',
        'Récompense'                                    => 'RE',
        'Plaquette / Flyer'                             => 'PF',
        'Plan qualité'                                  => 'PQ',
        'Certification / Norme'                         => 'CN',
        'Livre blanc'                                   => 'LB',
        'Note de cadrage'                               => 'NC',
        'Fiche technique'                               => 'FT',
        'Presse / Média'                                => 'PM',
        'Bande dessinée'                                => 'BD',
    ];

    /**
     * @var string
     */
    protected $_type = 'OTHER';

    protected $_other_specific_wantedTags = [
        self::META_OTHERTYPE_OTHERTYPE,
        self::META_VOLUME,
    ];

    protected $_image_specific_wantedTags = [
        self::META_IMAGETYPE,
    ];

    protected $_report_specific_wantedTags = [
        self::META_REPORTTYPE,
        self::META_VOLUME,
    ];

    protected $_specific_wantedTags = [
        self::META_REPORTNUMBER,
        self::META_PAGE,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Report
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Report($id);
        $doc->setDomPath($domxpath);

        return $doc;
    }

    /**
     * @return array
     */
    public function getMetadatas()
    {
        $this->setDocumentType();

        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = parent::getMetadatas();

        if ('OTHER' === $this->_type) {
            $this->setOtherSpecificWantedTags();
        }

        if ('IMG' === $this->_type) {
            $this->setImageSpecificWantedTags();
        }

        if ('REPORT' === $this->_type) {
            $this->setReportSpecificWantedTags();
        }

        $this->setSpecificWantedTags();

        return $this->_metas;
    }

    private function setDocumentType()
    {
        $this->_type = 'OTHER';

        $otherReportType = $this->getOtherReportType();

        if ('Logo/Dessin' === $otherReportType) {
            $this->_type = 'IMG';
        }

        if ('Autres rapports' === $otherReportType) {
            $this->_type = 'REPORT';
        }
    }

    private function setOtherSpecificWantedTags()
    {
        $this->deleteMetas(array(
            self::META_VOLUME,
        ));

        foreach ($this->_other_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_OTHERTYPE_OTHERTYPE:
                    $meta = $this->getTranslatedType();
                    break;
                case self::META_VOLUME:
                    $meta = $this->getFormattedCollection();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    private function setImageSpecificWantedTags()
    {
        foreach ($this->_image_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_IMAGETYPE:
                    $meta = 'IL'; // = Illustration
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    private function setReportSpecificWantedTags()
    {
        $this->deleteMetas(array(
            self::META_VOLUME,
        ));

        foreach ($this->_report_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_REPORTTYPE:
                    $meta = 'AU'; // = Autre
                    break;
                case self::META_VOLUME:
                    $meta = $this->getFormattedCollection();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    private function setSpecificWantedTags()
    {
        foreach ($this->_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_REPORTNUMBER:
                    $meta = $this->getReportNumber();
                    break;
                case self::META_PAGE:
                    $meta = $this->getNbPage();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    /**
     * @return string
     */
    private function getTranslatedType()
    {
        $type = $this->getOtherReportType();
        if (!empty($type) && array_key_exists($type, self::META_ARRAY_OTHERTYPE_OTHER)) {
            $type = self::META_ARRAY_OTHERTYPE_OTHER[$type];
        }

        if (!isset($type) || empty($type)) {
            $type = '0';
        }

        return $type;
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:report']", "Ccsd_Externdoc_Inra_Report");
