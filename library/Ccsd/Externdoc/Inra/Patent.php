<?php

use SameerShelavale\PhpCountriesArray\CountriesArray;

class Ccsd_Externdoc_Inra_Patent extends Ccsd_Externdoc_Inra
{


    /**
     * @var string
     */
    protected $_type = "PATENT";



    protected $_specific_wantedTags = [
        self::META_PUBLISHER,
        self::META_DEPOSIT_INRA,
        self::META_DATESUBMISSION,
        self::META_PATENTNUMBER,
        self::META_PATENTCLASSIFICATION,
        self::META_PAGE,
        self::META_VALIDITY_INRA,
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Patent
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_Patent($id);

        $domxpath = self::dom2xpath($xmlDom);

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




        foreach ($this->_specific_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_PUBLISHER:
                    $meta = $this->getAllPublisherInfo();
                    break;
                case self::META_DEPOSIT_INRA:
                    $meta = $this->getAssignee();
                    break;
                case self::META_DATESUBMISSION:
                    $meta = $this->getSubmissionDate();
                    if (empty($meta)) {
                        $meta = $this->getDate();
                    }
                    break;
                case self::META_PATENTNUMBER:
                    $meta = $this->getPatentNumber();
                    break;
                case self::META_PATENTCLASSIFICATION:
                    $meta = $this->getClassification();
                    break;
                case self::META_PAGE:
                    $meta = $this->getNbPage();
                    break;
                case self::META_VALIDITY_INRA:
                    $meta = $this->getPatentLandscape();
                    break;
                default:
                    break;
            }

            $this->addMeta($metakey, $meta);
        }


        return $this->_metas;


    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPublisher()
    {
        $publisher = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_NAME);
        $publisher = empty($publisher) ? '' : $publisher;
        if (is_array($publisher)) {
            $publisher = implode(' ', $publisher);
        }

        return $publisher;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCity()
    {
        $pubPlaceCity = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_CITY);

        return empty($pubPlaceCity) ? '' : $pubPlaceCity;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCountry()
    {
        $pubPlaceCountry = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_COUNTRY);
        $pubPlaceCountry = empty($pubPlaceCountry)? '' : $pubPlaceCountry;

        if (!empty($pubPlaceCountry)) {
            if ($pubPlaceCountry === 'The Netherlands') {
                $pubPlaceCountry = 'NLD';
            }
            if ($pubPlaceCountry === 'France' || $pubPlaceCountry === 'FR') {
                $pubPlaceCountry = 'FRA';
            }
            if ($pubPlaceCountry === 'UK') {
                $pubPlaceCountry = 'GBR';
            }
            if ($pubPlaceCountry === 'HR') {
                $pubPlaceCountry = 'HRV';
            }

            $arrayCountries = CountriesArray::get('alpha3','alpha2');
            foreach ($arrayCountries as $key=>$country) {
                if (is_array($pubPlaceCountry)) {
                    foreach ($pubPlaceCountry as $keypub=>$pub) {
                        if ($pub === 'The Netherlands') {
                            $pub = 'NLD';
                        }
                        if ($pub === 'UK') {
                            $pub = 'GBR';
                        }
                        if ($pub === 'HR') {
                            $pub = 'HRV';
                        }
                        if ($pub === 'France' || $pub === 'FR') {
                            $pub = 'FRA';
                        }
                        if ($pub === $key) {
                            $pubPlaceCountry[$keypub]= strtolower($country);
                        }
                    }
                } else if ($pubPlaceCountry === $key) {
                    $pubPlaceCountry = strtolower($country);
                    break;
                }
            }
        }

        if ($pubPlaceCountry === 'INT') {
            $pubPlaceCountry = '';
        }

        return empty($pubPlaceCountry) ? '' : $pubPlaceCountry;
    }
}


Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:patent']", "Ccsd_Externdoc_Inra_Patent");
