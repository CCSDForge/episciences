<?php


use Solarium\QueryType\Update\Query\Document;

class Ccsd_Search_Solr_Indexer_Episciences extends Ccsd_Search_Solr_Indexer
{

    public static string $coreName = 'episciences';

    public static int $maxDocsInBuffer = 50;

    public static string $dbConfName = 'episciences';

    private array $_reviews = [];

    private array $_volumes = [];

    private array $_sections = [];

    public function __construct(array $options)
    {
        $options['core'] = self::$coreName;
        $options['maxDocsInBuffer'] = self::$maxDocsInBuffer;
        parent::__construct($options);
    }

    /**
     * Set the select request to get the list of Id to index
     * @param Zend_Db_Select $select
     */
    protected function selectIds(Zend_Db_Select $select)
    {
        $select->from('PAPERS', 'DOCID')->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
    }

    /**
     * @param int $docId
     * @param Document $docToIndex
     * @return Document
     */
    protected function addMetadataToDoc(int $docId, Document $docToIndex)
    {

        // Suffixes (conventions)
        // _t : text (correspondance approximative : insensible à la casse, aux accents)
        // _s : string (correspondance exacte)

        $repositories = Episciences_Repositories::getRepositories();

        $paperData = $this->getDocidData($docId);
        $paperVolumesData = $this->getPaperVolumesData($docId);

        if ($paperData === null) {
            Ccsd_Log::message('Update doc ' . $docId . ' : cet article n\'existe pas/plus.', true, 'WARN');
            return false;
        }

        // Récupération des infos du déposant
        $submitter = $paperData->getSubmitter();


        // Récupération des infos de la revue
        $review = $this->getReview($paperData->getRvid());

        $volumeTranslations = $review['TRANSLATIONS']['volumes'] ?? null;

        $this->indexAuthors($paperData, $docToIndex);
        $this->indexKeywords($paperData, $docToIndex);


        // Date de soumission
        $submission_date = ($paperData->getSubmission_date()) ? $this->getFormattedDate($paperData->getSubmission_date()) : null;

        $publication_date = $paperData->getPublication_date();
        if ($publication_date) {
            $publication_date = $this->getFormattedDate($publication_date);
            $publication_date_array = explode('-', $publication_date);
            [$publication_year, $publication_month, $publication_day] = $publication_date_array;
        } else {
            $publication_year = null;
            $publication_month = null;
            $publication_day = null;
        }


        $review_title = $this->cleanChars($review['NAME']);

        $revue_date_creation = $this->getFormattedDate($review['CREATION']);
        $es_doc_url = 'https://' . $review['CODE'] . '.' . DOMAIN . '/' . $paperData->getPaperid();
        $es_pdf_url = $es_doc_url . '/pdf';

        $dataToIndex = [
            'docid' => $docId,
            'doi_s' => $paperData->getDoi(),
            'paperid' => $paperData->getPaperid(),
            'language_s' => Ccsd_Tools::xpath($paperData->getRecord(), '//dc:language'),
            'identifier_s' => $paperData->getIdentifier(),
            'version_td' => $paperData->getVersion(),
            //'doc_url_s' => $paperData->getDocUrl(),
            //'paper_url_s' => $paperData->getDocUrl(),
            'submitter_id_i' => $submitter->getUid(),
            'submitter_firstname_t' => $submitter->getFirstname(),
            'submitter_lastname_t' => $submitter->getLastname(),
            'submitter_email_s' => $submitter->getEmail(),

            'es_submission_date_tdate' => $submission_date,
            'es_publication_date_tdate' => $publication_date,
            'es_doc_url_s' => $es_doc_url,
            'es_pdf_url_s' => $es_pdf_url,

            'publication_date_tdate' => $publication_date,
            'publication_date_year_fs' => $publication_year,
            'publication_date_month_fs' => $publication_month,
            'publication_date_day_fs' => $publication_day,

            'revue_issn_s' => $review['SETTINGS']['ISSN'] ?? null,
            'revue_id_i' => $paperData->getRvid(),
            'revue_status_i' => $review['STATUS'],
            'revue_code_t' => $review['CODE'],
            'revue_title_s' => $review_title,
            'revue_creation_date_tdate' => $revue_date_creation,

            'repo_id_i' => $paperData->getRepoid(),
            'repo_title_s' => $repositories[$paperData->getRepoid()][Episciences_Repositories::REPO_LABEL]
        ];


        $titles = $paperData->getMetadata('title');
        foreach ($titles as $locale => $title) {
            if (Zend_Locale::isLocale($locale)) {
                $titlesToIndex[$locale . '_paper_title_t'] = $title;
            }
        }
        if (empty($titlesToIndex)) {
            $titlesToIndex['paper_title_t'] = $titles;
        }

        $abstracts = $paperData->getAllAbstracts();
        foreach ($abstracts as $locale => $abstract) {
            if (Zend_Locale::isLocale($locale)) {
                $abstractsToIndex[$locale . '_abstract_t'] = $abstract;
            }
        }
        if (empty($abstractsToIndex)) {
            $abstractsToIndex['abstract_t'] = $abstracts;
        }

        $dataToIndex = array_merge($dataToIndex, $titlesToIndex, $abstractsToIndex);


        foreach ($dataToIndex as $fieldName => $fieldValue) {
            if ($fieldValue) {
                if (is_array($fieldValue)) {
                    $fieldValue = array_map('trim', $fieldValue);
                } else {
                    $fieldValue = trim($fieldValue);
                }

                $docToIndex->addField($fieldName, $fieldValue);
            }
        }

        $this->indexVolume($paperData, $docId, $docToIndex, $volumeTranslations, $paperVolumesData);
        $this->indexSection($paperData, $docToIndex, $review['TRANSLATIONS']['sections']);

        $docToIndex->addField('indexing_date_tdate', date("Y-m-d\Th:i:s\Z"));

        return $docToIndex;
    }

