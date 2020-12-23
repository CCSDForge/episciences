<?php


class Ccsd_Externdoc_Inra_Dissertation extends Ccsd_Externdoc_Inra
{
    const META_ARRAY_LECTURETYPE = array(
        'DEA'                   => '1',
        'École thématique'      => '2',
        '3ème cycle'            => '7',
        'École d\'ingénieur'    => '10',
        'Licence'               => '11',
        'Master'                => '12',
        'Doctorat'              => '13',
        'DEUG'                  => '14',
        'Maitrise'              => '15',
        'Licence / L1'          => '21',
        'Licence / L2'          => '22',
        'Licence / L3'          => '23',
        'Master / M1'           => '31',
        'Master / M2'           => '32',
        'Vulgarisation'         => '40',
    );

    const DEGREE_TRANSFORMATION = array(
        'Bac +1' => 'DEUG',
        'Bac +2' => 'DEUG',
        'Bac +3' => 'Licence',
        'Bac +4' => 'Maîtrise',
        'Bac +5' => 'Master',
        'Bac +6' => '3ème cycle',
    );

    /**
     * @var string
     */
    protected $_type = 'MEM';

    protected $_specific_wantedTags = [
        self::META_DISSERTATION_DIRECTOR,
        self::META_DATEDEFENDED,
        self::META_PAGE,
        self::META_AUTHORITYINSTITUTION,
        self::META_SPECIALITY_INRA,
        self::META_DISSERTATIONLEVEL,
        self::META_GRANT_INRA,
    ];

    protected $_report_specific_wantedTags = [
        self::META_REPORTTYPE,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Dissertation
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Dissertation($id);
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

        if ('REPORT' === $this->_type) {
            $this->setReportSpecificWantedTags();
        }

        $this->setSpecificWantedTags();

        return $this->_metas;
    }

    private function setDocumentType()
    {
        $this->_type = 'MEM';

        if ('Rapport de stage' === $this->getDissertationType()) {
            $this->_type = 'REPORT';
        }
    }

