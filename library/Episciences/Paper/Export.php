<?php

namespace Episciences\Paper;

use Ccsd_DOMDocument;
use Ccsd_View_Helper_FormatIssn;
use DateTime;
use DOMDocument;
use DOMException;
use Episciences_BibliographicalsReferencesTools;
use Episciences_Paper;
use Episciences_Paper_DocumentBackup;
use Episciences_Paper_Tei;
use Episciences_Repositories;
use Episciences_Review;
use Episciences_ReviewsManager;
use Episciences_Section;
use Episciences_SectionsManager;
use Episciences_Volume;
use Episciences_VolumesManager;
use Episciences_ZbjatsTools;
use Exception;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Zend_Db_Select_Exception;
use Zend_Db_Statement_Exception;
use Zend_Exception;
use Zend_Locale;
use Zend_Registry;
use Zend_View;

class Export
{
    const MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT = '/modules/journal/views/scripts/export/';
    public const FUNDER_NAME = 'funderName';

    public static function getCrossref(Episciences_Paper $paper): string
    {

        $previousVersionsUrl = self::getPreviousVersionsUrls($paper);
        list($volume, $section, $proceedingInfo) = self::getPaperVolumeAndSection($paper);
        $journal = self::getJournalSettings($paper);

        // Create new DOI if none exist
        if ($paper->getDoi() == '') {
            $journalDoi = $journal->getDoiSettings();
            $doi = $journalDoi->createDoiWithTemplate($paper);
        } else {
            $doi = $paper->getDoi();
        }

        $paperLanguage = $paper->getMetadata('language');

        if ($paperLanguage == '') {
            $paperLanguage = 'eng';
        }
        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . self::MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT);

