<?php


class Ccsd_Externdoc_Inra_ResearchReportChapter extends Ccsd_Externdoc_Inra_ResearchReport
{






    protected $_specific_wantedTags = [
        self::META_REPORTNUMBER,
        self::META_SUBTITLE
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ResearchReportChapter
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_ResearchreportChapter($id);

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

                case self::META_REPORTNUMBER:
                    $meta = $this->getReportNumber();
                    break;
                case self::META_SUBTITLE:
                    $meta = $this->getReportTitle();
                    break;
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
Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:researchReportChapter']", "Ccsd_Externdoc_Inra_ResearchReportChapter");