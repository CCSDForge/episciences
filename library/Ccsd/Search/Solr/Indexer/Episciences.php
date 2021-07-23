<?php


use Solarium\QueryType\Update\Query\Document\Document;

class Ccsd_Search_Solr_Indexer_Episciences extends Ccsd_Search_Solr_Indexer
{

    public static $_coreName = 'episciences';

    public static $_maxDocsInBuffer;

    public static $dbConfName = 'episciences';

    private $_reviews = [];

    private $_volumes = [];

    private $_sections = [];

    public function __construct(array $options)
    {
        $options['core'] = self::$_coreName;
        $options['maxDocsInBuffer'] = self::$_maxDocsInBuffer;
        parent::__construct($options);
    }

    /**
     * Set the select request to get the list of Id to index
     * @param $select
     */
    protected function selectIds($select)
    {
        $select->from('PAPERS', 'DOCID')->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
    }

    /**
     * @param int $docId
     * @param Document $ndx
     * @return bool|mixed
     * @throws Zend_Db_Statement_Exception
     */
    protected function addMetadataToDoc(int $docId, Document $ndx)
    {

        // Suffixes (conventions)
        // _t : text (correspondance approximative : insensible à la casse, aux accents)
        // _s : string (correspondance exacte)

        $repositories = Episciences_Repositories::getRepositories();

        $paperData = $this->getDocidData($docId);
        $paperVolumesData = $this->getPaperVolumesData($docId);

        if ($paperData == null) {
            Ccsd_Log::message('Update doc ' . $docId . ' : cet article n\'existe pas/plus.', true, 'WARN');
            return false;
        }

        // Récupération des infos du déposant
        $submitter = new Episciences_User;
        $submitter->findWithCas($paperData['UID']);

        // Récupération des infos de la revue
        $review = $this->getReview($paperData['RVID']);

        $volumeTranslations = $review['TRANSLATIONS']['volumes'] ?? null;

        // Récupération des infos sur les auteurs
        $authors = Ccsd_Tools::xpath($paperData['RECORD'], '//dc:creator');
        if (is_array($authors)) {
            $author_sort = [];
            foreach ($authors as $author) {
                $this->indexOneAuthor($author, $ndx);
                $author_sort[] = $author;
            }
            $author_fullname_sort = substr(implode(' ', $author_sort), 0, 50);

        } elseif (is_string($authors)) {
            $this->indexOneAuthor($authors, $ndx);
            $author_fullname_sort = self::cleanAuthorName($authors);
        }
        $ndx->addField('author_fullname_sort', $author_fullname_sort);

        // Récupération des mots-clés
        $keywords = Ccsd_Tools::xpath($paperData['RECORD'], '//dc:subject');
        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                $ndx->addField('keyword_t', $keyword);
            }
        } else {
            $ndx->addField('keyword_t', $keywords);
        }

        // Date de soumission
        $submission_date = ($paperData['SUBMISSION_DATE']) ? date_format(new DateTime($paperData['SUBMISSION_DATE']), "Y-m-d\Th:i:s\Z") : null;

        // Date de publication
        if ($paperData['PUBLICATION_DATE']) {
            // $publication_date = date_format(new DateTime(Ccsd_Tools::xpath($paperData['RECORD'], '//publication_date')), "Y-m-d\Th:i:s\Z");
            $publication_date = date_format(new DateTime($paperData['PUBLICATION_DATE']), "Y-m-d\Th:i:s\Z");
            $publication_date_array = explode('-', $publication_date);
            $publication_year = $publication_date_array[0];
            $publication_month = $publication_date_array[1];
            $publication_day = explode('T', $publication_date_array[2])[0];
        } else {
            $publication_date = null;
        }

        $review_title = $this->cleanChars($review['NAME']);

        $revue_date_creation = date_format(new DateTime($review['CREATION']), "Y-m-d\Th:i:s\Z");
        $es_doc_url = 'https://' . $review['CODE'] . '.' . DOMAIN . '/' . $docId;
        $es_pdf_url = $es_doc_url . '/pdf';

        $dataToIndex = [
            'docid' => $docId,
            'paperid' => $paperData['PAPERID'],
            'language_s' => Ccsd_Tools::xpath($paperData['RECORD'], '//dc:language'),
            'identifier_s' => $paperData['IDENTIFIER'],
            'version_td' => $paperData['VERSION'],
            'doc_url_s' => Ccsd_Tools::xpath($paperData['RECORD'], '//docURL'),
            'paper_url_s' => Ccsd_Tools::xpath($paperData['RECORD'], '//paperURL'),
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
            'revue_id_i' => $paperData['RVID'],
            'revue_status_i' => $review['STATUS'],
            'revue_code_t' => $review['CODE'],
            'revue_title_s' => $review_title,
            'revue_creation_date_tdate' => $revue_date_creation,

            'repo_id_i' => $paperData['REPOID'],
            'repo_title_s' => $repositories[$paperData['REPOID']]['label']];


        $titles = Episciences_Tools::xpath($paperData['RECORD'], '//dc:title', true);
        foreach ($titles as $locale => $title) {
            if (Zend_Locale::isLocale($locale)) {
                $titlesToIndex[$locale . '_paper_title_t'] = $title;
            }
        }
        if (!isset($titlesToIndex) || empty($titlesToIndex)) {
            $titlesToIndex['paper_title_t'] = $titles;
        }

        $abstracts = Episciences_Tools::xpath($paperData['RECORD'], '//dc:description', true);
        foreach ($abstracts as $locale => $abstract) {
            if (Zend_Locale::isLocale($locale)) {
                $abstractsToIndex[$locale . '_abstract_t'] = $abstract;
            }
        }
        if (!isset($abstractsToIndex) || empty($abstractsToIndex)) {
            $abstractsToIndex['abstract_t'] = $abstracts;
        }

        $dataToIndex = array_merge($dataToIndex, $titlesToIndex, $abstractsToIndex);


        foreach ($dataToIndex as $fieldName => $fieldValue) {
            if ($fieldValue) {
                $ndx->addField($fieldName, $fieldValue);
            }
        }

        // master volume data
        if ($paperData['VID']) {
            $volume = $this->getVolume($paperData['VID']);
            if (!$volume) {
                Ccsd_Log::message("Update doc " . $docId . " : le volume (" . $paperData['VID'] . ") de cet article n'existe pas/plus.", true, 'WARN');
                return false;
            }
            $ndx->addField('volume_id_i', $paperData['VID']);
            $ndx->addField('volume_status_i', $volume['SETTINGS']['status']);
            if (is_array($volumeTranslations)) {
                foreach ($volumeTranslations as $lang => $translations) {
                    if (array_key_exists('volume_' . $paperData['VID'] . '_title', $translations)) {
                        $ndx->addField($lang . '_volume_title_t', $translations['volume_' . $paperData['VID'] . '_title']);
                    }
                }
            }

            // Facette "volume_fs"
            $ndx->addField('volume_fs', $paperData['VID'] . parent::SOLR_FACET_SEPARATOR . 'volume_' . $paperData['VID'] . '_title');
        }

        // secondary volumes data
        if (!empty($paperVolumesData)) {
            foreach ($paperVolumesData as $vid) {
                $volume = $this->getVolume($vid);
                if (!$volume) {
                    Ccsd_Log::message("Update doc " . $docId . " : le volume secondaire (" . $vid . ") de cet article n'existe pas/plus.", true, 'WARN');
                    continue;
                }
                $ndx->addField('secondary_volume_id_i', $vid);
                if (is_array($volumeTranslations)) {
                    foreach ($volumeTranslations as $lang => $translations) {
                        if (array_key_exists('volume_' . $vid . '_title', $translations)) {
                            $ndx->addField($lang . '_secondary_volume_title_t', $translations['volume_' . $vid . '_title']);
                        }
                    }
                }

                // Facette "volume_fs"
                $ndx->addField('secondary_volume_fs', $vid . parent::SOLR_FACET_SEPARATOR . 'volume_' . $vid . '_title');
            }
        }

        // section data
        if ($paperData['SID']) {
            $ndx->addField('section_id_i', $paperData['SID']);
            $sectionTranslations = $review['TRANSLATIONS']['sections'];
            if (is_array($sectionTranslations)) {
                foreach ($sectionTranslations as $lang => $translations) {
                    $ndx->addField($lang . '_section_title_t', $translations['section_' . $paperData['SID'] . '_title']);
                }
            }

            // Facette "section_fs"
            $ndx->addField('section_fs', $paperData['SID'] . parent::SOLR_FACET_SEPARATOR . 'section_' . $paperData['SID'] . '_title');
        }

        $ndx->addField('indexing_date_tdate', date("Y-m-d\Th:i:s\Z"));

        // Facets ************************
        $ndx->addField('revue_title_fs', $paperData['RVID'] . parent::SOLR_FACET_SEPARATOR . $review_title);

        return $ndx;
    }

    protected function getDocidData($docId)
    {
        $db = $this->getDb();

        $select = $db->select();

        $select->from('PAPERS')
            ->where('DOCID = ?', $docId);

        $stmt = $select->query();
        $res = $stmt->fetchAll();
        if (empty($res)) {
            return null;
        }
        return $res[0];
    }

    protected function getPaperVolumesData($docId)
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


    private static function cleanAuthorName($name): string
    {
        $name = Ccsd_Tools::space_clean($name);
        $name = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $name);
        $name = Ccsd_Tools_String::stripCtrlChars($name, '');
        $name = str_replace(' ,', '', $name);
        return trim($name);
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


    // Renvoie les données d'une section

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

    /**
     * @param $sid
     * @return mixed
     */
    private function getSection($sid)
    {
        if (array_key_exists($sid, $this->_sections)) {
            return $this->_sections[$sid];
        }

// Data ***
        $select = $this->getDb()
            ->select()
            ->from('SECTION')
            ->where('SID = ?', $sid);
        $section = $this->getDb()->fetchRow($select);
        $this->_sections[$sid] = $section;
        return $section;
    }
}
