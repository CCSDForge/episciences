<?php


class Ccsd_Externdoc_Inra_BookTranslation extends Ccsd_Externdoc_Inra
{




    /**
     * @var string
     */
    protected $_type = "OUV";





    protected $_specific_wantedTags = [
        self::META_SERIE,
        self::META_BOOK_DIRECTOR,
        self::META_PUBLISHED,
        self::META_PUBLISHER_NAME,
        self::META_PUBLISHER_CITY,
        self::META_PUBLISHER_COUNTRY,
        self::META_BOOKTYPE,
        self::META_LINK,
        self::META_INRA_ISSN
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_BookTranslation
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_BookTranslation($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    /**
     * @return string
     */
    public function getNbPage(): string
    {
        $nbPage = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PAGES);
        $nbPage = empty($nbPage) ? '' : $nbPage;
        return $nbPage;

    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookType(){
        return 'TR';
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
                case self::META_SERIE :
                    $meta = $this->getSerie();
                    break;
                case self::META_BOOKTYPE :
                    $meta = $this->getBookType();
                    break;
                case self::META_BOOK_DIRECTOR :
                    $meta = $this->getRecordBookAuthor();
                    break;
                case self::META_PUBLISHED:
                    $meta = $this->getPublished();
                    break;
                case self::META_PUBLISHER_NAME:
                    $meta = $this->getAllPublisherInfo();
                    break;
                case self::META_DOCUMENTLOCATION:
                    $meta = $this->getDocumentLocation();
                    break;
                case self::META_PUBLICATION_LOCATION:
                    $meta = $this->getPubPlace();
                    break;
                case self::META_LINK:
                    $meta= $this->getRecordLink();
                    break;
                case self::META_INRA_ISSN:
                    $meta= $this->getIssn();
                    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }

        // Gestion spécifique de la méta page
        if (!empty($this->getNbPage())){
            $this->_metas[self::META_PAGE]= $this->getNbPage();
        }
        else if (isset($this->_metas[self::META_PAGE])) {
            unset($this->_metas[self::META_PAGE]);
        }

        return $this->_metas;

    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:bookTranslation']", "Ccsd_Externdoc_Inra_BookTranslation");