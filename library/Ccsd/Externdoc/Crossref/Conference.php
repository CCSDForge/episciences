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
 * Class Ccsd_Externdoc_Crossref_Conference
 */
class Ccsd_Externdoc_Crossref_Conference extends Ccsd_Externdoc_Crossref
{
    /**
     * @var string
     */
    protected $_type = "COMM";

    // Type Communication dans un congrès
    const XPATH_CONFTITLE = '/doi_records/doi_record/crossref/conference/event_metadata/conference_name';
    const XPATH_CONFLOCATION = '/doi_records/doi_record/crossref/conference/event_metadata/conference_location';
    const XPATH_PROCEEDINGSTITLE = '/doi_records/doi_record/crossref/conference/proceedings_metadata/proceedings_title';
    const XPATH_PUBLISHER = '/doi_records/doi_record/crossref/conference/proceedings_metadata/publisher/publisher_name';
    const XPATH_CONFISBN = '/doi_records/doi_record/crossref/conference/proceedings_metadata/isbn[@media_type="print"]';
    const XPATH_CONFCONTRIBUTOR = '/doi_records/doi_record/crossref/conference/conference_paper/contributors/person_name[@contributor_role="author"]';
    const REL_XPATH_FIRSTNAME = 'given_name';
    const REL_XPATH_LASTNAME = 'surname';
    const REL_XPATH_ORCID = 'ORCID';
        //const XPATH_AFFILIATION => '/doi_records/doi_record/crossref/conference/conference_paper/contributors/person_name/affiliation';
    const XPATH_CONFSTARTYEAR = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@start_year';
    const XPATH_CONFSTARTMONTH = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@start_month';
    const XPATH_CONFSTARTDAY = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@start_day';
    const XPATH_CONFSTOPYEAR = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@end_year';
    const XPATH_CONFSTOPMONTH = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@end_month';
    const XPATH_CONFSTOPDAY = '/doi_records/doi_record/crossref/conference/event_metadata/conference_date/@end_day';
    const XPATH_CONFPAPERTITLE = '/doi_records/doi_record/crossref/conference/conference_paper/titles/title';
    const XPATH_FIRSTPAGE = '/doi_records/doi_record/crossref/conference/conference_paper/pages/first_page';
    const XPATH_LASTPAGE = '/doi_records/doi_record/crossref/conference/conference_paper/pages/last_page';

    // Tableau de correspondance Auteur => Metas Intermediaires
    /*protected $_interToAuthors = array(
        self::CONFAUTHORS => [self::INTER_CONFCONTRIBUTOR, self::INTER_CONFCONTRIBFIRST, self::INTER_CONFCONTRIBLAST]
    );*/

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Crossref_Conference
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Crossref_Conference($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
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

        $this->_metas = [];
        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_CONFTITLE :
                    $meta = $this->getConfTitle();
                    break;
                case self::META_PROCEEDINGSTITLE :
                    $meta = $this->getProceedingsTitle();
                    break;
                case self::META_PUBLISHER :
                    $meta = $this->getPublisher();
                    break;
                case self::META_CONFISBN :
                    $meta = $this->getConfIsbn();
                    break;
                case self::META_TITLE :
                    $meta = $this->getTitle();
                    break;
                case self::META_CONFDATESTART :
                    $meta = $this->getConferenceStartDate();
                    break;
                case self::META_CONFDATEEND :
                    $meta = $this->getConferenceEndDate();
                    break;
                case self::META_CITY :
                    $meta = $this->getCity();
                    break;
                case self::META_COUNTRY :
                    $meta = $this->getCountry();
                    break;
                case self::META_PAGE :
                    $meta = $this->getPage();
                    break;
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
        $this->_metas[self::META_LANG] = $this->formateLang('', $titleLang);

        $this->_metas[self::META][self::META_IDENTIFIER]["doi"] = $this->_id;
        $this->_metas[self::AUTHORS] = $this->getAuthors(self::XPATH_CONFCONTRIBUTOR);

        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

    /**
     * @return string[]
     */
    public function getConfTitle()
    {
        return $this->getValue(self::XPATH_CONFTITLE);
    }

    /**
     * @return string[]
     */
    public function getProceedingsTitle()
    {
        return $this->getValue(self::XPATH_PROCEEDINGSTITLE);
    }

    /**
     * @return string[]
     */
    public function getPublisher()
    {
        return $this->getValue(self::XPATH_PUBLISHER);
    }

    /**
     * @return string|string[]
     */
    public function getConfIsbn()
    {
        $isbns = $this->getValue(self::XPATH_CONFISBN);
        return empty($isbns) ? "" : $isbns;
    }

    /**
     * @return string
     */
    public function getConferenceStartDate()
    {
        $year = $this->getValue(self::XPATH_CONFSTARTYEAR);
        $month = $this->getValue(self::XPATH_CONFSTARTMONTH);
        $day = $this->getValue(self::XPATH_CONFSTARTDAY);

        return $this->formateDate($year, $month, $day);
    }

    /**
     * @return string
     */
    public function getConferenceEndDate()
    {
        $year = $this->getValue(self::XPATH_CONFSTOPYEAR);
        $month = $this->getValue(self::XPATH_CONFSTOPMONTH);
        $day = $this->getValue(self::XPATH_CONFSTOPDAY);

        return $this->formateDate($year, $month, $day);
    }

    /**
     * @return string
     */
    public function getCity()
    {
        $city = $this->getValue(self::XPATH_CONFLOCATION);

        if (!empty($city)) {

            $city = explode(",", $city);

            if (count($city) >= 1) {
                return $city[0];
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        // todo : il faut pouvoir mettre la clé du pays plutôt que le nom complet !

        $city = $this->getValue(self::XPATH_CONFLOCATION);

        if (!empty($city)) {

            $city = explode(",", $city);

            if (count($city) >= 1) {
                return trim($city[1]);
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getPage()
    {
        $first = $this->getValue(self::XPATH_FIRSTPAGE);
        $first = empty($first) ? "" : $first;

        $last = $this->getValue(self::XPATH_LASTPAGE);
        $last = empty($last) ? "" : $last;

        return $this->formatePage($first, $last);
    }

    /**
     * @param string $defaultLang
     * @return string[]
     */
    public function getTitle($defaultLang='en')
    {
        $title = $this->getValue(self::XPATH_CONFPAPERTITLE);
        $title = empty($title) ? "" : $title;

        // Transformation du titre en tableau avec la clé comme langue
        $title = $this->metasToLangArray($title, $defaultLang);
        return $title;
    }
}

Ccsd_Externdoc_Crossref::registerType("/doi_records/doi_record/crossref/conference", "Ccsd_Externdoc_Crossref_Conference");