<?php

use Episciences\Paper\Export;

class Episciences_Paper_XmlExportManager
{

    public const PUBLIC_KEY = 'public_properties';
    public const PRIVATE_KEY = 'private_properties';
    public const ALL_KEY = 'all';
    public const DATABASE_KEY = 'database';
    public const JOURNAL_ARTICLE_KEY = 'journal_article';
    public const CONFERENCE_PAPER_KEY = 'conference_paper';
    public const JOURNAL_METADATA_KEY = 'journal_metadata';
    public const JOURNAL_KEY = 'journal';
    public const CONFERENCE_KEY = 'conference';
    public const BODY_KEY = 'body';

    public const CROSSREF_FORMAT = 'crossref';
    public const DATACITE_FORMAT = 'datacite';
    public const DEFAULT_PAPER_LANGUAGE = 'en';

    public const FORMATS_EXPORT_MAP = [
        self::CROSSREF_FORMAT => 'export/crossref.phtml',
    ];

    public static function xmlExport(Episciences_Paper $paper, string $format = ''): string
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

        $volume = '';
        $section = '';
        $proceedingInfo = '';
        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en');
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

        $journal = Episciences_ReviewsManager::find($paper->getRvid());
        $journal->loadSettings();


        // Create new DOI if none exist
        if ($paper->getDoi() == '') {
            $journalDoi = $journal->getDoiSettings();
            $doi = $journalDoi->createDoiWithTemplate($paper, $journal->getCode());
        } else {
            $doi = $paper->getDoi();
        }

        /**
         * TODO temporary fix see https://gitlab.ccsd.cnrs.fr/ccsd/episciences/issues/215
         *  this attribute is required by the datacite schema
         *  arxiv doesnt have it, we need to fix this by asking the author additional information
         */

        $paperLanguage = Export::getPaperLanguageCode($paper, 2, self::DEFAULT_PAPER_LANGUAGE );

        //header('Content-Type: text/xml; charset: utf-8');
        $view = new Zend_View();
        $view->paper = $paper;
        $view->paperLanguage = $paperLanguage;
        $view->titles = Export::crossrefGetTitlesWithLanguages($paper);
        $view->abstracts = Export::crossrefGetAbstractsWithLanguages($paper);

        $view->volume = $volume;
        $view->proceedingInfo = $proceedingInfo;
        $view->section = $section;
        $view->doi = $doi;
        $view->previousVersionsUrl = $previousVersionsUrl;


        $view->journal = $journal;
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $output = $view->render(self::FORMATS_EXPORT_MAP[$format] ?? self::FORMATS_EXPORT_MAP[self::DATACITE_FORMAT]);
        return self::displayXml($output);
    }

    private static function displayXml(string $output): string
    {
        $dom = new DOMDocument();

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $loadResult = $dom->loadXML($output);

        $dom->encoding = 'utf-8';
        $dom->xmlVersion = '1.0';

        if ($loadResult) {

            $xml = $dom->saveXML();

            if (!$xml) {
                trigger_error('XML DUMP Fail : ' . $output, E_USER_WARNING);
                return '';
            }

            return $xml;
        }

        trigger_error('XML Fail in export: ' . $output, E_USER_WARNING);
        return '';

    }

    public static function getXmlCleaned(string $xml = ''): string
    {

        if ($xml === '') {
            return '';
        }

        return Episciences_Tools::spaceCleaner($xml);

    }

}