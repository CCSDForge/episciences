<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 07/03/19
 * Time: 17:01
 */

class Ccsd_Externdoc_Irstea_Article extends Ccsd_Externdoc_Irstea
{

	/**
	 * @var string
	 */
	protected $_type = 'ART';

	protected $_specific_wantedTags = [
		self::META_JOURNAL,
		self::META_VOLUME,
		self::META_TARGETAUDIENCE,
		self::META_ISSN,
		self::META_OTHERTYPE,
		self::META_VULGARISATION,
		self::META_STATUT,
		self::META_PEEREVIEWING,
		self::META_OTHERTYPE
	];


	/**
	 * @param string $id
	 * @param DOMDocument $xmlDom
	 * @return Ccsd_Externdoc_Irstea
	 */
	public static function createFromXML($id, $xmlDom) : Ccsd_Externdoc_Irstea_Article
	{
		$doc = new Ccsd_Externdoc_Irstea_Article($id);
		$domxpath = self::dom2xpath($xmlDom);
		$doc->setDomPath($domxpath);
		return $doc;
	}



	/**
	 * @return string
	 */
	public function getHalTypology()
	{
		return 'ART';
	}


	// Récupération du booleen de vulgarisation == vulgarisation à partir du doc_type
	public function getVulgarisation(){
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		if ($docType == "Article de revue de vulgarisation")
			return "1";
		else
			return "0";
	}

	// Récupération du comite de lecture == peerRewiewing à partir du doc_type
	public function getComiteLecture(){
		$retour = false;
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		switch($docType) {
			case 'Article de revue technique à comité de lecture':
				$retour = "1";
				break;
			case 'Article de revue technique sans comité de lecture':
				$retour = "0";
				break;
			case 'Article de revue scientifique à comité de lecture':
				$retour = "1";
				break;
			case 'Article de revue scientifique sans comité de lecture':
				$retour = "0";
				break;
			case 'Article de revue de vulgarisation':
				$retour = "1";
				break;
			case 'Data paper' :
				$retour = "1";
				break;
		}
		return $retour;
	}

	// Récupération du titre de périodique == journal
	public function getJournal(){

		return parent::getJournal();

		$journal = $this->getValue(parent::XPATH_ROOT_RECORD . parent::REL_XPATH_COLLECTION_TITLE);
		$journal = empty($journal) ?  '' : $journal;
		return $journal;
	}

	public function getInPress(){
		$statut = $this->getValue(parent::XPATH_ROOT_RECORD . parent::REL_XPATH_STATUT);
		$statut = empty($statut) ?  '' : $statut;
		if ($statut == 'sous presse')
			return true;
		else
			return false;
	}

	public function getOtherType(){
		$retour='';
		$docType = $this->getValue(parent::XPATH_ROOT_RECORD . parent::XPATH_ROOT_RECORD_TYPDOC);
		if ($docType == "Data paper")
			$retour = "DR";
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
			$meta = '';
			switch ($metakey) {
				case self::META_JOURNAL:          // meta à utiliser pour les revues level="j"
					$meta = $this->getJournal();
					break;
				case self::META_VOLUME :    // DOC_NUMVOL
					$meta = $this->getVolume();
					break;
				case self::META_TARGETAUDIENCE:
					$meta = $this->getTargetAudience();
					break;
				case self::META_ISSN:
					$meta = $this->getIssn();
					break;
				case self::META_OTHERTYPE:
					$meta = $this->getOtherType();
					break;
				case self::META_VULGARISATION:    //popularLevel
					$meta = $this->getVulgarisation();
					break;
				case self::META_STATUT:
					$meta = $this->getInPress();
					break;
				case self::META_PEEREVIEWING:
					$meta = $this->getComiteLecture();
					break;
				/*case self::META_BOOKTITLE:
					$meta = $this->getSerie();
					break;
				*/
				default:
					break;
			}

			if (!empty($meta))
				$this->_metas[self::META][$metakey] = $meta;

			if (!is_array($meta) && $meta === '0')
				$this->_metas[self::META][$metakey] = $meta;

		}

		return $this->_metas;
	}
}

Ccsd_Externdoc_Irstea::registerType('Article de revue technique à comité de lecture', 'Ccsd_Externdoc_Irstea_Article');
Ccsd_Externdoc_Irstea::registerType('Article de revue technique sans comité de lecture','Ccsd_Externdoc_Irstea_Article');
Ccsd_Externdoc_Irstea::registerType('Article de revue scientifique à comité de lecture','Ccsd_Externdoc_Irstea_Article');
Ccsd_Externdoc_Irstea::registerType('Article de revue scientifique sans comité de lecture','Ccsd_Externdoc_Irstea_Article');
Ccsd_Externdoc_Irstea::registerType('Article de revue de vulgarisation','Ccsd_Externdoc_Irstea_Article');
Ccsd_Externdoc_Irstea::registerType('Data paper','Ccsd_Externdoc_Irstea_Article');  // public visé:scientifique & comité de lecture:oui