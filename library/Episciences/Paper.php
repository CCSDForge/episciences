<?php

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class Episciences_Paper
 */
class Episciences_Paper
{
    /**
     * Expire OAI Headers after 1 month
     */
    public const CACHE_EXPIRE_OAI_HEADER = 3600 * 24 * 31;

    /**
     * Expire metadata of UNpublished articles after 1 hour
     */
    public const CACHE_EXPIRE_METADATA_UNPUBLISHED = 3600;

    /**
     * Expire metadata of published articles after 1 month
     */
    public const CACHE_EXPIRE_METADATA_PUBLISHED = 3600 * 24 * 31;

    /**
     *
     */
    public const CACHE_CLASS_NAMESPACE = 'paper';

    const STATUS_SUBMITTED = 0;
    // reviewers have been assigned, but did not start their reports
    const STATUS_OK_FOR_REVIEWING = 1;
    // rating has begun (at least one reviewer has starter working on his rating report)
    const STATUS_BEING_REVIEWED = 2;
    // rating is finished (all reviewers)
    const STATUS_REVIEWED = 3;
    const STATUS_ACCEPTED = 4;
    const STATUS_REFUSED = 5;
    const STATUS_OBSOLETE = 6;
    const STATUS_WAITING_FOR_MINOR_REVISION = 7;
    const STATUS_WAITING_FOR_MAJOR_REVISION = 15;
    const STATUS_TMP_VERSION = 9;
    const STATUS_NO_REVISION = 10;
    const STATUS_NEW_VERSION = 11;
    const STATUS_WAITING_FOR_COMMENTS = 8;
    // paper removed by contributor (before publication)
    const STATUS_DELETED = 12;
    // paper removed by editorial board (after publication)
    const STATUS_REMOVED = 13;
    // reviewers have been invited, but no one has accepted yet
    const STATUS_REVIEWERS_INVITED = 14;
    const STATUS_PUBLISHED = 16;
    // Le processus de publication peut être stoppé tant que l'article n'est pas publié
    const STATUS_ABANDONED = 17;

    //Copy editing
    const STATUS_CE_WAITING_FOR_AUTHOR_SOURCES = 18;
    const STATUS_CE_AUTHOR_SOURCES_DEPOSED = 19;
    const STATUS_CE_REVIEW_FORMATTING_DEPOSED = 20;
    const STATUS_CE_WAITING_AUTHOR_FINAL_VERSION = 21;
    // version finale déposée en attente de validation
    const STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED = 22;
    const STATUS_CE_READY_TO_PUBLISH = 23;
    const STATUS_CE_AUTHOR_FORMATTING_DEPOSED = 24; // la mise en forme par l'auteur a été validée
    // paper settings
    const SETTING_UNWANTED_REVIEWER = 'unwantedReviewer';
    const SETTING_SUGGESTED_REVIEWER = 'suggestedReviewer';
    const SETTING_SUGGESTED_EDITOR = 'suggestedEditor';

    // paper status
    const STATUS_CODES = [
        self::STATUS_SUBMITTED,
        self::STATUS_OK_FOR_REVIEWING,
        self::STATUS_BEING_REVIEWED,
        self::STATUS_REVIEWED,
        self::STATUS_ACCEPTED,
        self::STATUS_REFUSED,
        self::STATUS_WAITING_FOR_MINOR_REVISION,
        self::STATUS_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_PUBLISHED,
        self::STATUS_ABANDONED,
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
        self::STATUS_CE_READY_TO_PUBLISH,
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED
    ];

    // Non présents dans le filtre de recherche

    const OTHER_STATUS_CODE = [
        self::STATUS_OBSOLETE,
        self::STATUS_TMP_VERSION,
        self::STATUS_NO_REVISION,
        self::STATUS_NEW_VERSION,
        self::STATUS_WAITING_FOR_COMMENTS,
        self::STATUS_DELETED,
    ];

    // exclude from a list of sorted papers for current volume
    const DO_NOT_SORT_THIS_KIND_OF_PAPERS = [
        self::STATUS_DELETED,
        self::STATUS_ABANDONED,
        self::STATUS_REMOVED,
        self::STATUS_REFUSED
    ];

    /**
     * @const string DOI prefix
     */
    const DOI_ORG_PREFIX = 'https://doi.org/';

    // status priorities
    public static $_statusPriority = [
        self::STATUS_SUBMITTED => 0,
        self::STATUS_BEING_REVIEWED => 1,
        self::STATUS_REVIEWED => 2,
        self::STATUS_ACCEPTED => 3,
        self::STATUS_REFUSED => 3,
        self::STATUS_OBSOLETE => 3,
        self::STATUS_DELETED => 3,
        self::STATUS_PUBLISHED => 3,
        self::STATUS_WAITING_FOR_MINOR_REVISION => 2,
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 2,
        self::STATUS_WAITING_FOR_COMMENTS => 2,
        self::STATUS_TMP_VERSION => 2
    ];

    // status order (for sorting)
    public static $_statusOrder = [
        self::STATUS_SUBMITTED => 0,
        self::STATUS_OK_FOR_REVIEWING => 1,
        self::STATUS_BEING_REVIEWED => 2,
        self::STATUS_REVIEWED => 3,
        self::STATUS_WAITING_FOR_MINOR_REVISION => 4,
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 4,
        self::STATUS_WAITING_FOR_COMMENTS => 5,
        self::STATUS_TMP_VERSION => 6,
        self::STATUS_OBSOLETE => 7,
        self::STATUS_ACCEPTED => 8,
        self::STATUS_REFUSED => 9,
        self::STATUS_DELETED => 10,
        self::STATUS_PUBLISHED => 11,
    ];

    public static $_statusLabel = [
        self::STATUS_SUBMITTED => 'soumis',
        self::STATUS_OK_FOR_REVIEWING => 'en attente de relecture',
        self::STATUS_BEING_REVIEWED => 'en cours de relecture',
        self::STATUS_REVIEWED => 'relu',
        self::STATUS_ACCEPTED => 'accepté',
        self::STATUS_PUBLISHED => 'publié',
        self::STATUS_REFUSED => 'refusé',
        self::STATUS_OBSOLETE => 'obsolète',
        self::STATUS_WAITING_FOR_MINOR_REVISION => 'en attente de modifications mineures',
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 'en attente de modifications majeures',
        self::STATUS_WAITING_FOR_COMMENTS => 'en attente d\'éclaircissements',
        self::STATUS_TMP_VERSION => 'version temporaire',
        self::STATUS_NO_REVISION => 'réponse à une demande de modifications: pas de modifications',
        self::STATUS_NEW_VERSION => 'réponse à une demande de modifications: nouvelle version',
        self::STATUS_DELETED => 'supprimé',
        self::STATUS_ABANDONED => 'abandonné',
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES => 'copy ed : en attente des sources auteurs',
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED => 'copy ed. : en attente de la mise en forme par la revue',
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION => 'copy ed : en attente de la version finale auteur',
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED => 'copy ed : version finale déposée en attente de validation',
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED => 'copy ed : mise en forme par la revue terminée, en attente de la version finale',
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED => "copy ed : mise en forme par l'auteur terminée, en attente de la version finale",
        self::STATUS_CE_READY_TO_PUBLISH => 'copy ed : prêt à publier',
    ];

    public static $_noEditableStatus = [
        self::STATUS_PUBLISHED,
        self::STATUS_REFUSED,
        self::STATUS_REMOVED,
        self::STATUS_DELETED,
        self::STATUS_OBSOLETE,
        self::STATUS_ABANDONED
    ];

    public static $_canBeAssignedDOI = [
        self::STATUS_ACCEPTED,
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
        self::STATUS_CE_READY_TO_PUBLISH,
        self::STATUS_PUBLISHED,
    ];

    /**
     * @var int
     */
    private $_docId;
    /**
     * @var int
     */
    private $_paperId = 0;
    /**
     * @var string
     */
    private $_doi;
    private $_version;
    private $_rvId = 0;
    private $_vId = 0;
    private $_sId = 0;
    private $_uId;
    private $_status = 0;
    private $_identifier;
    private $_repoId = 0;
    private $_record;
    /**
     * Pour vérifier si les versions (autres archives (exp Zenodo)) sont liées entre elles.
     * @var string
     */
    private $_concept_identifier;
    private $_when;
    private $_submission_date;
    private $_modification_date;
    /**
     * @var string
     */
    private $_publication_date;

    private $_settings;
    private $_otherVolumes;
    private $_withxsl = true;

    /**
     * @var array
     */
    private $_versionsIds;

    private $_previousVersions;
    private $_metadata;
    /** @var Episciences_User */
    private $_submitter;
    private $_xslt;
    private $_xml;
    private $_docUrl;
    private $_paperUrl;
    private $_solrData = [];
    private $_history;
    private $_latestVersionId;

    private $_suggestedEditors;
    /** @var Episciences_Editor[] $_editors */
    private $_editors = [];
    private $_suggestedReviewers;
    /** @var Episciences_Reviewer[] $_reviewers */
    private $_reviewers;
    private $_invitations;
    private $_comments;

    private $_ratingGrid;
    private $_ratings;

    private $_reports;

    /** @var Episciences_CopyEditor[] $_copyEditors */
    private $_copyEditors;

    /** @var Array [Episciences_Paper_Conflict] */
    private $_conflicts = [];

    /**
     * Position in volume
     * @var null | int
     */
    private $_position;
    private $_files;
    private $_datasets;
    /** @var string  */
    private $_flag = 'submitted'; // defines whether the paper has been submitted or imported
    public $hasHook; // !empty(Episciences_Repositories::hasHook($this->getRepoid()));

    public static $validMetadataFormats = ['bibtex', 'tei', 'dc', 'datacite', 'crossref', 'zbjats'];

    /**
     * Episciences_Paper constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * set paper options
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $classMethods = get_class_methods($this);

        foreach ($options as $key => $value) {

            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $classMethods, true)) {
                if ($method === 'setRecord') {
                    // if method is setRecord, wait before running it
                    $record = $value;
                } else {
                    $this->$method($value);
                }
            }
        }

        if (isset($record)) {
            $this->setRecord($record);
        }

        return $this;
    }

    /**
     * @param int $rvid
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Exception
     */
    public static function createPaperDoi(int $rvid, Episciences_Paper $paper): array
    {
        // if a DOI exists, do not update
        if (($paper->hasDoi()) || (!$paper->canBeAssignedDOI())) {
            error_log('No DOI assigned because paper ' . $paper->getDoi() . ' has a DOI.');
            return ['doi' => $paper->getDoi(), 'resUpdateDoi' => 0, 'resUpdateDoiQueue' => 0];
        }

        $doiSettings = Episciences_Review_DoiSettingsManager::findByJournal($rvid);
        $doi = $doiSettings->createDoiWithTemplate($paper);

        if ($doi == '') {
            return ['doi' => $doi, 'resUpdateDoi' => 0, 'resUpdateDoiQueue' => 0];
        }

        $resUpdateDoi = Episciences_PapersManager::updateDoi($doi, $paper->getPaperid());

        if ($resUpdateDoi === 0) {
            return ['doi' => $doi, 'resUpdateDoi' => 0, 'resUpdateDoiQueue' => 0];
        }

        $doiQueue = new Episciences_Paper_DoiQueue(['paperid' => $paper->getPaperid(), 'doi_status' => Episciences_Paper_DoiQueue::STATUS_ASSIGNED]);
        $resUpdateDoiQueue = Episciences_Paper_DoiQueueManager::add($doiQueue);

        $paper->log(Episciences_Paper_Logger::CODE_DOI_ASSIGNED, null, ['DOI' => $doi]);


        return ['doi' => $doi, 'resUpdateDoi' => $resUpdateDoi, 'resUpdateDoiQueue' => $resUpdateDoiQueue];
    }

    /**
     * @return bool
     */
    public function hasDoi(): bool
    {
        return $this->getDoi() != '';
    }

    /**
     * @return mixed
     */
    public function getDoi($withPrefix = false)
    {
        if ($withPrefix && $this->_doi !== '') {
            return self::DOI_ORG_PREFIX . $this->_doi;
        }
        return $this->_doi;
    }


