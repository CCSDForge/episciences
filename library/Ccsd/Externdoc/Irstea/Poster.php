<?php

class Ccsd_Externdoc_Irstea_Poster extends Ccsd_Externdoc_Irstea
{
	protected $_type = "POSTER";

	public function getHalTypology()
	{
		return 'POSTER';
	}

	const META_PROCEEDINGS = 'proceedings';  // sans acte

	protected $_specific_wantedTags = [
		self::META_PROCEEDINGS,
		self::META_CONFTITLE,
		self::META_CONFDATESTART,
		self::META_CONFDATEEND,
		self::META_CITY,
		self::META_COUNTRY,
		self::META_CONFINVITE,
		self::META_TARGETAUDIENCE,
		self::META_JOURNAL,
		self::META_SERIE,
		self::META_PEEREVIEWING
	];

	/**
	 * @param string $id
	 * @param DOMDocument $xmlDom
	 * @return Ccsd_Externdoc_Irstea_Poster
	 */
	static public function createFromXML($id, $xmlDom)  : Ccsd_Externdoc_Irstea_Poster
	{
		$doc = new Ccsd_Externdoc_Irstea_Poster($id);
		$domxpath = self::dom2xpath($xmlDom);
		$doc->setDomPath($domxpath);
		return $doc;
	}


	// Récupération du type de communication : avec ou sans acte   META_PROCEEDINGS
	public function getActe(){
		$typeActe = '';
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		if (strpos($docType, "avec") !== FALSE)
			$typeActe = "1";
		if (strpos($docType, "sans") !== FALSE)
			$typeActe = "0";
		return $typeActe;
	}

	// portée du colloque / audience
	public function getAudience(){
		$docType = strtolower($this->getValue(parent::XPATH_ROOT_RECORD . parent::REL_XPATH_CEM_COLNAT,null,false));
		switch ($docType) {
			case "colloque international":
				$retour = "international";
				break;
			case "colloque national":
				$retour = "national";
				break;
			default:
				$retour = "non spécifiée";
				break;
		}
		return $retour;
	}


	public function getTitreActe(){
		$doc_orig_titre = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_DOC_ORIG_TITRE);
		$doc_orig_titre = empty($doc_orig_titre) ? '' : $doc_orig_titre;
		return $doc_orig_titre;
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
				case self::META_TARGETAUDIENCE:
					$meta = "AU";   // Récupération du public visé == inra_publicVise_local à partir du doc_type  inra_publicVise_SC
					break;
				case self::META_PROCEEDINGS:
					$meta = "0";
					break;
				case self::META_VULGARISATION:    // toujours non
					$meta = "0";
					break;
				case self::META_PEEREVIEWING:   // comite de lecture
					$meta = "0";
					break;
				case self::META_CONFTITLE:
					$meta = $this->getConfTitle();
					break;
				case self::META_CONFDATESTART:
					$meta = $this->getConfDateDebut();
					break;
				case self::META_CONFDATEEND:
					$meta = $this->getConfDateFin();
					break;
				case self::META_CITY:
					$meta = $this->getCity();
					break;
				case self::META_COUNTRY:
					$meta = $this->getCountry();
					break;
				case self::META_AUDIENCE:
					$meta = $this->getAudience();
					break;
				/*case self::META_CONFINVITE:
					$meta = $this->getConfInvite();
					break;*/
				case self::META_JOURNAL:
					$meta = $this->getJournal();
					break;
				case self::META_SERIE:
					$meta = $this->getTitreActe();
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

		return $this->_metas;
	}

}
Ccsd_Externdoc_Irstea::registerType('Poster','Ccsd_Externdoc_Irstea_Poster');