<?php


class Ccsd_Externdoc_Inra_Proceedings extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'COMM';

    protected $_specific_wantedTags = [
        self::META_CONFORGANIZER,
        self::META_LINK,
        self::META_CONFTITLE,
        self::META_CONFDATESTART,
        self::META_CONFDATEEND,
        self::META_CITY,
        self::META_COUNTRY,
        self::META_PEERREVIEWED,
        self::META_PAGE,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Proceedings
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Proceedings($id);
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
                    $meta=$this->getEventMeetingStartDate();
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
                case self::META_PAGE:
                    $meta = $this->getNbPage();
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
     * @return string
     */
    public function getConferenceOrganizer()
    {
        $organizer = '';

        $inraAffiliation        = $this->getEventOrganizerInraAffiliation();
        $externalAffiliation    = $this->getEventOrganizerExternalAffiliation();

        // INRA Affiliation
        if (!empty($inraAffiliation)) {
            $organizer .= $this->getConferenceOrganizerInraAffiliation();
        }

        // External Affiliation
        if (!empty($externalAffiliation) && empty($organizer)) {
            $organizer .= $this->getConferenceOrganizerExternalAffiliation();
        }

        return $organizer;
    }

    /**
     * @return string
     */
    public function getConferenceOrganizerInraAffiliation()
    {
        $inraAffiliation = '';

        $inraAffiliation .= $this->getEventOrganizerInraAffiliationName();

        $acronym = $this->getEventOrganizerInraAffiliationAcronym();
        if (!empty($acronym)) {
            if (!empty($inraAffiliation)) {
                $inraAffiliation .= ' ';
            }

            $inraAffiliation .= '('.$acronym.')';
        }

        $unit = $this->getEventOrganizerInraAffiliationUnit();
        if (!empty($unit)) {
            $unitType = $this->getEventOrganizerInraAffiliationUnitType();
            $unitName = $this->getEventOrganizerInraAffiliationUnitName();
            $unitCode = $this->getEventOrganizerInraAffiliationUnitCode();
            if ((!empty($unitType) || !empty($unitName) || !empty($unitCode)) && !empty($inraAffiliation)) {
                $inraAffiliation .= '.';
            }

            if (!empty($unitType)) {
                if (!empty($inraAffiliation)) {
                    $inraAffiliation .= ' ';
                }

                $inraAffiliation .= $unitType;
            }

            if (!empty($unitName)) {
                if (!empty($inraAffiliation)) {
                    $inraAffiliation .= ' ';
                }

                $inraAffiliation .= $unitName;
            }

            if (!empty($unitCode)) {
                if (!empty($inraAffiliation)) {
                    $inraAffiliation .= ' ';
                }

                $inraAffiliation .= '('.$unitCode.')';
            }
        }

        return $inraAffiliation;
    }

    /**
     * @return string
     */
    public function getConferenceOrganizerExternalAffiliation()
    {
        $externalAffiliation = '';

        $firstPart  = $this->getExternalAffiliationFirstPart();
        $secondPart = $this->getExternalAffiliationSecondPart();
        $thirdPart  = $this->getExternalAffiliationThirdPart();

        $externalAffiliation .= $firstPart;

        if (!empty($secondPart)) {
            if (!empty($externalAffiliation)) {
                $externalAffiliation .= ' ';
            }

            $externalAffiliation .= $secondPart;
        }

        if (!empty($thirdPart)) {
            if (!empty($externalAffiliation)) {
                $externalAffiliation .= ' ';
            }

            $externalAffiliation .= $thirdPart;
        }

        return $externalAffiliation;
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

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:proceedings']", "Ccsd_Externdoc_Inra_Proceedings");
