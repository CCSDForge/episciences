<?php

/**
 * Class Ccsd_Externdoc_Pubmed_Article
 */
class Ccsd_Externdoc_Pubmed_Article extends Ccsd_Externdoc_Pubmed
{
    /**
     * @var string
     */
    protected $_type = "ART";

    const ABS_LANG       = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Language';
    const ABS_VOLUME     = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Journal/JournalIssue/Volume';
    const ABS_ISSUE      = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Journal/JournalIssue/Issue';
    const ABS_TITLE      = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/ArticleTitle';
    const ABS_ABSTRACT   = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Abstract/AbstractText';
    const ABS_PAGE       = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Pagination/MedlinePgn';
    const ABS_MESH       = '/PubmedArticleSet/PubmedArticle/MedlineCitation/MeshHeadingList/MeshHeading/DescriptorName';
    const ABS_IDENTIFIER = '/PubmedArticleSet/PubmedArticle/PubmedData/ArticleIdList/ArticleId';
    const ABS_KEYWORD    = '/PubmedArticleSet/PubmedArticle/MedlineCitation/KeywordList/Keyword';
    const ABS_JOURNAL    = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Journal';
    const REL_ISSN       = 'ISSN[@IssnType="Print"]';
    const REL_EISSN      = 'ISSN[@IssnType="Electronic"]';
    const REL_JTITLE     = 'Title';
    const ABS_PUBDATE    = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/Journal/JournalIssue/PubDate';
    const      REL_YEAR  = 'Year';
    const      REL_MONTH = 'Month';
    const      REL_DAY   = 'Day';
    const ABS_AUTHOR     = '/PubmedArticleSet/PubmedArticle/MedlineCitation/Article/AuthorList/Author';
    const      FIRSTNAME = 'ForeName';
    const      LASTNAME  = 'LastName';
    const      INITIALS  = 'Initials';


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Pubmed_Article
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Pubmed_Article($id);
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

        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];

        $lang = $this->getDocLang();
        $defaultLang = empty($lang) ? 'en' : $lang;

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_LANG :
                    $meta = $this->getDocLang();
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

                case self::META_MESH:
                    $meta = $this->getMesh();
                    break;
                case self::META_KEYWORD:
                    $meta = $this->getKeywords();
                    break;

                default:

                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }
        // Meta speciales  (enfin pourquoi speciale???
        //    Principalement une question d'ordre:
        //    Il faut avoir la langue du document avant de pouvoir generer la meta title et abstract par example.
        $title = $this->getTitle($defaultLang);
        // Récupération de la langue du premier titre
        $titleLang = isset($title) ? array_keys($title)[0] : '';
        $this->_metas[self::META][self::META_TITLE] = $title;

        $this->_metas[self::META][self::META_ABSTRACT] = $this->getAbstract($defaultLang);
        // Ajout de la langue
        $this->_metas[self::META][self::META_LANG] = $this->formateLang($lang, $titleLang);

        $this->_metas[self::META][self::META_IDENTIFIER]= $this->getIdentifier();

        $this->_metas[self::AUTHORS] = $this->getAuthors(self::ABS_AUTHOR);

        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

    /**
     * Traduction de la date
     * @return string
     */
    public function getDate()
    {
        $xpathObject = $this->getDomPath();
        $nodes = $xpathObject->query(self::ABS_PUBDATE);
        if ($nodes) {
            $node = $nodes[0];

            $year = $this->getNodesValue($xpathObject, self::REL_YEAR, $node);
            $month = $this->getNodesValue($xpathObject, self::REL_MONTH, $node);
            $day = $this->getNodesValue($xpathObject, self::REL_DAY, $node);
            $dateString = '';
            if ($day) {
                $dateString .= $this->arrayToString($day) . '-';
            }
            if ($month) {
                $dateString .= $this->arrayToString($month) . '-';
            }
            if ($year) {
                $dateString .= $this->arrayToString($year);
            }
            return date('Y-m-d', strtotime($dateString));
        } else {
            return null;
        }
    }

    /**
     * @return string|string[]
     */
    public function getMesh() {
        $xpathObject = $this->getDomPath();
        return $this->getNodesValue($xpathObject, self::ABS_MESH);
    }

    /**
     * @return string|string[]
     */
    public function getJournal() {
        $xpathObject = $this->getDomPath();
        $journalNodes  = $xpathObject -> query(self::ABS_JOURNAL);
        if (!$journalNodes) {
            return null;
        }
        $journalNode = $journalNodes[0];
        $issn         = $this->getNodesValue($xpathObject, self::REL_ISSN,$journalNode);
        $eissn        = $this->getNodesValue($xpathObject, self::REL_EISSN,$journalNode);
        $journaltitle = $this->getNodesValue($xpathObject, self::REL_JTITLE,$journalNode);
        $shortname = ''; // inutile ici: garde pour voir la compatibilite...
        if (is_array($journaltitle)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "journaltitle is an array(first: " .  $journaltitle[0] . ")");
        }
        if (is_array($shortname)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "shortname is an array(first: " .  $shortname[0] . ")");
        }
        if (is_array($issn)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "issn is an array (first: " .  $issn[0] . ")");
        }
        if (is_array($eissn)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "eissn is an array (first: " .  $eissn[0] . ")");
        }
        // recherche dans le refentiel HAL
        $param = 'title_t:"' . $journaltitle . '" OR issn_s:"' . $issn . '" OR eissn_s:"' . $eissn . '"';

        $solrResult = Ccsd_Referentiels_Journal::search($param, 1);
        if (isset($solrResult[0]['docid']))
            return new Ccsd_Referentiels_Journal($solrResult[0]['docid']);
        else
            return new Ccsd_Referentiels_Journal(0, ['VALID' => 'INCOMING', 'JID' => '', 'JNAME' => $journaltitle, 'SHORTNAME' => $shortname, 'ISSN' => $issn, 'EISSN' => $eissn, 'PUBLISHER' => '', 'URL' => '']);
    }


    /**
     * @param string $pathAuthors  (xpath string)
     * @return array
     */
    public function getAuthors($pathAuthors) {
        $authors = [];
        $xpathObject = $this->getDomPath();
        $nodeAuthors = $xpathObject->query($pathAuthors);
        foreach ($nodeAuthors as $node) {
            $author = [];
            $firstNames = $this->getNodesValue($xpathObject, self::FIRSTNAME, $node);
            if (!empty($firstNames)) {
                $author['firstname'] = self::cleanFirstname($firstNames);
            }
            $lastNames = $this->getNodesValue($xpathObject,  self::LASTNAME, $node);
            if (!empty($lastNames)) {
                $author['lastname'] = $lastNames;
            }

            $initial = $this->getNodesValue($xpathObject,  self::INITIALS, $node);
            if (!empty($orcIds)) {
                $author['initial'] = $initial;
            }
            // $affiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT . self::REL_XPATH_AUT_AFFILIATION);
            // $affiliations = $this->getInraAffiliation($affiliationsNode);
            // if (!empty($affiliations)) {
            //    $author['affiliation'] = $affiliations;
            //}
            //$extAffiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT . self::REL_XPATH_EXTAUT_EXTAFFILIATION);
            //$extAffiliations = $this->getExtAffiliation($extAffiliationsNode);
            //if (!empty($extAffiliations)) {
            //    $author['affiliation externe'] = $extAffiliations;
            //}

            //$emails = $this->getValue(self::REL_ROOT_AUT . self::REL_XPATH_AUT_EMAIL, $domxpathAut);
            //if (!empty($emails)) {
            //    $author['email'] = $emails;
            //}
            if (!empty($author)) {
                $authors[] = $author;
            }
        }
        return $authors;
    }

    /**
     * @param string $defaultLang
     * @return array|string
     */
    public function getTitle($defaultLang='en')
    {
        $xpathObject = $this->getDomPath();
        $title = $this->getNodesValue($xpathObject, self::ABS_TITLE);
        $title = empty($title) ? "" : $title;

        // Transformation du titre en tableau avec la clé comme langue
        $title = $this->metasToLangArray($title, $defaultLang);
        return $title;
    }

    //-----------------------------------------------------------------
    // A mettre dans Externe DOC avec un peu plus de genericite dans les nom de constant et utilisation de static::

    /**
     * Traduction de la page
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPage() {
        $xpathObject = $this->getDomPath();
        return $this->getNodesValue($xpathObject, self::ABS_PAGE);
    }

    /**
     * @return string|string[]
     */
    public function getDocLang() {
        $xpathObject = $this->getDomPath();
        return $this->getNodesValue($xpathObject, self::ABS_LANG);
    }

    /**
     * @return string|string[]
     */
    public function getVolume() {
        $xpathObject = $this->getDomPath();
        return $this->getNodesValue($xpathObject, self::ABS_VOLUME);
    }

    /**
     * @return string|string[]
     */
    public function getIssue() {
        $xpathObject = $this->getDomPath();
        return $this->getNodesValue($xpathObject, self::ABS_ISSUE);
    }

    /**
     * Traduction de la page
     * @return string|string[]
     */
    public function getAbstract($defaultLang='en') {
        $xpathObject = $this->getDomPath();
        $abstract = $this->getNodesValue($xpathObject, self::ABS_ABSTRACT);

        $abstract = empty($abstract) ? "" : $abstract;

        // Transformation du titre en tableau avec la clé comme langue
        $abstract = $this->metasToLangArray($abstract, $defaultLang);
        return $abstract;
    }

    /**
     * @return string|string[]
     */
     public function getKeywords() {
         $xpathObject = $this->getDomPath();
         return $this->getNodesValue($xpathObject, self::ABS_KEYWORD);
     }
      /**
     * @return string|string[]
     */
     public function getIdentifier() {
         $xpathObject = $this->getDomPath();
         $nodesList = $xpathObject -> query(self::ABS_IDENTIFIER);
         $idList = [];
         foreach ($nodesList as $node) {
             /** @var DOMElement $node */
             $value = $node -> nodeValue;
             $typeId = $node ->getAttribute('IdType');
             switch ($typeId) {
                 case 'doi':
                 case 'pubmed':
                     $idList[$typeId] = $value;
                     break;
                 default:
                     // Id ignored
             }
         }
         return $idList;
     }

}

Ccsd_Externdoc_Pubmed::registerType("/PubmedArticleSet/PubmedArticle", "Ccsd_Externdoc_Pubmed_Article");