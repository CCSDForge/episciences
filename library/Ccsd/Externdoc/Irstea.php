<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 07/03/19
 * Time: 16:59
 */

use LanguageDetection\Language;

class Ccsd_Externdoc_Irstea extends Ccsd_Externdoc
{

	/**
	 * @var string
	 */
	protected $_idtype = 'doi';
	protected $_dbHalV3;


	const DOC_TYPE = 'typdoc';

	/**
	 * Clé : Le XPATH qui permet de repérer la classe => Valeur : La classe à créer
	 * @var array
	 */
	static public $_existing_types = [];

	protected $_xmlNamespace = array('CADIC'=>'http://cadic.eu',
		'DOC'=>'http://cadic.eu',
		'EXP'=>'http://cadic.eu',
		'dc' =>'http://purl.org/dc/emlements/1.1/',
		'rdf' =>'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'xsi' =>'http://www.w3.org/2001/XMLSchema-instance'
	);


	public static $NAMESPACE =  array('CADIC'=>'http://cadic.eu',
		'DOC'=>'http://cadic.eu',
		'EXP'=>'http://cadic.eu',
		'dc' =>'http://purl.org/dc/emlements/1.1/',
		'rdf' =>'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'xsi' =>'http://www.w3.org/2001/XMLSchema-instance'
	);
	/**
	 * @var DOMXPath
	 */
	protected $_domXPath = null;

	public function __construct($id)
	{
		parent::__construct($id);
		$this->_dbHalV3 = Zend_Db_Table_Abstract::getDefaultAdapter();
	}

	// Metadata Génériques pour tous les documents INRA
	const META_STATUT = 'inPress';
	const META_ARTICLETYPE = 'articleType';  // todo
	const META_SUBTITLE = 'subTitle';
	const META_COMMENT = 'inra_inraComment_local';
	const META_COMMENTAIRE_INTERNE = 'comment';
	const META_ALTTITLE = 'alternateTitle';
	const META_SENDING = 'hal_sending';
	const META_CLASSIFICATION = 'hal_classification';
	const META_DOCUMENTLOCATION = 'inra_lieu_local';
	const META_INDEXATION = 'inra_indexation_local';
	const META_ISSN = 'issn';
	const META_ISBN = 'isbn';
	const META_JOURNAL = 'journal';
	const META_SOURCE = 'source';
	const META_NOSPECIAL = 'inra_noSpecial';
	const META_TITRESPECIAL = 'inra_titreSpecial';
	const META_TARGETAUDIENCE = 'inra_publicVise_local';
	const META_VULGARISATION = 'popularLevel';
	const EXTAUTHORS = 'extAuthors';
	const META_EUROPEANPROJECT = 'europeanProject';
	const META_FUNDING = 'funding';
	const META_DIRECTOR = 'director';
	const META_PEEREVIEWING = 'peerReviewing';
	const META_DOI = 'doi';
	const META_PRODINRA = 'irstea';
	const META_WOS = 'wos';
	const META_PAGE = 'page';
	const META_PUBLISHER = 'publisher';
	const META_SERIE = 'serie';
	const META_VOIR_AUSSI = 'seeAlso';   // DOC_URL
	const META_CITY = 'city';   // ou settlement
	const META_COUNTRY = 'country';
	const META_BOOKTITLE = 'bookTitle';
	const META_CONFDATESTART = 'conferenceStartDate';
	const META_CONFDATEEND = 'conferenceEndDate';
	const META_COLLECTION_SHORTTITLE = 'meetingTitle';  // todo
	const META_HAL_REF = 'halRef';  // pour les notices déjà dans hal, on envoie l'identifiant HAL
	const META_HAL_DOMAIN_CODE = 'domain';
	const META_HAL_DOMAIN_LIB = 'halDomain';    // à 90% "Sciences de l'environnement" et parfois des champs multivalués, Si HAL_DOM_LIB est vide on place "Sciences de l'e..."

	const META_CONFLOCATION     = "conferenceLocation";
	const META_CONFISBN         = "conferenceISBN";      // mauvais non
	const META_CONFTITLE        = "conferenceTitle";     // meetingTitle
	const META_AUDIENCE         = "audience";
	const META_CONFINVITE       = "invitedCommunication";
	const META_TITRE_ACTE       = "serie";

	// sous-type
	const META_OTHERTYPE = 'inra_otherType_Art_local';


	// XPATH GENERIQUES

	// Racine de l'article
	const XPATH_ROOT = '/notices/notice';
	const XPATH_ROOT_RECORD = "/notice";
	const XPATH_ROOT_RECORD_TYPDOC = "/DOC:DOC_TYPE";
	const REL_XPATH_RECORD_ID = '/DOC:DOC_REF';
	const REL_XPATH_RECORD_TYPE = '/itemType';

	//Classification Hal
	const REL_XPATH_HAL_DOMAINE_REF = '/DOC:HAL_DOM_REF';
	const REL_XPATH_HAL_DOMAINE_LIB = '/DOC:HAL_DOM_LIB';
	const REL_XPATH_HAL_REF = '/DOC:HAL_REF';

	// Informations de base sur l'article
	const REL_XPATH_RECORD_TITLE = '/DOC:DOC_TITRE';
	const REL_XPATH_RECORD_TITLE_FR = '/DOC:DOC_TITRE2';   // tjs en fr
	const REL_XPATH_RECORD_TITLE_EN = '/DOC:DOC_TITRE3';   // tjs en en
	const REL_XPATH_RECORD_LANGUAGE = '/DOC:DOC_LANGUE';         // A passer en ISO2
	const REL_XPATH_RECORD_ABSTRACT = '/DOC:DOC_AB';             // tjs en fr
	const REL_XPATH_RECORD_ABSTRACT_EN = '/DOC:CEM_AB_ENG';      // tjs en en
	const REL_XPATH_RECORD_YEAR = '/DOC:CEM_DP_NUM';             // date de publication

	/* IDENTIFICATION */
	const REL_XPATH_RECORD_DOI = '/DOC:CEM_DOI';