        return self::compactXml($view->partial('crossref.phtml', [
            'volume' => $volume,
            'proceedingInfo' => $proceedingInfo,
            'section' => $section,
            'journal' => $journal,
            'paper' => $paper,
            'doi' => $doi,
            'paperLanguage' => $paperLanguage,
            'previousVersionsUrl' => $previousVersionsUrl
        ]));

    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private static function getPreviousVersionsUrls(Episciences_Paper $paper): array
    {
        $previousVersionsUrl = [];
        $previousVersions = $paper->getPreviousVersions(false, false);
        if (!empty($previousVersions)) {
            foreach ($previousVersions as $paperVersions) {
                /** Episciences_Paper $paperVersions */
                if ($paperVersions instanceof Episciences_Paper) {
                    $previousVersionsUrl[] = $paperVersions->getDocUrl();
                }
            }
        }
        return $previousVersionsUrl;
    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     */
    private static function getPaperVolumeAndSection(Episciences_Paper $paper): array
    {
        $volume = '';
        $section = '';
        $proceedingInfo = '';
        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
                if ($oVolume->isProceeding()) {
                    $proceedingInfo = $oVolume->getProceedingInfo();
                }
            }
        }


        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
            }
        }
        return array($volume, $section, $proceedingInfo);
    }

    /**
     * @param Episciences_Paper $paper
     * @return bool|Episciences_Review
     */
    private static function getJournalSettings(Episciences_Paper $paper): bool|Episciences_Review
    {
        $journal = Episciences_ReviewsManager::find($paper->getRvid());
        $loadedSettings = 'reviewSettings-' . $paper->getRvid();
        try {
            Zend_Registry::get($loadedSettings);
        } catch (Exception $e) {
            $journal->loadSettings();
            Zend_Registry::set($loadedSettings, $journal);
        }
        return $journal;
    }

    private static function compactXml(string $xml)
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $loadResult = $dom->loadXML($xml);
        if ($loadResult) {
            $output = $dom->saveXML();
            $output = str_replace(array("\r\n", "\r", "\n"), '', $output);
        } else {
            $output = '<error>Error loading XML source. Please report to Journal Support.</error>';
            trigger_error('XML Fail in export: ' . $output, E_USER_WARNING);
        }
        return $output;
    }

    /**
     * @throws Zend_Db_Statement_Exception
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public static function getZbjats(Episciences_Paper $paper): string
    {
        $bibRef = '';
        $previousVersionsUrl = self::getPreviousVersionsUrls($paper);
        list($volume, $section, $proceedingInfo) = self::getPaperVolumeAndSection($paper);
        $journal = self::getJournalSettings($paper);

        $paperLanguage = $paper->getMetadata('language');

        if ($paperLanguage == '') {
            $paperLanguage = 'eng';
        }

        $nbPages = self::getDocumentBackupNbOfPages($paper);


        $url = SERVER_PROTOCOL . '://' . $journal->getCode() . '.' . DOMAIN;
        $url .= '/' . $paper->getDocid();


        $pdf = $url . '/pdf';
        $refBibJson = Episciences_BibliographicalsReferencesTools::getBibRefFromApi($pdf);
        if (!empty($refBibJson)) {
            $bibRef = Episciences_ZbjatsTools::jsonToZbjatBibRef($refBibJson);
        }
        $doi = $paper->getDoi();

        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . self::MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT);

        return self::compactXml($view->partial('zbjats.phtml', [
            'nbPages' => $nbPages,
            'bibRef' => $bibRef,
            'volume' => $volume,
            'proceedingInfo' => $proceedingInfo,
            'section' => $section,
            'journal' => $journal,
            'paper' => $paper,
            'doi' => $doi,
            'paperLanguage' => $paperLanguage,
            'previousVersionsUrl' => $previousVersionsUrl
        ]));

    }

    /**
     * @param Episciences_Paper $paper
     * @return int
     */
    public static function getDocumentBackupNbOfPages(Episciences_Paper $paper): int
    {
        $paperDocBackup = new Episciences_Paper_DocumentBackup($paper->getDocid(), \Episciences_ReviewsManager::findByRvid($paper->getRvid())->getCode());
        $nbPages = 0;
        if ($paperDocBackup->hasDocumentBackupFile()) {
            $parser = new Parser();
            try {
                $pdf = $parser->parseFile($paperDocBackup->getPathFileName());
                $pdfMeta = $pdf->getDetails();
            } catch (Exception $exception) {
                // Fail, meh
            }

            if (!empty($pdfMeta['Pages'])) {
                $nbPages = (int)$pdfMeta['Pages'];
            }
        }
        return $nbPages;
    }

    public static function getDoaj(Episciences_Paper $paper): string
    {

        $previousVersionsUrl = self::getPreviousVersionsUrls($paper);
        list($volume, $section, $proceedingInfo) = self::getPaperVolumeAndSection($paper);
        $journal = self::getJournalSettings($paper);


        $doi = $paper->getDoi();


        $paperLanguage = $paper->getMetadata('language');


        if ($paperLanguage == '') {
            $paperLanguage = 'eng';
        }
        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . self::MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT);
        $view->addScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/partials/');

        return self::compactXml($view->partial('doaj.phtml', [
            'volume' => $volume,
            'proceedingInfo' => $proceedingInfo,
            'section' => $section,
            'journal' => $journal,
            'paper' => $paper,
            'doi' => $doi,
            'paperLanguage' => $paperLanguage,
            'previousVersionsUrl' => $previousVersionsUrl
        ]));

    }

    /**
     * @param int $volumeId
     * @param int $journalId
     * @return string
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    public static function getVolDoaj(int $volumeId, int $journalId): string
    {
        $getVolume = Episciences_VolumesManager::find($volumeId);
        $review = Episciences_ReviewsManager::find($journalId);

        $listOfPaper = $getVolume->getSortedPapersFromVolume('object');
        foreach ($listOfPaper as $key => $value) {
            if (!$value->isPublished()) {
                unset($listOfPaper[$key]);
            }
        }

        $journal = $review;
        $journal->loadSettings();
        $view = new Zend_View();
        $view->listOfPaper = $listOfPaper;
        $view->journal = $journal;
        $view->volume = $getVolume->getName('en', true);

        header('Content-Type: text/xml; charset: utf-8');

        return $view->render('export/volumesdoaj.phtml');

    }

    /**
     * Return TEI formatted paper
     * @param Episciences_Paper $paper
     * @return string
     * @throws Zend_Exception
     */
    public static function getTei(Episciences_Paper $paper): string
    {
        $tei = new Episciences_Paper_Tei($paper);
        return $tei->generateXml();
    }

    /**
     * return dc formatted paper
     * @param Episciences_Paper $paper
     * @return string
     * @throws Zend_Exception
     * @throws DOMException
     */
    public static function getDc(Episciences_Paper $paper): string
    {
        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');

        $xml->formatOutput = false;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;

        $root = $xml->createElement('oai_dc:dc');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->appendChild($root);

        // titles
        foreach ($paper->getMetadata('title') as $lang => $title) {
            $node = $xml->createElement('dc:title', $title);
            if (Zend_Locale::isLocale($lang)) {
                $node->setAttribute('xml:lang', $lang);
            }
            $root->appendChild($node);
        }

        // authors
        $authors = $paper->getMetadata('authors');
        if (is_array($authors)) {
            foreach ($authors as $author) {
                $creator = $xml->createElement('dc:creator', $author);
                $root->appendChild($creator);
            }
        }

        // Contributor
        $contributor = $paper->getSubmitter();
        if ($contributor->getFullName() != '') {
            $contributorFullName = $contributor->getFullName();
            $contributorNode = $xml->createElement('dc:contributor', $contributorFullName);
            $root->appendChild($contributorNode);
        }

        $oReview = self::getJournalSettings($paper);

        if ($oReview->getSetting(Episciences_Review::SETTING_ISSN)) {
            $source = $xml->createElement('dc:source', 'ISSN: ' . Ccsd_View_Helper_FormatIssn::FormatIssn($oReview->getSetting('ISSN')));
            $root->appendChild($source);
        }

        // journal name
        $source = $xml->createElement('dc:source', $oReview->getName());
        $root->appendChild($source);

        // platform name
        $source = $xml->createElement('dc:source', ucfirst(DOMAIN));
        $root->appendChild($source);

        // identifier
        $identifier = $xml->createElement('dc:identifier', $oReview->getUrl() . '/' . $paper->getDocid());
        $root->appendChild($identifier);

        if (!empty($paper->getDoi())) {
            $identifierDoi = $xml->createElement('dc:identifier', 'info:doi:' . $paper->getDoi());
            $root->appendChild($identifierDoi);
        }

        // quotation
        //  'Journal of Data Mining and Digital Humanities, Episciences.org, 2015, pp.43'
        $source = $xml->createElement('dc:source', $paper->getCitation());
        $root->appendChild($source);

        // paper language
        if ($paper->getMetadata('language')) {
            $language = $xml->createElement('dc:language', $paper->getMetadata('language'));
            $root->appendChild($language);
        }

        // paper subjects
        $subjects = $paper->getMetadata('subjects');
        if (is_array($subjects)) {
            foreach ($subjects as $lang => $keyword) {

                if (is_array($keyword)) {
                    foreach ($keyword as $kwdLang => $kwd) {
                        $termNode = $xml->createElement('dc:subject', $kwd);
                        if (Zend_Locale::isLocale($kwdLang)) {
                            $termNode->setAttribute('xml:lang', $kwdLang);
                        }
                        $root->appendChild($termNode);
                    }
                } else {
                    $termNode = $xml->createElement('dc:subject', $keyword);
                    if (Zend_Locale::isLocale($lang)) {
                        $termNode->setAttribute('xml:lang', $lang);
                    }
                    $root->appendChild($termNode);
                }
            }
        }

        $openaireRight = $xml->createElement('dc:rights', 'info:eu-repo/semantics/openAccess');
        $root->appendChild($openaireRight);

        $openaireRight = $xml->createElement('dc:rights', 'info:eu-repo/semantics/openAccess');
        $root->appendChild($openaireRight);

        $openaireType = $xml->createElement('dc:type', 'info:eu-repo/semantics/article');
        $root->appendChild($openaireType);

        $type = $xml->createElement('dc:type', 'Journal articles');
        $root->appendChild($type);

        $openaireTypeVersion = $xml->createElement('dc:type', 'info:eu-repo/semantics/publishedVersion');
        $root->appendChild($openaireTypeVersion);

        $openAireAudience = $xml->createElement('dc:audience', 'Researchers');
        $root->appendChild($openAireAudience);


        // description
        foreach ($paper->getAllAbstracts() as $lang => $abstract) {
            $abstract = trim($abstract);
            if ($abstract === 'International audience') {
                continue;
            }
            $description = $xml->createElement('dc:description', $abstract);
            if ($lang && Zend_Locale::isLocale($lang)) {
                $description->setAttribute('xml:lang', $lang);
            }
            $root->appendChild($description);
        }

        // publication date
        if ($paper->getPublication_date()) {
            $date = new DateTime($paper->getPublication_date());
            $publicationDate = $date->format('Y-m-d');
            $date = $xml->createElement('dc:date', $publicationDate);
            $root->appendChild($date);
        }

        return $xml->saveXML($xml->documentElement);
    }

    public static function getJson(Episciences_Paper $paper): string
    {
        return json_encode(Export::toPublicArray($paper));
    }

    public static function toPublicArray(Episciences_Paper $paper): array
    {


        $volumeMeta = [];
        $sectionMeta = [];

        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volumeMeta[] = $oVolume->toPublicArray();
            }
        }


        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $sectionMeta[] = $oSection->toPublicArray();
            }
        }


        $journal = Episciences_ReviewsManager::find($paper->getRvid());

        $result = [];
        $result['docId'] = $paper->getDocid();
        $result['paperId'] = $paper->getPaperid();
        $result['url'] = sprintf('%s/%s', $journal->getUrl(), $paper->getPaperid());
        $result['doi'] = $paper->getDoi();
        $result['journalName'] = $journal->getName();
        $result['issn'] = $journal->getSetting(Episciences_Review::SETTING_ISSN_PRINT);
        $result['eissn'] = $journal->getSetting(Episciences_Review::SETTING_ISSN);

        $result['volume'] = $volumeMeta;
        $result['section'] = $sectionMeta;
        $result['repositoryName'] = Episciences_Repositories::getLabel($paper->getRepoid());
        $result['repositoryIdentifier'] = $paper->getIdentifier();
        $result['repositoryVersion'] = $paper->getVersion();
        $result['repositoryLink'] = Episciences_Repositories::getDocUrl($paper->getRepoid(), $paper->getIdentifier(), $paper->getVersion());
        $result['dateSubmitted'] = $paper->getSubmission_date();
        $result['dateAccepted'] = $paper->getAcceptanceDate();
        $result['datePublished'] = $paper->getPublication_date();

        $result['titles'] = $paper->getMetadata('title');
        $result['authors'] = $paper->getMetadata('authors');
        $result['abstracts'] = $paper->getAbstractsCleaned();
        $result['keywords'] = $paper->getMetadata('subjects');

        if ($paper->hasHook && $paper->getConcept_identifier() !== null) {
            $result['concept_identifier'] = $paper->getConcept_identifier();
        }

        return $result;
    }

    public static function getBibtex(Episciences_Paper $paper): string
    {
        list($volume, $section, $proceedingInfo) = self::getPaperVolumeAndSection($paper);
        $journal = self::getJournalSettings($paper);

        $doi = $paper->getDoi();


        $subjects = $paper->getMetadata('subjects');
        $subjects = self::flattenArray($subjects);

        $authors = [];
        try {
            foreach ($paper->getAuthors() as $author) {
                $authors[] = $author['fullname'];
            }
        } catch (JsonException $e) {
            $authors[] = '';
        }

        $paperLanguage = $paper->getMetadata('language');

        if (empty($paperLanguage)) {
            $paperLanguage = 'en';
        }

        if ($paperLanguage == 'eng') {
            $paperLanguage = 'en';
        } elseif ($paperLanguage == 'fre') {
            $paperLanguage = 'fr';
        }
        if (strlen($paperLanguage) > 2) {
            $paperLanguage = ''; // give up for the moment
        }

        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . self::MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT);

        return $view->partial('bibtex.phtml', [
            'authors' => $authors,
            'volume' => $volume,
            'section' => $section,
            'journal' => $journal,
            'paper' => $paper,
            'doi' => $doi,
            'keywords' => $subjects,
            'paperLanguage' => $paperLanguage
        ]);
    }

    public static function flattenArray(array $array): array
    {
        $result = [];

        array_walk_recursive($array, function ($item) use (&$result) {
            $result[] = $item;
        });

        return $result;
    }

    /**
     * @param mixed $arrayContrib
     * @param array $jsonCsl
     * @param int $i
     * @return array
     */
    public static function getAuthorsCsl(mixed $arrayContrib, array $jsonCsl, int $i): array
    {
        foreach ($arrayContrib['person_name'] as $value) {
            if (!is_array($value)) {
                $arrayContrib['person_name'] = [$arrayContrib['person_name']];
                break;
            }
        }
        foreach ($arrayContrib['person_name'] as $value) {
            if (isset($value['surname'])) {
                $jsonCsl['author'][$i]['family'] = $value['surname'];
            }
            if (isset($value['given_name'])) {
                $jsonCsl['author'][$i]['given'] = $value['given_name'];
            }
            $i++;
        }
        return $jsonCsl;
    }

    /**
     * @param $public_properties
     * @param array $jsonCsl
     * @return array
     */
    public static function getConferenceInfo($public_properties, array $jsonCsl): array
    {
        if (array_key_exists('conference', $public_properties)) {
            $jsonCsl['event-title'] = $public_properties['conference']['event_metadata']['conference_name'];
            $jsonCsl['event-place'] = !is_null($public_properties['conference']['event_metadata']['conference_location']) ? $public_properties['conference']['event_metadata']['conference_location'] : null;
            $jsonCsl['event-date'] = $public_properties['conference']['event_metadata']['conference_date']['@start_year'];
        }
        return $jsonCsl;
    }

    /**
     * OpenAIRE export format
     * @param Episciences_Paper $paper
     * @return string
     * @deprecated use getOpenaire
     */
    public function getDatacite(Episciences_Paper $paper): string
    {
        return Export::getOpenaire($paper);
    }

    public static function getOpenaire(Episciences_Paper $paper): string
    {
        list($volume, $section, $proceedingInfo) = self::getPaperVolumeAndSection($paper);
        $journal = self::getJournalSettings($paper);

        $doi = $paper->getDoi();

        $paperLanguage = $paper->getMetadata('language');

        if (empty($paperLanguage)) {
            $paperLanguage = 'eng';
        }

        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . self::MODULES_JOURNAL_VIEWS_SCRIPTS_EXPORT);

        return self::compactXml($view->partial('datacite.phtml', [
            'volume' => $volume,
            'section' => $section,
            'journal' => $journal,
            'paper' => $paper,
            'doi' => $doi,
            'paperLanguage' => $paperLanguage
        ]));
    }


    /**
     * @param $docid
     * @return string
     */
    public static function getCsl($docid): string
    {
        $jsonCsl = [];

        try {
            $jsonDb = json_decode(\Episciences_PapersManager::getJsonDocumentByDocId($docid), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        $jsonCsl = [];
        $isJournal = array_key_exists('journal', $jsonDb['public_properties']);


        $jsonCsl['type'] = $jsonDb['public_properties']['database']['current']['type']['title'];
        $jsonCsl['id'] = $isJournal
            ? "https://doi.org/" . $jsonDb['public_properties']['journal']['journal_article']['doi_data']['doi']
            : "https://doi.org/" . $jsonDb['public_properties']['conference']['conference_paper']['doi_data']['doi'];


        $jsonCsl['author'] = [];
        $arrayContrib = $isJournal
            ? $jsonDb['public_properties']['journal']['journal_article']['contributors']
            : $jsonDb['public_properties']['conference']['conference_paper']['contributors'];
        $jsonCsl = self::getAuthorsCsl($arrayContrib, $jsonCsl, 0);


        $jsonCsl['issued']["date-parts"][][] = $jsonDb['public_properties']['database']['database_metadata']['database_date']["publication_date"]['year'];

        if ($isJournal) {
            $jsonCsl['DOI'] = $jsonDb['public_properties']['journal']['journal_article']['doi_data']['doi'];
            $jsonCsl['publisher'] = $jsonDb['public_properties']['journal']['journal_metadata']['full_title'];
            $jsonCsl['title'] = $jsonDb['public_properties']['journal']['journal_article']['titles']['title'];
        } else {
            $jsonCsl = self::getConferenceInfo($jsonDb['public_properties'], $jsonCsl);
        }

        $jsonCsl['volume'] = !is_null($vol = $jsonDb['public_properties']['database']['current']['volume']) ? $vol['id'] : null;
        $jsonCsl['issue'] = !is_null($section = $jsonDb['public_properties']['database']['current']['section']) ? $section['id'] : null;
        $jsonCsl['version'] = $jsonDb['public_properties']['database']['current']['version'];
        try {
            $jsonString = json_encode($jsonCsl, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        return $jsonString;
    }

}