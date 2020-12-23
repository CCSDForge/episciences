<?php


class Ccsd_Externdoc_Irstea_ArticleTranslation extends Ccsd_Externdoc_Irstea
{


    /**
     * @var string
     */
    protected $_type = "OTHER";


    const XPATH_ROOT_RECORD = "//produit/record[@xsi:type='ns2:articleTranslation']";

    protected $_specific_wantedTags = [
        self::META_ARTICLETYPE,
        self::META_COLLECTION_SHORTTITLE,
        self::META_VULGARISATION,
        self::META_NOSPECIAL,
        self::META_TITRESPECIAL,
        self::META_PEERREVIEWED,
        self::META_PAGE
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Irstea_ArticleTranslation
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Irstea_ArticleTranslation($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    /**
     * traitement spécifique :  les traductions dans Hal doivent être rangés dans Autres publications >> Traduction
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getTypeArticle(){
        $typeArticle = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_TYPE);
        $typeArticle = empty($typeArticle) ? '' : $typeArticle;
        return $typeArticle;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getIdentifier()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ID);
    }


    /**
     * @return array
     */
    public function getMetadatas()
    {
        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_TITLE :
                    $meta = $this->getTitle();
                    break;
                case self::META_ALTTITLE :
                    $meta = $this->getAltTitle();
                    break;
                case self::META_SUBTITLE :
                    $meta = $this->getSubtitle();
                    break;
                case self::META_IDENTIFIER :
                    $meta = [];
                    break;
                case self::META_CONFISBN :
                    $meta = $this->getIsbn();
                    break;
                case self::META_DATE :
                    $meta = $this->getDate();
                    break;
                case self::META_SERIE :
                    $meta = $this->getSerie();
                    break;
                case self::META_VOLUME :
                    $meta = $this->getVolume();
                    break;
                case self::META_ISSUE :
                    $meta = $this->getIssue();
                    break;
                case self::META_PAGE :
                    $meta = $this->getPage();
                    break;
                case self::META_PUBLISHER :
                    $meta = $this->getPublisher();
                    break;
                case self::META_PUBLICATION :
                    $meta = $this->getPubPlace();
                    break;
                case self::META_SERIESEDITOR :
                    $meta = $this->getEditor();
                    break;
                case self::META_ABSTRACT :
                    $meta = $this->getAbstract();
                    break;
                case self::META_KEYWORD :
                    $meta = $this->getKeywords();
                    break;
                Case self::META_DOMAIN :
                    $meta = $this->getHalDomain();
                    break;
                case self::META_SENDING:
                    $meta = $this->getHalSending();
                    break;
                case self::META_EXPERIMENTALUNIT:
                    $meta = $this->getExperimentalUnit();
                    break;
                case self::META_ISSN:
                    $meta = $this->getIssn();
                    break;
                case self::META_SOURCE:
                    $meta = $this->getSource();
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }

        foreach ($this->_specific_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_ARTICLETYPE :
                    $meta = $this->getTypeArticle();
                    break;
                case self::META_COLLECTION_SHORTTITLE:
                    $meta = $this->getCollectionShortTitle();
                    break;
                case self::META_VULGARISATION:
                    $meta = $this->getVulgarisation();
                    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }




        // Récupération de la langue du premier titre
        $titleLang = isset($this->_metas[self::META_TITLE]) ? array_keys($this->_metas[self::META_TITLE])[0] : '';

        // Ajout de la langue
        $this->_metas[self::META_LANG] = $this->formateLang($this->getDocLang(), $titleLang);



        if (!empty($this->getDOI())) $this->_metas[self::META_IDENTIFIER]["doi"] = $this->getDOI();
        if (!empty($this->getIdentifier())) $this->_metas[self::META_IDENTIFIER]["prodinra"] = $this->getIdentifier();
        if (!empty($this->getUtKey())) $this->_metas[self::META_IDENTIFIER]["utKey"] = $this->getUtKey();
        $this->_metas[self::AUTHORS] = $this->getAuthors();
        $this->_metas[self::AUTHORS] = array_merge($this->_metas[self::AUTHORS] ,$this->getExtAuthors());
        if (!empty($this->getDocumentLocation())) $this->_metas[self::META_DOCUMENTLOCATION] = $this->getDocumentLocation();

        $this->_metas[self::DOC_TYPE] = $this->_type;

        return $this->_metas;
    }

}


Ccsd_Externdoc_Irstea::registerType("/produit/record[@xsi:type='ns2:articleTranslation']", "Ccsd_Externdoc_Irstea_ArticleTranslation");