	// Commentaires
	const REL_XPATH_RECORD_COMMENTAIRE = '/DOC:DOC_COMMENT';   // Notes_IRSTEA
	const REL_XPATH_RECORD_DIVISION = '/DOC:CEM_DIVISION';     // Département_IRSTEA
	const REL_XPATH_RECORD_PROGRAM = '/DOC:CEM_PROGRAM';      // TR1_IRSTEA
	const REL_XPATH_RECORD_EQUIPE = '/DOC:CEM_EQUIPE';       // AXE_IRSTEA
	const REL_XPATH_RECORD_PROGRAM2 = '/DOC:CEM_PROGRAM2';     // TR2_IRSTEA
	const REL_XPATH_RECORD_ADD = '/DOC:CEM_ADD';          // ADD1_IRSTEA
	const REL_XPATH_RECORD_ADD2 = '/DOC:CEM_ADD2';         // ADD2_IRSTEA

	// Source
	const REL_XPATH_RECORD_FIRSTPAGE = '/DOC:CEM_PG_DEB';
	const REL_XPATH_RECORD_LASTPAGE = '/DOC:CEM_PG_FIN';
	const REL_XPATH_RECORD_SOURCE = '/DOC:DOC_SOURCE';

	// Auteurs
	const REL_XPATH_RECORD_AUTEURS = '/DOC:DOC_AUTEUR';
	const REL_XPATH_RECORD_AFFILIATIONS = '/DOC:CEM_AUT_AFF';
	const REL_XPATH_RECORD_AFFILIATIONS_SEC = '/DOC:CEM_AUT_AFFSEC';
	const REL_XPATH_RECORD_AUTEUR_ACS = '/DOC:DOC_AUTEURSEC';
	const REL_XPATH_RECORD_AFFILIATION_ACS = '/DOC:CEM_AUT_COORD';
	//const REL_XPATH_RECORD_AUTEUR_COLLECTIF = '/DOC:DOC_AUTEUR';   Auteur collectif

	// Mots clés
	const REL_XPATH_RECORD_KEYWORDS_DEE = '/DOC:DOC_DEE';
	const REL_XPATH_RECORD_KEYWORDS_DL = '/DOC:DOC_DL';
	const REL_XPATH_RECORD_KEYWORDS_GEO = '/DOC:DOC_GEO';
	const REL_XPATH_RECORD_KEYWORDS_DL_AUT = '/DOC:CEM_DL_AUT';
	const REL_XPATH_RECORD_KEYWORDS_SIGLE = '/DOC:CEM_SIGLE';
	const REL_XPATH_RECORD_KEYWORDS_CITATION = '/DOC:CEM_CITATIO';

	// Information de collection
	const REL_XPATH_COLLECTION_TITLE = '/DOC:DOC_PER_TITRE';
	const REL_XPATH_COLLECTION_ISSN = '/DOC:DOC_ISSN';
	const REL_XPATH_COLLECTION_ISSUE_NUMBER = '/DOC:DOC_PER_NUMERO';
	const REL_XPATH_COLLECTION_ISSUE_VOLUME = '/DOC:DOC_NUMVOL';

	//informations sur l'article dans sa publication
	const REL_XPATH_ARTICLEINFOS_SOURCE = '/DOC:DOC_SOURCE';
	const REL_XPATH_ARTICLEINFOS_PAGEDEBUT = '/DOC:CEM_PG_DEB';
	const REL_XPATH_ARTICLEINFOS_PAGEFIN = '/DOC:CEM_PG_FIN';

	//informations sur la localisation geographique
	const REL_XPATH_RECORD_DOCUMENTLOCATION = '/DOC:DOC_COTE';
	const REL_XPATH_RECORD_CENTRELOCATION = '/DOC:CEM_AUT_CENTRE';

	// Informations sur l'editeur
	const REL_XPATH_DOC_EDITEUR = '/DOC:DOC_EDITEUR';
	const REL_XPATH_PUBLICATION_ISBN = '/DOC:DOC_ISBN';
	const REL_XPATH_PUBLICATION_PAGES = '/DOC:DOC_SOURCE';
	const REL_XPATH_DOC_LIEU_EDIT = '/DOC:DOC_LIEU_EDIT';
	const REL_XPATH_TITRE_VOLUME = '/DOC:DOC_COLLECTION';

	//informations concernant la pièce jointe (le document)
	const REL_XPATH_ATTACHMENT_FILENAME = '/DOC:FT_SFNAME';
	/* migrer PJ si CEM_DIFF_WEB= "1" ou "2"   Si 2 : migrer en document en accès restreint   si cem_diff_web=0 (diffusion interne) le document est migré sur le serveur INRA. Mettre dans le champ Voir aussi de HAL le lien pointant vers ce document : archives-publications.inrae.fr/pub00059681 ( forme à valider)  */
	const REL_XPATH_RESSOURCES_LIEES = '/DOC:DOC_URL';
	const REL_XPATH_DIFFUSION_WEB = '/DOC:CEM_DIFF_WEB';
	const REL_XPATH_ATTACHMENT_VISIBILITY = '/DOC:CEM_INTERNET';
	const REL_XPATH_ATTACHMENT_SECONDARY = '/DOC:DOC_ATTACHE';

	const REL_XPATH_STATUT = '/DOC:CEM_STATUT';

	/* Conférence, communications, congrés */
	const REL_XPATH_CEM_CONF_TITRE = '/DOC:CEM_CONF_TITRE';
	const REL_XPATH_CEM_CONF_DATE = '/DOC:CEM_CONF_DATE';
	const REL_XPATH_CEM_CONF_DATEFIN = '/DOC:CEM_CONF_DATEFIN';
	const REL_XPATH_CEM_CONF_VILLE = '/DOC:CEM_CONF_VILLE';
	const REL_XPATH_CEM_CONF_PAYS = '/DOC:CEM_CONF_PAYS';
	const REL_XPATH_CEM_COLNAT = '/DOC:CEM_COLNAT';
	const REL_XPATH_DOC_ORIG_TITRE = '/DOC:DOC_ORIG_TITRE';