    protected function getDocidData($docId)
    {

        $papersManager = new Episciences_PapersManager();
        $paper = $papersManager::get($docId);

        if (!$paper) {
            return null;
        }

        return $paper;

    }

    protected function getPaperVolumesData($docId): array
    {
        $db = $this->getDb();
        $select = $db->select()->from('VOLUME_PAPER', ['VID'])->where('DOCID = ?', $docId);
        return $db->fetchCol($select);
    }

    // Renvoie les données d'une revue ainsi que ses fichiers de traduction

    private function getReview($rvid)
    {
        if (!array_key_exists($rvid, $this->_reviews)) {

            // Data ***
            $select = $this->getDb()
                ->select()
                ->from('REVIEW')
                ->where('RVID = ?', $rvid);
            $review = $this->getDb()->fetchRow($select);

            $select = $this->getDb()
                ->select()
                ->from('REVIEW_SETTING', ['SETTING', 'VALUE'])
                ->where('RVID = ?', $rvid);
            $review['SETTINGS'] = $this->getDb()->fetchPairs($select);

            $translations = [];

            // Scan the translation folder for available languages
            $rvcode = $review['CODE'];
            $path = APPLICATION_PATH . '/../data/' . $rvcode . '/languages/';


            $langs = scandir($path);

            if ($langs !== false) {
                array_splice($langs, 0, 2);
                // For each language, we get the translation files
                foreach ($langs as $lang) {

                    $files = scandir($path . $lang);
                    array_splice($files, 0, 2);

                    foreach ($files as $file) {
                        $filepath = $path . $lang . '/' . $file;
                        $filename = basename($filepath, '.php');
                        $translations[$filename][$lang] = Episciences_Tools::readTranslation($filepath, $lang);
                    }
                }
            }

            $review['TRANSLATIONS'] = $translations;

            $this->_reviews[$rvid] = $review;
            return $review;
        }

        return $this->_reviews[$rvid];
    }

    /**
     * @param $paperData
     * @param Document $ndx
     * @return void
     */
    private function indexAuthors($paperData, Document $ndx): void
    {
        /** @var string[] $authors */
        $authors = $paperData->getMetadata('authors');

        if (is_array($authors)) {
            $author_sort = [];
            foreach ($authors as $author) {
                $this->indexOneAuthor($author, $ndx);
                $author_sort[] = $author;
            }
            $author_fullname_sort = substr(implode(' ', $author_sort), 0, 50);
            $ndx->addField('author_fullname_sort', $author_fullname_sort);

        } elseif (is_string($authors)) {
            $this->indexOneAuthor($authors, $ndx);
            $author_fullname_sort = self::cleanAuthorName($authors);
            $ndx->addField('author_fullname_sort', $author_fullname_sort);
        }
    }

    /**
     * @param string $authors
     * @param Document $ndx
     */
    protected function indexOneAuthor(string $authors, Document $ndx): void
    {
        $authorsCleaned = self::cleanAuthorName($authors);
        $ndx->addField('author_fullname_fs', $authorsCleaned);

        $authorsFormatted = Episciences_Tools::reformatOaiDcAuthor($authors);
        $authorsFormattedCleaned = self::cleanAuthorName($authorsFormatted);
        $ndx->addField('author_fullname_s', $authorsFormattedCleaned);
    }

