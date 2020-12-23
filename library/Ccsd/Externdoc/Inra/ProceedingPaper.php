<?php


class Ccsd_Externdoc_Inra_ProceedingPaper extends Ccsd_Externdoc_Inra
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
        self::META_LINK,
        self::META_CONFTITLE,
        self::META_CONFDATESTART,
        self::META_CONFDATEEND,
        self::META_CITY,
        self::META_COUNTRY,
        self::META_PEERREVIEWED,
        self::META_NBPAGES_INRA,
        self::META_PROCEEDINGSTYPE,
        self::META_PROCEEDINGSTITLE,
        self::META_CONFINVITE,
        self::META_SERIE,
        self::META_PAPERNUMBER,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ProceedingPaper
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_ProceedingPaper($id);
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

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getCommunicationType()
    {
        $type = $this->getProceedingPaperType();

        if (!empty($type) && array_key_exists($type, self::META_ARRAY_COMMUNICATIONTYPE)) {
            $communicationType = self::META_ARRAY_COMMUNICATIONTYPE[$type];
        }

        if (!isset($communicationType)) {
            $communicationType = '0';
        }

        return $communicationType;
    }

    /**
     * @return string
     */
    public function getConferenceOrganizer()
    {
        $organizer = '';

        $externalAffiliation = $this->getEventOrganizerExternalAffiliation();
        if (!empty($externalAffiliation)) {
            $firstPart  = $this->getExternalAffiliationFirstPart();
            $secondPart = $this->getExternalAffiliationSecondPart();
            $thirdPart  = $this->getExternalAffiliationThirdPart();

            $organizer .= $firstPart;

            if (!empty($secondPart)) {
                if (!empty($organizer)) {
                    $organizer .= ' ';
                }

                $organizer .= $secondPart;
            }

            if (!empty($thirdPart)) {
                if (!empty($organizer)) {
                    $organizer .= ' ';
                }

                $organizer .= $thirdPart;
            }
        }

        return $organizer;
    }

    private function setDocumentType()
    {
        $this->_type = 'COMM';

        if ('Poster' === $this->getProceedingPaperType()) {
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
                case self::META_CONFORGANIZER :
                    $meta = $this->getConferenceOrganizer();
                    break;
                case self::META_LINK :
                    $meta = $this->getRecordLink();
                    break;
                case self::META_CONFTITLE:
                    $meta = $this->getEventName();
                    break;
                case self::META_CONFDATESTART:
                    $meta = $this->getEventMeetingStartDate();
                    break;
                case self::META_CONFDATEEND:
                    $meta = $this->getEventMeetingEndDate();
                    break;
                case self::META_CITY:
                    $meta = $this->getEventMeetingCity();
                    break;
                case self::META_COUNTRY:
                    $meta = $this->getEventMeetingCountry();
                    break;
                case self::META_PEERREVIEWED:
                    $meta = $this->getRecordPeerReviewed();
                    break;
                case self::META_NBPAGES_INRA:
                    $meta = $this->getRecordPagination();
                    break;
                case self::META_PAGE :
                    $meta = $this->getPage();
                    break;
                case self::META_PROCEEDINGSTYPE:
                    $meta = $this->getProceedingType();
                    break;
                case self::META_CONFINVITE :
                    $meta = $this->getConferenceInvite();
                    break;
                case self::META_PAPERNUMBER :
                    $meta = $this->getPaperNumber();
                    break;
                case self::META_SERIE:
                    $meta = $this->getProceedingTitle();
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
    private function getExternalAffiliationFirstPart()
    {
        $firstPart = '';

        $affiliationName    = $this->getEventOrganizerExternalAffiliationName();
        $affiliationAcronym = $this->getEventOrganizerExternalAffiliationAcronym();

        $firstPart .= $affiliationName;

        if (!empty($affiliationAcronym)) {
            if (!empty($firstPart)) {
                $firstPart .= ' ';
            }

            $firstPart .= '('.$affiliationAcronym.')';
        }

        if (!empty($firstPart)) {
            $firstPart .= '.';
        }

        return $firstPart;
    }

    /**
     * @return string
     */
    private function getExternalAffiliationSecondPart()
    {
        $secondPart = '';

        $affiliationSection = $this->getEventOrganizerExternalAffiliationSection();
        $affiliationRnsr    = $this->getEventOrganizerExternalAffiliationRnsr();
        $affiliationCity    = $this->getEventOrganizerExternalAffiliationCity();
        $affiliationCountry = $this->getEventOrganizerExternalAffiliationCountry();

        $secondPart .= $affiliationSection;

        if (!empty($affiliationRnsr)) {
            if (!empty($secondPart)) {
                $secondPart .= ' ';
            }

            $secondPart .= '('.$affiliationRnsr.')';
        }

        if (!empty($affiliationCity)) {
            if (!empty($secondPart)) {
                $secondPart .= ', ';
            }

            $secondPart .= $affiliationCity;
        }

        if (!empty($affiliationCountry)) {
            if (!empty($secondPart)) {
                $secondPart .= ', ';
            }

            $secondPart .= $affiliationCountry;
        }

        if (!empty($secondPart)) {
            $secondPart .= '.';
        }

        return $secondPart;
    }

    /**
     * @return string
     */
    private function getExternalAffiliationThirdPart()
    {
        $thirdPart = '';

        if (!empty($this->getEventOrganizerExternalAffiliationAffiliationPartners())) {
            $partnersName       = $this->getEventOrganizerExternalAffiliationAffiliationPartnersName();
            $partnersAcronym    = $this->getEventOrganizerExternalAffiliationAffiliationPartnersAcronym();
            $partnersCountry    = $this->getEventOrganizerExternalAffiliationAffiliationPartnersCountry();

            $thirdPart .= $partnersName;

            if (!empty($partnersAcronym)) {
                if (!empty($thirdPart)) {
                    $thirdPart .= ' ';
                }

                $thirdPart .= '('.$partnersAcronym.')';
            }

            if (!empty($partnersCountry)) {
                if (!empty($thirdPart)) {
                    $thirdPart .= ', ';
                }

                $thirdPart .= $partnersCountry;
            }

            if (!empty($thirdPart)) {
                $thirdPart .= '.';
            }
        }

        return $thirdPart;
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:proceedingPaper']", "Ccsd_Externdoc_Inra_ProceedingPaper");