    private function setReportSpecificWantedTags()
    {
        foreach ($this->_report_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_REPORTTYPE:
                    $meta = 'RS'; // = Rapport de stage
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
                case self::META_DISSERTATION_DIRECTOR :
                    $meta = $this->getDissertationDirectors();
                    break;
                case self::META_DATEDEFENDED :
                    $meta = $this->getDissertationDefenseDate();
                    break;
                case self::META_PAGE :
                    $meta = $this->getNbPage();
                    break;
                case self::META_AUTHORITYINSTITUTION :
                    $meta = $this->getDissertationAuthorityInstitution();
                    break;
                case self::META_SPECIALITY_INRA :
                    $meta = $this->getSpeciality();
                    break;
                case self::META_DISSERTATIONLEVEL :
                    $meta = $this->getTranslatedDegree();
                    break;
                case self::META_GRANT_INRA :
                    $meta = $this->getGrant();
                    break;
                default:
                    $meta = '';
                    break;
            }

            $this->addMeta($metaKey, $meta);
        }
    }

    /**
     * @return array
     */
    private function getDissertationDirectors()
    {
        $internshipSupervisors = $this->getFlattenedDirectors($this->getInternshipSupervisor());
        $dissertationDirectors = $this->getFlattenedDirectors($this->getDissertationDirector());

        return array_unique(Ccsd_Tools::space_clean(array_merge($internshipSupervisors, $dissertationDirectors)));
    }

    /**
     * Example:
     *  $directors = array(
     *      'test1, test2, test3',
     *      'test4',
     *      array(
     *          'test5',
     *          array(
     *              'test6, test7',
     *              'test8'
     *          )
     *      ),
     *      'test9'
     *  );
     *  return array('test1', 'test2', 'test3', 'test4', 'test5', 'test6', 'test7', 'test8', 'test9');
     *
     * @param string|array  $directors
     * @param array         $return
     *
     * @return array
     */
    private function getFlattenedDirectors($directors, array $return = array())
    {
        if (isset($directors) && !empty($directors)) {
            if (is_string($directors)) {
                $explodedDirectors = $this->tryToExplodeString($directors);
                if (isset($explodedDirectors) && !empty($explodedDirectors)) {
                    if (is_string($explodedDirectors)) {
                        $return[] = $explodedDirectors;
                    }

                    if (is_array($explodedDirectors)) {
                        $return = $this->getFlattenedDirectors($explodedDirectors, $return);
                    }
                }
            }

            if (is_array($directors)) {
                foreach ($directors as $director) {
                    if (isset($director) && !empty($director)) {
                        if (is_string($director)) {
                            $explodedDirectors = $this->tryToExplodeString($director);
                            if (isset($explodedDirectors) && !empty($explodedDirectors)) {
                                if (is_string($explodedDirectors)) {
                                    $return[] = $explodedDirectors;
                                }

                                if (is_array($explodedDirectors)) {
                                    $return = $this->getFlattenedDirectors($explodedDirectors, $return);
                                }
                            }
                        }

                        if (is_array($director)) {
                            $return = $this->getFlattenedDirectors($director, $return);
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return string
     */
    private function getDissertationDefenseDate()
    {
        $date = '';

        $date .= $this->getDefenseDate();

        if (empty($date)) {
            $date .= $this->getDate();
        }

        return $date;
    }

    /**
     * @return array
     */
    private function getDissertationAuthorityInstitution()
    {
        $authorityInstitutions = array();

        $recordOrganizationsDegree = $this->getRecordOrganizationsDegree();
        foreach ($recordOrganizationsDegree as $recordOrganizationDegree) {
            $organizationDegree = '';

            $firstPart = $this->getOrganizationDegreeFirstPart($recordOrganizationDegree['section']);
            if (!empty($firstPart)) {
                $organizationDegree .= $firstPart;
            }

            $secondPart = $this->getOrganizationDegreeSecondPart($recordOrganizationDegree['name'], $recordOrganizationDegree['acronym'], $recordOrganizationDegree['city'], $recordOrganizationDegree['country']);
            if (!empty($secondPart)) {
                if (!empty($organizationDegree)) {
                    $organizationDegree .= ' ';
                }

                $organizationDegree .= $secondPart;
            }

            if (!empty($organizationDegree)) {
                $authorityInstitutions[] = $organizationDegree;
            }

            foreach ($recordOrganizationDegree['affiliation_partners'] as $recordOrganizationDegreeAffiliationPartner) {
                $organizationDegreeAffiliationPartner = $this->getOrganizationDegreeThirdPart($recordOrganizationDegreeAffiliationPartner);
                if (!empty($organizationDegreeAffiliationPartner)) {
                    $authorityInstitutions[] = $organizationDegreeAffiliationPartner;
                }
            }
        }

        $recordAffiliationPartners = $this->getRecordAffiliationPartners();
        foreach ($recordAffiliationPartners as $recordAffiliationPartner) {
            $affiliationPartner = $this->getOrganizationDegreeThirdPart($recordAffiliationPartner);
            if (!empty($affiliationPartner)) {
                $authorityInstitutions[] = $affiliationPartner;
            }
        }

        return $authorityInstitutions;
    }

    /**
     * @param string $section
     *
     * @return string
     */
    private function getOrganizationDegreeFirstPart(string $section)
    {
        $firstPart = '';

        if (!empty($section)) {
            $firstPart .= $section.'.';
        }

        return $firstPart;
    }

    /**
     * @param string $name
     * @param string $acronym
     * @param string $city
     * @param string $country
     *
     * @return string
     */
    private function getOrganizationDegreeSecondPart(string $name, string $acronym, string $city, string $country)
    {
        $secondPart = '';

        if (!empty($name)) {
            $secondPart .= $name;
        }

        if (!empty($acronym)) {
            if (!empty($secondPart)) {
                $secondPart .= ' ';
            }

            $secondPart .= '('.$acronym.')';
        }

        if (!empty($city)) {
            if (!empty($secondPart)) {
                $secondPart .= ', ';
            }

            $secondPart .= $city;
        }

        if (!empty($country)) {
            if (!empty($secondPart)) {
                $secondPart .= ', ';
            }

            $secondPart .= $country;
        }

        if (!empty($secondPart)) {
            $secondPart .= '.';
        }

        return $secondPart;
    }

    /**
     * @param array $affiliationPartner
     *
     * @return string
     */
    private function getOrganizationDegreeThirdPart(array $affiliationPartner)
    {
        $thirdPart = '';

        if (
            array_key_exists('name', $affiliationPartner)
            && array_key_exists('acronym', $affiliationPartner)
            && array_key_exists('country', $affiliationPartner)
        ) {
            if (!empty($affiliationPartner['name'])) {
                $thirdPart .= $affiliationPartner['name'];
            }

            if (!empty($affiliationPartner['acronym'])) {
                if (!empty($thirdPart)) {
                    $thirdPart .= ' ';
                }

                $thirdPart .= '('.$affiliationPartner['acronym'].')';
            }

            if (!empty($affiliationPartner['country'])) {
                if (!empty($thirdPart)) {
                    $thirdPart .= ', ';
                }

                $thirdPart .= $affiliationPartner['country'];
            }

            if (!empty($thirdPart)) {
                $thirdPart .= '.';
            }
        }

        return $thirdPart;
    }

    /**
     * @return string
     */
    private function getTranslatedDegree()
    {
        $degree = $this->getDegree();
        if (!empty($degree)) {
            if (array_key_exists($degree, self::DEGREE_TRANSFORMATION)) {
                $degree = self::DEGREE_TRANSFORMATION[$degree];
            }

            if (array_key_exists($degree, self::META_ARRAY_LECTURETYPE)) {
                $key = self::META_ARRAY_LECTURETYPE[$degree];
            }
        }

        if (!isset($key) || empty($key)) {
            $key = '0';
        }

        return $key;
    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:dissertation']", "Ccsd_Externdoc_Inra_Dissertation");
