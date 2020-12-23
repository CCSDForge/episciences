<?php


class Ccsd_Externdoc_Inra_ActivityReport extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'REPORT';

    protected $_specific_wantedTags = [
        self::META_REPORTNUMBER,
        self::META_PAGE,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ActivityReport
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_ActivityReport($id);
        $doc->setDomPath($domxpath);

        return $doc;
    }

    public function getMetadatas()
    {
        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = parent::getMetadatas();

        foreach ($this->_specific_wantedTags as $metaKey) {
            switch ($metaKey) {
                case self::META_REPORTNUMBER:
                    $meta = $this->getReportNumber();
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
}

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:activityReport']", "Ccsd_Externdoc_Inra_ActivityReport");
