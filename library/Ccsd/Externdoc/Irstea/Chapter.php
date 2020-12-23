<?php


class Ccsd_Externdoc_Irstea_Chapter extends Ccsd_Externdoc_Irstea
{


    protected $_type = "CHAPTER";

	public function getHalTypology()
    {
        return 'CHAPTER';
    }


    protected $_specific_wantedTags = [
		self::META_ARTICLETYPE
    ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Irstea_Chapter
     */
    static public function createFromXML($id, $xmlDom)  : Ccsd_Externdoc_Irstea_Chapter
    {
        $doc = new Ccsd_Externdoc_Irstea_Chapter($id);

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

        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];
		
		$this->_metas = parent::getMetadatas();

        foreach ($this->_specific_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_TARGETAUDIENCE:
                    $meta = $this->getTargetAudience();
                    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
			if (!is_array($meta) && $meta === '0'){
				$this->_metas[self::META][$metakey] = $meta;
			}
        }




        // Récupération de la langue du premier titre
        $titleLang = isset($this->_metas[self::META_TITLE]) ? array_keys($this->_metas[self::META_TITLE])[0] : '';

        // Ajout de la langue
        $this->_metas[self::META_LANG] = $this->formateLang($this->getDocLang(), $titleLang);


        if (!empty($this->getDOI())) $this->_metas[self::META_IDENTIFIER]["doi"] = $this->getDOI();
        if (!empty($this->getIdentifier())) $this->_metas[self::META_IDENTIFIER]["irstea"] = $this->getIdentifier();
        
        $this->_metas[self::AUTHORS] = $this->getAuthors();
       // $this->_metas[self::AUTHORS] = array_merge($this->_metas[self::AUTHORS] ,$this->getExtAuthors());
        if (!empty($this->getDocumentLocation())) $this->_metas[self::META_DOCUMENTLOCATION] = $this->getDocumentLocation();

        $this->_metas[self::DOC_TYPE] = $this->_type;

        return $this->_metas;
    }


}


Ccsd_Externdoc_Irstea::registerType("Chapitre d'ouvrage scientifique", "Ccsd_Externdoc_Irstea_Chapter");
Ccsd_Externdoc_Irstea::registerType("Chapitre d'ouvrage technique", "Ccsd_Externdoc_Irstea_Chapter");