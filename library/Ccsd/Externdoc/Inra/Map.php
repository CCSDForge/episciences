<?php


class Ccsd_Externdoc_Inra_Map extends Ccsd_Externdoc_Inra
{
    /**
     * @var string
     */
    protected $_type = 'MAP';

    protected $_specific_wantedTags = [
        self::META_SCALE,
        self::META_DESCRIPTION,
        self::META_SUPPORT_INRA,
    ];

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_Map
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);

        $doc = new Ccsd_Externdoc_Inra_Map($id);
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
                case self::META_SCALE:
                    $meta = $this->getScale();
                    break;
                case self::META_DESCRIPTION:
                    $meta = $this->getDescription();
                    break;
                case self::META_SUPPORT_INRA:
                    $meta = $this->getFormattedCollection();
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

Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:map']", "Ccsd_Externdoc_Inra_Map");
