<?php


class Ccsd_Externdoc_Inra_ChapterTranslation extends Ccsd_Externdoc_Inra
{

    /**
     * @var string
     */
    protected $_type = "OTHER";



    const META_BOOKLINK = 'bookLink';
    const META_SPECIALTITLE = 'inra_titreSpecial_local';

    protected $_specific_wantedTags = [
        self::META_SERIE,
        self::META_TITLE,
        self::META_BOOKAUTHOR,
        self::META_BOOK_DIRECTOR,
        self::META_LINK,
        self::META_SPECIALTITLE,
        self::META_NBPAGES_INRA,
        self::META_OTHERTYPE,
        self::META_COORDINATOR,
        self::META_INRA_ISSN
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ChapterTranslation
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_ChapterTranslation($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }


    /**
     * @return string
     */
    public function getBookType()
    {
        return 'TR';
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
                case self::META_BOOKAUTHOR :
                    $meta = $this->getBookAuthor();
                    break;
                case self::META_BOOK_DIRECTOR :
                    $meta = $this->getBookDirector();
                    break;
                case self::META_LINK :
                    $meta = $this->getBookLink();
                    break;
                case self::META_SPECIALTITLE :
                    $meta = $this->getTitreSpecial();
                    break;
                case self::META_NBPAGES_INRA :
                    $meta = $this->getRecordPagination();
                    break;
                case self::META_OTHERTYPE :
                    $meta = $this->getBookType();
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

        if (!empty($this->getRecordPagination())){
            $this->_metas[self::META_PAGE]= $this->getRecordPagination();
        }
        else if (isset($this->_metas[self::META_PAGE])) {
            unset($this->_metas[self::META_PAGE]);
        }

        if (!empty($this->getNbPage())){
            if (isset($this->_metas[self::META][self::META_PAGE]))  $this->_metas[self::META_PAGE].=' (Nbre de page de l\'ouvrage :'.$this->getNbPage().' ) ';
            else $this->_metas[self::META][self::META_PAGE] = $this->getNbPage();
        }


        return $this->_metas;


    }
}


Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:chapterTranslation']", "Ccsd_Externdoc_Inra_ChapterTranslation");