	protected $_wantedTags = array(
		self::META_TITLE,
		self::META_ALTTITLE,
		self::META_SUBTITLE,
		self::META_LANG,
		self::META_DATE,
		self::META_ABSTRACT,
		self::META_KEYWORD,
		self::META_INDEXATION,
		self::META_JOURNAL,
		self::META_MESH,
		self::META_SERIE,
		self::META_VOLUME,
		self::META_IDENTIFIER,
		self::META_COMMENT,
		self::META_COMMENTAIRE_INTERNE,
		self::META_DOMAIN,
		self::META_ISSUE,
		self::META_CITY,
		self::META_COUNTRY,
		self::META_BOOKTITLE,
		self::META_CONFTITLE,
		self::META_CONFDATESTART,
		self::META_CONFDATEEND,
		self::META_BIBLIO,
		self::META_BIBLIO_TITLE,
		self::META_PUBLISHER,
		self::META_CONFLOCATION,
		self::META_CONFISBN,
		self::META_ISBN,
		self::META_ABSTRACT,
		self::META_CLASSIFICATION,
		self::META_SENDING,
		self::META_ISSN,
		self::META_SOURCE,
		self::META_TARGETAUDIENCE,
		self::META_VULGARISATION,
		self::META_EUROPEANPROJECT,
		self::META_FUNDING,
		self::META_DIRECTOR,
		self::META_PAGE,
		self::META_VOIR_AUSSI,
		self::META_HAL_REF,
		self::META_HAL_DOMAIN_CODE,
		self::META_HAL_DOMAIN_LIB,
		self::META_OTHERTYPE
	);

	/* function permettant de choisir le meilleur résultat issu de la détection de langue
		param : array
		return : string
	*/
	public function getBestLang($tabBestResult){
		$max = max($tabBestResult);
		$lang = array_search($max,$tabBestResult,true);
		return $lang;
	}


	/**
	 * @param $xmlDom
	 * @return DOMXPath
	 */
	public static function dom2xpath($xmlDom): DOMXPath
	{
		$domxpath = new DOMXPath($xmlDom);
		foreach (self::$NAMESPACE as $key => $value) {
			$domxpath->registerNamespace($key, $value);
		}
		return $domxpath;
	}


