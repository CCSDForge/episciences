<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 01/09/17
 * Time: 15:50
 */

class Episciences_Paper_Tei
{
    private $_paper;

    /**
     * @var Episciences_Review
     */
    private $_review;
    private $_languages;
    private $_translator;
    private $_defaultLocale;

    /**
     * Episciences_Paper_Tei constructor.
     * @param Episciences_Paper $paper
     * @throws Zend_Exception
     */
    public function __construct(Episciences_Paper $paper)
    {
        $this->setPaper($paper);

        $review = Episciences_ReviewsManager::find($paper->getRvid());
        $review->loadSettings();
        $this->setReview($review);

        $this->setLanguages(Episciences_Tools::getLanguages());

        $translator = Zend_Registry::get('Zend_Translate');

        // load journal translations in context of OAI (eg volumes ; sections)
        if ((APPLICATION_MODULE === 'oai') && is_dir($review->getTranslationsPath()) && count(scandir($review->getTranslationsPath())) > 2) {
            $translator->addTranslation($review->getTranslationsPath());
        }

        $this->setTranslator($translator);
    }

    /**
     * @param Episciences_Review $review
     */
    private function setReview(Episciences_Review $review)
    {
        $this->_review = $review;
    }

    /**
     * @return mixed
     */
    public function getLanguages()
    {
        return $this->_languages;
    }

    /**
     * @param mixed $languages
     */
    public function setLanguages($languages)
    {
        if (array_key_exists('en', $languages)) {
            $defaultLocale = 'en';
        } else {
            reset($languages);
            $defaultLocale = key($languages);
        }
        $this->setDefaultLocale($defaultLocale);
        $this->_languages = $languages;
    }

    /**
     * @return mixed
     */
    public function getTranslator()
    {
        return $this->_translator;
    }

    /**
     * @param mixed $translator
     */
    public function setTranslator($translator)
    {
        $this->_translator = $translator;
    }

    /**
     * generate XML TEI
     * @return string
     */
    public function generateXml()
    {
        // xml init and settings
        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');
        $xml->formatOutput = false;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;

        // generate headers
        $root = $xml->createElement('TEI');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.tei-c.org/ns/1.0');
        $xml->appendChild($root);

        // get head section
        $root->appendChild($this->generateXmlHead($xml));
        // get text section
        $root->appendChild($this->generateXmlText($xml));
        // save to string
        return $xml->saveXML($xml->documentElement);

    }

    /**
     * generate head section of XML TEI
     * @param DOMDocument $xml
     * @return DOMElement
     */
    private function generateXmlHead(DOMDocument $xml)
    {
        // HEAD ********************************
        $head = $xml->createElement('teiHeader');

        $fd = $xml->createElement('fileDesc');
        $ts = $xml->createElement('titleStmt');
        $title = $xml->createElement('title', 'Episciences.org TEI export of ' . $this->getPaper()->getCitation());
        $ts->appendChild($title);
        $fd->appendChild($ts);

        $ps = $xml->createElement('publicationStmt');
        $ps->appendChild($xml->createElement('distributor', 'CCSD - Episciences'));
        $headeravailability = $xml->createElement('availability');
        $headeravailability->setAttribute('status', 'restricted');
        $enrichmentLicence = $this->getPaper()->getLicence();
        if ($enrichmentLicence !== "") {
            $headerlicence = $xml->createElement('licence', Ccsd_Tools::translate($enrichmentLicence));
            $headerlicence->setAttribute('target', $enrichmentLicence);
            $headeravailability->appendChild($headerlicence);
        }
        $ps->appendChild($headeravailability);
        $headerdate = $xml->createElement('date');
        $headerdate->setAttribute('when', date('Y-m-d', strtotime($this->getPaper()->getPublication_date())));
        $ps->appendChild($headerdate);
        $fd->appendChild($ps);

        $sourceDesc = $xml->createElement('sourceDesc');
        $p = $xml->createElement('p', 'Episciences.org API platform');

        $sourceDesc->appendChild($p);
        $fd->appendChild($sourceDesc);

        $head->appendChild($fd);

        $comments = $this->getComments();
        // notesStmt > note type='commentary'
        if (!empty($comments)) {
            $ns = $xml->createElement('notesStmt');
            foreach ($comments as $locale => $t) {
                $c = $xml->createElement('note', $t);
                if (Zend_Locale::isLocale($locale)) {
                    $c->setAttribute('xml:lang', $locale);
                }
                $c->setAttribute('type', 'commentary');
                $ns->appendChild($c);
            }
            $head->appendChild($ns);
        }

        return $head;

    }