    /**
     * @param string $doi
     * @return $this
     */
    public function setDoi($doi): self
    {
        $this->_doi = $doi;
        return $this;
    }

    public function canBeAssignedDOI()
    {
        if (in_array($this->getStatus(), self::$_canBeAssignedDOI, true)) {
            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->_status;
    }

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status): self
    {
        $this->_status = (int)$status;
        return $this;
    }

    /**
     * @return int
     */
    public function getPaperid(): int
    {
        return $this->_paperId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setPaperid($id): self
    {
        $this->_paperId = (int)$id;
        return $this;
    }

    /**
     * @param $action
     * @param null $uid
     * @param null $detail
     * @param null $date
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function log($action, $uid = null, $detail = null, $date = null): bool
    {
        if ($this->getPaperid() && $this->getDocid()) {
            $detail = (is_array($detail)) ? Zend_Json::encode($detail) : $detail;
            Episciences_Paper_Logger::log($this->getPaperid(), $this->getDocid(), $action, $uid, $detail, $date, $this->getRvid());
            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getDocid()
    {
        return $this->_docId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setDocid($id): self
    {
        $this->_docId = (int)$id;
        return $this;
    }

    /**
     * @return int
     */
    public function getRvid(): int
    {
        return $this->_rvId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setRvid($id): self
    {
        $this->_rvId = (int)$id;
        return $this;
    }

    /**
     * @param string $format
     * @return string
     */
    public static function getEarliestPublicationDate(string $format = 'Y-m-d'): string
    {
        $earliestPublicationDate = Episciences_PapersManager::getEarliestPublicationDate();

        if ($earliestPublicationDate === '') {
            // silly default
            $earliestPublicationDate = '1970-01-01 00:00:00';
        }

        try {
            $earliestPublicationDateObj = new DateTime($earliestPublicationDate);
            $earliestPublicationDateFormatted = $earliestPublicationDateObj->format($format);
        } catch (Exception $exception) {
            $earliestPublicationDateFormatted = '1970-01-01';
        }
        return $earliestPublicationDateFormatted;
    }


    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        $result['uid'] = $this->getUid();
        $result['docId'] = $this->getDocid();
        $result['doi'] = $this->getDoi();
        $result['paperId'] = $this->getPaperid();
        $result['rvId'] = $this->getRvid();
        $result['date'] = $this->getWhen();
        $result['vId'] = $this->getVid();
        $result['sId'] = $this->getSid();
        $result['status'] = $this->getStatus();
        $result['identifier'] = $this->getIdentifier();
        $result['repoId'] = $this->getRepoid();
        $result['record'] = $this->getRecord();
        $result['xml'] = $this->getXml();
        $result['xslt'] = $this->getXslt();
        $result['metadata'] = $this->getAllMetadata();
        $result['submitter'] = $this->getSubmitter();
        $result['oaLink'] = $this->getOALink();
        $result['publication_date'] = $this->getPublication_date();

        if ($this->_ratings) {
            $result['averageRating'] = $this->getAverageRating();
            $ratings = $this->_ratings;
            /** @var Episciences_Rating_Report $rating */
            foreach ($ratings as &$rating) {
                $rating = $rating->toArray();
            }
            unset($rating);
            $result['ratings'] = $ratings;
        }

        if ($this->_reviewers) {
            $reviewers = $this->_reviewers;
            foreach ($reviewers as &$reviewer) {
                $reviewer = $reviewer->toArray();
            }
            unset($reviewer);
            $result['reviewers'] = $reviewers;
        }

        if ($this->_editors) {
            $editors = $this->_editors;
            foreach ($editors as &$editor) {
                $editor = $editor->toArray();
            }
            unset($editor);
            $result['editors'] = $editors;
        }

        if (isset($this->_latestVersionId) && $this->_latestVersionId) {
            $result['latestVersionId'] = $this->_latestVersionId;
        }

        if ($this->hasHook && isset($this->_concept_identifier)) {
            $result['concept_identifier'] = $this->getConcept_identifier();
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getUid()
    {
        return $this->_uId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setUid($id): self
    {
        $this->_uId = (int)$id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getWhen()
    {
        return $this->_when;
    }

    /**
     * @param $when
     * @return $this
     */
    public function setWhen($when): self
    {
        $this->_when = $when;
        return $this;
    }

    /**
     * @return int
     */
    public function getVid(): int
    {
        return $this->_vId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setVid($id): self
    {
        $this->_vId = (int)$id;
        return $this;
    }

    /**
     * @return int
     */
    public function getSid(): int
    {
        return $this->_sId;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setSid($id): self
    {
        $this->_sId = (int)$id;
        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        /*
         *  Quant un article est refusé par la revue , l'auteur pourra soumettre une nouvelle version, dans ce cas précis,
         *  l'identfiiant de cet article est renommé en "IDENTIFIER-REFUSED"
        */

        if ($this->getStatus() === self::STATUS_REFUSED && false !== strpos($this->_identifier, "-REFUSED")) {
            $this->setIdentifier(explode('-REFUSED', $this->_identifier)[0]);
        }

        return $this->_identifier;
    }

    /**
     * @param $identifier
     * @return $this
     */
    public function setIdentifier($identifier): self
    {
        $this->_identifier = trim($identifier);
        return $this;
    }

    /**
     * @return int
     */
    public function getRepoid(): int
    {
        return $this->_repoId;
    }

    /**
     * @param $repoId
     * @return $this
     */
    public function setRepoid($repoId): self
    {
        $this->_repoId = (int)$repoId;
        $this->hasHook = !empty(Episciences_Repositories::hasHook($this->getRepoid()));
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRecord()
    {
        return $this->_record;
    }

    /**
     * @param $record
     * @return $this
     */
    public function setRecord($record): self
    {
        $this->_record = $record;

        // if script is run from shell, do not updateXML
        if (PHP_SAPI !== 'cli' && $this->getWithxsl()) {
            $this->updateXml();
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getXml()
    {
        return $this->_xml;
    }

    /**
     * @return mixed
     */
    public function getXslt()
    {
        return $this->_xslt;
    }

    /**
     * @param $xml
     * @param string $theme
     * @return $this
     */
    public function setXslt($xml, $theme = 'full_paper'): self
    {
        $this->_xslt = Ccsd_Tools::xslt($xml, APPLICATION_PUBLIC_PATH . '/xsl/' . $theme . '.xsl');
        return $this;
    }

    /**
     * fetch all metadata
     * @return mixed
     */
    public function getAllMetadata()
    {
        if (!$this->_metadata && $this->getRecord()) {
            $this->setMetadata($this->getRecord());
        }

        return $this->_metadata;
    }

    /**
     * @return Episciences_User
     */
    public function getSubmitter(): \Episciences_User
    {
        if (empty($this->_submitter) && $this->getUid()) {
            $this->loadSubmitter();
        }
        return $this->_submitter;
    }

    /**
     * @param bool $withCAS
     * @return bool
     */

    public function loadSubmitter($withCAS = true): bool
    {
        if (!$this->getUid()) {
            return false;
        }

        $submitter = new Episciences_User;
        $findMethod = ($withCAS) ? 'findWithCas' : 'find';
        if ($submitter->$findMethod($this->getUid())) {
            $this->_submitter = $submitter;
        }
        return true;
    }

    /**
     * @return bool|mixed
     */
    public function getOALink()
    {
        if ($this->_repoId && $this->_identifier) {
            return Episciences_Repositories::getDocUrl($this->_repoId, $this->_identifier);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getPublication_date()
    {
        return $this->_publication_date;
    }

    /**
     * @param $publication_date
     * @return $this
     */
    public function setPublication_date($publication_date): self
    {
        $this->_publication_date = $publication_date;
        return $this;
    }

    /**
     * fetch average rating (calculated from all completed rating reports)
     * @param int $precision
     * @return bool|float|null
     * @throws Zend_Db_Statement_Exception
     */
    public function getAverageRating(int $precision = 0)
    {
        $ratings = $this->getRatings();
        if (empty($ratings)) {
            return false;
        }
        return Episciences_Rating_Manager::getAverageRating($ratings, $precision);
    }

    /**
     * return paper rating reports (can be filtered)
     * @param null $reviewer_uid : if reviewer_uid is given, filter reports that were not emitted by this reviewer
     * @param null $status : if status is given, filter reports that do not match
     * @param Episciences_User|null $user : if user is given, remove criteria that this user is not allowed to see
     * @return Episciences_Rating_Report[]
     * @throws Zend_Db_Statement_Exception
     */
    public function getRatings($reviewer_uid = null, $status = null, Episciences_User $user = null)
    {
        $reports = $this->_ratings;

        // if a reviewer uid is provided, remove rating reports that do not match
        if ($reviewer_uid && $reports) {
            $reports = $this->filterReportsByReviewer($reports, $reviewer_uid);
        }

        // if a status is provided, remove rating reports that do not match
        if ($status && $reports) {
            $reports = $this->filterReportsByStatus($reports, $status);
        }

        // if visibility filter is activated, remove criteria that not authenticated/authenticated user is not allowed to see
        if ((!Episciences_Auth::isLogged() || $user) && $reports) {
            $reports = $this->filterReportsByUserRole($reports, $user);
        }

        return $reports;
    }

    /**
     * @param $ratings
     * @return $this
     */
    public function setRatings($ratings): self
    {
        $this->_ratings = $ratings;
        return $this;
    }

    /**
     * filter an array of rating reports, according to a reviewer uid
     * @param Episciences_Rating_Report[] $reports
     * @param $uid
     * @return Episciences_Rating_Report[]
     */
    public function filterReportsByReviewer(array $reports, $uid): array
    {
        /** @var Episciences_Rating_Report $report */
        foreach ($reports as $id => $report) {
            if ($report->getUid() != $uid) {
                unset($reports[$id]);
            }
        }
        return $reports;
    }

    /**
     * filter an array of rating reports, according to a rating status
     * @param Episciences_Rating_Report[] $reports
     * @param $status
     * @return Episciences_Rating_Report[]
     */
    public function filterReportsByStatus(array $reports, $status): array
    {
        /** @var Episciences_Rating_Report $report */
        foreach ($reports as $id => $report) {
            if ($report->getStatus() != $status) {
                unset($reports[$id]);
            }
        }
        return $reports;
    }

    /**
     * filter an array of rating reports criterion, according to a given user
     * @param Episciences_Rating_Report[] $reports
     * @param Episciences_User $user
     * @return Episciences_Rating_Report[]
     * @throws Zend_Db_Statement_Exception
     */
    public function filterReportsByUserRole(array $reports, Episciences_User $user = null): array
    {
        if (null != $user) {
            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();

            $isEditor = ($review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS) == 1) ?
                $this->getEditor($user->getUid()) :
                $user->isEditor();

            if ($user->getUid() == $this->getUid()) {
                // user is the paper contributor
                $visibility = [
                    Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                    Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR
                ];
            } elseif (Episciences_Auth::isChiefEditor() || Episciences_Auth::isSecretary() || $isEditor) {
                // user is an editor
                $visibility = [
                    Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                    Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR,
                    Episciences_Rating_Criterion::VISIBILITY_EDITORS
                ];
            } else {
                // user has no specific privilege
                $visibility = [Episciences_Rating_Criterion::VISIBILITY_PUBLIC];
            }

        } else { // not logged
            $visibility = [Episciences_Rating_Criterion::VISIBILITY_PUBLIC];
        }

        return $this->filterRatingReports($reports, $visibility, $user);
    }

    /**
     * fetch an editor
     * @param $uid
     * @return Episciences_Editor|bool
     * @throws Zend_Db_Statement_Exception
     */
    public function getEditor($uid)
    {
        if (empty($this->_editors)) {
            $this->_editors = Episciences_PapersManager::getEditors($this->getDocid(), true, true);
        }

        $isExist = array_key_exists($uid, $this->_editors);

        return ($isExist) ? $this->_editors[$uid] : $isExist;
    }

    /**
     * filter rating reports
     * @param array $reports
     * @param array $visibility
     * @param Episciences_User|null $user
     * @return array
     */
    private function filterRatingReports(array $reports, array $visibility = [Episciences_Rating_Criterion::VISIBILITY_PUBLIC], Episciences_User $user = null): array
    {
        /** @var Episciences_Rating_Report $report */
        foreach ($reports as &$report) {
            $report = clone $report;
            /** @var Episciences_Rating_Criterion $criterion */
            foreach ((array)$report->getCriteria() as $criterion) {
                if ($criterion->isEmpty()) { // git #248 : ne pas afficher les rapport vides
                    $report->removeCriterion($criterion->getId());
                } elseif (!in_array($criterion->getVisibility(), $visibility)) {
                    if (null != $user && $report->getUid() == $user->getUid()) {
                        continue;
                    }
                    $report->removeCriterion($criterion->getId());
                }
            }

            if (empty($report->getCriteria())) {
                unset($reports[$report->getUid()]);
            }
        }
        return $reports;
    }

    /**
     * assign user to paper (reviewer or editor)
     * @param $uid
     * @param $roleId
     * @param null $status
     * @return bool|int
     * @throws Zend_Exception
     */
    public function assign($uid, $roleId, $status = null)
    {
        if (!is_numeric($uid)) {
            return false;
        }

        $oAssignment = new Episciences_User_Assignment([
            'uid' => $uid,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'itemid' => $this->getDocid(),
            'roleid' => $roleId,
            'status' => Ccsd_Tools::ifsetor($status, Episciences_User_Assignment::STATUS_ACTIVE)
        ]);

        if ($oAssignment->save()) {
            return $oAssignment->getId();
        }

        throw new Zend_Exception("Failed to save assignment.");

    }

    /**
     * remove user assignment (reviewer or editor)
     * @param $uid
     * @param $roleId
     * @return bool|int
     * @throws Zend_Exception
     */
    public function unassign($uid, $roleId)
    {
        if (!is_numeric($uid)) {
            return false;
        }

        $oAssignment = new Episciences_User_Assignment([
            'uid' => $uid,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'itemid' => $this->getDocid(),
            'roleid' => $roleId,
            'status' => Ccsd_Tools::ifsetor($status, Episciences_User_Assignment::STATUS_INACTIVE)
        ]);

        if ($oAssignment->save()) {
            return $oAssignment->getId();
        }

        throw new Zend_Exception("Failed to save assignment.");

    }

    /**
     * check if paper already exists in database
     * @return string
     */
    public function alreadyExists(): string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(T_PAPERS, ['DOCID'])
            ->where('RVID = ?', $this->getRvid())
            ->where('STATUS != ?', Episciences_Paper::STATUS_DELETED);

        if ($this->hasHook) {
            $sql->where('CONCEPT_IDENTIFIER = ?', $this->getConcept_identifier());
        } else {
            $sql->where('IDENTIFIER = ?', $this->getIdentifier());
        }

        if ($this->getVersion()) {
            $sql->where('VERSION = ?', $this->getVersion());
        }

        $sql->where('REPOID = ?', $this->getRepoid());

        // Si plusieurs version de l'article, on recupère l'article dans sa dernière version
        $sql->order('WHEN DESC');

        return ($db->fetchOne($sql));
    }

    /**
     *  return oa version number
     * @return mixed
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @param $version
     * @return $this
     */
    public function setVersion($version): self
    {
        $this->_version = (float)$version;
        return $this;
    }

    /**
     * check if paper can be reviewed
     * paper can be reviewed if status is not one of these:
     * accepted, published, refused, removed, deleted, obsolete
     * @return bool
     */
    public function canBeReviewed(): bool
    {
        return ($this->isEditable() && !$this->isAccepted() && !$this->isRevisionRequested());
    }

    /**
     * check if paper can be edited
     * paper is editable if status is not one of these:
     * published, refused, removed, deleted, obsolete
     * @return bool
     */
    public function isEditable(): bool
    {
        return (Episciences_Auth::getUid() != $this->getUid()) &&
            !in_array($this->getStatus(), self::$_noEditableStatus);
    }

    /**
     * @return bool
     */
    public function isAccepted(): bool
    {
        return ($this->getStatus() === self::STATUS_ACCEPTED);
    }

    /**
     * @return bool
     */
    public function isRefused(): bool
    {
        return ($this->getStatus() === self::STATUS_REFUSED);
    }

    /**
     * @return bool
     */
    public function isRemoved(): bool
    {
        return ($this->getStatus() === self::STATUS_REMOVED);
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->getStatus() === self::STATUS_DELETED);
    }

    /**
     * @return bool
     */
    public function isTmp(): bool
    {
        return ($this->getRepoid() === 0);
    }

    /**
     * Verifie si le processus de publication d'un article a été abandonné
     * @return bool
     */
    public function isAbandoned(): bool
    {
        return ($this->getStatus() === self::STATUS_ABANDONED);
    }

    public function isRevisionRequested(): bool
    {
        return in_array($this->getStatus(), [self::STATUS_WAITING_FOR_MINOR_REVISION, self::STATUS_WAITING_FOR_MAJOR_REVISION], true);
    }

    /**
     * @return string
     */
    public function getLatestVersionId(): string
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPERS, 'DOCID')
            ->where('PAPERID = ?', $this->getPaperid())
            ->order('WHEN DESC');
        $latestId = $db->fetchOne($sql);
        $this->_latestVersionId = $latestId;
        return $latestId;
    }

    /**
     * @return mixed
     */
    public function getStatusLabel()
    {
        return Episciences_PapersManager::getStatusLabel($this->getStatus());
    }

    /**
     * @param $uid
     * @return mixed|null
     */
    public function getRating($uid)
    {
        $ratings = $this->_ratings;
        return (is_array($ratings) && array_key_exists($uid, $ratings)) ? $ratings[$uid] : null;
    }

    /**
     * @param $uid
     * @return mixed|null
     */
    public function getReport($uid)
    {
        $reports = $this->_reports;
        return (is_array($reports) && array_key_exists($uid, $reports)) ? $reports[$uid] : null;
    }

    /**
     * @return mixed
     */
    public function getHistory()
    {
        if (!$this->_history) {
            $this->loadHistory();
        }
        return $this->_history;
    }

    /**
     * @param $history
     * @return $this
     */
    public function setHistory($history): self
    {
        $this->_history = $history;
        return $this;
    }

    /**
     * Load history
     * @return $this
     */
    public function loadHistory(): self
    {
        $history = null;

        if ($this->getPaperid()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            $sql = $this->loadHistoryQuery($db);

            $logs = $db->fetchAll($sql);

            $versionsIds = $this->getVersionsIds();
            $versionsNumbers = array_flip($versionsIds);

            // sort logs by version (docid)
            foreach ($logs as $log) {
                $history[$versionsNumbers[$log['DOCID']]]['logs'][] = $log;
                if (!array_key_exists('docid', $history[$versionsNumbers[$log['DOCID']]])) {
                    $history[$versionsNumbers[$log['DOCID']]]['docid'] = $log['DOCID'];
                }
            }
        }

        return $this->setHistory($history);
    }

    /**
     * Load history query
     * @param Zend_Db_Adapter_Abstract $db
     * @param array $actions
     * @return Zend_Db_Select
     */
    private function loadHistoryQuery(Zend_Db_Adapter_Abstract $db, array $actions = []): \Zend_Db_Select
    {
        $query = $db->select()
            ->from(T_LOGS)
            //->where('DOCID = ?', $this->getDocid())
            ->where('PAPERID = ?', $this->getPaperid());

        if (!empty($actions)) {
            $query->where('ACTION in (?)', $actions);
        }

        $query->order('DOCID DESC');
        $query->order('DATE DESC');
        $query->order('LOGID DESC');

        return $query;
    }

    /**
     * @return mixed
     */
    public function getVersionsIds()
    {
        if (!$this->_versionsIds) {
            $this->loadVersionsIds();
        }
        return $this->_versionsIds;
    }

    /**
     * @param $versionsIds
     * @return $this
     */
    public function setVersionsIds($versionsIds): self
    {
        $this->_versionsIds = $versionsIds;
        return $this;
    }

    /**
     * load other versions ids
     * @return $this
     */
    public function loadVersionsIds(): self
    {
        $versionsIds = [];
        if ($this->getPaperid()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sql = $db->select()
                ->distinct()
                ->from(T_PAPERS, ['DOCID', 'VERSION', 'WHEN'])
                ->where('PAPERID = ?', $this->getPaperid())
                ->order('WHEN ASC');

            $sqlResult = $db->fetchAssoc($sql);

            foreach ($sqlResult as $docId => $value) {
                $version = $value['VERSION'];
                $versionsIds[$version] = $docId;
            }
        }

        return $this->setVersionsIds($versionsIds);
    }

    /**
     * return episciences version number when given a docid (default: current docid)
     * @param null $docId
     * @return null
     */
    public function getVersionNumber($docId = null)
    {
        $version = null;

        if (!$docId) {
            $docId = $this->getDocid();
        }

        $versionsIds = $this->getVersionsIds();
        if (is_array($versionsIds)) {
            $versions = array_flip($versionsIds);
            if (array_key_exists($docId, $versions)) {
                $version = $versions[$docId];
            }
        }

        return $version;
    }

    /**
     * fetch editors
     * @param bool $active
     * @param bool $getCASdata
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getEditors($active = true, $getCASdata = false): array
    {
        if (empty($this->_editors) || $getCASdata) {
            $editors = Episciences_PapersManager::getEditors($this->getDocid(), $active, $getCASdata);
            $this->_editors = $editors;
        }

        return $this->_editors;
    }

    /**
     * @param bool $active
     * @param bool $getCASdata
     * @return Episciences_CopyEditor[]
     * @throws Zend_Db_Statement_Exception
     */
    public function getCopyEditors($active = true, $getCASdata = false)
    {
        if (empty($this->_copyEditors) || $getCASdata) {
            $copyEditors = Episciences_PapersManager::getCopyEditors($this->getDocid(), $active, $getCASdata);
            $this->_copyEditors = $copyEditors;
        }

        return $this->_copyEditors;
    }

    /**
     * fetch suggested reviewers
     * @return mixed
     */
    public function getSuggestedReviewers()
    {
        if (empty($this->_suggestedReviewers)) {

            $suggestedReviewers = Episciences_ReviewersManager::getSuggestedReviewers($this->getDocid());
            $this->setSuggestedReviewers($suggestedReviewers);
        }

        return $this->_suggestedReviewers;
    }

    /**
     * @param $reviewers
     * @return $this
     */
    public function setSuggestedReviewers($reviewers): self
    {
        $this->_suggestedReviewers = $reviewers;
        return $this;
    }

    /**
     * fetch suggested editors
     * @return array
     */
    public function getSuggestedEditors(): array
    {
        if (empty($this->_suggestedEditors)) {

            $suggestedEditors = Episciences_EditorsManager::getSuggestedEditors($this->getDocid());
            $this->_suggestedEditors = $suggestedEditors;
        }

        return $this->_suggestedEditors;
    }

    /**
     * fetch comments
     * @param null $settings
     * @return array|null
     */
    public function getComments($settings = null)
    {
        if (empty($this->_comments)) {
            $this->_comments = Episciences_CommentsManager::getList($this->getDocid(), $settings);
        }

        return $this->_comments;
    }

    /**
     * @param $grid
     * @return $this
     */
    public function setRatingGrid($grid): self
    {
        $this->_ratingGrid = $grid;
        return $this;
    }

    /**
     * @param null $status
     * @param bool $priority
     * @return int|null
     * @throws Zend_Db_Statement_Exception
     */
    public function updateStatus($status = null, $priority = false)
    {
        $currentStatus = $this->getStatus();
        $newStatus = null;

        // process status priority
        if (!$priority) {

            // Ancien statut : demande de modifications
            if ($currentStatus === self::STATUS_WAITING_FOR_MINOR_REVISION || $currentStatus === self::STATUS_WAITING_FOR_MAJOR_REVISION) {

                // Nouveau statut : l'auteur répond sans modifications
                if ($status === self::STATUS_NO_REVISION) {
                    if ($this->isReviewed()) {
                        $newStatus = self::STATUS_REVIEWED;
                    } elseif ($this->isBeingReviewed()) {
                        $newStatus = self::STATUS_BEING_REVIEWED;
                    } elseif (count($this->getReviewers())) {
                        $newStatus = self::STATUS_OK_FOR_REVIEWING;
                    } else {
                        $newStatus = self::STATUS_SUBMITTED;
                    }
                }

            } elseif ($currentStatus === self::STATUS_WAITING_FOR_COMMENTS) {

                if ($this->isReviewed()) {
                    $newStatus = self::STATUS_REVIEWED;
                } elseif ($this->isBeingReviewed()) {
                    $newStatus = self::STATUS_BEING_REVIEWED;
                } elseif (count($this->getReviewers())) {
                    $newStatus = self::STATUS_OK_FOR_REVIEWING;
                } else {
                    $newStatus = self::STATUS_SUBMITTED;
                }

            }

            $status = ($newStatus) ?: $status;

        }

        $this->setStatus($status);

        // TODO : vérifier mise à jour du statut XML (sans réécrire le XML à chaque récupération de l'article..)

        return $status;
    }

    /**
     * check if all reviewers have completed their report
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function isReviewed(): bool
    {

        $reports = $this->getReports();

        if (!$reports) {
            // if there isn't any report, paper is not reviewed
            return false;
        }

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->loadRatings(null, Episciences_Rating_Report::STATUS_COMPLETED);

        if ($review->getSetting('requiredReviewers')) {
            return (count($this->getRatings(null, Episciences_Rating_Report::STATUS_COMPLETED)) >= (int)$review->getSetting('requiredReviewers'));
        }

        $completed_reports = 0;

        foreach ($reports as $report) {
            if ($report->isCompleted()) {
                $completed_reports++;
            }
        }

        return ($completed_reports === count($reports));
    }

    /**
     * @param null $status
     * @param bool $forceFiltering
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getReports($status = null, $forceFiltering = false)
    {
        if (!is_array($this->_reports)) {
            $this->loadReports();
        }

        $reports = $this->_reports;

        // if a status is provided, we remove reports that do not match
        if ($status && $reports) {
            /** @var Episciences_Rating_Report $report */
            foreach ($reports as $id => $report) {
                if ($report->getStatus() != $status) {
                    unset($reports[$id]);
                }
            }
        }
        return $forceFiltering ? $this->filterReportsByUserRole($reports) : $reports;
    }

    /**
     * @param $reports
     * @return $this
     */
    public function setReports($reports): self
    {
        $this->_reports = $reports;
        return $this;
    }

    /**
     * @return array
     */
    public function loadReports(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()->from(T_REVIEWER_REPORTS)->where('DOCID = ?', $this->getDocid());
        // WHERE DOCID IN (SELECT DOCID FROM `PAPERS` WHERE PAPERID = (SELECT PAPERID FROM PAPERS WHERE DOCID = 2443))

        $reports = [];
        foreach ($db->fetchAll($sql) as $row) {
            $report = new Episciences_Rating_Report($row);
            $reports[$report->getUid()] = $report;
        }

        $this->setReports($reports);
        return $reports;
    }

    /**
     * load rating reports
     * @param null $uid
     * @param null $status
     * @return Episciences_Paper
     */
    public function loadRatings($uid = null, $status = null): \Episciences_Paper
    {
        $reports = [];
        foreach (Episciences_Rating_Manager::getList($this->getDocid(), $uid, $status) as $report) {
            $reports[$report->getUid()] = $report;
        }

        return $this->setRatings($reports);
    }

    /**
     * check if at least one reviewer has started his report
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function isBeingReviewed(): bool
    {
        foreach ($this->getReports() as $report) {
            if ($report->isCompleted() || $report->isInProgress()) {
                return true;
            }
        }
        return false;
    }

    /**
     * fetch reviewers
     * @param null $status
     * @param bool $getCASdata
     * @param bool $vid
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getReviewers($status = null, $getCASdata = false, $vid = false): array
    {
        if (!isset($this->_reviewers)) {
            $reviewers = Episciences_PapersManager::getReviewers($this->getDocid(), $status, $getCASdata, $vid);
            $this->_reviewers = $reviewers;
        }

        return $this->_reviewers;
    }

    /**
     * set article XML (record + local data)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function updateXml()
    {
        $xml = $this->getRecord();
        if (!$xml) {
            return false;
        }

        $dom = new Ccsd_DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $header = $dom->getElementsByTagName('header')->item(0);

        // Si le noeud episciences existe déjà, on le supprime pour le recréer
        $episciences = $dom->getElementsByTagName('episciences')->item(0);
        if ($episciences) {
            $dom->documentElement->removeChild($episciences);
        }

        // Création du node episciences
        $node = $dom->createElement('episciences');
        $header->parentNode->insertBefore($node, $header);

        // Récupération des infos de la revue
        $oReview = Episciences_ReviewsManager::find($this->getRvid());
        $oReview->loadSettings();

        // Création des éléments et ajout au node episciences
        $node->appendChild($dom->createElement('id', $this->getDocid())); // Identifiant Episciences
        $node->appendChild($dom->createElement('paperId', $this->getPaperid())); // Identifiant unique perenne
        $node->appendChild($dom->createElement('identifier', Episciences_Repositories::getIdentifier($this->getRepoid(), $this->getIdentifier(), $this->getVersion()))); // Identifiant source
        $node->appendChild($dom->createElement('doi', $this->getDoi())); // DOI
        $node->appendChild($dom->createElement('hasOtherVersions', ($this->getDocid() != $this->getPaperid()) ? 1 : 0));
        $node->appendChild($dom->createElement('tmp', $this->isTmp()));
        $node->appendChild($dom->createElement('review', $oReview->getName()));
        $node->appendChild($dom->createElement('review_code', $oReview->getCode()));
        $node->appendChild($dom->createElement('review_url', HTTP . '://' . RVCODE . '.' . DOMAIN));
        $node->appendChild($dom->createElement('version', $this->getVersion()));
        $node->appendChild($dom->createElement('esURL', HTTP . '://' . RVCODE . '.' . DOMAIN . '/' . $this->getDocid()));
        $node->appendChild($dom->createElement('docURL', $this->getDocUrl()));
        $node->appendChild($dom->createElement('paperURL', $this->getPaperUrl()));
        $node->appendChild($dom->createElement('volume', $this->getVid()));
        $node->appendChild($dom->createElement('section', $this->getSid()));
        $node->appendChild($dom->createElement('status', $this->getStatus()));
        $node->appendChild($dom->createElement('status_date', $this->getWhen()));
        $node->appendChild($dom->createElement('submission_date', $this->getSubmission_date()));
        $node->appendChild($dom->createElement('publication_date', $this->getPublication_date()));
        $submitter = ($this->getSubmitter()) ? $this->getSubmitter()->getFullName() : null;
        $node->appendChild($dom->createElement('submitter', $submitter));
        $node->appendChild($dom->createElement('uid', $this->getUid()));
        $node->appendChild($dom->createElement('notHasHook', !$this->hasHook));
        $node->appendChild($dom->createElement('isImported', $this->isImported()));
        $node->appendChild($dom->createElement('acceptance_date', $this->getAcceptanceDate()));

        // fetch volume data
        if ($this->getVid()) {
            $oVolume = Episciences_VolumesManager::find($this->getVid());
            if ($oVolume instanceof Episciences_Volume) {
                $oVolume->loadSettings();
            }
        }

        /**
         * Condition d'affichage du bouton d'abondon du processus de publication
         */

        $canAbandonPublicationProcess = $this->getDocid() &&
            !$this->isDeleted() &&
            !$this->isObsolete() &&
            !$this->isRefused() &&
            !$this->isRemoved() &&
            !$this->isPublished() &&
            (
                Episciences_Auth::isSecretary() ||

                (// activation de l'option + auteur de l'artcile
                    $oReview->getSetting(Episciences_Review::SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS) &&
                    Episciences_Auth::getUid() == $this->getUid()
                ) ||

                (// activation de l'option + rédacteur de l'article ou rédacteur en chef
                    $oReview->getSetting(Episciences_Review::SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS) &&
                    array_key_exists(Episciences_Auth::getUid(), $this->getEditors())
                )
            );

        if ($canAbandonPublicationProcess) {
            $node->appendChild($dom->createElement('canAbandonContinuePublicationProcess', true));
        }

        if ($this->isAbandoned() && Episciences_Auth::isSecretary()) {
            $node->appendChild($dom->createElement('isAllowedToContinuePublicationProcess', true));
        }

        // conditions d'affichage du bouton de réattribution de l'article
        // si l'option est activée
        // et qu'il s'agit d'un volume spécial
        // et qu'on est rédacteur de l'article
        if ($this->getDocid() &&
            $oReview->getSetting(Episciences_Review::SETTING_EDITORS_CAN_REASSIGN_ARTICLES) &&
            isset($oVolume) && $oVolume instanceof Episciences_Volume && $oVolume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE) &&
            array_key_exists(Episciences_Auth::getUid(), $this->getEditors(true, true))
        ) {

            // nombre de rédacteurs du volume spécial qui ne sont pas déjà assignés à l'article
            $volume_editors = $oVolume->getEditors();
            $paper_editors = $this->getEditors();
            $available_editors = 0;
            /** @var Episciences_Editor $editor */
            foreach ($volume_editors as $editor) {
                if (!array_key_exists($editor->getUid(), $paper_editors)) {
                    $available_editors++;
                }
            }

            // si il y en a au moins un, on crée le bouton
            if ($available_editors > 0) {
                $node->appendChild($dom->createElement('reassign_button', true));
            }
        }

        // Conserve l'indentation
        $dom->formatOutput = true;
        $dom->normalizeDocument();

        // Récupère la chaîne XML
        $xml = $dom->saveXML();

        $this->_xml = $xml;

        $this->setXslt($this->getXml());
        $this->setMetadata($this->getXml());

        return true;
    }

    /**
     * fetch article OAI header
     * @return string xml
     */
    public function getOaiHeader(): string
    {
        $cache = new FilesystemAdapter(self::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA);
        $cacheName = $this->getPaperid() . '-' . __FUNCTION__;
        $oaiHeaderItem = $cache->getItem($cacheName);
        $oaiHeaderItem->expiresAfter(self::CACHE_EXPIRE_OAI_HEADER);

        if (!$oaiHeaderItem->isHit()) {

            $xml = new Ccsd_DOMDocument('1.0', 'utf-8');
            $root = $xml->createElement('header');
            $root->appendChild($xml->createElement('identifier', $this->getOaiIdentifier()));
            $root->appendChild($xml->createElement('datestamp', substr($this->getPublication_date(), 0, 10)));
            // set : journal ET journal:revue
            $root->appendChild($xml->createElement('setSpec', 'journal'));
            $root->appendChild($xml->createElement('setSpec', 'journal:' . Episciences_Review::getData($this->getRvid())['CODE']));
            $xml->appendChild($root);
            $xml->formatOutput = true;
            $xml->substituteEntities = true;
            $xml->preserveWhiteSpace = false;
            $oaiHeaderXml = $xml->saveXML($xml->documentElement);

            $oaiHeaderItem->set($oaiHeaderXml);
            $cache->save($oaiHeaderItem);
        } else {
            $oaiHeaderXml = $oaiHeaderItem->get();
        }

        return $oaiHeaderXml;

    }

    /**
     * fetch article OAI identifier on Episciences
     * @return string
     */
    public function getOaiIdentifier(): string
    {
        return 'oai:' . DOMAIN . ':' . Episciences_Review::getData($this->getRvid())['CODE'] . ':' . $this->getPaperid();
    }

    /**
     * fetch article in given format
     * @param string $format
     * @return string|false
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function get(string $format = 'tei')
    {
        $format = strtolower(trim($format));
        $method = 'get' . ucfirst($format);

        if ((!self::isValidMetadataFormat($format)) || (!method_exists($this, $method))) {
            return false;
        }

        $cache = new FilesystemAdapter(self::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA);
        $cacheName = $this->getPaperid() . '-' . $method;
        $metadataCache = $cache->getItem($cacheName);

        if ($this->isPublished()) {
            $expireAfterSec = self::CACHE_EXPIRE_METADATA_PUBLISHED;
        } else {
            $expireAfterSec = self::CACHE_EXPIRE_METADATA_UNPUBLISHED;
        }

        $metadataCache->expiresAfter($expireAfterSec);

        if (!$metadataCache->isHit()) {
            $getOutput = $this->$method();
            $metadataCache->set($getOutput);
            $cache->save($metadataCache);
        } else {
            $getOutput = $metadataCache->get();
        }

        return $getOutput;


    }

    /**
     * @return mixed
     */
    public function getDocUrl()
    {
        if (!$this->_docUrl) {
            $this->setDocUrl(Episciences_Repositories::getDocUrl($this->getRepoid(), $this->getIdentifier(), $this->getVersion()));
        }

        return $this->_docUrl;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setDocUrl($url): self
    {
        $this->_docUrl = $url;
        return $this;
    }

    /**
     * Return Repository URL of a paper
     * @return mixed
     */
    public function getPaperUrl()
    {
        if (!$this->_paperUrl) {
            $this->setPaperUrl(Episciences_Repositories::getPaperUrl($this->getRepoid(), $this->getIdentifier(), $this->getVersion()));
        }

        return $this->_paperUrl;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setPaperUrl($url): self
    {
        $this->_paperUrl = $url;
        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function loadSolrData(): bool
    {
        if (!$this->getDocid()) {
            return false;
        }

        $query = 'q=*%3A*&wt=phps&omitHeader=true&fq=docid:' . $this->getDocid();
        $res = Episciences_Tools::solrCurl($query, 'episciences', 'select', true);
        if ($res) {
            $solrData = unserialize($res, ['allowed_classes' => false]);
            $solrData = array_shift($solrData['response']['docs']);
            $this->setSolrData($solrData);
            return true;
        }

        return false;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function setSetting($name, $value): self
    {
        $settings = $this->getSettings();
        $settings[$name] = $value;
        $this->setSettings($settings);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSettings()
    {
        if (!$this->_settings) {
            $this->loadSettings();
        }
        return $this->_settings;
    }

    /**
     * @param $settings
     * @return $this
     */
    public function setSettings($settings): self
    {
        $this->_settings = $settings;
        return $this;
    }

    /**
     * @return bool
     */
    public function loadSettings(): bool
    {
        if (!$this->getDocid() || !is_numeric($this->getDocid())) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_PAPER_SETTINGS, ['SETTING', 'VALUE'])->where('DOCID = ?', $this->getDocid());
        $this->setSettings($db->fetchPairs($sql));
        return true;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getSetting($key)
    {
        $settings = $this->getSettings();
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return null;
    }

    /**
     * @param null $lang
     * @param bool $forceResult
     * @return mixed|string|null
     * @throws Zend_Exception
     */
    public function getTitle($lang = null, $forceResult = false)
    {
        $result = null;
        if ($lang) {
            $result = $this->getTitleByLanguage($lang);
            if (!$forceResult) {
                return $result;
            }
        }

        if (!$result) {

            $result = $this->getTitleByLanguage(Episciences_Tools::getLocale());

            if (!$result && $this->getMetadata('language')) {
                $language = $this->getMetadata('language');
                if (is_array($language)) {
                    foreach ($language as $locale) {
                        $result = $this->getTitleByLanguage($locale);
                        if ($result) {
                            break;
                        }
                    }
                } else {
                    $result = $this->getTitleByLanguage($language);
                }
            }
            if (!$result) {
                $result = $this->getMetadata('title');
            }
            if (!$result) {
                $result = 'Document sans titre';
            }
        }

        $result = (is_array($result)) ? array_shift($result) : $result;


        return Episciences_Tools::decodeLatex($result);
    }

    /**
     * @param string $language
     * @return string
     */
    private function getTitleByLanguage(string $language)
    {
        $title = $this->getMetadata('title');
        if ((is_array($title)) && (Episciences_Tools::epi_array_key_first($title) == 0)) {
            $title = array_shift($title);

            if (!is_array($title)) {
                return $title;
            }

            if (!array_key_exists($language, $title)) {
                $title = '';
            } else {
                $title = $title[$language];
            }
        }


        return $title;
    }

    /**
     * fetch a metadata
     * @param $name string metadata name
     * @param null $key array index (if metadata is an array)
     * @return mixed|null
     */
    public function getMetadata($name, $key = null)
    {
        if (!$this->_metadata && $this->getRecord()) {
            $this->setMetadata($this->getRecord());
        }

        $result = null;
        $metadata = $this->_metadata;

        // if metadata exists
        if (is_array($metadata) && array_key_exists($name, $metadata)) {

            if ($key) {
                if (array_key_exists($key, $metadata[$name])) {
                    $result = $metadata[$name][$key];
                }
            } else {
                $result = $metadata[$name];
            }
        }

        return $result;
    }

    /**
     * @param $xml
     * @return $this
     */
    public function setMetadata($xml)
    {
        $metadata = [];

        $metadata['id'] = $this->getDocid();

        try {
            $metadata['submitter'] = Episciences_Tools::xpath($xml, '/episciences/submitter');
            $metadata['submission_date'] = Episciences_Tools::xpath($xml, '/episciences/submission_date');
            $metadata['publication_date'] = Episciences_Tools::xpath($xml, '/episciences/publication_date');
            $metadata['version'] = Episciences_Tools::xpath($xml, '/episciences/version');
            $metadata['title'] = Episciences_Tools::xpath($xml, '//dc:title', true);
            $metadata['description'] = Episciences_Tools::xpath($xml, '//dc:description', true);
            $metadata['authors'] = Episciences_Tools::xpath($xml, '//dc:creator', true);
            $metadata['subjects'] = Episciences_Tools::xpath($xml, '//dc:subject', true, false);
            $metadata['language'] = Episciences_Tools::xpath($xml, '//dc:language');
        } catch (Exception $e) {
            $metadata['title'] = 'Erreur : la source XML de ce document semble corrompue. Les métadonnées ne sont pas utilisables.';
            $metadata['description'] = 'Merci de contacter le support pour vérifier le document et ses métadonnées';
        }


        $this->_metadata = $metadata;
        return $this;
    }

    /**
     * @param null $lang
     * @param bool $forceResult
     * @return mixed|string|null
     * @throws Zend_Exception
     */
    public function getAbstract($lang = null, $forceResult = false)
    {
        $result = null;
        if ($lang) {
            $result = $this->getMetadata('description', $lang);
            if (!$forceResult) {
                return $result;
            }
        }

        if (!$result) {
            $result = $this->getMetadata('description', Episciences_Tools::getLocale());
            if (!$result && $this->getMetadata('language')) {
                $result = $this->getMetadata('description', $this->getMetadata('language'));
            }
            if (!$result) {
                $description = $this->getMetadata('description');
                $result = (is_array($description)) ? array_shift($description) : $description;
            }
            if (!$result) {
                $result = '';
            }
        }

        return $result;
    }

    /**
     * @param null $publication_date
     * @param string $doc_type
     * @param array|false|string $rvcode
     * @throws Zend_Exception
     */
    public function updateHALMetadata($publication_date = null, $doc_type = 'ART', $rvcode = RVCODE)
    {
        $identifier = $this->getIdentifier();
        $version = $this->getVersion();
        $volume = null;
        if ($this->getVid()) {
            $oVolume = Episciences_VolumesManager::find($this->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
            }
        }
        $token = hash('sha256', EPISCIENCES_SECRET_KEY . $rvcode . $volume . $identifier . $version);

        $curl = curl_init(EPISCIENCES_HAL_API . "/episciences/publication");
        // result is returned instead of being displayed
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $params = [
            'identifier' => $identifier,
            'version' => $version,
            'rvcode' => $rvcode,
            'volume' => $volume,
            'date' => ($publication_date) ?: $this->getPublication_date(),
            'token' => $token
        ];
        if ($doc_type) {
            $params['typdoc'] = $doc_type;
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * @return string | null
     */
    public function getConcept_identifier(): ?string
    {
        return $this->_concept_identifier;
    }

    /**
     * @param string|null $conceptIdentifier
     * @return $this
     */
    public function setConcept_identifier(string $conceptIdentifier = null): self
    {
        $this->_concept_identifier = $conceptIdentifier;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModification_date()
    {
        return $this->_modification_date;
    }

    /**
     * @param $modification_date
     * @return $this
     */
    public function setModification_date($modification_date): self
    {
        $this->_modification_date = $modification_date;
        return $this;
    }

    /**
     * return true if xsl is needed, false otherwise
     * @return bool
     */
    public function getWithxsl(): bool
    {
        return $this->_withxsl;
    }

    public function setWithxsl($withxsl): self
    {
        $this->_withxsl = $withxsl;
        return $this;
    }

    /**
     * return rating grid (can be filtered according to visibility level: public, contributor, editor)
     * @param null $visibility_filter
     * @return bool|Episciences_Rating_Grid
     */
    public function getGrid($visibility_filter = null)
    {
        $path = $this->getGridPath();
        if (!$path) {
            return false;
        }

        $grid = new Episciences_Rating_Grid;
        $grid->loadXML($path);

        if ($visibility_filter) {
            $grid = clone $grid;
            if ($grid->getCriteria()) {
                /** @var Episciences_Rating_Criterion $criterion */
                foreach ($grid->getCriteria() as $id => $criterion) {
                    $cVisibility = $criterion->getVisibility();
                    $criterion_visibility = Ccsd_Tools::ifsetor($cVisibility, 'editors');

                    // if criterion_visibility does not match visibility filter, we remove it from the grid
                    if ((is_array($visibility_filter) && !in_array($criterion_visibility, $visibility_filter)) ||
                        (!is_array($visibility_filter) && $visibility_filter != $criterion_visibility)
                    ) {
                        $grid->removeCriterion($id);
                    }
                }
            }
        }

        return $grid;
    }

    /**
     * return rating grid path
     * @return string|bool
     */
    public function getGridPath()
    {
        if (Episciences_GridsManager::gridExists('grid_' . $this->getVid() . '.xml')) {
            return REVIEW_GRIDS_PATH . 'grid_' . $this->getVid() . '.xml';
        }

        if (Episciences_GridsManager::gridExists(REVIEW_GRID_NAME_DEFAULT)) {
            return REVIEW_GRIDS_PATH . REVIEW_GRID_NAME_DEFAULT;
        }

        return false;
    }

    /**
     * Save other volumes
     */
    public function saveOtherVolumes(): void
    {
        Episciences_Volume_PapersManager::updatePaperVolumes($this->getDocid(), $this->getOtherVolumes());
    }

    /**
     * @param bool $force
     * @return array
     */
    public function getOtherVolumes(bool $force = false): array
    {
        if ($force || !is_array($this->_otherVolumes)) {
            $this->loadOtherVolumes();
        }
        return $this->_otherVolumes;
    }

    /**
     * @param array $paper_volumes
     */
    public function setOtherVolumes(array $paper_volumes)
    {
        $this->_otherVolumes = $paper_volumes;
    }

    /**
     * load secondary volumes
     */
    public function loadOtherVolumes()
    {
        $paper_volumes = Episciences_Volume_PapersManager::findPaperVolumes($this->getDocid());
        $this->setOtherVolumes($paper_volumes);
    }

    /**
     * Gère les erreurs de soumission d'une nouvelle version
     * @param array $options
     * @return string
     * @throws Zend_Exception
     */
    public function manageNewVersionErrors(array $options = []): string
    {
        $id = $this->getDocid();

        if ($this->isObsolete()) {
            $this->loadVersionsIds();
            $versionIds = $this->getVersionsIds();
            $id = $versionIds[array_key_last($versionIds)];
        }


        $canReplace = false;
        $docId = $this->getDocid();
        $translator = Zend_Registry::get('Zend_Translate');
        $span = '<span class="fas fa-exclamation-circle">'; // A personaliser
        $warning = $span;
        $warning .= ' ';
        $submitted = $translator->translate("Vous n'êtes pas l'auteur de cet article.");
        $canNotChangeIt = $translator->translate('Vous ne pouvez pas le modifier.');
        $status = $this->getStatus();
        $identifier = $this->getIdentifier();
        $version = $this->getVersion();
        $repoId = $this->getRepoid();
        $isNewSubmission = array_key_exists('isNewVersionOf', $options) && !$options['isNewVersionOf'];
        $link = $isNewSubmission ? '/submit/index' : '/paper/view?id=' . $id;
        $style = 'btn btn-default btn-xs';

        $exitLink = '&nbsp;&nbsp;&nbsp;';
        $exitLink .= '<a class="' . $style . '" href="' . $link . '">';
        $exitLink .= '<span class="glyphicon glyphicon-remove-circle" ></span>&nbsp;';
        $exitLink .= $translator->translate('Annuler');
        $exitLink .= '</a>';

        $confirm = '<p style="margin:1em;">';
        $confirm .= '<button class="' . $style . '" onclick="hideResultMessage();">';
        $confirm .= '<span class="glyphicon glyphicon-ok-circle"></span>&nbsp;';
        $confirm .= $translator->translate('Remplacer');
        $confirm .= '</button>';
        $confirm .= $exitLink;
        $confirm .= '</p>';

        if (
            Episciences_Auth::isLogged() &&
            (
                $this->getUid() === Episciences_Auth::getUid() ||
                (
                    (
                        Episciences_Auth::isSecretary() ||
                        $this->getEditor(Episciences_Auth::getUid()) ||
                        $this->getCopyEditor(Episciences_Auth::getUid())
                    ) &&
                    !$isNewSubmission
                )
            )
        ) {

            $review = Episciences_ReviewsManager::find(RVID);
            $question = $translator->translate('Souhaitez-vous remplacer la version précédente ?');
            $result['message'] = $warning;
            // Arrêt du processus de publication
            if ($status === self::STATUS_ABANDONED) {

                $selfMsg = $translator->translate("On ne peut pas re-proposer un article <strong>abandonné</strong>, Pour de plus amples renseignements, veuillez contacter le comité éditorial.");

                $result['message'] = $selfMsg;

            } elseif ($status === self::STATUS_SUBMITTED || $status === self::STATUS_OK_FOR_REVIEWING) {  /* Soumis ou En attente de relecture */
                $selfMsg = $result['message'];
                $selfMsg .= $question;
                $selfMsg .= $confirm;
                $result['message'] = $selfMsg;
                $result['oldPaperId'] = (int)$this->getPaperid();
                $result['submissionDate'] = $this->getSubmission_date();
                $result['oldVid'] = $this->getVid();
                $result['oldSid'] = $this->getSid();
                $canReplace = true;

            } elseif (
                ($status === self::STATUS_WAITING_FOR_MINOR_REVISION || $status === self::STATUS_WAITING_FOR_MAJOR_REVISION) &&
                (!empty($options) && $isNewSubmission)
            ) {
                $url = '/paper/view/id/' . $this->getDocid();
                $selfMsg = $result['message'];
                $selfMsg .= $translator->translate('Pour déposer votre nouvelle version, veuillez utiliser le lien figurant dans le courriel qui vous a été envoyé par la revue, ');
                $selfMsg .= '<br>';

                $selfMsg .= $translator->translate('ou');
                $selfMsg .= '<span style="margin-right: 3px;"></span>';
                $selfMsg .= '<a class="' . $style . '" href="' . $url . '">';
                $selfMsg .= '<span class="glyphicon glyphicon-chevron-right"></span>';
                $selfMsg .= $translator->translate("Accédez directement à l'article");
                $selfMsg .= '</a>';
                $selfMsg .= '<span style="margin-left: 3px;"></span>';

                $selfMsg .= $translator->translate('pour répondre à la demande de modification.');
                $result['message'] = $selfMsg;

            } elseif ($status === self::STATUS_BEING_REVIEWED) { // En cours de relecture
                $selfMsg = $result['message'];
                $selfMsg .= $translator->translate('Cet article a déjà été soumis et il est en cours de relecture.');
                $selfMsg .= $canNotChangeIt;
                $result['message'] = $selfMsg;
            } elseif ($status === self::STATUS_REVIEWED) { /* relu */
                $selfMsg = $result['message'];
                $selfMsg .= $translator->translate("Cet article est en cours d'évaluation.");
                $selfMsg .= $canNotChangeIt;
                $result['message'] = $selfMsg;
            } elseif ($status === self::STATUS_ACCEPTED) { /*Accepté*/
                $selfMsg = $result['message'];
                $selfMsg .= $translator->translate('Cet article a été accepté.');
                $selfMsg .= $canNotChangeIt;
                $result['message'] = $selfMsg;
            } elseif ($status === self::STATUS_REFUSED) { /*Refusé*/

                if ($review->getSetting(Episciences_Review::SETTING_CAN_RESUBMIT_REFUSED_PAPER)) {
                    $selfMsg = $result['message'];
                    $selfMsg .= $translator->translate('Cet article a déjà été soumis et refusé. Avez-vous apporté des modifications majeures au document ?');
                    $result['message'] = $selfMsg;
                    $selfMsg .= $confirm;
                    $result['message'] = $selfMsg;
                    $result['oldPaperId'] = (int)$this->getPaperid();
                    $result['oldVid'] = $this->getVid();
                    $result['oldSid'] = $this->getSid();
                    $canReplace = true;
                } else {
                    $result['message'] = $warning . $canNotChangeIt . ' ' . $translator->translate('Cet article a déjà été soumis et refusé, merci de contacter le comité editorial.');
                }

            } elseif (
                !empty($options) &&
                array_key_exists('version', $options) &&
                $options['version'] <= $this->getVersion()
            ) {


                $selfMsg = $translator->translate('Cette version');
                $selfMsg .= ' [<strong>v' . $this->getVersion() . '</strong>] ';
                $selfMsg .= $translator->translate('du document existe déjà dans la revue.');
                $selfMsg .= '&nbsp;';
                $selfMsg .= '<a class="btn btn-default btn-sm" href="' . $link . '">';
                $selfMsg .= '<span class="fas fa-redo" style="margin-right: 5px;"></span>';
                $selfMsg .= $translator->translate('Retour');
                $selfMsg .= '</a>';
                $result['message'] = $warning . ' ' . $translator->translate($selfMsg);

            } else { // others status
                $result['message'] = $translator->translate("Le processus de publication de cet article est en cours, vous ne pourrez donc pas le remplacer.");
            }

            $result['oldDocId'] = (int)$docId;
            $result['oldPaperStatus'] = (int)$status;

        } else { // Pas de détails sur le statut de l'article, si on est pas l'auteur de ce dernier

            $result['message'] = $span . $translator->translate('Erreur') . $translator->translate(': ') . $submitted;
        }

        $result['message'] .= '</span>';

        $result['canReplace'] = $canReplace; // Peut-on remplacer l'ancienne version
        $result['oldIdentifier'] = $identifier;
        $result['oldVersion'] = (int)$version;
        $result['oldRepoId'] = (int)$repoId;

        return json_encode($result);
    }

    /**
     * @return bool
     */
    public function isObsolete(): bool
    {
        return ($this->getStatus() === self::STATUS_OBSOLETE);
    }

    /**
     * fetch an copy editor
     * @param $uid
     * @return Episciences_CopyEditor|bool
     * @throws Zend_Db_Statement_Exception
     */
    public function getCopyEditor($uid)
    {
        if (empty($this->_copyEditors)) {
            $this->_copyEditors = Episciences_PapersManager::getCopyEditors($this->getDocid(), true, true);
        }

        return (array_key_exists($uid, $this->_copyEditors));
    }

    /**
     * @return mixed
     */
    public function getSubmission_date()
    {
        return $this->_submission_date;
    }

    /**
     * @param $submission_date
     * @return $this
     */
    public function setSubmission_date($submission_date): self
    {
        $this->_submission_date = $submission_date;
        return $this;
    }

    /**
     * @param array $values
     * @return array
     * @throws Zend_Exception
     */
    public function updatePaper(array $values)
    {
        $status = $this->getStatus();
        try {
            $update[] = null;
            $update['code'] = 0;
            $translator = Zend_Registry::get('Zend_Translate');
            $message = $translator->translate("Aucune modification n'a été enregistrée");

            $paper = new Episciences_Paper([
                'identifier' => $values['search_doc']['docId'],
                'version' => (int)$values['search_doc']['version'],
                'repoId' => (int)$values['search_doc']['repoId']
            ]);

            if ($this->getIdentifier() === $paper->getIdentifier() &&
                $this->getVersion() === $paper->getVersion() &&
                $this->getRepoid() === $paper->getRepoid()) {
                $message .= ' : ';
                $message .= $translator->translate("la version précédente est identique.");
                $update['message'] = $message;
                return $update;
            }

            if (!$this->hasHook && $this->getIdentifier() !== $paper->getIdentifier()) {
                $message .= ' : ';
                $message .= $translator->translate("l'identifiant de l'article a changé.");

            } elseif ($this->getRepoid() !== $paper->getRepoid()) {
                $message .= " : ";
                $message .= $translator->translate("l'entrepôt de cet article a changé.");

            } elseif ($paper->getVersion() > $this->getVersion()) {
                if (
                    ($status === self::STATUS_SUBMITTED || $status === self::STATUS_OK_FOR_REVIEWING) ||
                    ($status === self::STATUS_REFUSED && Episciences_PapersManager::renameIdentifier($this->getIdentifier(), $this->getIdentifier() . '-REFUSED'))
                ) {
                    $submit = new Episciences_Submit();
                    $result = $submit->saveDoc($values);
                    if ($result['code'] == 0) {
                        $message = $result['message'];
                    } else {
                        $message = $translator->translate("La nouvelle version de votre article a bien été enregistrée.");
                    }

                    $update['code'] = 1;
                }
            } else {
                $message .= ' : ';
                $message .= $translator->translate("la version de l'article à mettre à jour doit être supérieure à la version précédente.");
            }

            $update['message'] = $message;
            return $update;

        } catch (Exception $e) {
            $translator = Zend_Registry::get('Zend_Translate');
            $message = $translator->translate("Une erreur interne s'est produite, veuillez recommencer.");
            $update['message'] = $message;
            return $update;
        }
    }

    /**
     * Return an array of title from metadata
     * @return array
     */
    public function getAllTitles()
    {
        $titles = $this->getMetadata('title');
        if (!is_array($titles)) {
            $titles = [];
        }
        return $titles;
    }

    /**
     * @param $name
     * @param null $key
     * @return mixed|null
     */
    public function getSolrData($name, $key = null)
    {
        $result = null;
        $data = $this->getAllSolrData();

        if (is_array($data) && array_key_exists($name, $data)) {

            if ($key) {
                if (array_key_exists($key, $data[$name])) {
                    $result = $data[$name][$key];
                }
            } else {
                $result = $data[$name];
            }
        }

        return $result;
    }

    /**
     * @param $solrData
     * @return $this
     */
    public function setSolrData($solrData): self
    {
        $this->_solrData = $solrData;
        return $this;
    }

    /**
     * @return array
     */
    public function getAllSolrData()
    {
        return $this->_solrData;
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function refreshStatus()
    {
        // update paper status
        $status = ($this->isReviewed()) ? self::STATUS_REVIEWED : self::STATUS_BEING_REVIEWED;
        $oldStatus = $this->getStatus();
        if ($oldStatus !== self::STATUS_OBSOLETE &&
            $oldStatus !== self::STATUS_PUBLISHED &&
            $oldStatus !== self::STATUS_ACCEPTED &&
            $oldStatus !== self::STATUS_REFUSED &&
            $oldStatus !== self::STATUS_WAITING_FOR_MINOR_REVISION &&
            $oldStatus !== self::STATUS_WAITING_FOR_MAJOR_REVISION &&
            $oldStatus !== $status) {
            $this->setStatus($status);
            $this->save();
            // log new paper status
            $this->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => $status]);
        }
    }

    /**
     * save paper to database
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $docId = $this->getDocid();

        if (!$docId) {
            // INSERT
            $data = [
                'PAPERID' => $this->getPaperid(),
                'DOI' => $this->getDoi(),
                'VERSION' => $this->getVersion(),
                'RVID' => $this->getRvid(),
                'VID' => $this->getVid(),
                'SID' => $this->getSid(),
                'UID' => $this->getUid(),
                'STATUS' => $this->getStatus(),
                'IDENTIFIER' => $this->getIdentifier(),
                'REPOID' => $this->getRepoid(),
                'RECORD' => $this->getRecord(),
                'WHEN' => new Zend_Db_Expr('NOW()'),
                'SUBMISSION_DATE' => ($this->getSubmission_date()) ?: new Zend_Db_Expr('NOW()'),
                'MODIFICATION_DATE' => new Zend_Db_Expr('NOW()'),
                'FLAG' => $this->getFlag()
            ];

            if ($this->getPublication_date()) {
                $data['PUBLICATION_DATE'] = $this->getPublication_date();
            } elseif ($this->getStatus() === self::STATUS_PUBLISHED) {
                $data['PUBLICATION_DATE'] = new Zend_Db_Expr('NOW()');
            }

            if ($this->getConcept_identifier()) {
                $data['CONCEPT_IDENTIFIER'] = $this->getConcept_identifier();
            }

            if ($db->insert(T_PAPERS, $data)) {
                $this->setDocid($db->lastInsertId());
                if (!$this->getPaperid()) {
                    $this->setPaperid($this->getDocid());
                    $this->save();
                } else {
                    $this->setPosition($this->applyPositioningStrategy());
                }
                return true;
            }

            return false;
        }

// UPDATE
        $data = [
            'PAPERID' => $this->getPaperid(),
            'DOI' => $this->getDoi(),
            'VERSION' => $this->getVersion(),
            'VID' => $this->getVid(),
            'SID' => $this->getSid(),
            'STATUS' => $this->getStatus(),
            'REPOID' => $this->getRepoid(),
            'RECORD' => $this->getRecord(),
            'SUBMISSION_DATE' => $this->getSubmission_date(),
            'MODIFICATION_DATE' => new Zend_Db_Expr('NOW()'),
            'FLAG' => $this->getFlag()
        ];
        if ($this->getIdentifier()) {
            $data['IDENTIFIER'] = $this->getIdentifier();
        }
        if ($this->getPublication_date()) {
            $data['PUBLICATION_DATE'] = $this->getPublication_date();
        }

        if ($this->getConcept_identifier()) {
            $data['CONCEPT_IDENTIFIER'] = $this->getConcept_identifier();
        }

        if (!$db->update(T_PAPERS, $data, ['DOCID = ?' => $docId])) {
            return false;
        }

        $this->setPosition($this->applyPositioningStrategy());
        return true;
    }

    /**
     * Create or delete an position in volume
     * @return int|null
     */
    public function applyPositioningStrategy()
    {

        if (empty($this->getVid())) {
            return null;
        }

        if (in_array($this->getStatus(), self::DO_NOT_SORT_THIS_KIND_OF_PAPERS, true)) {
            $this->deletePosition();
            $this->setPosition(null);
            return null;
        }

        if ($this->isAccepted() || $this->copyEditingProcessStarted() || $this->isReadyToPublish()) {
            return $this->createPositionProcessing();
        }

        return $this->getPosition();
    }

    /**
     *delete paper volume position
     * @return int
     */
    public function deletePosition()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $where['VID = ?'] = $this->getVid();
        $where['PAPERID = ?'] = $this->getPaperid();

        return ($db->delete(T_VOLUME_PAPER_POSITION, $where) > 0);
    }

    /**
     * @return bool
     */

    public function copyEditingProcessStarted(): bool
    {
        return in_array($this->getStatus(), [
            self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
            self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
            self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
            self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
            self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
            self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED
        ], true);
    }

    public function isReadyToPublish(): bool
    {
        return $this->getStatus() === self::STATUS_CE_READY_TO_PUBLISH;
    }

    /**
     * @return int|null
     */
    private function createPositionProcessing()
    {
        return $this->getPosition() ?? $this->insertPosition();
    }

    /**
     * get paper position in volume
     * @return int | null
     */
    public function getPosition()
    {
        /** @var $volumePaperPosition [PAPERID => POSITION] */

        if (null === $this->_position && !empty($this->_vId)) {
            $volumePaperPosition = Episciences_VolumesManager::loadPositionsInVolume($this->_vId);
            return array_key_exists($this->_paperId, $volumePaperPosition) ? $volumePaperPosition[$this->getPaperid()] : null;
        }

        return $this->_position;
    }

    /**
     * set paper position in volume
     * @param int $position
     * @return $this
     */
    public function setPosition(int $position = null): self
    {

        $this->_position = $position;

        return $this;

    }

    /**
     * assign position
     * @return int|null
     */
    private function insertPosition()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $this->nextPositionQuery();
        $position = $db->fetchOne($select);

        if (!is_numeric($position)) {
            $position = 0;
        }

        $paperPosition[$position] = $this->getPaperid();

        Episciences_VolumesManager::savePaperPositionsInVolume($this->getVid(), $paperPosition);

        return $this->setPosition((int)$position)->getPosition();
    }

    /**
     * retourne la prochaine position
     * @return Zend_Db_Select
     */
    private function nextPositionQuery(): \Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->select()
            ->from(T_VOLUME_PAPER_POSITION, new Zend_Db_Expr('MAX(POSITION) + 1 AS POSITION'))
            ->where('VID = ?', $this->getVid());
    }

    /**
     * Add or update a paper in Solr
     * @return bool
     */
    public function indexUpdatePaper(): bool
    {
        return self::indexPaper($this->getDocid(), Ccsd_Search_Solr_Indexer::O_UPDATE);
    }

    /**
     * Add or update or delete a papaer ins Solr
     * @param int $docid
     * @param string $typeOfIndex
     * @return bool
     */
    public static function indexPaper(int $docid, string $typeOfIndex): bool
    {

        if (($typeOfIndex !== Ccsd_Search_Solr_Indexer::O_UPDATE) && ($typeOfIndex !== Ccsd_Search_Solr_Indexer::O_DELETE)) {
            return false;
        }

        $options['env'] = APPLICATION_ENV;
        $indexer = new Ccsd_Search_Solr_Indexer_Episciences($options);
        $indexer->setOrigin($typeOfIndex);
        $indexer->processDocid($docid);
        return true;
    }

    /**
     * delete a paper in Solr
     * @return bool
     */
    public function indexRemovePaper(): bool
    {
        return self::indexPaper($this->getDocid(), Ccsd_Search_Solr_Indexer::O_DELETE);
    }

    /**
     * Retourne le détail de la dernière action de l'arrêt du processus de publication
     * @return stdClass|null
     * @throws Zend_Exception
     */
    public function loadLastAbandonActionDetail()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var  Zend_Db_Select $sql */
        $sql = $this->loadHistoryQuery($db);
        $detail = null;

        $logs = $db->fetchAll($sql);
        foreach ($logs as $value) {
            if ($value['ACTION'] == Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS) {
                /** @var stdClass $detail */
                $detail = json_decode($value['DETAIL']);
                break;
            }
        }

        //A la reprise de la publication d’un article une exception est lancée si on trouve pas de traces de l’abandon du processus de publication
        // précédemment effectué .

        if (null == $detail) {
            throw new Zend_Exception('Pas de trace de l\'action : ' . Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS);
        }

        return $detail;
    }

    /**
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getRatingInvitations(): array
    {
        if ($this->isObsolete()) {
            // fetch all reviewers (when a paper becomes obsolete, reviewers are disabled)
            $this->getReviewers([
                Episciences_User_Assignment::STATUS_ACTIVE,
                Episciences_User_Assignment::STATUS_INACTIVE,
                Episciences_User_Assignment::STATUS_PENDING],
                true);
        } else {
            // fetch active reviewers only, or those with pending invitation
            $this->getReviewers(
                [
                    Episciences_User_Assignment::STATUS_ACTIVE,
                    Episciences_User_Assignment::STATUS_PENDING
                ],
                true);
        }

        // fetch all rating invitations
        $invitations = $this->getInvitations(
            [
                Episciences_User_Assignment::STATUS_ACTIVE,
                Episciences_User_Assignment::STATUS_EXPIRED,
                Episciences_User_Assignment::STATUS_INACTIVE,
                Episciences_User_Assignment::STATUS_PENDING,
                Episciences_User_Assignment::STATUS_CANCELLED,
                Episciences_User_Assignment::STATUS_DECLINED
            ],
            true
        );

        if (array_key_exists(Episciences_User_Assignment::STATUS_ACTIVE, $invitations)) {
            foreach ($invitations[Episciences_User_Assignment::STATUS_ACTIVE] as &$invitation) {
                // try to fetch matching reviewer and reviewing.
                // if not found, skip this invitation
                $reviewer = $this->getReviewer($invitation['UID']);
                if (!$reviewer) {
                    continue;
                }
                $reviewing = $reviewer->getReviewing($this->getDocid());
                if (!$reviewing) {
                    continue;
                }
                $invitation['reviewer']['rating']['status'] = $reviewing->getStatus();
                $invitation['reviewer']['rating']['last_update'] = $reviewing->getUpdateDate();
            }
            unset($invitation);
        }
        return $invitations;
    }

    /**
     * fetch reviewer invitations
     * @param null $status
     * @param bool $sorted
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getInvitations($status = null, $sorted = false): array
    {
        if (!isset($this->_invitations)) {
            $invitations = Episciences_PapersManager::getInvitations($this->getDocid(), $status, $sorted);
            $this->_invitations = $invitations;
        }

        return $this->_invitations;
    }

    /**
     * fetch a reviewer
     * @param $uid
     * @return bool|Episciences_Reviewer
     */
    public function getReviewer($uid)
    {
        if (isset($this->_reviewers[$uid])) {
            return $this->_reviewers[$uid];
        }

        return false;
    }

    /**
     * @param string $locale
     * @return string
     * @throws Zend_Exception
     */
    public function formatAuthorsMetadata(string $locale = ''): string
    {
        $translator = Zend_Registry::get('Zend_Translate');

        if (empty($locale)) {
            $locale = Zend_Registry::get('lang');
        }

        /** @var string[] $authors */
        $authors = $this->getMetadata('authors');

        if ($authors) {
            $this->trimAuthorsMeta($authors);
        }

        $str = '';
        $length = count($authors);

        switch ($length) {
            case 0:
                return $str;
            case 1:
                return $authors[0];
            default :
                foreach ($authors as $index => $author) {
                    if ($index < $length - 1) {
                        $str .= $author . ' ; ';
                    } else {
                        $str = substr($str, 0, -2) . $translator->translate('et', $locale) . ' ' . $author;
                    }
                }
        }

        return addslashes($str);

    }

    /**
     * Nécessité de nettoyer cette méta pour éviter les erreurs d'encodage JSON par exemple.
     * @param array $meta
     */
    private function trimAuthorsMeta(array &$meta)
    {
        foreach ($meta as $index => $value) {

            $explodedValue = explode(',', $value);
            $out = [];

            foreach ($explodedValue as $expValue) {
                $expValue = trim($expValue);
                if ($expValue !== '') {
                    $out[] = $expValue;
                }
            }

            if (!empty($out)) {
                $meta[$index] = implode(', ', $out);
            } else {
                unset($meta[$index]);
            }
        }
    }

    /**
     * @param string|null $locale
     * @return string
     * @throws Zend_Exception
     */
    public function buildVolumeName(string $locale = null): string
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $locale = !$locale ? $translator->getLocale() : $locale;
        $volume = Episciences_VolumesManager::find($this->getVid());
        return !$volume ? $translator->translate('Hors volume', $locale) : $volume->getName($locale, true);
    }

    /**
     * @param string|null $locale
     * @return string|null
     * @throws Zend_Exception
     */
    public function buildSectionName(string $locale = null)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $locale = !$locale ? $translator->getLocale() : $locale;
        $section = Episciences_VolumesManager::find($this->getSid());
        return !$section ? $translator->translate('Hors rubrique', $locale) : $section->getName($locale);
    }

    /**
     * @param string $locale = null (ISO FORMAT)
     * @return false|string
     * @throws Zend_Date_Exception
     * @throws Zend_Exception
     */
    public function buildRevisionDates(string $locale = null)
    {
        $revisionDates = '';
        $previousVersions = $this->getPreviousVersions(true);
        if (!$previousVersions) {
            return $revisionDates;
        }
        ksort($previousVersions);
        /**  @var  Episciences_Paper $paper */
        foreach ($previousVersions as $paper) {
            $revisionDates .= (!$locale) ? date('Y-m-d', strtotime($paper->getWhen())) . '; ' : Episciences_View_Helper_Date::Date($paper->getWhen(), $locale) . '; ';
        }
        return substr($revisionDates, 0, strlen($revisionDates) - 2);
    }

    /**
     * return an array of papers (previous versions of this paper)
     * @param bool $isCurrentVersionIncluded
     * @return array|null
     */
    public function getPreviousVersions(bool $isCurrentVersionIncluded = false)
    {
        if (($isCurrentVersionIncluded || !isset($this->_previousVersions)) && $this->getPaperid() !== $this->getDocid()) {

            $this->_previousVersions = null;
            $parentId = $this->getPaperid();

            if ($parentId) {

                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $sql = $db->select()
                    ->from(T_PAPERS)
                    ->where('PAPERID = ?', $parentId);

                !$isCurrentVersionIncluded ? $sql->where('`WHEN` < ?', $this->getWhen()) : $sql->where('`WHEN` <= ?', $this->getWhen());

                $sql->order('WHEN DESC');

                $results = $db->fetchAssoc($sql);
                $papers = [];

                if ($results) {
                    foreach ($results as $paperId => $result) {
                        $papers[$paperId] = new Episciences_Paper($result);
                    }
                    $this->_previousVersions = $papers;
                }
            }
        }

        return $this->_previousVersions;
    }

    /**
     * get real paper position in volume. (position in VOLUME_PAPER_POSITION table  + 1)
     * @return int
     */
    public function getPaperPositionInVolume(): int
    {
        $vId = $this->getVid();

        if (!$vId || !$volume = Episciences_VolumesManager::find($vId)) {
            return 0;
        }

        $positions = $volume->getPaperPositions();
        return (array_search($this->getPaperid(), $positions, false) + 1);
    }

    /**
     * get acceptance date form paper log
     * @return string|null
     */
    public function getAcceptanceDate(): ?string
    {
        $date = null;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $this->loadHistoryQuery($db, [Episciences_Paper_Logger::CODE_STATUS]);
        $logs = $db->fetchAll($sql);

        foreach ($logs as $value) {

            $detail = json_decode($value['DETAIL'], true);

            if (isset($detail['status']) && (int)$detail['status'] === self::STATUS_ACCEPTED) {
                $date = $value['DATE'];
                break;
            }
        }

        return $date;
    }

    /**
     * git#295
     * @param array $recipients
     * @param int|null $principalRecipient
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function extractCCRecipients(array &$recipients = [], int $principalRecipient = null): array
    {
        $CC = [];

        if (!$principalRecipient) {
            foreach ($recipients as $uid => $recipient) {
                if (!$this->getEditor($uid)) {
                    $CC[$uid] = $recipient;
                    unset($recipients[$uid]);
                }
            }
        } else {
            $CC = $recipients;
            unset($CC[$principalRecipient]);
        }

        return $CC;

    }

    /**
     * @return string
     */
    public function getPublicationYear(): string
    {
        $year = date('Y');
        if ($this->isPublished()) {
            $date = DateTime::createFromFormat("Y-m-d H:i:s", $this->getPublication_date());
            $year = $date->format('Y');
        }
        return $year;
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return ($this->getStatus() === self::STATUS_PUBLISHED);
    }

    /**
     * @return string
     */
    public function getPublicationMonth(): string
    {
        $month = date('m');
        if ($this->isPublished()) {
            $date = DateTime::createFromFormat("Y-m-d H:i:s", $this->getPublication_date());
            $month = $date->format('m');
        }
        return $month;
    }


    /**
     * return Bibtex formatted paper
     * @return string
     */
    private function getBibtex(): string
    {
        $this->setXslt($this->getXml(), 'bibtex');
        return $this->getXslt();
    }

    /**
     * return dc formatted paper
     * @return string
     * @throws Zend_Exception
     */
    private function getDc(): string
    {
        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');

        $xml->formatOutput = true;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;

        $root = $xml->createElement('oai_dc:dc');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->appendChild($root);

        // titles
        foreach ($this->getMetadata('title') as $lang => $title) {
            $node = $xml->createElement('dc:title', $title);
            if (Zend_Locale::isLocale($lang)) {
                $node->setAttribute('xml:lang', $lang);
            }
            $root->appendChild($node);
        }

        // authors
        $authors = $this->getMetadata('authors');
        if (is_array($authors)) {
            foreach ($authors as $author) {
                $creator = $xml->createElement('dc:creator', $author);
                $root->appendChild($creator);
            }
        }

        // Contributor
        $contributor = $this->getSubmitter();
        if ($contributor instanceof Episciences_User) {
            $contributorFullName = $contributor->getFullName();
            $contributorNode = $xml->createElement('dc:contributor', $contributorFullName);
            $root->appendChild($contributorNode);
        }


        // ISSN (if exists)
        $oReview = Episciences_ReviewsManager::find($this->getRvid());
        $oReview->loadSettings();
        if ($oReview->getSetting('ISSN')) {
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
        $identifier = $xml->createElement('dc:identifier', $oReview->getUrl() . '/' . $this->getDocid());
        $root->appendChild($identifier);

        if (!empty($this->getDoi())) {
            $identifierDoi = $xml->createElement('dc:identifier', 'info:doi:' . $this->getDoi());
            $root->appendChild($identifierDoi);
        }

        // quotation
        //  'Journal of Data Mining and Digital Humanities, Episciences.org, 2015, pp.43'
        $source = $xml->createElement('dc:source', $this->getCitation());
        $root->appendChild($source);

        // paper language
        if ($this->getMetadata('language')) {
            $language = $xml->createElement('dc:language', $this->getMetadata('language'));
            $root->appendChild($language);
        }

        // paper subjects
        $subjects = $this->getMetadata('subjects');
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
        foreach ($this->getAllAbstracts() as $lang => $abstract) {
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
        if ($this->getPublication_date()) {
            $date = new DateTime($this->getPublication_date());
            $publicationDate = $date->format('Y-m-d');
            $date = $xml->createElement('dc:date', $publicationDate);
            $root->appendChild($date);
        }

        return $xml->saveXML($xml->documentElement);
    }

    /**
     * Get a paper citation
     * @return string|null
     * @throws Zend_Exception
     */
    public function getCitation()
    {
        $citation = null;

        // locale selection: english if possible, first available language otherwise
        $languages = Episciences_Tools::getLanguages();
        if (array_key_exists('en', $languages)) {
            $locale = 'en';
        } else {
            reset($languages);
            $locale = key($languages);
        }

        // revue-dev:5 - Epitest, 5 février 2015, Premier Volume

        $review = Episciences_ReviewsManager::find($this->getRvid());

        // load journal translations in context of OAI (eg volumes ; sections)
        if (APPLICATION_MODULE === 'oai') {
            if (is_dir($review->getTranslationsPath()) && count(scandir($review->getTranslationsPath())) > 2) {
                try {
                    Zend_Registry::get('Zend_Translate')->addTranslation($review->getTranslationsPath());
                } catch (Zend_Exception $exception) {
                    error_log($exception->getMessage());
                }
            }
        }

        $citation = $review->getCode() . ':' . $this->getPaperid() . ' - ' . $review->getName() . ', ';
        $citation .= date('Y-m-d', strtotime($this->getPublication_date()));
        if ($this->getVid()) {
            $volume = Episciences_VolumesManager::find($this->getVid());
            if ($volume instanceof Episciences_Volume) {
                $citation .= ', ' . $volume->getName($locale, true);;
            }
        }


        return $citation;
    }

    /**
     * Get array of abstracts
     * @return array
     */
    public function getAllAbstracts(): array
    {
        if ((!$this->_metadata) || (!array_key_exists('description', $this->_metadata))) {
            return [];
        }

        return $this->_metadata['description'];
    }

    /**
     * Return TEI formatted paper
     * @return string
     * @throws Zend_Exception
     */
    private function getTei(): string
    {
        $tei = new Episciences_Paper_Tei($this);
        return $tei->generateXml();
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        if (!$this->_files) {
            $this->loadFiles();
        }

        return $this->_files;
    }

    /**
     * @param mixed $files
     */
    public function setFiles($files): void
    {
        $this->_files = $files;
    }


    private function loadFiles(): void
    {
        if (!$this->_docId || !is_numeric($this->_docId)) {
            $this->_files = [];
            return;
        }

        $this->_files = Episciences_Paper_FilesManager::findByDocId($this->_docId);
    }

    /**
     * @param string $fileName
     * @return Episciences_Paper_File|null
     */
    public function getFileByName(string $fileName): ?Episciences_Paper_File
    {
        if (!$this->hasHook) {
            return null;
        }

        return Episciences_Paper_FilesManager::findByName($this->_docId, $fileName);

    }

    /**
     * @param string $format
     * @return bool
     */
    public static function isValidMetadataFormat(string $format): bool
    {
        return in_array($format, self::$validMetadataFormats);
    }

    /**
     * @return mixed
     */
    public function getDatasets()
    {
        if (!$this->_datasets) {
            $this->loadDatasets();
        }

        return $this->_datasets;
    }

    /**
     * @param $datasets
     */
    public function setDatasets($datasets): void
    {
        $this->_datasets = $datasets;
    }


    private function loadDatasets(): void
    {
        if (!$this->_docId || !is_numeric($this->_docId)) {
            $this->_datasets = [];
            return;
        }

        $this->_datasets = Episciences_Paper_DatasetsManager::findByDocId($this->_docId);
    }


    public function getDatasetByValue(string $value): ?Episciences_Paper_Dataset
    {

        return Episciences_Paper_DatasetsManager::findByValue($this->_docId, $value);

    }

    /**
     * Datacite export format
     * @return string
     * @throws Zend_Exception
     */
    public function getDatacite(): string
    {
        $volume = '';
        $section = '';

        if ($this->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($this->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
            }
        }

        if ($this->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($this->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
            }
        }

        // Récupération des infos de la revue
        $journal = Episciences_ReviewsManager::find($this->getRvid());
        $journal->loadSettings();

        // Create new DOI if none exist
        if ($this->getDoi() !== '') {
            $doi = $this->getDoi();
        }

        $paperLanguage = $this->getMetadata('language');

        if (empty($paperLanguage)) {
            $paperLanguage = 'eng';
            // TODO temporary fix see https://gitlab.ccsd.cnrs.fr/ccsd/episciences/issues/215
            // this attribute is required by the datacite schema
            //arxiv doesnt have it, we need to fix this by asking the author additional information
        }

        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/export/');

        return $view->partial('datacite.phtml', [
            'volume' => $volume,
            'section' => $section,
            'journal' => $journal,
            'paper' => $this,
            'doi' => $doi,
            'paperLanguage' => $paperLanguage
        ]);

    }

    /**
     * @return string
     */
    public function getFlag(): string
    {
        return $this->_flag;
    }

    /**
     * @param string $flag
     * @return $this
     */
    public function setFlag(string $flag): \Episciences_Paper
    {
        $this->_flag = $flag;
        return $this;
    }

    /**
     * @return bool
     */
    public function isImported(): bool
    {
        return ($this->getFlag() === 'imported');
    }

    /**
     * @return array [Episciences_Paper_Conflict]
     */
    public function getConflicts(): array
    {
        return $this->_conflicts;
    }

    /**
     * @param array $conflicts
     * @return Episciences_Paper
     */
    public function setConflicts(array $conflicts): self
    {
        $oConflicts = [];

        foreach ($conflicts as $conflict){
            $oConflicts[] = new Episciences_Paper_Conflict($conflict);
        }

        $this->_conflicts = $oConflicts;
        return $this;
    }

    /**
     * @param int $uid
     * @return string
     */
    public function checkConflictResponse(int $uid): string
    {

        /** @var Episciences_Paper_Conflict $oConflict */
        foreach ($this->getConflicts() as $oConflict) {

            if ($oConflict->getBY() === $uid && $oConflict->getPaperId() === $this->getPaperid()) { // unique in T_PAPER_CONFLICTS table
                return $oConflict->getAnswer();
            }

        }

        return Episciences_Paper_Conflict::AVAILABLE_ANSWER['later'];
    }

}
