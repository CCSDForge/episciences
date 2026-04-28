<?php
declare(strict_types=1);

use Episciences\AppRegistry;
use Episciences\Paper\Export;
use Solarium\QueryType\Update\Query\Document;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class Ccsd_Search_Solr_Indexer_Episciences extends Ccsd_Search_Solr_Indexer
{

    const OTHERS_STRING_PREFIX = 'Others';
    public static string $coreName = 'episciences';

    private const AUTHOR_FIRST_LETTER_PATTERN = '/^[A-Z]$/';

    public static int $maxDocsInBuffer = 25;

    private ArrayAdapter $cache;


    /** @param array<string, mixed> $options */
    public function __construct(array $options)
    {
        $options['core'] = self::$coreName;
        $maxDocsInBuffer = $options[Ccsd_Search_Solr_Indexer_Core::OPTION_MAX_DOCS_IN_BUFFER] ?? self::$maxDocsInBuffer;
        if (is_numeric($maxDocsInBuffer)) {
            self::$maxDocsInBuffer = $maxDocsInBuffer;
        }
        $this->initCache();
        parent::__construct($options);
    }

    private function initCache(): void
    {
        // $storeSerialized=false: store object references directly, no serialize()/unserialize().
        // Serializing complex objects (Episciences_Volume, Episciences_Review with their full
        // object graphs) during a 7000-paper bulk run caused OOM in ArrayAdapter::freeze().
        // We only read from cached objects (titles, status…), so reference sharing is safe.
        $this->setCache(new ArrayAdapter(0, false));
    }

    /**
     * Set the select request to get the list of Id to index
     * @param Zend_Db_Select $select
     */
    protected function selectIds(Zend_Db_Select $select): void
    {
        $select->from('PAPERS', 'DOCID')->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
    }

    /**
     * Adds metadata to the document.
     *
     * @param int $docId The ID of the document.
     * @param Document $docToIndex The document to index.
     * @return false|Document The indexed document.
     * @throws Zend_Db_Statement_Exception
     */
    protected function addMetadataToDoc(int $docId, Document $docToIndex): Document|false
    {

        // Suffixes (conventions)
        // _t : text (correspondance approximative : insensible à la casse, aux accents)
        // _s : string (correspondance exacte)

        $paperData = Episciences_PapersManager::get($docId, false);

        if (!$paperData) {
            AppRegistry::getMonoLogger()?->warning('Update doc ' . $docId . ' No content found for this article.');
            return false;
        }

        $tei = Export::getTei($paperData);
        $openaire = Export::getOpenaire($paperData);
        $dc = Export::getDc($paperData);
        $crossref = Export::getCrossref($paperData);
        $zbjats = Export::getZbjats($paperData);
        $doaj = Export::getDoaj($paperData);
        $bibtex = Export::getBibtex($paperData);
        $csl = Export::getCsl($paperData->getDocid());

        $docToIndex->setField('doc_tei', $tei);
        $docToIndex->setField('doc_dc', $dc);
        $docToIndex->setField('doc_openaire', $openaire);
        $docToIndex->setField('doc_crossref', $crossref);
        $docToIndex->setField('doc_zbjats', $zbjats);
        $docToIndex->setField('doc_doaj', $doaj);
        $docToIndex->setField('doc_bibtex', $bibtex);
        $docToIndex->setField('doc_csl', $csl);
        $docToIndex->setField('doc_type_fs', $paperData->getTypeWithKey());

        // Récupération des infos de la revue
        $journal = $this->getJournalMetadata($paperData->getRvid());


        $this->indexAuthors($paperData, $docToIndex);
        $this->indexKeywords($paperData, $docToIndex);


        // Date de soumission
        $submission_date = ($paperData->getSubmission_date()) ? $this->getFormattedDate($paperData->getSubmission_date()) : null;

        $publication_date = $paperData->getPublication_date();
        if ($publication_date) {
            $publication_date_array = explode('-', $this->getFormattedDate($publication_date, 'Y-m-d'));
            [$publication_year, $publication_month, $publication_day] = $publication_date_array;
            $publication_date = $this->getFormattedDate($publication_date);
        } else {
            $publication_year = null;
            $publication_month = null;
            $publication_day = null;
        }


        $review_title = $this->cleanChars($journal->getName());


        $es_doc_url = sprintf("https://%s.%s/%s", $journal->getCode(), DOMAIN, $paperData->getPaperid());
        $es_pdf_url = $es_doc_url . '/pdf';

        $dataToIndex = [
            'docid' => $docId,
            'paperid' => $paperData->getPaperid(),
            'doi_s' => $paperData->getDoi(),

            'language_s' => Ccsd_Tools::xpath($paperData->getRecord(), '//dc:language'),
            'identifier_s' => $paperData->getIdentifier(),
            'version_td' => $paperData->getVersion(),

            'es_submission_date_tdate' => $submission_date,
            'es_publication_date_tdate' => $publication_date,
            'es_doc_url_s' => $es_doc_url,
            'es_pdf_url_s' => $es_pdf_url,

            'publication_date_tdate' => $publication_date,
            'publication_date_year_fs' => $publication_year,
            'publication_date_month_fs' => $publication_month,
            'publication_date_day_fs' => $publication_day,

            'revue_id_i' => $paperData->getRvid(),
            'revue_code_t' => $journal->getCode(),
            'revue_title_s' => $review_title
        ];

        $docToIndex = $this->addArrayOfMetaToDoc($dataToIndex, null, $docToIndex);

        $docToIndex = $this->indexTitles($paperData->getMetadata('title'), $docToIndex);
        $docToIndex = $this->indexAbstracts($paperData->getAbstractsCleaned(), $docToIndex);


        $this->indexVolume($paperData->getVid(), $docToIndex);
        $this->indexSecondaryVolumes($paperData, $docToIndex);
        $this->indexSection($paperData->getSid(), $docToIndex);

        $docToIndex->addField('indexing_date_tdate', date("Y-m-d\TH:i:s\Z"));

        return $docToIndex;
    }

    private function getJournalMetadata(int $rvid): Episciences_Review
    {
        $cache = $this->getCache();
        $cacheName = 'rvid.' . $rvid;
        $journal = $cache->getItem($cacheName);

        if (!$journal->isHit()) {
            $journalDataFromDb = Episciences_Review::getData($rvid);
            $journalMetadata = new Episciences_Review($journalDataFromDb);
            $cache->save($journal->set($journalMetadata));
        } else {
            $journalMetadata = $journal->get();
        }

        return $journalMetadata;
    }

    public function getCache(): ArrayAdapter
    {
        return $this->cache;
    }

    public function setCache(ArrayAdapter $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Indexes all authors of a paper into the Solr document.
     * Silently skips on any error from getAuthors() (e.g. bad JSON, invalid data).
     */
    private function indexAuthors(Episciences_Paper $paperData, Document $ndx): void
    {
        try {
            $authors = $paperData->getAuthors();
        } catch (\Throwable $e) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf('indexAuthors docid=%d: %s', $paperData->getDocid(), $e->getMessage())
            );
            return;
        }

        if ($authors === []) {
            return;
        }

        $authorNames = [];
        foreach ($authors as $author) {
            $formattedName = self::formatAuthorName($author);
            $this->indexOneAuthor($formattedName, $ndx);
            $authorNames[] = $formattedName;
        }

        $ndx->addField('author_fullname_sort', self::buildAuthorSortKey($authorNames));
    }

    /**
     * Formats a raw author array into a "Family, Given" string.
     *
     * @param array{family?: string, given?: string} $author
     */
    protected static function formatAuthorName(array $author): string
    {
        $family = trim($author['family'] ?? '');
        $given  = trim($author['given'] ?? '');

        if ($given === '') {
            return $family;
        }
        if ($family === '') {
            return $given;
        }
        return $family . ', ' . $given;
    }

    /**
     * Computes the sort key from a list of formatted author names.
     * Truncates the joined string to 30 characters, then strips spaces and commas.
     *
     * @param string[] $authorNames
     */
    protected static function buildAuthorSortKey(array $authorNames): string
    {
        $key = mb_substr(implode(' ', $authorNames), 0, 30);

        return str_replace([' ', ','], '', $key);
    }

    /**
     * Returns the uppercase first letter of the author's cleaned name,
     * or OTHERS_STRING_PREFIX when the first character is not an ASCII letter (A–Z).
     */
    protected static function classifyAuthorFirstLetter(string $authorCleaned): string
    {
        $firstLetter = mb_strtoupper(mb_substr($authorCleaned, 0, 1));

        return preg_match(self::AUTHOR_FIRST_LETTER_PATTERN, $firstLetter) === 1
            ? $firstLetter
            : self::OTHERS_STRING_PREFIX;
    }

    /**
     * Indexes a single author name into the four dedicated Solr fields.
     */
    protected function indexOneAuthor(string $author, Document $ndx): void
    {
        $authorCleaned = self::cleanAuthorName($author);
        $ndx->addField('author_fullname_fs', $authorCleaned);

        $firstLetter = self::classifyAuthorFirstLetter($authorCleaned);
        $ndx->addField('authorFirstLetters_s', $firstLetter);

        $prefixedName = $firstLetter === self::OTHERS_STRING_PREFIX
            ? self::OTHERS_STRING_PREFIX . self::SOLR_FACET_SEPARATOR . $authorCleaned
            : $authorCleaned;
        $ndx->addField('authorLastNameFirstNamePrefixed_fs', $prefixedName);

        $ndx->addField('author_fullname_s', $authorCleaned);
    }

    /**
     * Cleans and normalises an author name string before Solr indexing.
     * Strips control characters, trailing commas, and redundant whitespace.
     */
    protected static function cleanAuthorName(string $name): string
    {
        $name = Episciences_Tools::spaceCleaner($name);
        $name = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $name);
        $name = Ccsd_Tools_String::stripCtrlChars($name, '');
        $name = str_replace(' ,', '', $name);
        $name = rtrim($name, ',');

        return trim($name);
    }

    private function indexKeywords(Episciences_Paper $paperData, Document $ndx): void
    {
        $subjects = $paperData->getMetadata('subjects');
        if (is_array($subjects)) {
            foreach ($subjects as $keyword) {
                if (is_array($keyword)) {
                    foreach ($keyword as $kwd) {
                        $ndx->addField('keyword_t', $kwd);
                    }
                } else {
                    $ndx->addField('keyword_t', $keyword);
                }
            }
        }
    }

    private function getFormattedDate(string $dateToFormat, string $format = 'Y-m-d\TH:i:s\Z'): string
    {
        try {
            return date_format(new DateTime($dateToFormat), $format);
        } catch (Exception $e) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf('getFormattedDate: invalid date "%s": %s', $dateToFormat, $e->getMessage())
            );
            return '1970-01-01T00:00:00Z';
        }
    }

    /**
     * Nettoie une chaine avant de l'indexer
     *
     * @param string $inputString
     * @return string
     */
    private function cleanChars(string $inputString): string
    {
        $outputString = html_entity_decode($inputString);
        $outputString = Ccsd_Tools_String::stripCtrlChars($outputString);
        return trim($outputString);
    }

    /** @param array<array-key, mixed> $titles */
    private function indexTitles(array $titles, Document $docToIndex): Document
    {
        $titlesToIndex = [];
        foreach ($titles as $locale => $title) {
            if (Zend_Locale::isLocale($locale)) {
                $titlesToIndex[$locale . '_paper_title_t'] = $title;
            }
        }
        if (empty($titlesToIndex)) {
            $titlesToIndex['paper_title_t'] = $titles;
        }
        return $this->addArrayOfMetaToDoc($titlesToIndex, null, $docToIndex);
    }

    /** @param array<array-key, mixed> $abstracts */
    private function indexAbstracts(array $abstracts, Document $docToIndex): Document
    {
        $abstractsToIndex = [];
        foreach ($abstracts as $locale => $abstract) {
            if (Zend_Locale::isLocale($locale)) {
                $abstractsToIndex[$locale . '_abstract_t'] = $abstract;
            }
        }
        if (empty($abstractsToIndex)) {
            $abstractsToIndex['abstract_t'] = $abstracts;
        }
        return $this->addArrayOfMetaToDoc($abstractsToIndex, null, $docToIndex);
    }

    private function indexVolume(int $vid, Document $docToIndex): void
    {
        if ($vid === 0) {
            return;
        }


        $volume = $this->getVolumeFromDbOrCache($vid);

        if ($volume) {
            $docToIndex->addField('volume_id_i', $vid);
            $docToIndex->addField('volume_status_i', $volume->getStatus());
            $volumeTranslationsTitles = $volume->getTitles();
            if (is_array($volumeTranslationsTitles)) {

                // We take the first language found because the field is not multivalued
                $firstLanguageFound = array_key_exists('en', $volumeTranslationsTitles) ? 'en' : array_key_first($volumeTranslationsTitles);
                $docToIndex->addField('volume_fs', $vid . parent::SOLR_FACET_SEPARATOR . $volumeTranslationsTitles[$firstLanguageFound]);

                foreach ($volumeTranslationsTitles as $lang => $translations) {
                    $docToIndex->addField($lang . '_volume_title_t', $translations);
                    $docToIndex->addField('volume_title_fs', $vid . parent::SOLR_FACET_SEPARATOR . $lang . '_' . $translations);

                }
            }
        } else {
            AppRegistry::getMonoLogger()?->warning(
                sprintf("Update doc : le volume (%s) de cet article n'existe pas/plus.", $vid)
            );
        }
    }

    private function getVolumeFromDbOrCache(int $vid): Episciences_Volume|false
    {
        $cache = $this->getCache();
        $cacheName = 'volume.' . $vid;
        $volumeCacheItem = $cache->getItem($cacheName);

        $volume = false;
        if (!$volumeCacheItem->isHit()) {
            $volume = Episciences_VolumesManager::find($vid);
            if ($volume) {
                $cache->save($volumeCacheItem->set($volume));
            }
        } else {
            $volume = $volumeCacheItem->get();
        }
        return $volume;
    }

    private function indexSecondaryVolumes(Episciences_Paper $paperData, Document $docToIndex): void
    {

        $secondaryVolumes = Episciences_Volume_PapersManager::findPaperVolumes($paperData->getDocid());

        foreach ($secondaryVolumes as $oneSecondaryVolume) {
            $vid = $oneSecondaryVolume->getVid();

            $volume = $this->getVolumeFromDbOrCache($vid);

            if ($volume) {
                $docToIndex->addField('secondary_volume_id_i', $vid);
                $volumeTranslationsTitles = $volume->getTitles();
                if (is_array($volumeTranslationsTitles)) {
                    foreach ($volumeTranslationsTitles as $lang => $translations) {
                        $docToIndex->addField($lang . '_secondary_volume_title_t', $translations);
                        $docToIndex->addField('secondary_volume_fs', $vid . parent::SOLR_FACET_SEPARATOR . $translations);
                    }
                }
            } else {
                AppRegistry::getMonoLogger()?->warning(
                    sprintf("Update doc %s : le volume secondaire (%s) de cet article n'existe pas/plus.", $paperData->getDocid(), $vid)
                );
            }
        }
    }

    private function indexSection(int $sectionId, Document $docToIndex): void
    {

        if ($sectionId === 0) {
            return;
        }

        $cache = $this->getCache();
        $cacheName = 'section.' . $sectionId;
        $sectionCacheItem = $cache->getItem($cacheName);

        if (!$sectionCacheItem->isHit()) {
            $section = Episciences_SectionsManager::find($sectionId);
            if ($section) {
                $cache->save($sectionCacheItem->set($section));
            }
        } else {
            $section = $sectionCacheItem->get();
        }


        if (!$section) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf("Update doc : la section (%s) de cet article n'existe pas/plus.", $sectionId)
            );
            return;
        }

        $docToIndex->addField('section_id_i', $sectionId);

        $sectionTranslations = $section->getTitles();
        if (is_array($sectionTranslations)) {

            // We take the first language found because the field is not multivalued
            $firstLanguageFound = array_key_exists('en', $sectionTranslations) ? 'en' : array_key_first($sectionTranslations);
            $docToIndex->addField('section_fs', $sectionId . parent::SOLR_FACET_SEPARATOR . $sectionTranslations[$firstLanguageFound]);

            foreach ($sectionTranslations as $lang => $translations) {
                $docToIndex->addField($lang . '_section_title_t', $translations);
                $docToIndex->addField('section_title_fs', $sectionId . parent::SOLR_FACET_SEPARATOR . $lang . '_' . $translations);

            }
        }


    }

    /**
     * @param mixed $docId
     * @throws Zend_Db_Statement_Exception
     */
    protected function getDocidData($docId): ?Episciences_Paper
    {

        $papersManager = new Episciences_PapersManager();
        $paper = $papersManager::get($docId);

        if (!$paper) {
            return null;
        }

        return $paper;

    }

}