    /**
     * @return array
     */
    private function getComments()
    {
        $comments = array();
        foreach ($this->getPaper()->getAllAbstracts() as $locale => $abstract) {
            if (is_array($abstract)) {
                $locale = array_key_first($abstract);
                $abstractText = array_shift($abstract);
                $abstractText = $this->cleanComment($abstractText);
                if ($this->isComment($abstractText)) {
                    $comments[][$locale] = $abstractText;
                }

            } else {
                $abstract = $this->cleanComment($abstract);
                // sort comments from abstracts
                if ($this->isComment($abstract)) {
                    $comments[$locale] = $abstract;
                }
            }
        }
        return $comments;
    }

    private function cleanComment(string $comment)
    {
        return trim(preg_replace("/\r|\n/", " ", $comment));
    }

    private function isComment(string $comment): bool
    {
        if (stripos($comment, 'comment:') === 0) {
            return true;
        }
        return false;
    }

    /**
     * generate text section of XML TEI
     * @param DOMDocument $xml
     * @return DOMElement
     * @throws DOMException
     */
    private function generateXmlText(DOMDocument $xml)
    {
        // create text section
        $text = $xml->createElement('text');
        // create body section
        $body = $xml->createElement('body');
        // create listBibl section
        $lb = $xml->createElement('listBibl');
        // create biblFull section
        $b = $xml->createElement('biblFull');
        // create back section
        $back = $xml->createElement('back');

        // add titleStmt section to biblFull
        $b->appendChild($this->generateXmlTextTitleStmt($xml));
        // add editionStmt section to biblFull
        $b->appendChild($this->generateXmlTextEditionStmt($xml));
        // add publicationStmt section to biblFull
        $b->appendChild($this->generateXmlTextPublicationStmt($xml));
        // add sourceDesc section to biblFull
        $b->appendChild($this->generateXmlTextSourceDesc($xml));
        // add profileDesc section to biblFull
        $b->appendChild($this->generateXmlTextProfileDesc($xml));

        // add biblFull section to listBibl
        $lb->appendChild($b);
        // add listBibl section to body
        $body->appendChild($lb);
        // add body section to text
        $text->appendChild($body);
        // add back section to text
        $back->appendChild($this->generateXmlBack($xml));
        $text->appendChild($back);

        return $text;
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     * @throws DOMException
     */
    private function generateXmlTextTitleStmt(DOMDocument $xml)
    {
        $ts = $xml->createElement('titleStmt');

        foreach ($this->getPaper()->getAllTitles() as $locale => $t) {
            if (is_array($t)) {
                foreach ($t as $tLang => $title) {
                    $tit = $xml->createElement('title', $title);
                    if (Zend_Locale::isLocale($tLang)) {
                        $tit->setAttribute('xml:lang', $tLang);
                    }
                    $ts->appendChild($tit);
                }
            } else {
                $tit = $xml->createElement('title', $t);
                if (Zend_Locale::isLocale($locale)) {
                    $tit->setAttribute('xml:lang', $locale);
                }
                $ts->appendChild($tit);
            }


        }
        $enrichmentAuthors = $this->getPaper()->getAuthorsWithAffiNumeric();

        foreach ($this->getPaper()->getMetadata('authors') as $order => $author) {
            $firstname = '';
            $lastname = '';
            if (str_contains($author, ',')) {
                [$lastname, $firstname] = explode(', ', $author);
            } else {
                $lastname = $author;
            }
            $aut = $xml->createElement('author');
            $aut->setAttribute('role', 'aut');
            $persName = $xml->createElement('persName');
            $first = $xml->createElement('forename', $firstname);
            $first->setAttribute('type', 'first');
            $persName->appendChild($first);
            $persName->appendChild($xml->createElement('surname', $lastname));

            $aut->appendChild($persName);
            $aut->appendChild($xml->createElement('email'));

            if (isset($enrichmentAuthors['authors'][$order]) && is_array($enrichmentAuthors['authors'][$order]) && array_key_exists('orcid', $enrichmentAuthors['authors'][$order])) {
                $orcid = $xml->createElement('idno', $enrichmentAuthors['authors'][$order]['orcid']);
                $orcid->setAttribute('type', 'ORCID');
                $aut->appendChild($orcid);
            }
            $enrichmentAuthors = $this->getAuthorAffiliation($enrichmentAuthors, $order, $xml, $aut, $ts);
        }

        return $ts;
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     * @throws DOMException
     */
    private function generateXmlTextEditionStmt(DOMDocument $xml)
    {
        $submitter = $this->getSubmitter();

        // ** editionStmt ****************************************
        $es = $xml->createElement('editionStmt');
        $edition = $xml->createElement('edition');
        $d = $xml->createElement('date', $this->getPaper()->getSubmission_date());
        $d->setAttribute('type', 'whenSubmitted');
        $edition->appendChild($d);
        $d = $xml->createElement('date', $this->getPaper()->getPublication_date());
        $d->setAttribute('type', 'whenProduced');
        $edition->appendChild($d);
        $ref = $xml->createElement('ref');
        $ref->setAttribute('type', 'file');

        $ref->setAttribute('target', $this->getReview()->getUrl() . '/' . $this->getPaper()->getPaperid() . '/pdf');
        $edition->appendChild($ref);
        $es->appendChild($edition);

        // ** respStmt ****************************************
        $respStmt = $xml->createElement('respStmt');
        $respStmt->appendChild($xml->createElement('resp', 'contributor'));
        $name = $xml->createElement('name');
        $name->setAttribute('key', $submitter->getUid());
        $persName = $xml->createElement('persName');
        $persName->appendChild($xml->createElement('forename', $submitter->getFirstname()));
        $persName->appendChild($xml->createElement('surname', $submitter->getLastname()));
        $name->appendChild($persName);
        $name->appendChild($xml->createElement('email', $submitter->getEmail()));
        $respStmt->appendChild($name);
        $es->appendChild($respStmt);

        return $es;
    }

    /**
     * @return Episciences_User
     */
    public function getSubmitter()
    {
        $submitter = new Episciences_User();
        $submitter->findWithCAS($this->getPaper()->getUid());

        return $submitter;
    }

    /**
     * @return null|Episciences_Review
     */
    private function getReview()
    {
        return $this->_review;
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     */
    private function generateXmlTextPublicationStmt(DOMDocument $xml)
    {

        $ps = $xml->createElement('publicationStmt');
        $ps->appendChild($xml->createElement('distributor', 'CCSD'));

        $id = $xml->createElement('idno', $this->getReview()->getCode() . ':' . $this->getPaper()->getPaperid());
        $id->setAttribute('type', 'id');
        $ps->appendChild($id);

        $id = $xml->createElement('idno', $this->getReview()->getUrl() . '/' . $this->getPaper()->getPaperid());
        $id->setAttribute('type', 'url');
        $ps->appendChild($id);

        $id = $xml->createElement('idno', $this->getPaper()->getCitation());
        $id->setAttribute('type', 'ref');
        $ps->appendChild($id);

        $enrichmentLicence = $this->getPaper()->getLicence();

        if ($enrichmentLicence !== "") {
            $licence = $xml->createElement('licence', Ccsd_Tools::translate($enrichmentLicence));
            $licence->setAttribute('target', $enrichmentLicence);
            $ps->appendChild($licence);
        }

        return $ps;
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     */
    private function generateXmlTextSourceDesc(DOMDocument $xml)
    {
        $sourceDesc = $xml->createElement('sourceDesc');
        $biblStruct = $xml->createElement('biblStruct');
        $analytic = $xml->createElement('analytic');

        foreach ($this->getPaper()->getAllTitles() as $locale => $t) {
            if (!Zend_Locale::isLocale($locale)) {
                continue;
            }
            $title = $xml->createElement('title', $t);
            $title->setAttribute('xml:lang', $locale);
            $analytic->appendChild($title);
        }

        $enrichmentAuthors = $this->getPaper()->getAuthorsWithAffiNumeric();

        foreach ($this->getPaper()->getMetadata('authors') as $order => $author) {
            $firstname = '';
            if (str_contains($author, ',')) {
                [$lastname, $firstname] = explode(', ', $author);
            } else {
                $lastname = $author;
            }
            $aut = $xml->createElement('author');
            $aut->setAttribute('role', 'aut');
            $persName = $xml->createElement('persName');
            $first = $xml->createElement('forename', $firstname);
            $first->setAttribute('type', 'first');
            $persName->appendChild($first);
            $persName->appendChild($xml->createElement('surname', $lastname));
            $aut->appendChild($persName);
            $aut->appendChild($xml->createElement('email'));

            if (
                isset($enrichmentAuthors['authors'][$order]) &&
                (is_array($enrichmentAuthors['authors'][$order])) && (array_key_exists('orcid', $enrichmentAuthors['authors'][$order]))) {
                $orcid = $xml->createElement('idno', $enrichmentAuthors['authors'][$order]['orcid']);
                $orcid->setAttribute('type', 'ORCID');
                $aut->appendChild($orcid);
            }
            $enrichmentAuthors = $this->getAuthorAffiliation($enrichmentAuthors, $order, $xml, $aut, $analytic);
        }
        $biblStruct->appendChild($analytic);

        $monogr = $xml->createElement('monogr');

        $identifier = $xml->createElement('idno', $this->getPaper()->getIdentifier());
        $identifier->setAttribute('type', Episciences_Repositories::getLabel($this->getPaper()->getRepoid()));
        $monogr->appendChild($identifier);

        if ($this->getReview()->getSetting('ISSN')) {
            $journal = $xml->createElement('idno', Ccsd_View_Helper_FormatIssn::FormatIssn($this->getReview()->getSetting('ISSN')));
            $journal->setAttribute('type', 'issn');
            $monogr->appendChild($journal);
        }

        $journal = $xml->createElement('title', $this->getReview()->getName());
        $journal->setAttribute('level', 'j');
        $monogr->appendChild($journal);

        $imprint = $xml->createElement('imprint');
        $publisher = $this->getReview()->getSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER) ?? DOMAIN;
        $imprint->appendChild($xml->createElement('publisher', ucfirst(trim($publisher))));
        $publisherLoc = $this->getReview()->getSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER_LOC);
        if ($publisherLoc){
            $imprint->appendChild($xml->createElement('pubPlace', ucfirst(trim($publisherLoc))));
        }
        $volumeName = $this->getVolumeName($this->getDefaultLocale());
        if ($volumeName) {
            $vn = $xml->createElement('biblScope', $volumeName);
            $vn->setAttribute('unit', 'volume');
            $imprint->appendChild($vn);
        }

        $sectionName = $this->getSectionName($this->getDefaultLocale());
        if ($sectionName) {
            $sn = $xml->createElement('biblScope', $sectionName);
            $sn->setAttribute('unit', 'issue');
            $imprint->appendChild($sn);
        }

        if ($this->getPaper()->getPublication_date()) {
            $d = $xml->createElement('date', date(DateTime::ATOM, strtotime($this->getPaper()->getPublication_date())));
            $d->setAttribute('type', 'datePub');
            $imprint->appendChild($d);
        }

        $monogr->appendChild($imprint);
        $biblStruct->appendChild($monogr);

        if ($this->getPaper()->getDoi()) {
            $doi = $xml->createElement('idno', $this->getPaper()->getDoi());
            $doi->setAttribute('type', 'doi');
            $biblStruct->appendChild($doi);
        }
        $enrichmentLinkedData = $this->getPaper()->getLinkedData();
        foreach ($enrichmentLinkedData as $linkedData) {
            $relatedItem = $xml->createElement('relatedItem');
            if (!is_null($linkedData['relationship'])) {
                $relatedItem->setAttribute('type', $linkedData['relationship']);
            }
            $urlTargetLinkedData = Episciences_Paper_DatasetsManager::getUrlLinkedData($linkedData['value'], $linkedData['link']);
            if ($urlTargetLinkedData !== '') {
                $relatedItem->setAttribute('target', $urlTargetLinkedData);
                $biblStruct->appendChild($relatedItem);
            }
        }
        $sourceDesc->appendChild($biblStruct);

        return $sourceDesc;
    }

    private function getVolumeName($locale = null): string
    {
        $volumeName = '';

        $vid = $this->getPaper()->getVid();
        if ($vid) {
            $volume = Episciences_VolumesManager::find($vid);
            if ($volume instanceof Episciences_Volume) {
                $volumeName = $volume->getName($locale);
            }
        }
        return $volumeName;
    }

    /**
     * @return Episciences_Paper|null
     */
    public function getPaper()
    {
        return $this->_paper;
    }

    /**
     * @param Episciences_Paper $paper
     */
    public function setPaper(Episciences_Paper $paper)
    {
        $this->_paper = $paper;
    }

    /**
     * @return mixed
     */
    public function getDefaultLocale()
    {
        return $this->_defaultLocale;
    }

    /**
     * @param mixed $defaultLocale
     */
    public function setDefaultLocale($defaultLocale)
    {
        $this->_defaultLocale = $defaultLocale;
    }

    private function getSectionName($locale = null): string
    {
        $sectionName = '';
        $sid = $this->getPaper()->getSid();
        if ($sid) {
            $section = Episciences_SectionsManager::find($sid);
            if ($section instanceof Episciences_Section) {
                $sectionName = $section->getName($locale);
            }
        }
        return $sectionName;
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     * @throws DOMException
     */
    private function generateXmlTextProfileDesc(DOMDocument $xml)
    {
        $profileDesc = $xml->createElement('profileDesc');

        if ($this->getPaper()->getMetadata('language')) {
            $langUsage = $xml->createElement('langUsage');
            $languageNode = $xml->createElement('language', Zend_Locale::getTranslation($this->getPaper()->getMetadata('language'), 'language', 'en'));
            $languageNode->setAttribute('ident', $this->getPaper()->getMetadata('language'));
            $langUsage->appendChild($languageNode);
            $profileDesc->appendChild($langUsage);
        }

        $textClassNode = $xml->createElement('textClass');
        $keywordsNode = $xml->createElement('keywords');


        $keywordsNode->setAttribute('scheme', 'author');

        $subjects = $this->getPaper()->getMetadata('subjects');

        if (is_array($subjects)) {
            foreach ($subjects as $lang => $keyword) {

                if (is_array($keyword)) {
                    foreach ($keyword as $kwdLang => $kwd) {
                        $termNode = $xml->createElement('term', $kwd);
                        if (Zend_Locale::isLocale($kwdLang)) {
                            $termNode->setAttribute('xml:lang', $kwdLang);
                        }
                        $keywordsNode->appendChild($termNode);
                    }
                } else {
                    $termNode = $xml->createElement('term', $keyword);
                    if (Zend_Locale::isLocale($lang)) {
                        $termNode->setAttribute('xml:lang', $lang);
                    }
                    $keywordsNode->appendChild($termNode);
                }
            }
        }
        $textClassNode->appendChild($keywordsNode);
        $profileDesc->appendChild($textClassNode);

        foreach ($this->getAbstracts() as $locale => $abstractText) {

            if (is_array($abstractText)) {
                $locale = array_key_first($abstractText);
                $abstractText = array_shift($abstractText);
            }

            $abstractText = trim($abstractText);
            $abstractNode = $xml->createElement('abstract');
            if (Zend_Locale::isLocale($locale)) {
                $abstractNode->setAttribute('xml:lang', $locale);
            }
            $p = $xml->createElement('p', $abstractText);
            $abstractNode->appendChild($p);
            $profileDesc->appendChild($abstractNode);
        }


        return $profileDesc;
    }

    /**
     * Get an array of abstracts
     * @return array
     */
    private function getAbstracts()
    {
        $abstracts = [];
        foreach ($this->getPaper()->getAllAbstracts() as $locale => $abstract) {
            if (is_array($abstract)) {
                $abstractLang = array_key_first($abstract);
                $abstractText = array_shift($abstract);
                $abstractText = $this->cleanAbstract($abstractText);
                $abstracts[][$abstractLang] = $abstractText;
            } else {
                $abstract = $this->cleanAbstract($abstract);
                $abstracts[$locale] = $abstract;
            }
        }

        return $abstracts;
    }

    /**
     * @param string $abstract
     * @return string
     */
    private function cleanAbstract(string $abstract): string
    {
        return trim(preg_replace("/\r|\n/", " ", $abstract));
    }

    /**
     * @param DOMDocument $xml
     * @return DOMElement
     * @throws DOMException
     */
    private function generateXmlBack(DOMDocument $xml)
    {

        $listOrg = $xml->createElement('listOrg');
        $listAffiliations = $this->getPaper()->getAuthorsWithAffiNumeric();
        if (!empty($listAffiliations["affiliationNumeric"])) {
            foreach ($listAffiliations["affiliationNumeric"] as $affiIndex => $affi) {
                $org = $xml->createElement('org');
                $org->setAttribute('xml:id', "struct-" . array_search($affiIndex, array_keys($listAffiliations["affiliationNumeric"])));

                if (array_key_exists('type', $affi) && !is_null($affi['type'])) {
                    $idno = $affi['type'] === "ROR" ? $xml->createElement('idno', $affi['url']) : $xml->createElement('idno');
                    $idno->setAttribute('type', $affi['type']);
                    $org->appendChild($idno);
                }
                $orgNameAcronym = "";
                $orgName = $xml->createElement('orgName', $affi['name']);
                if (array_key_exists('acronym', $affi)) {
                    $orgNameAcronym = $xml->createElement('orgName');
                    $orgNameAcronym->setAttribute('acronym', $affi['acronym']);
                }
                $org->appendChild($orgName);
                if ($orgNameAcronym !== "") {
                    $org->appendChild($orgNameAcronym);
                }
                $listOrg->appendChild($org);

            }
        }

        return $listOrg;
    }

    /**
     * @param array $enrichmentAuthors
     * @param int|string $order
     * @param DOMDocument $xml
     * @param bool|DOMElement $aut
     * @param bool|DOMElement $ts
     * @return array
     * @throws DOMException
     */
    private function getAuthorAffiliation(array $enrichmentAuthors, int|string $order, DOMDocument $xml, bool|DOMElement $aut, bool|DOMElement $ts): array
    {
        if (isset($enrichmentAuthors['authors'][$order]['idAffi'])) {
            foreach ($enrichmentAuthors['authors'][$order]['idAffi'] as $index => $affiNum) {
                $affiAuthorList = $xml->createElement('affiliation');
                $affiAuthorList->setAttribute("ref", "#struct-" . array_search($index, array_keys($enrichmentAuthors['affiliationNumeric']), true));
                $aut->appendChild($affiAuthorList);
            }
        }
        $ts->appendChild($aut);
        return $enrichmentAuthors;
    }


}
