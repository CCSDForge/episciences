<?php

/**
 * Created by PhpStorm.
 * User: sdenoux
 * Date: 06/07/18
 * Time: 16:19
 */

// @see https://wiki.epfl.ch/infoscience-historique/documents/crossref.pdf
require_once "Ccsd/Externdoc/Crossref.php";

/**
 * Class Ccsd_Externdoc_Crossref_Journal
 */
class Ccsd_Externdoc_Crossref_Journal extends Ccsd_Externdoc_Crossref
{
    /**
     * @var string
     */
    protected $_type = "ART";

    // Type Article
    const INTER_ISSN = "ISSN";
    const INTER_EISSN = "EISSN";
    const INTER_FULLTITLE = "JNAME";
    const INTER_ABBREVTITLE = "SHORTNAME";

    const XPATH_LANG = '/doi_records/doi_record/crossref/journal/journal_metadata/@language';
    const XPATH_VOLUME = '/doi_records/doi_record/crossref/journal/journal_issue/journal_volume/volume';
    const XPATH_ISSUE = '/doi_records/doi_record/crossref/journal/journal_issue/issue';
    const XPATH_TITLE = '/doi_records/doi_record/crossref/journal/journal_article/titles/title';
    const XPATH_YEAR = '/doi_records/doi_record/crossref/journal/journal_issue/publication_date/year';
    const XPATH_MONTH = '/doi_records/doi_record/crossref/journal/journal_issue/publication_date/month';
    const XPATH_DAY = '/doi_records/doi_record/crossref/journal/journal_issue/publication_date/day';
    const XPATH_ISSN = '/doi_records/doi_record/crossref/journal/journal_metadata/issn[@media_type="print"]';
    const XPATH_EISSN = '/doi_records/doi_record/crossref/journal/journal_metadata/issn[@media_type="electronic"]';
    const XPATH_FULLTITLE = '/doi_records/doi_record/crossref/journal/journal_metadata/full_title';
    const XPATH_ABBREVTITLE = '/doi_records/doi_record/crossref/journal/journal_metadata/abbrev_title';
    const XPATH_FIRSTPAGE = '/doi_records/doi_record/crossref/journal/journal_article/pages/first_page';
    const XPATH_LASTPAGE = '/doi_records/doi_record/crossref/journal/journal_article/pages/last_page';
    const XPATH_COMPLETE_AUTHOR = '/doi_records/doi_record/crossref/journal/journal_article/contributors/person_name[@contributor_role="author"]';

    const XPATH_CONTRIBUTORS_FIRST = '/doi_records/doi_record/crossref/journal/journal_article/contributors/person_name/given_name';
    const XPATH_CONTRIBUTORS_LAST = '/doi_records/doi_record/crossref/journal/journal_article/contributors/person_name/surname';
    const XPATH_CONTRIBUTORS_ORCID = '/doi_records/doi_record/crossref/journal/journal_article/contributors/person_name/ORCID';
    const XPATH_FUNDING = '/doi_records/doi_record/crossref/journal/journal_article//program[@name="fundref"]/assertion[@name="fundgroup"]';
    const XPATH_FUNDING_NAME = '/doi_records/doi_record/crossref/journal/journal_article//program[@name="fundref"]/assertion[@name="fundgroup"]/assertion[@name="funder_name"]/text()[1]';
    const XPATH_FUNDING_DOI = '/doi_records/doi_record/crossref/journal/journal_article//program[@name="fundref"]/assertion[@name="fundgroup"]/assertion[@name="funder_name"]/assertion[@name="funder_identifier"]';
    const XPATH_FUNDING_CODE = '/doi_records/doi_record/crossref/journal/journal_article//program[@name="fundref"]/assertion[@name="fundgroup"]/assertion[@name="award_number"]';

    const XPATH_IDENT_DOI = '/doi_records/doi_record/crossref/journal/journal_article/doi_data/doi';

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Crossref_Journal
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Crossref_Journal($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
        return $doc;
    }

    /**
     * Traduction de la date
     * @return string
     */
    public function getDate()
    {
        $yearconst = $this->getValue(self::XPATH_YEAR);
        $monthconst = $this->getValue(self::XPATH_MONTH);
        $dayconst = $this->getValue(self::XPATH_DAY);

        return $this->formateDate($yearconst, $monthconst, $dayconst);
    }

