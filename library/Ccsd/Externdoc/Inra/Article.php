<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 07/03/19
 * Time: 17:01
 */

class Ccsd_Externdoc_Inra_Article extends Ccsd_Externdoc_Inra
{

    /**
     * @var string
     */
    protected $_type = "ART";



    protected $_specific_wantedTags = [
        self::META_ARTICLETYPE,
        self::META_TITRESPECIAL,
        self::META_JOURNAL,
        self::META_NOSPECIAL,
        self::META_COLLECTION_SHORTTITLE,
        self::META_VULGARISATION,
        self::META_PEERREVIEWED
    ];


    const META_ARRAY_ARTICLETYPE =
        [   'Research article'=>'AR',
            'Review article'=>'RL',
            'Short article'=>'AC',
            'Data paper'=>'DR',
            'Letter'=>'LE',
            'Correspondence'=>'CO',
            'Opinion paper'=>'PO',
            'Methods article'=>'AM'
        ];




    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Article
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_Article($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getTypeArticle(){
        $typeArticle = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_TYPE);
        $typeArticle = empty($typeArticle) ? '' : $typeArticle;

        if (!empty($typeArticle) && isset(self::META_ARRAY_ARTICLETYPE[$typeArticle])){
            $typeArticle = self::META_ARRAY_ARTICLETYPE[$typeArticle];
        }
        else {
            $typeArticle = '0';
        }

        return $typeArticle;
    }


    /**
     * @return string
     */

    public function getLink()
    {
        return $this->getPublisherLink();
    }


    public function getPublisherLink(){
        $journalLink = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_JOURNALLINK);
        $journalLink = empty($journalLink) ? '' : $journalLink;
        return $journalLink;
    }


    // TODO

    /**
     * @return string
     */
    public function getHalTypology()
    {
        return "ART";
    }

    public function getOtherType()
    {
        return $this->getTypeArticle();
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
                case self::META_JOURNAL :
                    $meta = $this->getJournal();
                    break;
                case self::META_ARTICLETYPE :
                    $meta = $this->getTypeArticle();
                    break;
                case self::META_COLLECTION_SHORTTITLE:
                    $meta = $this->getCollectionShortTitle();
                    break;
                case self::META_VULGARISATION:
                    $meta = $this->getVulgarisation();
                    break;
                case self::META_NOSPECIAL:
                    $meta = $this->getTypeSpecial();
                    break;
                case self::META_TITRESPECIAL:
                    $meta = $this->getTitreSpecial();
                    break;
                case self::META_PEERREVIEWED:
                    $meta = $this->getArticlePeerReviewed();
                    break;
                default:
                    break;
            }

            if (!is_array($meta) && $meta === '0'){
                $this->_metas[self::META][$metakey] = $meta;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }


        return $this->_metas;


    }
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:article']", "Ccsd_Externdoc_Inra_Article");