	/**
	 * @param $node
	 * @return DOMXPath
	 */
	public function getDomXPath($node) : DOMXPath
	{

		$dom = new DOMDocument();
		if ($node instanceof DOMNode){
			$dom->appendChild($dom->importNode($node,true));
		}
		else if ($node instanceof DOMElement) {
			$dom->appendChild($node);
		}
		return self::dom2xpath($dom);

	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getIsbn()
	{
		$isbn = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_PUBLICATION_ISBN);
		$isbn = empty($isbn) ? '' : $isbn;
		return $isbn;
	}

	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getIssn()
	{
		$issn = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_COLLECTION_ISSN);
		$issn = empty($issn) ? '' : $issn;
		return $issn;
	}


	public function getJournal()
	{
		$issn = $this->getIssn();
		$fulltitle = $this->getSerie();
		$eissn = $issn;
		//$abbrevtitle = $this->getCollectionShortTitle();
		//return $this->formateJournal($fulltitle, '', $issn, $eissn);
		return $this->formateJournal($fulltitle, '', $issn, $eissn);
	}

	protected function formateJournal($journaltitle, $shortname, $issn, $eissn)
	{
		if ( (!isset($journaltitle) || $journaltitle == '')
			&& (!isset($issn) || $issn == '')
			&& (!isset($eissn) || $eissn == ''))
			return null;
		// Debug cas existant...
		if (is_array($journaltitle)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "journaltitle is an array(first: " .  $journaltitle[0] . ")");
		}
		if (is_array($issn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "issn is an array (first: " .  $issn[0] . ")");
		}
		if (is_array($eissn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "eissn is an array (first: " .  $eissn[0] . ")");
		}

		$param = 'title_t:"' . $journaltitle . '" OR issn_s:"' . $issn . '" OR eissn_s:"' . $eissn . '"';
		$solrResult = Ccsd_Referentiels_Journal::search($param, 1);
		/*echo "####### ".chr(10);var_dump($solrResult);echo chr(10);die();*/
		if (isset($solrResult[0]['docid']))
			return new Ccsd_Referentiels_Journal($solrResult[0]['docid']);
		else
			return new Ccsd_Referentiels_Journal(0, ['VALID' => 'INCOMING', 'JID' => '', 'JNAME' => $journaltitle, 'SHORTNAME' => $shortname, 'ISSN' => $issn, 'EISSN' => $eissn, 'PUBLISHER' => '', 'URL' => '']);
	}

	/** Création de la classe Ccsd_Referentiels_Journal à partir des paramètres spécifiques
	 * @param $journaltitle : string
	 * @param $shortname : string
	 * @param $issn : string
	 * @param $eissn : string
	 * @return  Ccsd_Referentiels_Journal
	 */
	protected function formateJournalOnBdd($journaltitle, $shortname, $issn, $eissn)
	{
		if ( (!isset($journaltitle) || $journaltitle == '')
			//        && (!isset($shortname) || $shortname == '')
			&& (!isset($issn) || $issn == '')
			&& (!isset($eissn) || $eissn == ''))
			return null;
		// Debug cas existant...
		if (is_array($journaltitle)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "journaltitle is an array(first: " .  $journaltitle[0] . ")");
		}
		/**
		if (is_array($shortname)) {
		Ccsd_Tools::panicMsg(__FILE__,__LINE__, "shortname is an array(first: " .  $shortname[0] . ")");
		}
		 **/
		if (is_array($issn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "issn is an array (first: " .  $issn[0] . ")");
		}
		if (is_array($eissn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "eissn is an array (first: " .  $eissn[0] . ")");
		}

		$query = 'select JID from REF_JOURNAL where '."JNAME = '".$journaltitle."' OR ISSN='".$issn."'";
		$dbHal= Zend_Db_Table_Abstract::getDefaultAdapter();
		$result=$dbHal->query($query);
		$result=$result->fetchAll();
		if (count($result)>=1){  // prendre le premier de la liste
			return new Ccsd_Referentiels_Journal($result[0]['JID']);
		}
		else {
			return new Ccsd_Referentiels_Journal(0, ['VALID' => 'INCOMING', 'JID' => '', 'JNAME' => $journaltitle, 'SHORTNAME' => $shortname, 'ISSN' => $issn, 'EISSN' => $eissn, 'PUBLISHER' => '', 'URL' => '']);
		}
	}

	/**
	 * @return string
	 */
	public function getDate()
	{
		$yearconst = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_YEAR);
		$yearconst = empty($yearconst) ? '' : $yearconst;
		return $yearconst;
	}

	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getSerie()  // DOC_PER_TITRE
	{
		$serie = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_COLLECTION_TITLE,null,false);
		$serie = empty($serie) ? '' : $serie;
		return $serie;
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getVolume()
	{
		$volume = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_COLLECTION_ISSUE_VOLUME);
		$volume = empty($volume) ? '' : $volume;
		return $volume;
	}

	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getIssue()
	{
		$issue = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_COLLECTION_ISSUE_NUMBER);
		$issue = empty($issue) ? '' : $issue;
		return $issue;
	}

	/**
	 * @return string
	 */
	public function getPage() : string
	{

		$first = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_FIRSTPAGE);
		$first = empty($first) ? '' : $first;

		$last  = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LASTPAGE);
		$last  = empty($last) ? '' : $last;

		if (empty($last) && empty($first)) {
			$source  = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SOURCE);
			$source  = empty($source) ? '' : $source;
			return $source;
		} else {
			return $first.'-'.$last;
		}


	}

	public function getIndexationVocInrae(){  // pas besoin de gestion de la langue
		$keywords = [];
		$keywords_dee = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_DEE,null,true));
		$keywords_dl = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_DL,null,true));
		return array_merge($keywords_dee,$keywords_dl);
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 *
	 */
	public function getKeywords()
	{
		$langArray=['fr','en','it','de','es'];
		$langDoc = $this->formateLang($this->getDocLang(),$this->getDocLang());
		if (!in_array($langDoc,$langArray,true)) $langArray[] = $langDoc;
		$ld = new Language($langArray);
		$ld->setMaxNgrams(9000);

		$keywords = []; // tableau à retourner
		$keywords_geo = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_GEO,null,true));  // c'est un tableau !
		$keywords_dl_aut = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_DL_AUT,null,true));
		$keywords_sigle = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_SIGLE,null,true));
		$keywords_citation = array_map('mb_strtoupper', $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS_CITATION,null,true));

		$keywords['fr'] = array_merge($keywords_sigle,$keywords_citation,$keywords_geo);
		$keywords['en'] = $keywords_dl_aut;

		/* on met de coté la détection de langue car pas au point, en attendant tout dans 'en'
		$keywords['en'] = $keywords_en;

		// pour appliquer la détection de langue
		foreach ($keywords_geo as $value){
			$lang = $ld->detect($value)->bestResults()->close();
			//echo 'Detection langue ----- ';
			//var_dump($ld->detect($value));
			//echo ' pour la valeur : '.$value.chr(10);
			$lang_detected = key($lang);
			$keywords[$lang_detected] = $value;
			// Atention : le terme FRANCE est détecté en anglais
		}
		foreach ($keywords_dl_aut as $value){
			$lang = $ld->detect($value)->bestResults()->close();
			$lang_detected = key($lang);
			$keywords[$lang_detected] = $value;
		}
		*/

		return $keywords;
	}


	/**
	 * @return string
	 */
	public function getSeriesEditor() : string
	{
		return '';
	}

	/* Pour les conferences, communication, congres  */
	public function getConfTitle() : string
	{
		$titreConf = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_CEM_CONF_TITRE);
		$titreConf = empty($titreConf) ? '' : $titreConf;
		return $titreConf;
	}
	public function getCity() : string
	{
		$city = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_CEM_CONF_VILLE);
		$city = empty($city) ? '' : $city;
		return $city;
	}
	public function getCountry() : string
	{
		$country = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_CEM_CONF_PAYS);
		$country = empty($country) ? '' : $country;
		return $this->country_get_iso3_mapping(strtoupper($country));
	}
	public function getConfDateDebut() : string
	{
		$dateDebut = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_CEM_CONF_DATE);
		$dateDebut = empty($dateDebut) ? '' : $dateDebut;
		return $dateDebut;
	}
	public function getConfDateFin() : string
	{
		$dateFin = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_CEM_CONF_DATEFIN);
		$dateFin = empty($dateFin) ? '' : $dateFin;
		return $dateFin;
	}

	// Editeur et lieu d'édition
	public function getEditeur() : string
	{
		$editeur = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_DOC_EDITEUR);
		$editeur = empty($editeur) ? '' : $editeur;
		return $editeur;
	}
	public function getLieuEdition() : string
	{
		$lieu_edit = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_DOC_LIEU_EDIT);
		$lieu_edit = empty($lieu_edit) ? '' : $lieu_edit;
		return $lieu_edit;
	}


	// Concaténation des cotes du document avec ajout du centre en prefixe : AN ( cote1 ; cote2 )
	public function getDocumentLocation(){
		$retour = '';
		$doc_cote = implode(' ; ',$this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTLOCATION,null,true));  // array return
		$cem_aut_centre = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_CENTRELOCATION,null,false);
		if (!empty($cem_aut_centre)) {
			if (!empty($doc_cote))
				$retour = $cem_aut_centre . ' (' .$doc_cote.')';
		} else {
			if (!empty($doc_cote))
				$retour = '('.$doc_cote.')';
		}
		return $retour;
	}


	// Récupération du public visé (inra_publicVise_local) à partir du doc_type
	/* "inra_publicVise_0" => "",
    "inra_publicVise_SC" => "Scientifique",
    "inra_publicVise_TE" => "Technique",
    "inra_publicVise_PP" => "Pouvoirs publics",
    "inra_publicVise_ET" => "Etudiants",
    "inra_publicVise_GP" => "Grand public",
    "inra_publicVise_AU" => "Autre",
	*/
	public function getTargetAudience(){
		$publicVise = "";
		$docType = $this->getValue(self::XPATH_ROOT_RECORD . self::XPATH_ROOT_RECORD_TYPDOC);
		if (strpos($docType, "scientifique") !== FALSE)
			$publicVise = "SC";
		if (strpos($docType, "technique") !== FALSE)
			$publicVise = "TE";
		if (strpos($docType, "vulgarisation") !== FALSE)
			$publicVise = "Grand public";
		if ($docType=="Data paper")
			$publicVise = "SC";
		if ($docType=="Conférence invitée" || $docType=="Communication à un congrès")
			$publicVise = "AU";
		return $publicVise;
	}

	// récupération du Coordinateur Scientifique CEM_AUT_COORD
	public function getAuteurCoord() : array
	{
		$authors = [];
		$authors['firstname'] = [];
		$authors['orcid'] = [];
		$authors['email'] = [];
		$auteurs = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUTEUR_ACS,null,true);
		$affiliations = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATION_ACS,null,true);
		if (!empty($auteurs))
			$authors['lastname'] = $auteurs;
		if (!empty($affiliations))
			$authors['affiliation'] = $affiliations;

		return $authors;
	}

	/**
	 * @return array
	 */
	public function getAuthors() : array
	{
		$authors = [];
		$authors['firstname'] = [];
		$authors['orcid'] = [];
		$authors['email'] = [];
		$auteurs = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUTEURS,null,true);
		$affiliations = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONS,null,true);
		$affiliations_secondaire = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONS_SEC,null,true);
		if (!empty($auteurs))
			$authors['lastname'] = $auteurs;
		if (!empty($affiliations))
			$authors['affiliation'] = $affiliations;
		if (!empty($affiliations_secondaire))
			$authors['affiliation externe'] = $affiliations_secondaire;

		return $authors;
	}

	/**
	 * @return array
	 */
	public function getExtAuthors() : array
	{
		$authors = [];
		return $authors;
	}


	/**
	 * @return string
	 */
	public function getSource() :string
	{
		return 'Prodinra';
	}

	/**
	 * Création d'un Doc INRA à partir d'un XPATH
	 * L'objet INRA est seulement une factory pour un sous-type réel.
	 * @param string $id
	 * @param DOMDocument $xmlDom
	 * @return Ccsd_Externdoc_Irstea | NULL
	 */
	public static function createFromXML($id,$xmlDom)
	{
		$domxpath = self::dom2xpath($xmlDom);
		$typdocxml = ( $domxpath->query('/notice/DOC:DOC_TYPE')->item(0)->textContent );

		// On recherche le type de document associé au DOI à partir du XPATH de référence
		foreach (self::$_existing_types as $order => $typdoc2class) {
			/**
			 * @var string  $xpath
			 * @var Ccsd_Externdoc $type
			 */
			foreach ($typdoc2class as $typdoc => $type) {
				//->item(0)->textContent

				if ($typdocxml==$typdoc) {
					return $type::createFromXML($id,$xmlDom);
				}
			}
		}

		return null;

	}


	/**
	 * On recrée les auteurs à partir des tableaux de Noms Complet / Prénoms / Noms
	 * @param $fullNames
	 * @param $firstNames
	 * @param $lastNames
	 * @param $orcids
	 * @param $emails
	 * @return array
	 * @deprecated fonction non utilisee afin de garder la structure déjà présente dans les documents.
	 */
	protected function formateAuthors($fullNames, $firstNames, $lastNames, $affiliations = [], $orcids = [],$emails = [])
	{
		$finalAuthors = [];

		// Boucle sur chaque 'auteur'
		foreach ($fullNames as $i => $fullName) {

			foreach ($firstNames as $firstname) {
				$firstname = self::cleanFirstname($firstname);

				// Le prénom doit se trouver dans l'information complète de l'auteur
				if (strpos($fullName, $firstname) !== false) {
					$finalAuthors[$i]['firstname'] = $firstname;
					break;
				}
			}

			foreach ($lastNames as $lastName) {
				// Le nom doit se trouver dans l'information complète de l'auteur
				if (strpos($fullName, $lastName) !== false) {
					$finalAuthors[$i]['lastname'] = $lastName;
					break;
				}
			}

			foreach ($orcids as $orcid) {
				// L'orcid doit se trouver dans l'information complète de l'auteur
				if (strpos($fullName, $orcid) !== false) {
					$finalAuthors[$i]['orcid'] = $orcid;
					break;
				}
			}

			foreach ($affiliations as $affiliation) {
				// L'affiliation doit se trouver dans l'information complète de l'auteur
				if (strpos($fullName, $affiliation) !== false) {
					$finalAuthors[$i]['affiliation'] = $affiliation;
					break;
				}
			}

			foreach($emails as $email){
				// L'email doit se trouver dans l'informations complète de l'auteur
				if (strpos($fullName,$email) !== false){
					$finalAuthors[$i]['email'] = $email;
				}
			}
		}

		return $finalAuthors;
	}

	public function getVoirAussi()   // DOC_URL
	{
		$doc_url = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RESSOURCES_LIEES);  // on recupere DOC_URL
		$doc_url = empty($doc_url) ? '' : $doc_url;
		return $doc_url;
	}


	/**
	 * @param string $defaultLang
	 * @return array
	 */
	public function getTitle($defaultLang='en'):array
	{
		$array = [];
		$langArray=['fr','en','it','es','de'];
		$langDoc = $this->formateLang($this->getDocLang(),$this->getDocLang());
		if (!in_array($langDoc,$langArray,true)){
			$langArray[] = $langDoc;
		}

		$ld = new Language($langArray);
		$ld->setMaxNgrams(9000);

		$title = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE);   // DOC_TITRE : titre traduit dont la langue est à détecter
		$title_fr = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE_FR);
		$title_en = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE_EN);
		if (!empty($title_fr))
			$array["fr"] = $title_fr;
		if (!empty($title_en))
			$array["en"] = $title_en;
		if (!empty($title)) {
			$lang = $ld->detect($title)->bestResults()->close();
			$lang_detected = key($lang);  //close selectionne la meilleure valeur puis on récupére la clé qui correspond à la langue
			if (!empty($lang_detected))
				$array[$lang_detected] = $title;
			else
				$array["default"] = $title;
		}
		return $array;
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]
	 */
	public function getIdentifier()
	{
		return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ID);
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getDOI()
	{
		$doi = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOI);
		$doi = empty($doi) ? '' : $doi;
		return $doi;
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getDocLang()
	{
		$docLang = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LANGUAGE, null, true);
		// $doclang est un tableau et on ne prend que la première valeur car les vieilles notices ont parfois deux langues
		$lang = empty($docLang[0]) ? '' : $docLang[0];
		return $lang;
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getAbstract()
	{
		$resume_fr = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ABSTRACT);
		$resume_en = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ABSTRACT_EN);
		$array = [];
		if (!empty($resume_fr))
			$array["fr"] = $resume_fr;
		if (!empty($resume_en))
			$array["en"] = $resume_en;

		return $array;
	}


	/**
	 * @param $xpath
	 * @param $type
	 * @param $order
	 */
	public static function registerType($xpath, $type, $order = 50)
	{
		self::$_existing_types[$order][$xpath] = $type;
		// Il faut trier suivant l'ordre car PHP ne tri pas numeriquement par defaut
		ksort(self::$_existing_types);
	}


	/**
	 * @return DOMNodeList|DOMNodeList[]|string
	 */
	public function getComment(){

		$comment = [];
		$recherche = "Hors";

		$note = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COMMENTAIRE,null,false);   // on retourne une string
		if (!empty($note))
			$comment[] = "[Notes_IRSTEA]".$note;

		$div = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DIVISION,null,true);     // tab   CEM_DIVISION
		if (!empty($div)) {
			foreach($div as $key => $value) {
				if(!preg_match('#'.$recherche.'#', $value))
					$comment[] = "[Departement_IRSTEA]".$value;
			}
		}

		$prog = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROGRAM,null,true);       // CEM_PROGRAM
		if (!empty($prog)) {
			foreach($prog as $key => $value) {
				if(!preg_match('#'.$recherche.'#', $value))
					$comment[] = "[TR1_IRSTEA]".$value;
			}
		}

		$equipe = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EQUIPE,null,true);       // tab
		if (!empty($equipe)) {
			foreach($equipe as $key => $value)
				$comment[] = "[Axe_IRSTEA]".$value;
		}

		$prog2 = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROGRAM2,null,true);     // tab
		if (!empty($prog2)) {
			foreach($prog2 as $key => $value) {
				if(!preg_match('#'.$recherche.'#', $value))
					$comment[] = "[TR2_IRSTEA]".$value;
			}
		}

		$add = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ADD,null,true);    // CEM_ADD
		if (!empty($add)) {
			foreach($add as $key => $value) {
				if(!preg_match('#'.$recherche.'#', $value))
					$comment[] = "[ADD1_IRSTEA]".$value;
			}
		}

		$add2 = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ADD2,null,true);         // tab   CEM_ADD2
		if (!empty($add2)) {
			foreach($add2 as $key => $value) {
				if(!preg_match('#'.$recherche.'#', $value))
					$comment[] = "[ADD2_IRSTEA]".$value;
			}
		}

		$collection = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_TITRE_VOLUME,null,true);         // tab   DOC_COLLECTION
		if (!empty($collection)) {
			foreach($collection as $key => $value)
				$comment[] = "[Coll_IRSTEA]".$value;
		}

		return implode(' ',$comment);  // on retourne un tableau pour le champ multivalué inra_inraComment_local
	}


	/**
	 * @param $value
	 * @param $domxpath
	 * @param : $returnArray booleen pour forcer à retourner une valeur seule en tableau et pas en string
	 * @return DOMNodeList[]|DOMNodeList
	 */
	protected function getValue($value,$domxpath = null, $returnArray = false)
	{
		if ($domxpath){
			$children = $domxpath->query($value);
		}
		else {
			$children = $this->getDomPath()->query($value);
		}
		if (isset($children)) {
			// Children : tableau de DOMElements
			// Unique élément : l'élément est une string
			if ($children->length === 1) {
				if ($returnArray) {  // si on veut un tableau
					$values[] = Ccsd_Tools::space_clean($children[0]->nodeValue);
					return $values;
				} else {  // sinon on retourne un string
					$retour = Ccsd_Tools::space_clean($children[0]->nodeValue);
					if (is_array($retour))
						return $retour[0];
					else
						return $retour;
				}
				// Multiple éléments : ajoutés dans un tableau
			} else if ($children->length > 1) {
				$values = [];
				foreach ($children as $child) {
					$values[] = Ccsd_Tools::space_clean($child->nodeValue);
				}
				if ($returnArray)
					return $values;
				else
					return implode(' ',$values);
			}
		}

		return [];
	}


	/*******************   Gestion du Domain HAL : SDE, SDV ....   ***************************************/
	public function testDomain($domain)
	{
		$ep = $this->_dbHalV3->query("SELECT CODE FROM `REF_DOMAIN` WHERE CODE ='" . trim($domain) . "'");
		$array_db = $ep->fetchAll();
		if (count($array_db) === 0) {
			$array_domain = explode('.',$domain);
			array_pop($array_domain);
			if (empty($array_domain)){
				return '';
			}
			else {
				return $this->testDomain(implode('.',$array_domain));
			}
		}
		else {
			return $domain;
		}
	}
	public function selectDomain(array $classif)
	{
		$array_return = [];
		if (!empty($classif)){
			foreach ($classif as $key => $item) {
				if (!empty($item)) {
					$item = strtolower(str_replace([':','_'], ['.','-'], $item));
					$item = $this->testDomain($item);
					if ($item!=='') {
						$array_return[] = $this->testDomain($item);
					}
				}
			}
		}
		return $array_return;
	}
	public function getHalDomainCode()
	{

		$halcod = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_HAL_DOMAINE_REF,null,true);
		$halcod = $this->selectDomain($halcod);
		$halcod = empty($halcod) ?  ['sde'] : $halcod;
		return $halcod;
	}
	/******************************* FIN Gestion du Domain HAL  *********************************************************************/

	public function getHalDomainLib(){

		$haldom = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_HAL_DOMAINE_LIB,null,true);
		return $haldom;
	}
	public function getHalRef(){
		$halref = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_HAL_REF);
		$halref = empty($halref) ? '' : $halref;
		return $halref;
	}

	public function getHalTypology(){

	}

	public function getMetadatas()
	{
		$this->_metas[self::META] = [];
		$this->_metas[self::AUTHORS] = [];

		foreach ($this->_wantedTags as $metakey) {

			$meta = "";

			switch ($metakey) {
				case self::META_TITLE :
					$meta = $this->getTitle();
					break;
				case self::META_IDENTIFIER :
					$meta = $this->getIdentifier();
					break;
				case self::META_DATE :
					$meta = $this->getDate();
					break;
				case self::META_COMMENT :   // departement, TR, AXE, TR secondaire, ADD principal et secondaire MULTIVALU2 vers inra_inraComment_local
					$meta = $this->getComment();
					break;
				case self::META_SERIE :
					$meta = $this->getSerie();
					break;
				case self::META_VOIR_AUSSI :
					$meta = $this->getVoirAussi();
					break;
				case self::META_VOLUME :
					$meta = $this->getVolume();
					break;
				case self::META_PAGE :
					$meta = $this->getPage();
					break;
				case self::META_DOCUMENTLOCATION :
					$meta = $this->getDocumentLocation();
					break;
				case self::META_ABSTRACT :
					$meta = $this->getAbstract();
					break;
				case self::META_KEYWORD :
					$meta = $this->getKeywords();
					break;
				case self::META_INDEXATION :
					$meta = $this->getIndexationVocInrae();
					break;
				case self::META_HAL_DOMAIN_CODE:
					$meta = $this->getHalDomainCode();
					break;
				case self::META_HAL_DOMAIN_LIB:
					$meta = $this->getHalDomainLib();
					break;
				case self::META_HAL_REF:
					$meta = $this->getHalRef();
					break;
				case self::META_PUBLISHER:
					$meta = $this->getEditeur();
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

		// Gestion des identifiants en tableau
		$doi = $this->getDOI();
		if (!empty($doi))
			$this->_metas['identifier']['doi'] = $doi;

		// Ajout de la COTE du document
		if (!empty($this->getDocumentLocation())) $this->_metas['metas']['inra_lieu_local'] = $this->getDocumentLocation();

		if (!empty($this->getIdentifier())) $this->_metas[self::META_IDENTIFIER][self::META_PRODINRA] = $this->getIdentifier();
		if (!empty($this->getIssn())) $this->_metas[self::META_IDENTIFIER][self::META_ISSN] = $this->getIssn();
		//if (!empty($this->getIsbn())) $this->_metas[self::META_IDENTIFIER][self::META_CONFISBN] = $this->getIsbn();  conferenceISBN

		// Construction des auteurs
		if (!empty($this->getAuthors())) $this->_metas[self::AUTHORS] = $this->getAuthors();
		//if (!empty($this->getExtAuthors())) $this->_metas[self::EXTAUTHORS] = $this->getExtAuthors();


		return $this->_metas;
	}

	public function country_get_iso3_mapping($code)
	{
		$pays = array(
			'AND' => 'AD',
			'ARE' => 'AE',
			'AFG' => 'AF',
			'ATG' => 'AG',
			'AIA' => 'AI',
			'ALB' => 'AL',
			'ARM' => 'AM',
			'AGO' => 'AO',
			'ATA' => 'AQ',
			'ARG' => 'AR',
			'ASM' => 'AS',
			'AUT' => 'AT',
			'AUS' => 'AU',
			'ABW' => 'AW',
			'ALA' => 'AX',
			'AZE' => 'AZ',
			'BIH' => 'BA',
			'BRB' => 'BB',
			'BGD' => 'BD',
			'BEL' => 'BE',
			'BFA' => 'BF',
			'BGR' => 'BG',
			'BHR' => 'BH',
			'BDI' => 'BI',
			'BEN' => 'BJ',
			'BLM' => 'BL',
			'BMU' => 'BM',
			'BRN' => 'BN',
			'BOL' => 'BO',
			'BES' => 'BQ',
			'BRA' => 'BR',
			'BHS' => 'BS',
			'BTN' => 'BT',
			'BVT' => 'BV',
			'BWA' => 'BW',
			'BLR' => 'BY',
			'BLZ' => 'BZ',
			'CAN' => 'CA',
			'CCK' => 'CC',
			'COD' => 'CD',
			'CAF' => 'CF',
			'COG' => 'CG',
			'CHE' => 'CH',
			'CIV' => 'CI',
			'COK' => 'CK',
			'CHL' => 'CL',
			'CMR' => 'CM',
			'CHN' => 'CN',
			'COL' => 'CO',
			'CRI' => 'CR',
			'CUB' => 'CU',
			'CPV' => 'CV',
			'CUW' => 'CW',
			'CXR' => 'CX',
			'CYP' => 'CY',
			'CZE' => 'CZ',
			'DEU' => 'DE',
			'DJI' => 'DJ',
			'DNK' => 'DK',
			'DMA' => 'DM',
			'DOM' => 'DO',
			'DZA' => 'DZ',
			'ECU' => 'EC',
			'EST' => 'EE',
			'EGY' => 'EG',
			'ESH' => 'EH',
			'ERI' => 'ER',
			'ESP' => 'ES',
			'ETH' => 'ET',
			'FIN' => 'FI',
			'FJI' => 'FJ',
			'FLK' => 'FK',
			'FSM' => 'FM',
			'FRO' => 'FO',
			'FRA' => 'FR',
			'GAB' => 'GA',
			'GBR' => 'GB',
			'GRD' => 'GD',
			'GEO' => 'GE',
			'GUF' => 'GF',
			'GGY' => 'GG',
			'GHA' => 'GH',
			'GIB' => 'GI',
			'GRL' => 'GL',
			'GMB' => 'GM',
			'GIN' => 'GN',
			'GLP' => 'GP',
			'GNQ' => 'GQ',
			'GRC' => 'GR',
			'SGS' => 'GS',
			'GTM' => 'GT',
			'GUM' => 'GU',
			'GNB' => 'GW',
			'GUY' => 'GY',
			'HKG' => 'HK',
			'HMD' => 'HM',
			'HND' => 'HN',
			'HRV' => 'HR',
			'HTI' => 'HT',
			'HUN' => 'HU',
			'IDN' => 'ID',
			'IRL' => 'IE',
			'ISR' => 'IL',
			'IMN' => 'IM',
			'IND' => 'IN',
			'IOT' => 'IO',
			'IRQ' => 'IQ',
			'IRN' => 'IR',
			'ISL' => 'IS',
			'ITA' => 'IT',
			'JEY' => 'JE',
			'JAM' => 'JM',
			'JOR' => 'JO',
			'JPN' => 'JP',
			'KEN' => 'KE',
			'KGZ' => 'KG',
			'KHM' => 'KH',
			'KIR' => 'KI',
			'COM' => 'KM',
			'KNA' => 'KN',
			'PRK' => 'KP',
			'KOR' => 'KR',
			'XKX' => 'XK',
			'KWT' => 'KW',
			'CYM' => 'KY',
			'KAZ' => 'KZ',
			'LAO' => 'LA',
			'LBN' => 'LB',
			'LCA' => 'LC',
			'LIE' => 'LI',
			'LKA' => 'LK',
			'LBR' => 'LR',
			'LSO' => 'LS',
			'LTU' => 'LT',
			'LUX' => 'LU',
			'LVA' => 'LV',
			'LBY' => 'LY',
			'MAR' => 'MA',
			'MCO' => 'MC',
			'MDA' => 'MD',
			'MNE' => 'ME',
			'MAF' => 'MF',
			'MDG' => 'MG',
			'MHL' => 'MH',
			'MKD' => 'MK',
			'MLI' => 'ML',
			'MMR' => 'MM',
			'MNG' => 'MN',
			'MAC' => 'MO',
			'MNP' => 'MP',
			'MTQ' => 'MQ',
			'MRT' => 'MR',
			'MSR' => 'MS',
			'MLT' => 'MT',
			'MUS' => 'MU',
			'MDV' => 'MV',
			'MWI' => 'MW',
			'MEX' => 'MX',
			'MYS' => 'MY',
			'MOZ' => 'MZ',
			'NAM' => 'NA',
			'NCL' => 'NC',
			'NER' => 'NE',
			'NFK' => 'NF',
			'NGA' => 'NG',
			'NIC' => 'NI',
			'NLD' => 'NL',
			'NOR' => 'NO',
			'NPL' => 'NP',
			'NRU' => 'NR',
			'NIU' => 'NU',
			'NZL' => 'NZ',
			'OMN' => 'OM',
			'PAN' => 'PA',
			'PER' => 'PE',
			'PYF' => 'PF',
			'PNG' => 'PG',
			'PHL' => 'PH',
			'PAK' => 'PK',
			'POL' => 'PL',
			'SPM' => 'PM',
			'PCN' => 'PN',
			'PRI' => 'PR',
			'PSE' => 'PS',
			'PRT' => 'PT',
			'PLW' => 'PW',
			'PRY' => 'PY',
			'QAT' => 'QA',
			'REU' => 'RE',
			'ROU' => 'RO',
			'SRB' => 'RS',
			'RUS' => 'RU',
			'RWA' => 'RW',
			'SAU' => 'SA',
			'SLB' => 'SB',
			'SYC' => 'SC',
			'SDN' => 'SD',
			'SSD' => 'SS',
			'SWE' => 'SE',
			'SGP' => 'SG',
			'SHN' => 'SH',
			'SVN' => 'SI',
			'SJM' => 'SJ',
			'SVK' => 'SK',
			'SLE' => 'SL',
			'SMR' => 'SM',
			'SEN' => 'SN',
			'SOM' => 'SO',
			'SUR' => 'SR',
			'STP' => 'ST',
			'SLV' => 'SV',
			'SXM' => 'SX',
			'SYR' => 'SY',
			'SWZ' => 'SZ',
			'TCA' => 'TC',
			'TCD' => 'TD',
			'ATF' => 'TF',
			'TGO' => 'TG',
			'THA' => 'TH',
			'TJK' => 'TJ',
			'TKL' => 'TK',
			'TLS' => 'TL',
			'TKM' => 'TM',
			'TUN' => 'TN',
			'TON' => 'TO',
			'TUR' => 'TR',
			'TTO' => 'TT',
			'TUV' => 'TV',
			'TWN' => 'TW',
			'TZA' => 'TZ',
			'UKR' => 'UA',
			'UGA' => 'UG',
			'UMI' => 'UM',
			'USA' => 'US',
			'URY' => 'UY',
			'UZB' => 'UZ',
			'VAT' => 'VA',
			'VCT' => 'VC',
			'VEN' => 'VE',
			'VGB' => 'VG',
			'VIR' => 'VI',
			'VNM' => 'VN',
			'VUT' => 'VU',
			'WLF' => 'WF',
			'WSM' => 'WS',
			'YEM' => 'YE',
			'MYT' => 'YT',
			'ZAF' => 'ZA',
			'ZMB' => 'ZM',
			'ZWE' => 'ZW',
			'SCG' => 'CS',
			'ANT' => 'AN'
		);
		if (array_key_exists($code, $pays))
			return $pays[$code];
		else
			return '';
	}

	// String : $langue
	function langue_to_iso( $lang ){
		$lang = strtolower($lang);
		$codeList = array(
			'allemand' => 'de',
			'anglais' => 'en',
			'français' => 'fr',
			'italien' => 'it',
			'espagnol' => 'es',
			'catalan' => 'ca',
			'en' => 'en',
			'fr' => 'fr',
			'chinois' => 'cn',
			'hongrois' => 'hu',
			'french' => 'fr',
			'persan' => 'fa',
			'polonais' => 'pl',
			'portuguais' => 'pt',
			'roumain' => 'ro',
			'suédois' => 'sv'
		);
		if (array_key_exists($lang, $codeList))
			return $codeList[$lang];
		else
			return 'fr';
	}
}

foreach (glob(__DIR__.'/Irstea/*.php') as $filename)
{
	require_once $filename;
}