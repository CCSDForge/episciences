<?php
/**
 * Created by PhpStorm.
 * User: bblondelle
 * Date: 13/03/19
 * Time: 14:58
 */

// @see https://wiki.epfl.ch/infoscience-historique/documents/crossref.pdf
require_once __DIR__. "/../Ird.php";

class Ccsd_Externdoc_Ird_Journal extends Ccsd_Externdoc_Ird
{
    /**
     * @var string
     */
    protected $_type = "ART";

    // Type Article
    const INTER_ISSN = "ISSN";
    const INTER_FULLTITLE = "JNAME";

    const XPATH_LANG = '/m:modsCollection/m:mods/m:language/m:languageTerm';
    const XPATH_VOLUME = '/m:modsCollection/m:mods/m:relatedItem/m:part/m:detail[@type="volume"]/m:number';
    const XPATH_ISSUE = '/m:modsCollection/m:mods/m:relatedItem/m:part/m:detail[@type="issue"]/m:number';
    const XPATH_VOLUME_TITLE = '/m:modsCollection/m:mods/m:relatedItem/m:titleInfo/m:title';
    const XPATH_TITLE = '/m:modsCollection/m:mods/m:titleInfo/m:title';
    const XPATH_KEYWORDS = '/m:modsCollection/m:mods/m:subject/*';
    const XPATH_ABSTRACT = '/m:modsCollection/m:mods/m:abstract';
    const XPATH_DATE = '/m:modsCollection/m:mods/m:relatedItem/m:originInfo/m:dateIssued';
    const XPATH_ISSN = '/m:modsCollection/m:mods/m:relatedItem/m:identifier[@type="issn"]';
    const XPATH_FULLTITLE = '/m:modsCollection/m:mods/m:relatedItem/m:titleInfo/m:title';
    const XPATH_FIRSTPAGE = '/m:modsCollection/m:mods/m:relatedItem/m:part/m:extent[@unit="pages"]/m:start';
    const XPATH_LASTPAGE = '/m:modsCollection/m:mods/m:relatedItem/m:part/m:extent[@unit="pages"]/m:end';
    const XPATH_COMPLETE_AUTHOR = '/m:modsCollection/m:mods/m:name';
    const XPATH_AUTHOR_AFFILIATION = '/m:modsCollection/m:mods/m:name/m:affiliation';
    const XPATH_CONTRIBUTORS_FIRST = '/m:modsCollection/m:mods/m:name/m:namePart[@type="given"]';
    const XPATH_CONTRIBUTORS_LAST = '/m:modsCollection/m:mods/m:name/m:namePart[@type="family"]';
    const XPATH_IDENTIFIER_DOI = '/m:modsCollection/m:mods/m:identifier[@type="doi"]';
    const XPATH_IDENTIFIER_URI = '/m:modsCollection/m:mods/m:identifier[@type="uri"]';
    const XPATH_CLASSIFICATION = '/m:modsCollection/m:mods/m:classification';

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Ird_Journal
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Ird_Journal($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
        $doc->registerNamespace();

        return $doc;
    }

    /**
     * Traduction de la date
     * @return string
     */
    public function getDate()
    {
        return Ccsd_Tools::str2date($this->getValue(self::XPATH_DATE));
    }

    /**
     * Traduction de la page
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPage()
    {
        $first = $this->getValue(self::XPATH_FIRSTPAGE);
        $last  = $this->getValue(self::XPATH_LASTPAGE);

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
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getVolume()
    {
        return $this->getValue(self::XPATH_VOLUME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getVolumeTitle()
    {
        return $this->getValue(self::XPATH_VOLUME_TITLE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getIssue()
    {
        return $this->getValue(self::XPATH_ISSUE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]
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


        $fulltitle = $this->getValue(self::XPATH_FULLTITLE);
        $fulltitle = empty($fulltitle) ? "" : $fulltitle;


        return $this->formateJournal($fulltitle, '', $issn, '');
    }

    /**
     * @return array
     */
    public function getAuthors()
    {
        $fullNames = $this->getValue(self::XPATH_COMPLETE_AUTHOR);
        $fullNames = is_array($fullNames) ? $fullNames : [$fullNames];

        $firstNames = $this->getValue(self::XPATH_CONTRIBUTORS_FIRST);
        $firstNames = is_array($firstNames) ? $firstNames : [$firstNames];

        $lastNames = $this->getValue(self::XPATH_CONTRIBUTORS_LAST);
        $lastNames = is_array($lastNames) ? $lastNames : [$lastNames];

        $affiliation = $this->getValue(self::XPATH_AUTHOR_AFFILIATION);
        $affiliation = is_array($affiliation) ? $affiliation : [$affiliation];

        return $this->formateAuthors($fullNames, $firstNames, $lastNames, $affiliation, []);
    }

    /**
     * @return string
     */
    public function getKeywords()
    {
        $list = $this->getValue(self::XPATH_KEYWORDS);
        if (is_array($list)) {
            return implode(';', $list);
        } else {
            return $list;
        }
    }

    /**
     * @return string
     */
    public function getClassification()
    {
        return implode(' ; ', $this->getValue(self::XPATH_CLASSIFICATION));
    }

    /**
     * @return array
     */
    public function getDoi()
    {
        return ['doi' => $this->getValue(self::XPATH_IDENTIFIER_DOI)];
    }

    /**
     * @return string
     */
    public function getSeeAlso()
    {
        return $this->getValue(self::XPATH_IDENTIFIER_URI);
    }

    /**
     * @param string $defaultLang
     * @return string
     */
    public function getAbstract($defaultLang)
    {
        $abstract = $this->getValue(self::XPATH_ABSTRACT);
        $abstract = empty($abstract) ? "" : $abstract;

        // Transformation du titre en tableau avec la clé comme langue
        $abstract = $this->metasToLangArray($abstract, $defaultLang);
        return $abstract;
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
        $defaultLang = empty($lang) ? 'en' : $this->formateLang($lang, '');

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
                case self::META_SERIE :
                    $meta = $this->getVolumeTitle();
                    break;
                case self::META_ISSUE :
                    $meta = $this->getIssue();
                    break;
                case self::META_KEYWORD :
                    $meta = $this->getKeywords();
                    break;
                case self::META_CLASSIFICATION :
                    $meta = $this->getClassification();
                    break;
                case self::META_IDENTIFIER :
                    $meta = $this->getDoi();
                    break;
                case self::META_SEEALSO :
                    $meta = $this->getSeeAlso();
                    break;
                case self::META_ABSTRACT :
                    $meta = $this->getAbstract($defaultLang);
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
        $this->_metas[self::META][self::META_LANG] = $this->formateLang($lang, $titleLang);

        $this->_metas[self::META][self::META_IDENTIFIER][$this->_idtype] = $this->_id;
        $this->_metas[self::AUTHORS] = $this->getAuthors();

        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

}

Ccsd_Externdoc_Ird::registerType("/m:modsCollection/m:mods/m:genre[string() = 'journalArticle']", "Ccsd_Externdoc_Ird_Journal");