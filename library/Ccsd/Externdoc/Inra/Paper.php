<?php


class Ccsd_Externdoc_Inra_Paper extends Ccsd_Externdoc_Inra
{
    const META_ARRAY_COMMUNICATIONTYPE = [
        'Full paper'            => 'FT',
        'Short paper'           => 'ST',
        'Extended abstract'     => 'LA',
        'Abstract'              => 'AB',
        'Présentation orale'    => 'OP',
        'Préface d\'acte'       => 'BP',
    ];

    /**
     * @var string
     */
    protected $_type = 'COMM';

    protected $_communication_specific_wantedTags = [
        self::META_COMMUNICATIONTYPE,
    ];

    protected $_poster_specific_wantedTags = [
    ];

    protected $_specific_wantedTags = [
        self::META_CONFORGANIZER,
        self::META_CONFTITLE,
        self::META_CONFDATESTART,
        self::META_CONFDATEEND,
        self::META_CITY,
        self::META_COUNTRY,
        self::META_PEERREVIEWED,
        self::META_PAGE,
//        self::META_NBPAGES_INRA, //FIXME: a delete ?
        self::META_CONFINVITE,
        self::META_PAPERNUMBER,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Paper
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Paper($id);
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

        if ('COMM' === $this->_type) {
            $this->setCommunicationSpecificWantedTags();
        }

        if ('POSTER' === $this->_type) {
            $this->setPosterSpecificWantedTags();
        }

        $this->setSpecificWantedTags();

        return $this->_metas;
    }

    private function setDocumentType()
    {
        $this->_type = 'COMM';

        if ('Poster' === $this->getPaperType()) {
            $this->_type = 'POSTER';
        }
    }

    private function setCommunicationSpecificWantedTags()
    {
        foreach ($this->_communication_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_COMMUNICATIONTYPE:
                    $meta = $this->getCommunicationType();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    private function setPosterSpecificWantedTags()
    {
        foreach ($this->_poster_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
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
                case self::META_CONFORGANIZER :         //FIXME: plusieurs événements possibles, séparé de meeting => cohérence avec le reste
                    $meta = $this->getConferenceOrganizer();
                    break;
                case self::META_CONFTITLE:              //FIXME: plusieurs événements possibles => plusieurs noeuds meeting ? cohérence avec CONFORGANIZER ?
                    $meta = $this->getEventName();
                    break;
                case self::META_CONFDATESTART:          //FIXME: plusieurs événements possibles => plusieurs noeuds meeting ? cohérence avec CONFORGANIZER ?
                    $meta = $this->getEventMeetingStartDate();
                    break;
                case self::META_CONFDATEEND:            //FIXME: plusieurs événements possibles => plusieurs noeuds meeting ? cohérence avec CONFORGANIZER ?
                    $meta = $this->getEventMeetingEndDate();
                    break;
                case self::META_CITY:                   //FIXME: plusieurs événements possibles => plusieurs noeuds meeting ? cohérence avec CONFORGANIZER ?
                    $meta = $this->getEventMeetingCity();
                    break;
                case self::META_COUNTRY:                //FIXME: plusieurs événements possibles => plusieurs noeuds meeting ? cohérence avec CONFORGANIZER ?
                    $meta = $this->getEventMeetingCountry();
                    break;
                case self::META_PEERREVIEWED:
                    $meta = $this->getRecordPeerReviewed();
                    break;
//                case self::META_NBPAGES_INRA: //FIXME: noeud pagination inexistant (demandé dans le excel)
//                    $meta = $this->getRecordPagination();
//                    $meta = 'test pagination';
                    break;
                case self::META_PAGE :
                    $meta = $this->getNbPage();
                    break;
                case self::META_CONFINVITE :
                    $meta = $this->getConferenceInvite();
                    break;
                case self::META_PAPERNUMBER :
                    $meta = $this->getPaperNumber();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getCommunicationType()
    {
        $type = $this->getPaperType();

        if (!empty($type) && array_key_exists($type, self::META_ARRAY_COMMUNICATIONTYPE)) {
            $communicationType = self::META_ARRAY_COMMUNICATIONTYPE[$type];
        }

        if (!isset($communicationType)) {
            $communicationType = '0';
        }

        return $communicationType;
    }

    private function getConferenceOrganizer()
    {
        return 'à fixer';
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:paper']", "Ccsd_Externdoc_Inra_Paper");