    /**
     * Traduction de la page
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPage()
    {
        $first = $this->getValue(self::XPATH_FIRSTPAGE);
        $last = $this->getValue(self::XPATH_LASTPAGE);

        if (!empty($first) && !empty($last)) {
            return $first . "-" . $last;
        }

        if (!empty($first)) {
            return $first;
        }

        return $last;
    }

    /**
     * @param string $defaultLang
     * @return array|string
     */
    public function getTitle($defaultLang='en')
    {
        $title = $this->getValue(self::XPATH_TITLE);
        $title = empty($title) ? "" : $title;

        // Transformation du titre en tableau avec la clé comme langue
        $title = $this->metasToLangArray($title, $defaultLang);
        return $title;
    }

    /**
     * @return string|string[]
     */
    public function getVolume()
    {
        return $this->getValue(self::XPATH_VOLUME);
    }

    /**
     * @return string|string[]
     */
    public function getIssue()
    {
        return $this->getValue(self::XPATH_ISSUE);
    }

    /**
     * @return string|string[]
     */
    public function getDocLang()
    {
        return $this->getValue(self::XPATH_LANG);
    }

    /** Création du Referentiel Journal
     * @return Ccsd_Referentiels_Journal
     */
    public function getJournal()
    {
        $issn = $this->getValue(self::XPATH_ISSN);
        $issn = empty($issn) ? "" : $issn;

        $eissn = $this->getValue(self::XPATH_EISSN);
        $eissn = empty($eissn) ? "" : $eissn;

        $fulltitle = $this->getValue(self::XPATH_FULLTITLE);
        $fulltitle = empty($fulltitle) ? "" : $fulltitle;

        $abbrevtitle = $this->getValue(self::XPATH_ABBREVTITLE);
        $abbrevtitle = empty($abbrevtitle) ? "" : $abbrevtitle;

        return $this->formateJournal($fulltitle, $abbrevtitle, $issn, $eissn);
    }

    /** Création des Funding/Projet ANR/Projet Européen
     * @return  Ccsd_Referentiels_Anrproject | Ccsd_Referentiels_Europeanproject | array
     */
    public function getFunding()
    {
        $funding = $this->getValue(self::XPATH_FUNDING);
        $funding = is_array($funding) ? $funding : [$funding];

        $fundingname = $this->getValue(self::XPATH_FUNDING_NAME);
        $fundingname = is_array($fundingname) ? $fundingname : [$fundingname];

        $fundingdoi = $this->getValue(self::XPATH_FUNDING_DOI);
        $fundingdoi = is_array($fundingdoi) ? $fundingdoi : [$fundingdoi];

        $fundingcode = $this->getValue(self::XPATH_FUNDING_CODE);
        $fundingcode = is_array($fundingcode) ? $fundingcode : [$fundingcode];

        return $this->formateFunding($funding, $fundingname, $fundingdoi, $fundingcode);
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

        $lang = $this->getDocLang();
        $defaultLang = empty($lang) ? 'en' : $lang;

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_TITLE :
                    $meta = $this->getTitle($defaultLang);
                    break;
                case self::META_DATE :
                    $meta = $this->getDate();
                    break;
                case self::META_PAGE :
                    $meta = $this->getPage();
                    break;
                case self::META_JOURNAL :
                    $meta = $this->getJournal();
                    break;
                case self::META_VOLUME :
                    $meta = $this->getVolume();
                    break;
                case self::META_ISSUE :
                    $meta = $this->getIssue();
                    break;
                /** TODO : Funding à terminer */
                /* case self::META_FUNDING :
                    $meta = $this->getFunding();
                    break; */
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }

        // Récupération de la langue du premier titre
        $titleLang = isset($this->_metas[self::META_TITLE]) ? array_keys($this->_metas[self::META_TITLE])[0] : '';

        // Ajout de la langue
        $this->_metas[self::META][self::META_LANG] = $this->formateLang($lang, $titleLang);

        $this->_metas[self::META][self::META_IDENTIFIER]["doi"] = $this->_id;
        $this->_metas[self::AUTHORS] = $this->getAuthors(self::XPATH_COMPLETE_AUTHOR);

        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

}

Ccsd_Externdoc_Crossref::registerType("/doi_records/doi_record/crossref/journal", "Ccsd_Externdoc_Crossref_Journal");