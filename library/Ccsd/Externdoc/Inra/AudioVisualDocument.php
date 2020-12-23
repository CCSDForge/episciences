<?php

use SameerShelavale\PhpCountriesArray\CountriesArray;

class Ccsd_Externdoc_Inra_AudioVisualDocument extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'VIDEO';

    protected $_specific_wantedTags = [
        self::META_DURATION,
        self::META_SUPPORT_INRA,
        self::META_DIFFUSEUR_INRA,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_AudioVisualDocument
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_AudioVisualDocument($id);
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
                case self::META_DURATION:
                    $meta = $this->getDuration();
                    break;
                case self::META_SUPPORT_INRA:
                    $meta = $this->getMedia();
                    break;
                case self::META_DIFFUSEUR_INRA:
                    $meta = $this->getAllPublisherInfo();
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
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPublisher()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_NAME, null, ' ');
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCity()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_CITY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCountry()
    {
        $pubPlaceCountry = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_COUNTRY);
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

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:audioVisualDocument']", "Ccsd_Externdoc_Inra_AudioVisualDocument");
