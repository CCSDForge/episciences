<?php

/**
 * Class Episciences_ReviewsManager
 * Journal Settings
 */
class Episciences_ReviewsManager
{
    /**
     * Intra-request cache for Review objects
     * Stores both successful finds and false results to avoid redundant DB queries
     * @var array<string, Episciences_Review|false>
     */
    private static array $_cache = [];

    /**
     * @param array|null $settings
     * @param bool $toArray
     * @return Episciences_Review[]
     * fetch a list of all episciences reviews
     */
    public static function getList(array $settings = null, $toArray = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $reviews = array();

        $select = $db->select()->from(T_REVIEW);

        // FILTRES
        $validFilters = array('rvid', 'code', 'status');

        if (isset($settings['is'])) {
            foreach ($settings['is'] as $setting => $value) {
                if (in_array(strtolower($setting), $validFilters)) {
                    $setting = strtoupper($setting);
                    if (is_array($value)) {
                        $select->where("$setting IN (?)", $value);
                    } else {
                        $select->where("$setting = ?", $value);
                    }
                }
            }
        }

        if (isset($settings['isNot'])) {
            foreach ($settings['isNot'] as $setting => $value) {
                if (in_array(strtolower($setting), $validFilters)) {
                    $setting = strtoupper($setting);
                    if (is_array($value)) {
                        $select->where("$setting NOT IN (?)", $value);
                    } else {
                        $select->where("$setting != ?", $value);
                    }
                }
            }
        }

        $data = $db->fetchAll($select);


        if ($data) {
            foreach ($data as $options) {
                $oReview = new Episciences_Review($options);
                $reviews[$oReview->getRvid()] = ($toArray) ? $oReview->toArray() : $oReview;
            }
        }
        return $reviews;
    }

    /**
     * Try to retrieve a review from a given rvid or rvcode
     * @param $id
     * @return bool|Episciences_Review
     */
    public static function find($id): Episciences_Review|bool
    {
        if (is_numeric($id)) {
            $review = self::findByRvid($id);
        } elseif (is_string($id)) {
            $review = self::findByRvcode($id);
        } else {
            $review = false;
        }
        return $review;
    }

    /**
     * Find a review by RVID (int)
     * @param int $id
     * @return bool|Episciences_Review
     */
    public static function findByRvid(int $id): Episciences_Review|bool
    {
        // Check cache first
        $cacheKey = 'rvid_' . $id;
        if (array_key_exists($cacheKey, self::$_cache)) {
            return self::$_cache[$cacheKey];
        }

        // Load from database
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_REVIEW)->where('RVID = ?', $id);
        $data = $db->fetchRow($select);

        $review = empty($data) ? false : new Episciences_Review($data);

        // Cache the result
        self::$_cache[$cacheKey] = $review;

        return $review;
    }

    /**
     * Find a review by RVCODE (string)
     * @param string $rvcode
     * @param bool $enabledOnly
     * @return bool|Episciences_Review
     */
    public static function findByRvcode(string $rvcode, bool $enabledOnly = false): Episciences_Review|bool
    {
        // Cache key includes the enabledOnly flag for proper segregation
        $cacheKey = 'rvcode_' . $rvcode . ($enabledOnly ? '_enabled' : '');
        if (array_key_exists($cacheKey, self::$_cache)) {
            return self::$_cache[$cacheKey];
        }

        // Load from database
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_REVIEW)->where('CODE = ?', $rvcode);

        if ($enabledOnly) {
            $select->where('STATUS = ?', Episciences_Review::ENABLED);
        }

        $data = $db->fetchRow($select);
        $review = empty($data) ? false : new Episciences_Review($data);

        // Cache the result
        self::$_cache[$cacheKey] = $review;

        return $review;
    }


    /**
     * OpenAIRE Metrics
     * @param string $creationDateBoundary
     * @return int
     */
    public static function findActiveJournalsWithAtLeastOneSubmission($creationDateBoundary = '1970-01-01 00:00:00')
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_REVIEW, [new Zend_Db_Expr("COUNT(DISTINCT(" . T_REVIEW . ".RVID)) AS NbActiveJournals")])
            ->from(T_PAPERS, null)
            ->where(T_REVIEW . '.STATUS = 1')
            ->where(T_REVIEW . '.CREATION >= ?', $creationDateBoundary);

        return (int)$db->fetchOne($select);
    }


    /**
     * Retrieve a list of publishing journals
     * @return array
     */
    public static function findActiveJournals(): array
    {

        $jNumber = 0;
        $journalCollection[$jNumber] = ['Number', 'Code', 'Title', 'ISSN', 'EISSN', 'Address', 'Accepted-repositories'];

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_REVIEW)->where('STATUS = 1');
        $select->order('NAME ASC');
        $allJournals = $db->fetchAll($select);
        if (!$allJournals) {
            return $journalCollection;
        }

        foreach ($allJournals as $journal) {
            $jNumber++;
            $oReview = new Episciences_Review($journal);
            $oReview->loadSettings();

            $issnPrint = $oReview->getSetting(Episciences_Review::SETTING_ISSN_PRINT);
            $issnElec = $oReview->getSetting(Episciences_Review::SETTING_ISSN);

            $acceptedRepositories = [];

            foreach ($oReview->getSetting(Episciences_Review::SETTING_REPOSITORIES) as $repoId) {

                $label = Episciences_Repositories::getLabel($repoId);

                if ('' !== $label) {

                    $automaticTransferParam = filter_var($oReview->getSetting(Episciences_Review::SETTING_DISABLE_AUTOMATIC_TRANSFER), FILTER_VALIDATE_BOOLEAN);

                    if ($automaticTransferParam && (int)$repoId === (int)Episciences_Repositories::HAL_REPO_ID) {
                        continue;
                    }

                    $acceptedRepositories[$repoId] = $label;
                }
            }

            if (!$issnPrint) {
                $issnPrint = '';
            } else {
                $issnPrint = Episciences_View_Helper_FormatIssn::FormatIssn($issnPrint);
            }

            if (!$issnElec) {
                $issnElec = '';
            } else {
                $issnElec = Episciences_View_Helper_FormatIssn::FormatIssn($issnElec);
            }
            $journalCollection[] = [$jNumber, $oReview->getCode(), $oReview->getName(), $issnPrint, $issnElec, $oReview->getUrl(), $acceptedRepositories];

        }

        return $journalCollection;

    }

    /**
     * Clear the internal cache
     * Useful for unit tests or when Review data is modified during request
     * @return void
     */
    public static function clearCache(): void
    {
        self::$_cache = [];
    }

}
