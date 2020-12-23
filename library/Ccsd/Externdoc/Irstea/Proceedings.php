<?php


class Ccsd_Externdoc_Irstea_Proceedings extends Ccsd_Externdoc_Irstea
{

	protected $_type = "COMM";

	public function getHalTypology()
	{
		return 'COMM';
	}

	const META_PROCEEDINGS = 'proceedings';  // avec acte ou sans acte

	protected $_specific_wantedTags = [
		self::META_PROCEEDINGS,
		self::META_CONFTITLE,
		self::META_CONFDATESTART,
		self::META_CONFDATEEND,
		self::META_CITY,
		self::META_COUNTRY,
		self::META_CONFINVITE,
		self::META_TARGETAUDIENCE,
		self::META_TITRE_ACTE,
		self::META_JOURNAL,
		self::META_AUDIENCE,
		self::META_PEEREVIEWING,
		self::META_VULGARISATION,
		self::META_ISBN,
		self::META_SERIE,
		self::META_ISSUE
	];

	/**
	 * @param string $id
	 * @param DOMDocument $xmlDom
	 * @return Ccsd_Externdoc_Irstea_Proceedings
	 */
	static public function createFromXML($id, $xmlDom)  : Ccsd_Externdoc_Irstea_Proceedings
	{
		$doc = new Ccsd_Externdoc_Irstea_Proceedings($id);
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
		if ($docType == "Conférence invitée")
			$typeActe = "0";
		if ($docType == "Communication à un congrès")
			$typeActe = "0";
		return $typeActe;
	}

	// portée du colloque / audience
	public function getAudience(){
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::REL_XPATH_CEM_COLNAT,null,false);
		$retour = 1;
		if (!empty($docType)){
			$docType = strtolower($docType);
			switch ($docType) {
				case "colloque international":
					$retour = 2;
					break;
				case "colloque national":
					$retour = 3;
					break;
			}
		}
		return $retour;
	}

	public function getConfInvite(){
		$invite = "0";
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		if ($docType == "Conférence invitée")
			$invite = true;

		return $invite;
	}

	public function getTitreActe(){
		$acte = $this->getActe();
		$doc_orig_titre = '';
		if ($acte == "1") {
			$doc_orig_titre = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_DOC_ORIG_TITRE,null,false);
			$doc_orig_titre = empty($doc_orig_titre) ? '' : $doc_orig_titre;
		}
		return $doc_orig_titre;
	}

	// Récupération du comite de lecture == peerRewiewing à partir du doc_type
	public function getComiteLecture(){
		$retour = false;
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		switch($docType) {
			case 'Communication scientifique avec actes':
				$retour = "1";
				break;
			case 'Communication scientifique sans actes':
				$retour = "0";
				break;
			case 'Communication technique avec actes':
				$retour = "1";
				break;
			case 'Communication technique sans actes':
				$retour = "0";
				break;
			case 'Communication à un congrès':
				$retour = "0";
				break;
			case 'Conférence invitée' :
				$retour = "0";
				break;
		}
		return $retour;
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
					$meta = $this->getTargetAudience();   // Récupération du public visé == inra_publicVise_local à partir du doc_type
					break;
				case self::META_PROCEEDINGS:
					$meta = $this->getActe();
					break;
				case self::META_PEEREVIEWING:
					$meta = $this->getComiteLecture();
					break;
				case self::META_VULGARISATION:    //popularLevel  toujours 'non'
					$meta = "0";
					break;
				case self::META_CONFTITLE:
					$meta = $this->getConfTitle();  // a voir
					break;
				case self::META_CONFDATESTART:
					$meta = $this->getConfDateDebut();  // a voir
					break;
				case self::META_CONFDATEEND:
					$meta = $this->getConfDateFin();  // a voir
					break;
				case self::META_CITY:
					$meta = $this->getCity();  // a voir
					break;
				case self::META_COUNTRY:
					$meta = $this->getCountry();  // a voir
					break;
				case self::META_AUDIENCE:
					$meta = $this->getAudience();
					break;
				case self::META_CONFINVITE:
					$meta = $this->getConfInvite();
					break;
				/*case self::META_TITRE_ACTE:      // on envoie doc_orig_titre dans serie
					$meta = $this->getTitreActe();
					break; */
				case self::META_JOURNAL:          // meta à utiliser pour les revues level="j"
					$meta = $this->getJournal();
					break;
				case self::META_ISBN:
					$meta = $this->getIsbn();
					break;
				/*case self::META_SERIE:                  // on envoie doc_orig_titre dans serie ou bookTitle => KO
					$meta = $this->getTitreActe();
					//echo "#####".chr(10); var_dump($meta);
					break;
				*/
				case self::META_ISSUE :  // DOC_PER_NUMERO
					$meta = $this->getIssue();
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

		// Ajout de la langue
		$this->_metas[self::META_LANG] = $this->langue_to_iso($this->getDocLang());

		if (!empty($this->getDOI())) $this->_metas[self::META_IDENTIFIER]["doi"] = $this->getDOI();
		if (!empty($this->getIdentifier())) $this->_metas[self::META_IDENTIFIER]["irstea"] = $this->getIdentifier();

		$this->_metas[self::AUTHORS] = $this->getAuthors();
		// $this->_metas[self::AUTHORS] = array_merge($this->_metas[self::AUTHORS] ,$this->getExtAuthors());
		if (!empty($this->getDocumentLocation())) $this->_metas[self::META_DOCUMENTLOCATION] = $this->getDocumentLocation();

		$this->_metas[self::DOC_TYPE] = $this->_type;

		return $this->_metas;
	}

}
Ccsd_Externdoc_Irstea::registerType('Communication scientifique avec actes', 'Ccsd_Externdoc_Irstea_Proceedings');
Ccsd_Externdoc_Irstea::registerType('Communication à un congrès', 'Ccsd_Externdoc_Irstea_Proceedings');
Ccsd_Externdoc_Irstea::registerType('Communication scientifique sans actes', 'Ccsd_Externdoc_Irstea_Proceedings');
Ccsd_Externdoc_Irstea::registerType('Communication technique avec actes', 'Ccsd_Externdoc_Irstea_Proceedings');
Ccsd_Externdoc_Irstea::registerType('Communication technique sans actes', 'Ccsd_Externdoc_Irstea_Proceedings');
Ccsd_Externdoc_Irstea::registerType('Conférence invitée','Ccsd_Externdoc_Irstea_Proceedings');