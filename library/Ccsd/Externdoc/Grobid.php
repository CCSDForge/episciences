<?php

class Ccsd_Externdoc_Grobid extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_idtype = "pdf";

    const INTER_HEADERDATE   = "headerdate";
    const INTER_JOURNALDATE  = "journaldate";
    const INTER_AUTHOR       = "author";
    const INTER_FORENAME     = "firstname";
    const INTER_MIDDLENAME   = "middlename";
    const INTER_LASTNAME     = "lastname";
    const INTER_FIRSTPAGE    = "fpage";
    const INTER_EMAIL        = "email";
    const INTER_QUALITY      = "quality";
    const INTER_LASTPAGE     = "lpage";
    const INTER_ISSN         = "issn";
    const INTER_EISSN        = "eissn";
    const INTER_JOURNALTITLE = "journaltitle";

    const STRUCTURE = "structure";
    const STRUCT_NAME = "structname";
    const STRUCT_TYPE = "typestruct";
    const STRUCT_COUNTRY = "paysid";
    const STRUCT_ADDRESS = "address";


    protected $_traductionArray = array(
        self::META_LANG          => '/xmlns:TEI/xmlns:teiHeader/@xml:lang',
        self::META_TITLE         => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:titleStmt/xmlns:title',
        self::META_ABSTRACT      => '/xmlns:TEI/xmlns:teiHeader/xmlns:profileDesc/xmlns:abstract/xmlns:p',
        self::META_VOLUME        => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:imprint/xmlns:biblScope[@unit="volume"]',
        self::META_IDENTIFIER    => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:idno[@type="DOI"]',
        self::META_KEYWORD       => '/xmlns:TEI/xmlns:teiHeader/xmlns:profileDesc/xmlns:textClass/xmlns:keywords/xmlns:term',
        self::INTER_HEADERDATE   => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:imprint/xmlns:date[@type="published"]',
        self::INTER_JOURNALDATE  => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:publicationStmt/xmlns:date[@type="published"]',
        self::INTER_AUTHOR       => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author',
        self::INTER_FORENAME     => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author/xmlns:persName/xmlns:forename[@type="first"]',
        self::INTER_MIDDLENAME   => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author/xmlns:persName/xmlns:forename[@type="middle"]',
        self::INTER_LASTNAME     => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author/xmlns:persName/xmlns:surname',
        self::INTER_EMAIL        => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author/xmlns:email',
        self::INTER_QUALITY      => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author/xmlns:role',
        self::INTER_FIRSTPAGE    => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:imprint/xmlns:biblScope[@unit="page"]/@from',
        self::INTER_LASTPAGE     => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:imprint/xmlns:biblScope[@unit="page"]/@to',
        self::INTER_ISSN         => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:idno[@type="ISSN"]',
        self::INTER_EISSN        => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:idno[@type="eISSN"]',
        self::INTER_JOURNALTITLE => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:monogr/xmlns:title'
    );

    protected $_xpathAuthor = [
        self::INTER_AUTHOR => '/xmlns:TEI/xmlns:teiHeader/xmlns:fileDesc/xmlns:sourceDesc/xmlns:biblStruct/xmlns:analytic/xmlns:author',
        self::INTER_FORENAME => 'xmlns:persName/xmlns:forename[@type="first"]',
        self::INTER_MIDDLENAME => 'xmlns:persName/xmlns:forename[@type="middle"]',
        self::INTER_LASTNAME => 'xmlns:persName/xmlns:surname',
        self::INTER_EMAIL => 'xmlns:email',
        self::INTER_QUALITY => 'xmlns:role',
        self::STRUCTURE => 'xmlns:affiliation',
        self::STRUCT_NAME => 'xmlns:orgName',
        self::STRUCT_COUNTRY => 'xmlns:address/xmlns:country/@key',
        self::STRUCT_ADDRESS => 'xmlns:address',
    ];


    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array(
        self::META_DATE => [self::INTER_HEADERDATE, self::INTER_JOURNALDATE],
        self::META_PAGE => [self::INTER_FIRSTPAGE, self::INTER_LASTPAGE],
        self::META_JOURNAL => [self::INTER_ISSN, self::INTER_EISSN, self::INTER_JOURNALTITLE]
    );

    protected $_interToAuthors = [self::AUTHORS =>[] ];


    protected $_xmlNamespace = array(
        'xmlns' => 'http://www.tei-c.org/ns/1.0',
    );

    /**
     * Ccsd_Externdoc_Document constructor.
     * @param string $id
     * @param DOMDocument $metas
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new self($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
        $doc->registerNamespace();

        if (!$doc->buildMetadatas()) {
            return null;
        }

        return $doc;
    }

    /**
     * 2- Traduction du XML en métadonnées intermédiaires grâce à un tableau de correspondance (NomMetaIntermediaire => XPath) défini dans la Classe Enfant
     *
     * @param $xpath : DOMXPath
     *
     * @return $interMetas : array
     */
    protected function xmlToInterMetas($xpath)
    {
        $interMetas = [];

        foreach ($this->_traductionArray as $interMeta => $value) {
            $children = $xpath->query($value);

            if (isset($children)) {
                // Children : tableau de DOMElements
                // Unique élément : l'élément est une string
                if ($children->length == 1) {
                    foreach ($children as $child) {
                        $interMetas[$interMeta] = Ccsd_Tools::space_clean($child->nodeValue);
                    }
                    // Multiple éléments : ajoutés dans un tableau
                } else if ($children->length > 1) {
                    if (!isset($interMetas[$interMeta]))
                        $interMetas[$interMeta] = array();

                    foreach ($children as $child) {
                        array_push($interMetas[$interMeta], Ccsd_Tools::space_clean($child->nodeValue));
                    }
                }
            }
        }

        //Récupération des auteurs / affiliations à partir du PDF
        /** @var Ccsd_Externdoc_Grobid $this */
        $interMetas = array_merge($interMetas, $this->loadAuthors($xpath));

        return $interMetas;
    }

    /** Date de publication de l'article
     *
     * @param $headerdate : $interMetas[$internames[0]] string
     * @param $journaldate : $interMetas[$internames[1]] string
     *
     * @return string journaldate si elle existe, headerdate sinon
     */
    public function treatdate($interMetas, $internames)
    {
        if (isset($interMetas[$internames[1]]))
            return $interMetas[$internames[1]];
        else if (isset($interMetas[$internames[0]]))
            return $interMetas[$internames[0]];
        else
            return "";

    }

    /** Traduction de la page :
     *
     * @param $firstpage : $interMetas[$internames[0]] string
     * @param $lastpage : $interMetas[$internames[1]] string
     *
     * @return string
     */

    public function treatpage($interMetas, $internames)
    {
        if(isset($interMetas[$internames[0]]) && isset($interMetas[$internames[1]]))
            return $interMetas[$internames[0]] . ' - '. $interMetas[$internames[1]];
        else if(isset($interMetas[$internames[0]]) && !isset($interMetas[$internames[1]]))
            return $interMetas[$internames[0]] . ' - '. $interMetas[$internames[0]];
        else if(!isset($interMetas[$internames[0]]) && isset($interMetas[$internames[1]]))
            return $interMetas[$internames[1]] . ' - '. $interMetas[$internames[1]];
        else
            return '';
    }

    /** Traduction de la page :
     *
     * @param $ISSN : $interMetas[$internames[0]] string
     * @param $EISSN : $interMetas[$internames[1]] string
     * @param $JOURNALTITLE : $interMetas[$internames[2]] string or array
     *
     * @return string
     */

    public function treatjournal($interMetas, $internames)
    {
        $i=0;

        while (isset($internames[$i])) {

            if (isset($interMetas[$internames[$i]])) {

                // On prend le premier journal trouvé s'il y en a plusieurs
                if (is_array($interMetas[$internames[$i]]))
                    $interMetas[$internames[$i]] =  $interMetas[$internames[$i]][0];
            } else {
                $interMetas[$internames[$i]] = "";
            }

            $i++;
        }

        return $this->formateJournal($interMetas[$internames[2]], "", $interMetas[$internames[0]], $interMetas[$internames[1]]);
    }


    /**
     * POur les métadonnées auteur, on récupère directemetn ce qui a été trouvé dans la méthode loadAuthors
     * @param $interMetas
     * @param $internames
     * @return mixed
     */
    public function treatauthors($interMetas, $internames)
    {
        if (isset($interMetas[self::AUTHORS])) {
            return $interMetas[self::AUTHORS];
        }

        return [];
    }

    /**
     * Récupération des auteurs / affiliations en provenance du fichier XML
     * @param DOMXPath $xpath
     * @return array
     */
    public function loadAuthors($xpath)
    {
        $result = [self::AUTHORS => [], self::STRUCTURES => []];
        $affiliations = [];

        foreach($xpath->query($this->_xpathAuthor[self::INTER_AUTHOR]) as $entry) {
            $author = [];

            $author[self::INTER_LASTNAME] = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::INTER_LASTNAME], $entry);
            $author[self::INTER_FORENAME] = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::INTER_FORENAME], $entry);

            if (empty($author[self::INTER_LASTNAME]) || empty($author[self::INTER_FORENAME])) {
                //pas de nom/prénom, on passe au suivant
                continue;
            }

            $tmp = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::INTER_MIDDLENAME], $entry);
            if ($tmp) {
                $author[self::INTER_MIDDLENAME] = $tmp;
            }

            $tmp = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::INTER_EMAIL], $entry);
            if ($tmp) {
                $author[self::INTER_EMAIL] = $tmp;
            }

            $tmp = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::INTER_QUALITY], $entry);
            if ($tmp && $tmp == 'corresp') {
                $author[self::INTER_QUALITY] = 'crp';
            } else {
                $author[self::INTER_QUALITY] = 'aut';
            }

            //Récupération des structures de l'auteur
            $nodeList = $xpath->query($this->_xpathAuthor[self::STRUCTURE], $entry);
            if ($nodeList->length != 0) {
                $author[self::STRUCTURES] = [];
                //Toutes les affilaitons sont dans la première balise
                $nodeAffiliations = $nodeList[0];
                $nodeList = $xpath->query($this->_xpathAuthor[self::STRUCT_NAME], $nodeAffiliations);
                foreach ($nodeList as $node) {
                    $structure = [];
                    $structure[self::STRUCT_NAME] = Ccsd_Tools::space_clean($node->nodeValue);
                    $structure[self::STRUCT_TYPE] = Ccsd_Tools::space_clean($node->getAttribute('type'));

                    $structure[self::STRUCT_COUNTRY] = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::STRUCT_COUNTRY], $nodeAffiliations);
                    $structure[self::STRUCT_ADDRESS] = $this->getFirstValueFromXPath($xpath, $this->_xpathAuthor[self::STRUCT_ADDRESS], $nodeAffiliations);

                    $structid = $this->addStructure($affiliations, $structure);
                    $author[self::STRUCTURES][] = $structid;
                }
            }
            $result[self::AUTHORS][] = $author;
        }
        $result[self::STRUCTURES] = $affiliations;
        return $result;
    }

    protected function addStructure(&$structures, $newStructure)
    {
        foreach ($structures as $i => $structure) {
            if ($structure == $newStructure) {
                return $i;
            }
        }
        $i = count($structures);
        $structures[] = $newStructure;
        return $i;
    }
}