    private static function cleanAuthorName($name): string
    {
        $name = Ccsd_Tools::space_clean($name);
        $name = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $name);
        $name = Ccsd_Tools_String::stripCtrlChars($name, '');
        $name = str_replace(' ,', '', $name);
        return trim($name);
    }

    /**
     * @param $paperData
     * @param Document $ndx
     * @return void
     */
    private function indexKeywords($paperData, Document $ndx): void
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

    private function getFormattedDate(string $dateToFormat, string $format = 'Y-m-d\TH:i:s\Z')
    {
        try {
            $revue_date_creation = date_format(new DateTime($dateToFormat), $format);
        } catch (Exception $e) {
            $revue_date_creation = '1970-01-01T00:00:00Z';
        }
        return $revue_date_creation;
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

    /**
     * @param $paperData
     * @param int $docId
     * @param Document $docToIndex
     * @param $volumeTranslations
     * @param array $paperVolumesData
     * @return array
     */
    private function indexVolume($paperData, int $docId, Document $docToIndex, $volumeTranslations, array $paperVolumesData): array
    {
        if ($paperData->getVid()) {
            $volume = $this->getVolume($paperData->getVid());
            if (!$volume) {
                Ccsd_Log::message("Update doc " . $docId . " : le volume (" . $paperData->getVid() . ") de cet article n'existe pas/plus.", true, 'WARN');
            } else {
                $docToIndex->addField('volume_id_i', $paperData->getVid());
                $docToIndex->addField('volume_status_i', $volume['SETTINGS']['status']);
                if (is_array($volumeTranslations)) {
                    foreach ($volumeTranslations as $lang => $translations) {
                        if (array_key_exists('volume_' . $paperData->getVid() . '_title', $translations)) {
                            $docToIndex->addField($lang . '_volume_title_t', $translations['volume_' . $paperData->getVid() . '_title']);
                        }
                    }
                }
            }

            // Facette "volume_fs"
            $docToIndex->addField('volume_fs', $paperData->getVid() . parent::SOLR_FACET_SEPARATOR . 'volume_' . $paperData->getVid() . '_title');
        }

        // secondary volumes data
        if (!empty($paperVolumesData)) {
            foreach ($paperVolumesData as $vid) {
                $volume = $this->getVolume($vid);
                if (!$volume) {
                    Ccsd_Log::message("Update doc " . $docId . " : le volume secondaire (" . $vid . ") de cet article n'existe pas/plus.", true, 'WARN');
                    continue;
                }
                $docToIndex->addField('secondary_volume_id_i', $vid);
                if (is_array($volumeTranslations)) {
                    foreach ($volumeTranslations as $lang => $translations) {
                        if (array_key_exists('volume_' . $vid . '_title', $translations)) {
                            $docToIndex->addField($lang . '_secondary_volume_title_t', $translations['volume_' . $vid . '_title']);
                        }
                    }
                }

                // Facette "volume_fs"
                $docToIndex->addField('secondary_volume_fs', $vid . parent::SOLR_FACET_SEPARATOR . 'volume_' . $vid . '_title');
            }
        }
        return array($lang, $translations);
    }

    private function getVolume($vid)
    {
        if (array_key_exists($vid, $this->_volumes)) {
            return $this->_volumes[$vid];
        }

// Data ***
        $select = $this->getDb()
            ->select()
            ->from('VOLUME')
            ->where('VID = ?', $vid);
        $volume = $this->getDb()->fetchRow($select);

        if (!$volume) {
            $this->_volumes[$vid] = null;
            return null;
        }

        // Settings ***
        $select = $this->getDb()
            ->select()
            ->from('VOLUME_SETTING', [
                'SETTING',
                'VALUE'
            ])
            ->where('VID = ?', $vid);
        $volume['SETTINGS'] = $this->getDb()->fetchPairs($select);

        $this->_volumes[$vid] = $volume;
        return $volume;

    }

    private function cleanString($inputString): string
    {
        $inputString = trim($inputString);
        $inputString = trim($inputString, '"');
        $inputString = trim($inputString, "'");

        $inputString = trim($inputString, chr(173)); // https://en.wikipedia.org/wiki/Soft_hyphen

        $inputString = Ccsd_Tools::space_clean($inputString);

        // http://stackoverflow.com/questions/4166896/trim-unicode-whitespace-in-php-5-2
        $inputString = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $inputString);

        $utf8NeedleArray = [
            '“',
            '”',
            '„',
            '«',
            '»',
            '‘',
            '¿',
            '§',
            '—',
            '_',
            '|',
            '(',
            '[',
            '{',
            '}',
            ']',
            ')',
            '#',
            '<',
            '>',
            ',',
            ';',
            ':',
            '*',
            '.'
        ];

        $inputString = trim($inputString, '-');

        $inputString = str_replace($utf8NeedleArray, '', $inputString);
        $inputString = Ccsd_Tools_String::stripCtrlChars($inputString, '');

        return trim($inputString);
    }

    /**
     * @param $paperData
     * @param Document $docToIndex
     * @param $sections
     * @return void
     */
    private function indexSection($paperData, Document $docToIndex, $sections): void
    {
        if ($paperData->getSid()) {
            $docToIndex->addField('section_id_i', $paperData->getSid());
            $sectionTranslations = $sections;
            if (is_array($sectionTranslations)) {
                foreach ($sectionTranslations as $lang => $translations) {
                    $docToIndex->addField($lang . '_section_title_t', $translations['section_' . $paperData->getSid() . '_title']);
                }
            }

            // Facette "section_fs"
            $docToIndex->addField('section_fs', $paperData->getSid() . parent::SOLR_FACET_SEPARATOR . 'section_' . $paperData->getSid() . '_title');
        }
    }

}
