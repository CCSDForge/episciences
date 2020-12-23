<?php


class Ccsd_Externdoc_Inra_ArticleTranslation extends Ccsd_Externdoc_Inra
{


    /**
     * @var string
     */
    protected $_type = "OTHER";



    const META_ARRAY_ARTICLETYPE =
        [   'Research article',
            'Review article',
            'Short article',
            'Data paper',
            'Letter',
            'Correspondence',
            'Opinion paper',
            'Methods article'
        ];

    protected $_specific_wantedTags = [
        self::META_JOURNAL,
        self::META_TITRESPECIAL,
        self::META_NOSPECIAL,
        self::META_OTHERTYPE,
        self::META_LINK
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ArticleTranslation
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_ArticleTranslation($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    /**
     * @return string
     */


    public function getOtherType()
    {
        return 'TR';
    }

    /**
     * @return string
     */
    public function getLink(){
        $link = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LINK);
        $link = empty($link) ? '' : $link;
        return $link;
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

            $meta = '';

            switch ($metakey) {
                case self::META_JOURNAL :
                    $meta = $this->getJournal();
                    break;
                case self::META_OTHERTYPE :
                    $meta = $this->getOtherType();
                    break;
                case self::META_LINK :
                    $meta = $this->getLink();
                    break;
                case self::META_NOSPECIAL:
                    $meta = $this->getTypeSpecial();
                    break;
                case self::META_TITRESPECIAL :
                    $meta = $this->getTitreSpecial();
                    break;
                //case self::META_ORIGINALAUTHOR :
                //    $meta = $this->getArticleAuthor();
                //    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }


        return $this->_metas;


    }
}


Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:articleTranslation']", "Ccsd_Externdoc_Inra_ArticleTranslation");