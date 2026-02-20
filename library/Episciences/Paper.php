<?php

use Episciences\Classification\jel;
use Episciences\Classification\msc2020;
use Episciences\Paper\DataDescriptorManager;
use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use Psr\Log\LogLevel;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class Episciences_Paper
 * @property string | null $_revisionDeadline
 * @property array $tmpFiles
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

    public const STATUS_SUBMITTED = 0;
    // reviewers have been assigned, but did not start their reports
    public const STATUS_OK_FOR_REVIEWING = 1;
    // rating has begun (at least one reviewer has starter working on his rating report)
    public const STATUS_BEING_REVIEWED = 2;
    // rating is finished (all reviewers)
    public const STATUS_REVIEWED = 3;
    public const STATUS_ACCEPTED = 4;
    public const STATUS_REFUSED = 5;
    public const STATUS_OBSOLETE = 6;
    public const STATUS_WAITING_FOR_MINOR_REVISION = 7;
    public const STATUS_WAITING_FOR_COMMENTS = 8;
    public const STATUS_TMP_VERSION = 9;
    public const STATUS_NO_REVISION = 10;
    public const STATUS_NEW_VERSION = 11;
    // paper removed by contributor (before publication)
    public const STATUS_DELETED = 12;
    // paper removed by editorial board (after publication)
    public const STATUS_REMOVED = 13;
    // reviewers have been invited, but no one has accepted yet
    public const STATUS_REVIEWERS_INVITED = 14;
    public const STATUS_WAITING_FOR_MAJOR_REVISION = 15;
    public const STATUS_PUBLISHED = 16;
    // Le processus de publication peut être stoppé tant que l'article n'est pas publié
    public const STATUS_ABANDONED = 17;
    //Copy editing
    public const STATUS_CE_WAITING_FOR_AUTHOR_SOURCES = 18;
    public const STATUS_CE_AUTHOR_SOURCES_DEPOSED = 19;
    public const STATUS_CE_REVIEW_FORMATTING_DEPOSED = 20; // Copy ed.: formatting by journal completed, waiting for a final version
    public const STATUS_CE_WAITING_AUTHOR_FINAL_VERSION = 21;
    // version finale déposée en attente de validation
    public const STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED = 22;
    public const STATUS_CE_READY_TO_PUBLISH = 23;
    public const STATUS_CE_AUTHOR_FORMATTING_DEPOSED = 24; // Copy ed.: formatting by author completed, waiting for final version
    public const STATUS_TMP_VERSION_ACCEPTED = 25; // tmp version accepted
    public const STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION = 26;
    public const STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION = 27;
    public const STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING = 28; // waiting to be edited by the Journal
    public const STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION = 29; // after author's modification
    public const STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION = 30;
    public const STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION = 31;
    public const STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION = 32;
    public const STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION = 33; // Journal formatting approved by author

    // paper settings
    public const SETTING_UNWANTED_REVIEWER = 'unwantedReviewer';
    public const SETTING_SUGGESTED_REVIEWER = 'suggestedReviewer';
    public const SETTING_SUGGESTED_EDITOR = 'suggestedEditor';

    // paper status
    public const STATUS_CODES = [
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
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
        self::STATUS_TMP_VERSION_ACCEPTED,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION,
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION,
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
        self::STATUS_NO_REVISION
    ];

    // Non présents dans le filtre de recherche

    public const OTHER_STATUS_CODE = [
        self::STATUS_OBSOLETE,
        self::STATUS_TMP_VERSION,
        self::STATUS_NEW_VERSION,
        self::STATUS_WAITING_FOR_COMMENTS,
        self::STATUS_DELETED,
        self::STATUS_REMOVED
    ];

    // exclude from a list of sorted papers for current volume
    public const DO_NOT_SORT_THIS_KIND_OF_PAPERS = [
        self::STATUS_DELETED,
        self::STATUS_ABANDONED,
        self::STATUS_REMOVED,
        self::STATUS_REFUSED
    ];

    // status priorities
    public const STATUS_DICTIONARY = [
        self::STATUS_SUBMITTED => 'submitted',
        self::STATUS_OK_FOR_REVIEWING => 'waitingForReviewing',
        self::STATUS_BEING_REVIEWED => 'underReview',
        self::STATUS_REVIEWED => 'reviewed pending editorial decision',
        self::STATUS_ACCEPTED => 'accepted',
        self::STATUS_PUBLISHED => 'published',
        self::STATUS_REFUSED => 'refused',
        self::STATUS_OBSOLETE => 'obsolete',
        self::STATUS_WAITING_FOR_MINOR_REVISION => 'pendingMinorRevision',
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 'pendingMajorRevision',
        self::STATUS_WAITING_FOR_COMMENTS => 'pendingClarification',
        self::STATUS_TMP_VERSION => 'temporaryVersion',
        self::STATUS_NO_REVISION => 'revisionRequestAnswerWithoutAnyModifications',
        self::STATUS_NEW_VERSION => 'answerToRevisionRequestNewVersion',
        self::STATUS_DELETED => 'deleted',
        self::STATUS_ABANDONED => 'abandoned',
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES => "waitingForAuthorsSources",
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED => 'waitingForFormattingByTheJournal',
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION => "waitingForAuthorsFinalVersion",
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED => 'finalVersionSubmittedWaitingForValidation',
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED => 'formattingByJournalCompletedWaitingForAFinalVersion',
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED => "formattingByAuthorCompletedWaitingForFinalVersion",
        self::STATUS_CE_READY_TO_PUBLISH => 'readyToPublish',
        self::STATUS_TMP_VERSION_ACCEPTED => "acceptedTemporaryVersionWaitingForAuthorsFinalVersion",
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION => "acceptedWaitingForAuthorsFinalVersion",
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'acceptedWaitingForMajorRevision',
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING =>
            'acceptedFinalVersionSubmittedWaitingForFormattingByCopyEditors',
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION =>
            "acceptedTemporaryVersionAfterAuthorsModifications",
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION =>
            'acceptedTemporaryVersionWaitingForMinorRevision',
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION =>
            'acceptedTemporaryVersionWaitingForMajorRevision"',
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION => "AcceptedWaitingForAuthorsValidation",
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION => "'AcceptedWaitingForFinalPublication'",
        self::STATUS_REMOVED => 'deletedByTheJournal',
    ];

    // status order (for sorting)
    public const EDITABLE_VERSION_STATUS = [
        self::STATUS_SUBMITTED,
        self::STATUS_OK_FOR_REVIEWING,
        self::STATUS_ACCEPTED,
        self::STATUS_CE_READY_TO_PUBLISH,
        self::STATUS_PUBLISHED,
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION,
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED
    ];
    public const ACCEPTED_SUBMISSIONS = [
        self::STATUS_ACCEPTED,
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
        self::STATUS_CE_READY_TO_PUBLISH,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION,
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED,
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION
    ];
    public const NOT_LISTED_STATUS = [
        Episciences_Paper::STATUS_OBSOLETE,
        Episciences_Paper::STATUS_DELETED,
        Episciences_Paper::STATUS_REMOVED
    ];
    public const STATUS_WITH_EXPECTED_REVISION = [
        self::STATUS_WAITING_FOR_MINOR_REVISION,
        self::STATUS_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION,
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION,
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION
    ];
    public const All_STATUS_WAITING_FOR_FINAL_VERSION = [
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_TMP_VERSION_ACCEPTED,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED

    ];
    public const TITLE_TYPE = 'title';
    public const TYPE_TYPE = 'type';
    public const TYPE_SUBTYPE = 'subtype';
    public const TITLE_TYPE_INDEX = 0;
    public const TYPE_TYPE_INDEX = 1;
    public const TYPE_SUBTYPE_INDEX = 2;
    public const DEFAULT_TYPE_TITLE = 'preprint';

    public const TEXT_TYPE_TITLE = 'text';//arXiv
    public const ARTICLE_TYPE_TITLE = 'article';
    public const DATASET_TYPE_TITLE = 'dataset';
    public const SOFTWARE_TYPE_TITLE = 'software';
    public const DATA_PAPER_TYPE = 'dataPaper';
    public const OTHER_TYPE = 'other';
    public const TMP_TYPE_TITLE = 'temporary version';

    public const TMP_TYPE = 'temporaryVersion';

    public const CONFERENCE_PAPER_TYPE_TITLE = 'conferencepaper';
    public const CONFERENCE_TYPE = 'conferenceobject';
    public const WORKING_PAPER_TYPE_TITLE = 'workingpaper'; //Zenodo
    public const JOURNAL_TYPE_TITLE = 'journal'; //Zenodo
    public const REGULAR_ARTICLE_TYPE_TITLE = 'regulararticle';
    public const JOURNAL_ARTICLE_TYPE_TITLE = 'journalarticle';
    public const PUBLICATION_TYPE_TITLE = 'publication';// Zenodo
    public const MED_ARXIV_PREPRINT = 'hwp-article-coll';


    public const ENUM_TYPES = [
        self::DEFAULT_TYPE_TITLE,
        self::TEXT_TYPE_TITLE,
        self::ARTICLE_TYPE_TITLE,
        self::DATASET_TYPE_TITLE,
        self::DATA_PAPER_TYPE,
        self::OTHER_TYPE,
        self::TMP_TYPE_TITLE,
        self::CONFERENCE_PAPER_TYPE_TITLE,
    ];
    public const PREPRINT_TYPES = [
        self::DEFAULT_TYPE_TITLE,
        self::TEXT_TYPE_TITLE,
        self::WORKING_PAPER_TYPE_TITLE,
        self::MED_ARXIV_PREPRINT,
        'E-print' //  Cryptology
    ];
    public const JSON_PATH_ABS_FILE = "$.database.current.graphical_abstract_file";
    public static array $_statusPriority = [
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
    public static array $_statusOrder = [
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
    public static array $_statusLabel = [
        self::STATUS_SUBMITTED => 'soumis',
        self::STATUS_OK_FOR_REVIEWING => 'en attente de relecture',
        self::STATUS_BEING_REVIEWED => 'en cours de relecture',
        self::STATUS_REVIEWED => 'évalué - en attente de décision éditoriale',
        self::STATUS_ACCEPTED => 'accepté',
        self::STATUS_PUBLISHED => 'publié',
        self::STATUS_REFUSED => 'refusé',
        self::STATUS_OBSOLETE => 'obsolète',
        self::STATUS_WAITING_FOR_MINOR_REVISION => 'en attente de modifications mineures',
        self::STATUS_WAITING_FOR_MAJOR_REVISION => 'en attente de modifications majeures',
        self::STATUS_WAITING_FOR_COMMENTS => 'en attente d\'éclaircissements',
        self::STATUS_TMP_VERSION => 'version temporaire',
        self::STATUS_NO_REVISION => 'réponse à une demande de modifications : pas de modifications',
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
        self::STATUS_TMP_VERSION_ACCEPTED => 'version temporaire acceptée, en attente de la version finale',
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION => "accepté - en attente de la version finale de l'auteur",
        self::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'accepté, en attente de modifications majeures',
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING => 'Accepté - version finale soumise, en attente de la mise en forme par la revue',
        self::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION => "version temporaire acceptée après modification de l'auteur",
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION => 'version temporaire acceptée, en attente des modifications mineures',
        self::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'version temporaire acceptée, en attente des modifications majeures',
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION => "accepté - en attente de validation par l'auteur",
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION => "approuvé - en attente de publication",
        self::STATUS_REMOVED => 'supprimé par la revue',
    ];
    public static array $_noEditableStatus = [
        self::STATUS_PUBLISHED,
        self::STATUS_REFUSED,
        self::STATUS_REMOVED,
        self::STATUS_DELETED,
        self::STATUS_OBSOLETE,
        self::STATUS_ABANDONED
    ];
    public static array $_canBeAssignedDOI = [
        self::STATUS_ACCEPTED,
        self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
        self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
        self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
        self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
        self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
        self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
        self::STATUS_CE_READY_TO_PUBLISH,
        self::STATUS_PUBLISHED,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION,
        self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
        self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION,
        self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
        self::STATUS_TMP_VERSION_ACCEPTED
    ];
    public static array $validMetadataFormats = ['bibtex', 'tei', 'dc', 'datacite', 'openaire', 'crossref', 'doaj', 'zbjats', 'json'];
    public $hasHook;
    protected array $_type = [self::TITLE_TYPE => self::DEFAULT_TYPE_TITLE, self::TYPE_TYPE => self::DEFAULT_TYPE_TITLE];
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
    private string $_doi = '';
    private $_version;
    private $_rvId = 0;
    private $_vId = 0;
    private $_sId = 0;
    private $_uId;
    private $_status = 0;
    private $_identifier;
    private $_repoId = 0;
    private $_record;
    private $_document;
    private ?array $_document_private = null;
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
    /** @var Episciences_User[] $_coAuthors */
    private $_coAuthors;
    /** @var Array [Episciences_Paper_Conflict] */
    private $_conflicts = []; // defines whether the paper has been submitted or imported
    /**
     * Position in volume
     * @var null | int
     */
    private $_position; // @see self::setRepoid()
    private $_files;
    private $_datasets;

    // variable for the export Enrichment
    /** @var string */
    private $_flag = 'submitted';
    private $_authors;
    /** @var Episciences_Paper_Licence $_licence */
    private $_licence;
    /** @var Episciences_Paper_Projects $_fundings */
    private $_fundings;
    /** @var Episciences_Paper_Dataset $_linkedData */

    private array $_linkedData;
    private ?string $_password = null;
    private ?string $_graphical_abstract = null;
    private ?array $_data_descriptors = null;

    /**
     * Episciences_Paper constructor.
     * @param array|null $options
     * @throws Zend_Db_Statement_Exception
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
     * @throws DOMException
     * @throws Zend_Db_Statement_Exception
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
                    if (($key === 'type') && $value) {
                        try {
                            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException $e) {
                            trigger_error($e->getMessage());
                        }
                    }
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
            trigger_error('No DOI assigned because paper ' . $paper->getDoi() . ' has a DOI.', E_USER_WARNING);
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
            return Episciences_DoiTools::DOI_ORG_PREFIX . $this->_doi;
        }
        return $this->_doi;
    }


    /**
     * @param string|null $doi
     * @return $this
     */
    public function setDoi(string $doi = null): self
    {
        $this->_doi = !$doi ? '' : $doi;
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

            if ($action === Episciences_Paper_Logger::CODE_STATUS) {
                $this->postPaperStatus();
            }

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
     * @throws Zend_Db_Statement_Exception
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

    /*
     * A paper object, as an array, with only public information
     */

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
    public function setWhen($when = null): self
    {
        $this->_when = $when ?: new Zend_Db_Expr('NOW()');
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
    public function setVid($id = 0): self
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

        if ($this->getStatus() === self::STATUS_REFUSED && str_contains($this->_identifier, "-REFUSED")) {
            $this->setIdentifier(str_replace('-REFUSED', '', $this->_identifier));
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

        $this->hasHook = !empty(Episciences_Repositories::hasHook($this->getRepoid())) &&
            (
                $this->getRepoid() === (int)Episciences_Repositories::ZENODO_REPO_ID ||
                Episciences_Repositories::isDataverse($repoId) ||
                Episciences_Repositories::isDspace($repoId)
            );

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
     * @throws Zend_Db_Statement_Exception
     * @throws DOMException
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
     * @param mixed $xml
     */
    public function setXml($xml): void
    {
        $this->_xml = $xml;
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
     * @throws Exception
     */
    public function setXslt($xml, string $theme = 'full_paper'): self
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
        if (!$this->_submitter) {
            // this is to handle development and test databases inconsistencies
            $this->_submitter = new Episciences_User();
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
     * @throws Zend_Db_Statement_Exception
     */
    public function getDocUrl()
    {
        if (!$this->_docUrl) {

            if (!$this->isTmp()) {
                $this->setDocUrl(Episciences_Repositories::getDocUrl($this->getRepoid(), $this->getIdentifier(), $this->getVersion()));
            } else {

                $paper = Episciences_PapersManager::get($this->getPaperid(), false);

                if ($paper) {
                    $this->setDocUrl(Episciences_Repositories::getDocUrl(
                        $paper->getRepoid(),
                        $paper->getIdentifier(),
                        (int)$this->getVersion()
                    ));

                } else {
                    trigger_error('The original version no longer exists!?');
                }

            }

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
     * @return bool
     */
    public function isTmp(): bool
    {
        return ($this->getRepoid() === 0);
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
     * fetch article in given format
     * @param string $format
     * @param int|null $version
     * @return string|false
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function get(string $format = 'tei', int $version = null)
    {
        $format = strtolower(trim($format));
        $method = 'get' . ucfirst($format);

        if ($format === 'json' && $version === 2) {
            $method .= 'V2';
        }

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
     * @param string $format
     * @return bool
     */
    public static function isValidMetadataFormat(string $format): bool
    {
        return in_array($format, self::$validMetadataFormats);
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return ($this->getStatus() === self::STATUS_PUBLISHED);
    }

    /**
     * save paper to database
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $docId = $this->getDocid();

        $type = $this->getType();

        if ($type) {
            try {
                $type = $this->getType() ? json_encode($this->getType(), JSON_THROW_ON_ERROR) : $this->getType();
            } catch (JsonException $e) {
                trigger_error($e->getMessage());
            }
        }

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
                'FLAG' => $this->getFlag(),
                'PASSWORD' => $this->getPassword(),
                'TYPE' => $type
            ];

            try {
                $document = $this->toJson();
            } catch (Zend_Db_Statement_Exception $e) {
                $document = null;
                trigger_error($e->getMessage());
            }

            $data['DOCUMENT'] = $document;

            if ($this->getPublication_date()) {
                $data['PUBLICATION_DATE'] = $this->getPublication_date();
            } elseif ($this->getStatus() === self::STATUS_PUBLISHED) {
                $data['PUBLICATION_DATE'] = new Zend_Db_Expr('NOW()');
            }

            if ($this->getConcept_identifier()) {
                $data['CONCEPT_IDENTIFIER'] = $this->getConcept_identifier();
            }

            if ($db?->insert(T_PAPERS, $data)) {
                $this->setDocid($db?->lastInsertId());
                if (!$this->getPaperid()) {
                    $this->setPaperid($this->getDocid());
                    $this->save();

                    //insert licence when save paper
                    try {
                        $callArrayResp = Episciences_Paper_LicenceManager::getApiResponseByRepoId($this->getRepoid(), $this->getIdentifier(), (int)$this->getVersion());
                        Episciences_Paper_LicenceManager::insertLicenceFromApiByRepoId($this->getRepoid(), $callArrayResp, $this->getDocid(), $this->getIdentifier());

                    } catch (\GuzzleHttp\Exception\GuzzleException|JsonException $e) {
                        trigger_error($e->getMessage());
                    }
                } else {
                    // keep linkeddata and insert line with the new doc id
                    Episciences_Paper_DatasetsManager::updateAllByDocId($this);
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
            'FLAG' => $this->getFlag(),
            'PASSWORD' => $this->getPassword(),
            'TYPE' => $type
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

        $this->setPosition($this->applyPositioningStrategy());

        try {
            $document = $this->toJson();
        } catch (Zend_Db_Statement_Exception $e) {
            $document = null;
            trigger_error($e->getMessage());
        }

        $data['DOCUMENT'] = $document;

        if (!$db?->update(T_PAPERS, $data, ['DOCID = ?' => $docId])) {
            return false;
        }

        return true;
    }

    public function getType(): array
    {
        return $this->_type;
    }

    /**
     * @param array|null $type
     * @return $this
     */

    public function setType(array $type = null): \Episciences_Paper
    {
        if (!empty($type)) {
            $this->_type = $type;
        } else {
            $this->_type = [self::TITLE_TYPE => self::DEFAULT_TYPE_TITLE];
        }
        return $this;
    }

    /**
     * @return string|null
     * @throws Zend_Db_Statement_Exception
     */

    public function toJson(): ?string
    {
        $sSection = null;
        $citedBy = null;
        $classifications = null;
        $sVolume = null;

        $journal = Episciences_ReviewsManager::find($this->getRvid());

        $serializer = new Serializer([new ObjectNormalizer()], [new XmlEncoder(), new JsonEncoder(new JsonEncode(['json_encode_options' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]))]);
        // from crossref template
        $crossRefXml = Episciences_Paper_XmlExportManager::getXmlCleaned(Episciences_Paper_XmlExportManager::xmlExport($this, Episciences_Paper_XmlExportManager::CROSSREF_FORMAT));
        $crossRefXml = str_replace(array("jats:p", "jats:"), array("value", ""), Episciences_Tools::spaceCleaner($crossRefXml));
        $xmlToArray = $serializer->decode($crossRefXml, "xml");

        if ($this->isTmp()) {
            $this->processTmpVersion($this);
        }

        $xmlToArray = $this->processDatasetsToJson($xmlToArray);
        $this->processProjects($xmlToArray);
        $sPreviousVersions = $this->getPreviousVersionsToJson($journal);

        if ($this->getDocid()) {
            $citations = Episciences_Paper_CitationsManager::getCitationByDocId($this->getDocid());
            if (!empty($citations)) {
                $citedBy = $citations;
            }

            $classifications = $this->getClassifications(true);
        }

        if ($this->getVid()) {
            $oVolume = Episciences_VolumesManager::find($this->getVid());
            if ($oVolume) {
                $sVolume = [
                    'id' => $oVolume->getVid() ?: null,
                    'position' => $oVolume->getPosition(),
                    'number' => $oVolume->getVol_num(),
                    'year' => $oVolume->getVol_year(),
                    'has_proceedings' => isset($xmlToArray[Episciences_Paper_XmlExportManager::BODY_KEY][Episciences_Paper_XmlExportManager::CONFERENCE_KEY]),
                    'titles' => $oVolume->getTitles(),
                    'descriptions' => $oVolume->getDescriptions(),
                    'bibliographical_references' => $oVolume->getBib_reference(),
                    'settings' => [
                        'is_current_issue' => (int)$oVolume->getSetting($oVolume::SETTING_CURRENT_ISSUE) === 1,
                        'is_special_issue' => (int)$oVolume->getSetting($oVolume::SETTING_SPECIAL_ISSUE) === 1,
                        'is_open' => (int)$oVolume->getSetting($oVolume::SETTING_STATUS) === 1
                    ],
                ];
            }
        }

        if ($this->getSid()) {
            $oSection = Episciences_SectionsManager::find($this->getSid());
            if ($oSection) {
                $sSection = [
                    'id' => $oSection->getSid(),
                    'position' => $oSection->getPosition(),
                    'titles' => $oSection->getTitles(),
                    'descriptions' => $oSection->getDescriptions(),
                    'settings' => [
                        'is_open' => (int)$oSection->getSetting($oSection::SETTING_STATUS) === $oSection::SECTION_OPEN_STATUS
                    ]
                ];
            }
        }
        $graphical_abstract_file = '';
        $current = $this->getDocument()['database']['current'] ?? null;
        if (isset($current['graphical_abstract_file'])) {
            $graphical_abstract_file = $current['graphical_abstract_file'];
        }
        $extraData = [

            Episciences_Paper_XmlExportManager::JOURNAL_ARTICLE_KEY => [
                'keywords' => $this->getMetadata('subjects')
            ],
            Episciences_Paper_XmlExportManager::DATABASE_KEY => [
                'current' => [
                    'mainPdfUrl' => $this->getMainPaperUrl(),
                    'original_language' => $xmlToArray[Episciences_Paper_XmlExportManager::BODY_KEY][Episciences_Paper_XmlExportManager::JOURNAL_KEY][Episciences_Paper_XmlExportManager::JOURNAL_METADATA_KEY]['@language'] ?? 'en',
                    'identifiers' => [
                        'permanent_item_number' => $this->getPaperid(),
                        'document_item_number' => $this->getDocid(),
                        'repository_identifier' => $this->getIdentifier(),
                        'concept_identifier' => $this->getConcept_identifier()
                    ],
                    'isTmp' => $this->isTmp(),
                    'flag' => $this->getFlag(),
                    'type' => $this->getType(),
                    'status' => [
                        'id' => $this->getStatus(),
                        'label' => [
                            'en' => $this->getStatusLabel('en'),
                            'fr' => $this->getStatusLabel(),
                        ]
                    ],
                    'url' => sprintf('%s/%s', $journal->getUrl(), $this->getDocid()),
                    'version' => $this->getVersion(),
                    'files' => $this->processFiles($journal->getUrl()),
                    'dates' => [
                        'first_submission_date' => $this->getSubmission_date(),
                        'posted_date' => $this->getWhen(),
                        'modification_date' => $this->getModification_date(),
                        'publication_date' => $this->getPublication_date()
                    ],
                    'volume' => $sVolume,
                    'position_in_volume' => $this->getPosition(),
                    'section' => $sSection,
                    'journal' => [
                        'id' => $journal->getRvid(),
                        'code' => $journal->getCode(),
                        'name' => $journal->getName(),
                        'url' => $journal->getUrl(),
                    ],

                    'repository' => Episciences_Repositories::getRepositories()[$this->getRepoid()] ?? null,
                    'cited_by' => $citedBy,
                    'classifications' => $classifications,
                    'graphical_abstract_file' => $graphical_abstract_file,
                    'metrics' => Episciences_Paper_Visits::getPaperMetricsByPaperId($this->getPaperid()),

                ],
                'latest_version_item_number' => (int)$this->getLatestVersionId(),
                'first_version_item_number' => $this->getPaperid(),
                'previous_versions' => $sPreviousVersions,
            ]

        ];
        if ($graphical_abstract_file === '') {
            unset($extraData[Episciences_Paper_XmlExportManager::PUBLIC_KEY][Episciences_Paper_XmlExportManager::DATABASE_KEY]['current']['graphical_abstract_file']);
        }
// Define the keys for better readability
        $keyBody = Episciences_Paper_XmlExportManager::BODY_KEY;
        $keyJournal = Episciences_Paper_XmlExportManager::JOURNAL_KEY;
        $keyJournalArticle = Episciences_Paper_XmlExportManager::JOURNAL_ARTICLE_KEY;
        $keyDatabase = Episciences_Paper_XmlExportManager::DATABASE_KEY;
        $keyConf = Episciences_Paper_XmlExportManager::CONFERENCE_KEY;
        $isConf = isset($xmlToArray[$keyBody][$keyConf]);
        $keyConfPaper = Episciences_Paper_XmlExportManager::CONFERENCE_PAPER_KEY;


        // Merge journal article or conference  data

        if (!$isConf) {

            if (!is_array($xmlToArray[$keyBody][$keyJournal][$keyJournalArticle])) {
                throw new InvalidArgumentException(sprintf("docid %s : %s-%s-%s is not an array", $this->getDocid(), $keyBody, $keyJournal, $keyJournalArticle));
            }


            $xmlToArray[$keyBody][$keyJournal][$keyJournalArticle] = array_merge(
                $xmlToArray[$keyBody][$keyJournal][$keyJournalArticle],
                $extraData[$keyJournalArticle]
            );

        } else {

            $xmlToArray[$keyBody][$keyConf][$keyConfPaper] = array_merge(
                $xmlToArray[$keyBody][$keyConf][$keyConfPaper],
                $extraData[$keyJournalArticle]
            );

        }

        $xmlToArray[$keyBody][$keyDatabase] = $extraData[$keyDatabase];


// Update document keys
        $document = $xmlToArray[$keyBody];
        $result = $serializer->serialize($document, 'json');
        $identifier = str_replace('"', '\"', $this->getIdentifier());
        $xmlToArray = null;
        return str_replace(array('"#"', '%%ID', '%%VERSION'), array('"value"', $identifier, $this->getVersion()), $result);
    }

    private function processTmpVersion(Episciences_Paper $paper): void
    {
        if (!$paper->isTmp()) {
            return;
        }

        $tmpIdentifier = $paper->getIdentifier();
        $tmpPaperId = (string)$paper->getPaperid();
        $subStr = substr($tmpIdentifier, (strlen($tmpPaperId) + 1));

        try {
            $tmpFiles = !Episciences_Tools::isJson($subStr) ? (array)$subStr : json_decode($subStr, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($tmpFiles)) {
                throw new InvalidArgumentException(sprintf('processTmpVersion() : $tmpFiles is not typed as an array for paperid %s', $tmpPaperId));
            }
            $tmpFiles = Episciences_Tools::arrayFilterEmptyValues($tmpFiles);
            $paper->tmpFiles = $tmpFiles;
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }

    }

    /**
     * @param array $xmlToArray
     * @return array
     */
    private function processDatasetsToJson(array $xmlToArray): array
    {
        $docType = null;
        $programPath = null;
        $programKey = null;
        $renameFirstKey = true;

        // Determine the document type and set the program path
        if (isset($xmlToArray['body']['journal']['journal_article']['program'])) {
            $docType = 'journal';
            $programPath = &$xmlToArray['body']['journal']['journal_article']['program'];
        } elseif (isset($xmlToArray['body']['conference']['conference_paper']['program'])) {
            $docType = 'conference';
            $programPath = &$xmlToArray['body']['conference']['conference_paper']['program'];
        }

        // If a valid document type was found, process the program
        if ($docType !== null && is_array($programPath) && !empty($programPath)) {
            $items = [];


            // Collect all 'related_item' elements and $programKey from the program array
            foreach ($programPath as $programKey => $value) {

                if (($programKey === 'related_item')) {
                    $renameFirstKey = false;
                    $items[] = $value;

                } elseif (!empty($value['related_item'])) {
                    $items[] = $value['related_item'];
                }
            }

            // If there are related items, process them
            if (!empty($items)) {
                $result = self::addUnstructuredCitationToDatasetsToJson($this->getDatasets(), $items, 'markdown', $renameFirstKey);
                // Update the corresponding keys in $programPath with the processed result
                $programPath[$programKey] = $result;
            }
        }

        return $xmlToArray;
    }

    private static function addUnstructuredCitationToDatasetsToJson(array $datasets, array $relations, string $format = 'markdown', bool $renameFirstkey = true): array
    {
        // Create a map of _value => metatextCitation
        $citationMap = [];

        /** @var Episciences_Paper_Dataset $dataset */
        foreach ($datasets as $dataset) {
            $itemValue = Episciences_DoiTools::cleanDoi($dataset->getValue());
            $metatextCitation = $dataset->getMetatextCitation($format);

            if ($itemValue !== '' && $metatextCitation !== '') {
                $citationMap[$itemValue] = $metatextCitation;
            }
        }

        // Iterate over the second array to add 'unstructured_citation'
        foreach ($relations[0] as &$relation) {
            foreach ($relation as &$data) {
                if (isset($data['#'])) {
                    $citationKey = $data['#'];
                    if (!empty($citationMap[$citationKey])) {
                        $data['unstructured_citation'] = $citationMap[$citationKey];
                    }
                }
            }

            unset($data);
        }

        unset($relation);

        // Rename the key 0 to 'related_item'
        if (isset($relations[0]) && $renameFirstkey) {
            $relations['related_item'] = $relations[0];
            unset($relations[0]);
        }

        return $renameFirstkey ? $relations : $relations[0];
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

    /**
     * @param Episciences_Review|bool $journal
     * @return array|null
     * @throws Zend_Db_Statement_Exception
     */
    private function getPreviousVersionsToJson(Episciences_Review|bool $journal): ?array
    {
        $tmp = null;
        $sPreviousVersions = null;

        $aOPreviousVersions = $this->getPreviousVersions() ?? [];
        if ($aOPreviousVersions === null) {
            return null;
        }
        /** @var Episciences_Paper $oPaper */
        foreach ($aOPreviousVersions as $oPaper) {

            if ($oPaper->isTmp()) {
                $this->processTmpVersion($oPaper);
            }

            $tmp = [
                'identifiers' => [
                    'permanent_item_number' => $oPaper->getPaperid(),
                    'document_item_number' => $oPaper->getDocid(),
                    'repository_identifier' => $oPaper->getIdentifier(),
                    'concept_identifier' => $oPaper->getConcept_identifier()
                ],
                'version' => $oPaper->getVersion(),
                'dates' => [
                    'posted_date' => $oPaper->getWhen(),
                    'modification_date' => $oPaper->getModification_date(),
                ],
                'status' => [
                    'id' => $oPaper->getStatus(),
                    'label' => [
                        'en' => $oPaper->getStatusLabel('en'),
                        'fr' => $oPaper->getStatusLabel(),
                    ]
                ],

                'url' => sprintf('%s/%s', $journal->getUrl(), $oPaper->getDocid()),
            ];

            $sPreviousVersions[] = $tmp;

        }

        unset($tmp);
        return $sPreviousVersions;
    }

    /**
     * return an array of papers (previous versions of this paper)
     * @param bool $isCurrentVersionIncluded
     * @param bool $includeTempVersions
     * @return array|null
     * @throws Zend_Db_Statement_Exception
     */
    public function getPreviousVersions(bool $isCurrentVersionIncluded = false, bool $includeTempVersions = true): ?array
    {
        if (($isCurrentVersionIncluded || !isset($this->_previousVersions)) && $this->getPaperid() !== $this->getDocid()) {

            $this->_previousVersions = null;
            $parentId = $this->getPaperid();

            if ($parentId) {

                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $sql = $db->select()
                    ->from(T_PAPERS)
                    ->where('PAPERID = ?', $parentId);

                if (!$includeTempVersions) {
                    $sql->where('REPOID != 0');
                }

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

    public function getStatusLabel(string $lang = null): string
    {

        $label = Episciences_PapersManager::getStatusLabel($this->getStatus());

        if ($lang) {

            try {
                $translator = Zend_Registry::get('Zend_Translate');
                $label = $translator->translate($label, $lang);
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

        return $label;
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
            return array_key_exists($this->_paperId, $volumePaperPosition) ? (int)$volumePaperPosition[$this->getPaperid()] : null;
        }

        return $this->_position;
    }

    /**
     * set paper position in volume
     * @param int|null $position
     * @return $this
     */
    public function setPosition(int $position = null): self
    {

        $this->_position = $position;

        return $this;

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
     * @return array|null
     */
    public function getDocument(): ?array
    {
        return $this->_document;
    }

    /**
     * @param string|null $document
     * @return Episciences_Paper
     */
    public function setDocument(string $document = null): self
    {

        if ($document) {

            try {
                $document = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                trigger_error($e->getMessage());
            }
        }

        $this->_document = $document;
        return $this;
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

            $currentMeta = $metadata[$name];

            if ($name === 'subjects') {
                $processedResult = [];
                foreach ($currentMeta as $index => $value) {
                    if (is_array($value)) {
                        $this->processArraySubject($value, $processedResult);
                    } else {
                        $this->processSingleSubject($index, $value, $processedResult);
                    }
                }

                $currentMeta = $processedResult;

            }

            if ($key) {
                if (array_key_exists($key, $currentMeta)) {
                    $result = $currentMeta[$key];
                }
            } else {
                $result = $currentMeta;
            }
        }
        if (is_array($result)) {
            $result = array_map('Episciences_Tools::spaceCleaner', $result);
            $result = array_map('Episciences_Tools::decodeAmpersand', $result);
        } else {
            $result = Episciences_Tools::spaceCleaner($result);
            // On reçois des chaînes avec des entités HTML (&amp;)
            // Pour revenir au texte logique (un seul &).
            $result = Episciences_Tools::decodeAmpersand($result);
        }
        return $result;

    }

    /**
     * @param string $xml
     * @return $this
     */
    public function setMetadata(string $xml): self
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
            $metadata['type'] = Episciences_Tools::xpath($xml, '//dc:type');
            $metadata['licenses'] = $metadata['type'] = Episciences_Tools::xpath($xml, '//dc:rights');
        } catch (Exception $e) {
            $metadata['title'] = 'Erreur : la source XML de ce document semble corrompue. Les métadonnées ne sont pas utilisables.';
            $metadata['description'] = 'Merci de contacter le support pour vérifier le document et ses métadonnées';
        }


        $this->_metadata = $metadata;
        return $this;
    }

    private function processArraySubject($subjectCollection, &$result = []): void
    {
        foreach ($subjectCollection as $languageCode => $subject) {
            $subject = trim($subject);
            $isStringKey = is_string($languageCode);
            try {
                $languageCode = ($isStringKey && $translatedKey = Languages::getAlpha2Code($languageCode)) ? $translatedKey : $languageCode;
            } catch (MissingResourceException $missingResourceException) {
                // $languageCode remains $languageCode
            }

            if ($isStringKey) {
                $result[$languageCode][] = $subject;
            } else {
                $result[] = $subject;
            }
        }
    }

    private function processSingleSubject($languageCode, $subject, &$result = []): void
    {
        $isStringIndex = is_string($languageCode);
        $subject = trim($subject);
        try {
            $languageCode = ($isStringIndex && $translatedIndex = Languages::getAlpha2Code($languageCode)) ? $translatedIndex : $languageCode;
        } catch (MissingResourceException $missingResourceException) {
            // $index remains $index
        }

        if ($isStringIndex) {
            $result[$languageCode][] = $subject;
        } else {
            $result[] = $subject;
        }
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

    private function processFiles(string $journalUrl): array
    {

        $processedFile = [];

        if ($this->hasHook) {
            $oCurrentFiles = $this->getFiles();
            /** @var Episciences_Paper_File $oCFile */
            foreach ($oCurrentFiles as $oCFile) {

                $fTmp = [
                    'name' => $oCFile->getName(),
                    'size' => $oCFile->getFileSize(),
                    'link' => $oCFile->getDownloadLike()
                ];

                $processedFile[] = $fTmp;

            }

        } elseif ($this->isTmp()) {

            if (isset($this->tmpFiles)) {

                foreach ($this->tmpFiles as $fileName) {

                    $fTmp = [
                        'name' => $fileName,
                        'link' => sprintf('%s/tmp_files/%s/%s', $journalUrl, $this->getPaperid(), urlencode($fileName))
                    ];

                    $processedFile[] = $fTmp;
                }
            }

        } else {

            $processedFile = [
                'link' => !$this->isPublished() ? $this->getPaperUrl() : sprintf('%s/%s/pdf', $journalUrl, $this->getDocid())
            ];

        }

        return $processedFile;
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
    public function setSubmission_date($submission_date = null): self
    {
        $this->_submission_date = $submission_date ?: new Zend_Db_Expr('NOW()');
        return $this;
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
     * @return string | null
     */
    public function getPassword(): ?string
    {
        return $this->_password;
    }

    /**
     * @param string|null $paperPassword
     * @param bool $encrypt
     * @return $this
     */
    public function setPassword(string $paperPassword = null, bool $encrypt = false): self
    {

        if (!empty($paperPassword) && $encrypt) {

            try {

                $paperPassword = Episciences_Tools::encrypt($paperPassword);

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);

            }
        }

        $this->_password = $paperPassword;

        return $this;
    }

    /**
     * Create or delete a position in volume
     * @return int|null
     */
    public function applyPositioningStrategy(): ?int
    {

        if (empty($this->getVid())) {
            return null;
        }

        if (in_array($this->getStatus(), self::DO_NOT_SORT_THIS_KIND_OF_PAPERS, true)) {
            $this->deletePosition();
            $this->setPosition();
            return null;
        }

        return $this->createPositionProcessing();

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
     * @return int|null
     */
    private function createPositionProcessing()
    {
        return $this->getPosition() ?? $this->insertPosition();
    }

    /**
     * assign position
     * @return int|null
     */
    private function insertPosition(): ?int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $this->nextPositionQuery();
        $position = $db->fetchOne($select);

        if (!is_numeric($position)) {
            return null;
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

        return array_key_exists($uid, $this->_editors) ? $this->_editors[$uid] : false;
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
     * @param int|null $uid
     * @return bool
     */
    public function isEditor(int $uid = null): bool
    {

        if (!$uid) {
            return false;
        }

        try {

            $editor = $this->getEditor($uid);

        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }

        return !empty($editor);

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

        $oAssignment->setRvid($this->getRvid());

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

        $oAssignment->setRvid($this->getRvid());

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
            ->where('STATUS != ?', self::STATUS_DELETED);

        if ($this->getConcept_identifier()) {
            $sql->where('CONCEPT_IDENTIFIER = ?', $this->getConcept_identifier());
        } else {
            $sql->where('IDENTIFIER = ?', $this->getIdentifier());
        }

        if ($this->getVersion()) {
            $sql->where('VERSION = ?', $this->getVersion());
        }

        //$sql->where('REPOID = ?', $this->getRepoid());

        // Si plusieurs version de l'article, on recupère l'article dans sa dernière version
        $sql->order('WHEN DESC');

        return ($db->fetchOne($sql));
    }

    /**
     * check if paper can be reviewed
     * paper can be reviewed if status is not one of these:
     * accepted, published, refused, removed, deleted, obsolete
     * @return bool
     */
    public function canBeReviewed(): bool
    {
        return
            (
                $this->isEditable() &&
                !$this->isAccepted() &&
                !$this->isRevisionRequested() &&
                !$this->isCopyEditingProcessStarted() &&
                !$this->isReadyToPublish()
            );
    }

    /**
     * check if paper can be edited
     * paper is editable if status is not one of these:
     * published, refused, removed, deleted, obsolete
     * @return bool
     */
    public function isEditable(): bool
    {
        return (Episciences_Auth::getUid() !== $this->getUid()) &&
            !in_array($this->getStatus(), self::$_noEditableStatus, true);
    }

    /**
     * @return bool
     */
    public function isAccepted(): bool
    {
        return ($this->getStatus() === self::STATUS_ACCEPTED);
    }

    public function isRevisionRequested(): bool
    {
        return in_array($this->getStatus(), self::STATUS_WITH_EXPECTED_REVISION, true);
    }

    /**
     * @return bool
     */

    public function isCopyEditingProcessStarted(): bool
    {
        return in_array($this->getStatus(), [
            self::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
            self::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
            self::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
            self::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
            self::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
            self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
            self::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
            self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION
        ], true);
    }

    public function isReadyToPublish(): bool
    {
        return in_array($this->getStatus(),
            [self::STATUS_CE_READY_TO_PUBLISH, self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION],
            true);
    }

    /**
     * @return bool
     */
    public function isApprovedByAuthor(): bool
    {
        return ($this->getStatus() === self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION);
    }

    /**
     * @return bool
     */
    public function isTmpVersionAccepted(): bool
    {
        return ($this->getStatus() === self::STATUS_TMP_VERSION_ACCEPTED);
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
     * Verifie si le processus de publication d'un article a été abandonné
     * @return bool
     */
    public function isAbandoned(): bool
    {
        return ($this->getStatus() === self::STATUS_ABANDONED);
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

            $sql = $this->loadHistoryQuery()
                ->order('DOCID DESC')
                ->order('DATE DESC')
                ->order('LOGID DESC');

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
     * @param string|array $cols
     * @return Zend_Db_Select
     */
    private function loadHistoryQuery(string|array $cols = '*'): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return $db?->select()
            ->from(T_LOGS, $cols)
            ->where('PAPERID = ?', $this->getPaperid());
    }

    /**
     * @param int|array $statues
     * @param string|array $cols
     * @return Zend_Db_Select
     */

    private function loadHistoryByStatuesQuery(int|array $statues , string|array $cols = '*'): Zend_Db_Select
    {
        $query = $this->loadHistoryQuery($cols);

        if (is_int($statues)) {
            $query->where('status = ?', $statues);
        } else {
            $query->where('status in (?)', $statues);
        }

        //in some situations, an article may be accepted several times:
        // the objective is to know when the article was first accepted
        $query->order('DOCID ASC')
            ->order('DATE ASC')
            ->order('LOGID ASC');

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
                $version = (string)$value['VERSION'];
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
     * @param array $settings
     * @return array|null
     */
    public function getComments(array $settings = [])
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
     * @throws DOMException
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function updateXml()
    {

        $isAllowedToListOnlyAssignedPapers = Episciences_Auth::isAllowedToListOnlyAssignedPapers();

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
        $repositoryIdentifier = Episciences_Repositories::getIdentifier($this->getRepoid(), $this->getIdentifier(), $this->getVersion());


        $oaiPrefixString = 'oai:';

        if ($repositoryIdentifier && str_starts_with($repositoryIdentifier, $oaiPrefixString)) {
            $repositoryIdentifier = substr($repositoryIdentifier, strlen($oaiPrefixString));
        }

        $node->appendChild($dom->createElement('identifier', $repositoryIdentifier)); // Identifiant source
        $node->appendChild($dom->createElement('doi', $this->getDoi())); // DOI
        $node->appendChild($dom->createElement('hasOtherVersions', ($this->getDocid() != $this->getPaperid()) ? 1 : 0));
        $node->appendChild($dom->createElement('tmp', $this->isTmp()));
        $node->appendChild($dom->createElement('review', $oReview->getName()));
        $node->appendChild($dom->createElement('review_code', $oReview->getCode()));
        $node->appendChild($dom->createElement('review_url', SERVER_PROTOCOL . '://' . RVCODE . '.' . DOMAIN));
        $node->appendChild($dom->createElement('version', $this->getVersion()));
        $node->appendChild($dom->createElement('esURL', SERVER_PROTOCOL . '://' . RVCODE . '.' . DOMAIN . '/' . $this->getDocid()));
        $node->appendChild($dom->createElement('docURL', $this->getDocUrl()));
        $mainUrl = $this->getMainPaperUrl();
        // ----  @sse [#644]: https://github.com/CCSDForge/episciences/issues/644
        $node->appendChild($dom->createElement('notHasHook', !empty($mainUrl)));
        $node->appendChild($dom->createElement('paperURL', $mainUrl));
        // ----- end @see [#644]
        $node->appendChild($dom->createElement('volume', $this->getVid()));
        $node->appendChild($dom->createElement('section', $this->getSid()));
        $node->appendChild($dom->createElement('status', $this->getStatus()));
        $node->appendChild($dom->createElement('status_date', $this->getWhen()));
        $node->appendChild($dom->createElement('submission_date', $this->getSubmission_date()));
        $node->appendChild($dom->createElement('publication_date', $this->getPublication_date()));
        $submitter = ($this->getSubmitter()) ? $this->getSubmitter()->getFullName() : null;
        $node->appendChild($dom->createElement('submitter', $submitter));
        $node->appendChild($dom->createElement('uid', $this->getUid()));
        $node->appendChild($dom->createElement('isImported', $this->isImported()));
        $node->appendChild($dom->createElement('acceptance_date', $this->getAcceptanceDate()));
        $node->appendChild($dom->createElement('isAllowedToListAssignedPapers', Episciences_Auth::isSecretary() || $isAllowedToListOnlyAssignedPapers || $this->getUid() === Episciences_Auth::getUid()));
        $node->appendChild($dom->createElement('submissionType', ucfirst($this->getTypeWithKey())));
        $node->appendChild($dom->createElement('docUrlBtnLabel', $this->combineDocUrlLabel()));


        //get licence paper
        if (!empty($this->getDocid())) {
            $licence = Episciences_Paper_LicenceManager::getLicenceByDocId($this->getDocid());
            if ($licence !== "") {
                $node->appendChild($dom->createElement('paperLicence', $licence));
            }
        }
        //author with orcid
        $authorEnrich = Episciences_Paper_AuthorsManager::formatAuthorEnrichmentForViewByPaper($this->_paperId);
        if (!empty($authorEnrich['template'])) {
            $node->appendChild($dom->createElement('authorEnriched', $authorEnrich['template']));
            $node->appendChild($dom->createElement('authorsOrcid', $authorEnrich['orcid']));
            $node->appendChild($dom->createElement('listAffi', $authorEnrich['listAffi']));
            $node->appendChild($dom->createElement('listAuthors', $authorEnrich['authorsList']));

        } else {
            $node->appendChild($dom->createElement('authorEnriched', ""));
            $node->appendChild($dom->createElement('authorsOrcid', ""));
            $node->appendChild($dom->createElement('listAffi', ""));
            $node->appendChild($dom->createElement('listAuthors', ""));
        }

        // project Funding
        $project = Episciences_Paper_ProjectsManager::formatProjectsForview($this->_paperId);
        if (!empty($project)) {
            $node->appendChild($dom->createElement('funding', $project['funding']));
        } else {
            $node->appendChild($dom->createElement('funding', ""));
        }

        ($this->isAllowedToManageOrcidAuthor(true)) ? $node->appendChild($dom->createElement('rightOrcid', '1'))
            : $node->appendChild($dom->createElement('rightOrcid', "0"));

        $node->appendChild($dom->createElement('isOwner', $this->isOwner()));

        // fetch volume data
        if ($this->getVid()) {
            $oVolume = Episciences_VolumesManager::find($this->getVid());
            if ($oVolume instanceof Episciences_Volume) {
                $node->appendChild($dom->createElement('volumeName', $oVolume->getNameKey()));
                $oVolume->loadSettings();
            }
        }

        // fetch section data
        if ($this->getSid()) {
            $oSection = Episciences_SectionsManager::find($this->getSid());
            if ($oSection) {
                $node->appendChild($dom->createElement('sectionName', $oSection->getNameKey()));
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
        $dom->formatOutput = false;
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
            $xml->formatOutput = false;
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
        return 'oai:' . DOMAIN . ':' . Episciences_Review::getData($this->getRvid())['CODE'] . ':' . $this->getDocid();
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
        $res = Episciences_Tools::solrCurl($query);
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
     * @return string | null
     * @throws Zend_Exception
     */
    private function getTitleByLanguage(string $language): ?string
    {
        $title = $this->getMetadata('title');

        if (is_array($title)) {

            if (array_key_exists($language, $title)) {
                $title = $title[$language];
            } elseif ((int)array_key_first($title) === 0) {
                $title = array_shift($title);
            } else {
                $title = Zend_Registry::get('Zend_Translate')->translate('Document sans titre');
            }

        }

        return $title;
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

        return trim($result);
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
    public function setOtherVolumes(array $paper_volumes = []): void
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
     * @param array $options
     * @return array|string
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */

    public function manageNewVersionErrors(array $options = []): array|string
    {
        $viewHelper  = new Zend_View_Helper_Url();
        $translator  = Zend_Registry::get('Zend_Translate');
        $isFromCli   = Ccsd_Tools::isFromCli();
        $isEpiNotify = !empty($options['isEpiNotify']);
        $rvId        = $options['rvId'] ?? RVID;

        $docId       = $this->getDocid();
        $status      = $this->getStatus();
        $identifier  = $this->getIdentifier();
        $version     = $this->getVersion();
        $repoId      = $this->getRepoid();

        $id = $docId;
        if ($this->isObsolete()) {
            $this->loadVersionsIds();
            $versionIds = $this->getVersionsIds();
            $id = $versionIds[array_key_last($versionIds)];
        }

        $isMainSubmission = array_key_exists('isNewVersionOf', $options) && !$options['isNewVersionOf']; // fisrt submission

        // Base UI helpers
        $span    = $isFromCli ? '' : '<span class="fas fa-exclamation-circle"></span>';
        $warning = $span;
        $style   = 'btn btn-default btn-xs';

        $submittedMsg    = $translator
            ? $translator->translate("Vous êtes connecté avec un compte différent de celui ayant été utilisé pour soumettre ce document. Veuillez vous déconnecter et vous reconnecter avec le bon compte pour continuer.")
            : "You’re signed in with a different account than the one used to submit this document. Please sign out and log in with the correct account to continue.";
        $cannotChangeMsg = $translator
            ? $translator->translate('Vous ne pouvez pas le modifier.')
            : 'You can not change it.';

        $confirmHtml = '';
        $link        = '';

        if (!$isFromCli) {
            $warning .= ' ';
            $link = $isMainSubmission
                ? $viewHelper->url(['controller' => 'submit'])
                : $viewHelper->url(['controller' => 'paper', 'action' => 'view', 'id' => $id]);

            $exitLink  = '&nbsp;&nbsp;&nbsp;';
            $exitLink .= '<a class="' . $style . '" href="' . $link . '">';
            $exitLink .= '<span class="glyphicon glyphicon-remove-circle"></span>&nbsp;';
            $exitLink .= $translator ? $translator->translate('Annuler') : 'Cancel';
            $exitLink .= '</a>';

            $confirmHtml  = '<p style="margin:1em;">';
            $confirmHtml .= '<button class="' . $style . '" onclick="hideResultMessage();">';
            $confirmHtml .= '<span class="glyphicon glyphicon-ok-circle"></span>&nbsp;';
            $confirmHtml .= $translator ? $translator->translate('Remplacer') : 'Replace';
            $confirmHtml .= '</button>';
            $confirmHtml .= $exitLink;
            $confirmHtml .= '</p>';
        }

        $canReplace = false;
        $result     = [];

        // Permission check
        $hasPermission =
            Episciences_Auth::isLogged()
            && (
                $this->getUid() === Episciences_Auth::getUid()
                || (
                    (
                        Episciences_Auth::isSecretary()
                        || $this->getEditor(Episciences_Auth::getUid())
                        || $this->getCopyEditor(Episciences_Auth::getUid())
                    )
                    && !$isMainSubmission
                )
            );

        if ($isFromCli || $hasPermission) {

            $review   = Episciences_ReviewsManager::find($rvId);
            $question = $translator
                ? $translator->translate('Souhaitez-vous remplacer la version précédente ?')
                : 'Do you want to replace the previous version?';

            $result['message'] = $warning;

            // 1. Abandoned
            if ($status === self::STATUS_ABANDONED) {
                $result['message'] = $translator
                    ? $translator->translate(
                        "On ne peut pas re-proposer un article <strong>abandonné</strong>, " .
                        "Pour de plus amples renseignements, veuillez contacter le comité éditorial."
                    )
                    : "You can't re-propose an abandoned article. For more information please contact the editorial committee.";

                // 2. Can be replaced
            } elseif ($this->canBeReplaced()) {
                $msg  = $result['message'];
                $msg .= $isEpiNotify ? ' *** The previous version will be replaced ***' : $question;
                $msg .= $confirmHtml;

                $result['message']        = $msg;
                $result['oldPaperId']     = $this->getPaperid();
                $result['submissionDate'] = $this->getSubmission_date();
                $result['oldVid']         = $this->getVid();
                $result['oldSid']         = $this->getSid();
                $canReplace               = true;

                if ($isEpiNotify) {
                    $result[InboxNotifications::PAPER_CONTEXT] = $this;
                    $result['message']                        = '*** Update Version ***';
                }

                // 3. New submission in expected revision / final version / obsolete
            } elseif ( // cas où la personne tente de répondre à une demande de révision via l'interface de soumission prévue pour les soumissions principales
                $isMainSubmission
                && (
                    in_array($status, self::STATUS_WITH_EXPECTED_REVISION, true)
                    || in_array($status, self::All_STATUS_WAITING_FOR_FINAL_VERSION, true)
                    || $status === self::STATUS_OBSOLETE
                )
            ) {

                if ($isEpiNotify) {

                    if ($status === self::STATUS_OBSOLETE) {
                        $latestVersionId = $this->getLatestVersionId();
                        if ($latestVersionId) {
                            $result[InboxNotifications::PAPER_CONTEXT] =
                                Episciences_PapersManager::get($latestVersionId, false);
                        }
                    } else {
                        $result[InboxNotifications::PAPER_CONTEXT] = $this;
                    }

                    $result['message'] = '*** New version ***';

                } else {

                    $url    = $viewHelper->url(['controller' => 'paper', 'action' => 'view', 'id' => $this->getDocid()]);
                    $selfMsg  = $result['message'];
                    $selfMsg .= $translator
                        ? $translator->translate(
                            'Pour déposer votre nouvelle version, veuillez utiliser le lien figurant dans le courriel ' .
                            'qui vous a été envoyé par la revue, '
                        )
                        : "To submit your new version, please use the link in the email you received from the journal, ";

                    if (!$isFromCli) {
                        $selfMsg .= '<br>';
                        $selfMsg .= $translator ? $translator->translate('ou') : 'or';
                        $selfMsg .= '<span style="margin-right: 3px;"></span>';
                        $selfMsg .= '<a class="' . $style . '" href="' . $url . '">';
                        $selfMsg .= '<span class="fa-solid fa-link" style="margin-right: 3px;"></span>';
                        $selfMsg .= $translator ? $translator->translate("Cliquer ici") : "Click here";
                        $selfMsg .= '</a>';
                        $selfMsg .= '<span style="margin-left: 3px;"></span>';
                        $selfMsg .= $translator
                            ? $translator->translate('pour répondre à la demande de modification.')
                            : "to meet the demand of requested changes.";
                    }

                    $result['message'] = $selfMsg;
                }

                // 4. Being reviewed
            } elseif ($status === self::STATUS_BEING_REVIEWED) {
                $msg  = $result['message'];
                $msg .= $translator
                    ? $translator->translate('Cet article a déjà été soumis et il est en cours de relecture.')
                    : "This article has been submitted and is waiting for reviewing.";
                $msg .= $cannotChangeMsg;
                $result['message'] = $msg;

                // 5. Reviewed
            } elseif ($status === self::STATUS_REVIEWED) {
                $msg  = $result['message'];
                $msg .= $translator
                    ? $translator->translate("Cet article est en cours d'évaluation.")
                    : "This article is under review.";
                $msg .= $cannotChangeMsg;
                $result['message'] = $msg;

                // 6. Accepted
            } elseif ($status === self::STATUS_ACCEPTED) {
                $msg  = $result['message'];
                $msg .= $translator
                    ? $translator->translate('Cet article a été accepté.')
                    : "This article has been accepted.";
                $msg .= $cannotChangeMsg;
                $result['message'] = $msg;

                // 7. Refused
            } elseif ($status === self::STATUS_REFUSED) {

                if ($review->getSetting(Episciences_Review::SETTING_CAN_RESUBMIT_REFUSED_PAPER)) {

                    $msg  = $result['message'];
                    $msg .= $translator
                        ? $translator->translate(
                            'Cet article a déjà été soumis et refusé. ' .
                            'Avez-vous apporté des modifications majeures au document ?'
                        )
                        : "This article has already been submitted and refused. Have you made any major changes to the document?";
                    $msg .= $isFromCli ? '' : $confirmHtml;

                    $result['message']    = $msg;
                    $result['oldPaperId'] = $this->getPaperid();
                    $result['oldVid']     = $this->getVid();
                    $result['oldSid']     = $this->getSid();
                    $canReplace           = true;

                    if ($isEpiNotify) {
                        $result[InboxNotifications::PAPER_CONTEXT] = $this;
                        $result['message'] =
                            '*** Previous paper has been refused: new submission ***';
                    }

                } else {

                    $msg  = $isFromCli ? '' : $warning;
                    $msg .= $cannotChangeMsg . ' ';
                    $msg .= $translator
                        ? $translator->translate(
                            'Cet article a déjà été soumis et refusé, ' .
                            'merci de contacter le comité editorial.'
                        )
                        : "This article has already been submitted and refused, please contact the editorial committee.";

                    $result['message'] = $msg;
                }

                // 8. Version already exists
            } elseif (isset($options['version']) && $options['version'] <= $this->getVersion()) {

                $selfMsg = $translator ? $translator->translate('Cette version') : 'This version';
                $selfMsg .= sprintf(
                    ' [%sv%s%s] ',
                    $isFromCli ? '' : '<strong>',
                    $this->getVersion(),
                    $isFromCli ? '' : '</strong>'
                );
                $selfMsg .= $translator
                    ? $translator->translate('du document existe déjà dans la revue.')
                    : "of the document already exists in journal.";

                if (!$isFromCli) {
                    $selfMsg .= '&nbsp;';
                    $selfMsg .= '<a class="btn btn-default btn-sm" href="' . $link . '">';
                    $selfMsg .= '<span class="fas fa-redo" style="margin-right: 5px;"></span>';
                    $selfMsg .= $translator ? $translator->translate('Retour') : "Back";
                    $selfMsg .= '</a>';
                }

                // Note: original code translates the whole message again
                $result['message'] = $warning . ' ' . ($translator ? $translator->translate($selfMsg) : $selfMsg);

                // 9. Other statuses
            } else {
                $result['message'] = $translator
                    ? $translator->translate(
                        "Le processus de publication de cet article est en cours, " .
                        "vous ne pourrez donc pas le remplacer."
                    )
                    : "The publication process of this article is in progress, so you will not be able to replace it.";
            }

            $result['oldDocId']      = (int) $docId;
            $result['oldPaperStatus'] = (int) $status;

        } else {
            // Not author, no extra info about status
            $message  = $span;
            $message .= $translator ? $translator->translate('Erreur') : "Erreur";
            $message .= $translator ? $translator->translate(': ') : ':';
            $message .= $submittedMsg;

            $result['message'] = $message;
        }

        if (!$isFromCli) {
            $result['message'] .= '</span>';
        }

        $result['canBeReplaced'] = $canReplace;
        $result['oldIdentifier'] = $identifier;
        $result['oldVersion']    = (float) $version;
        $result['oldRepoId']     = $repoId;

        try {
            $jResult = $isFromCli ? $result : json_encode($result, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $jResult = '';
            trigger_error($e->getMessage());
        }

        return $jResult;
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
     * @param bool $active
     * @param bool $getCASdata
     * @return Episciences_CopyEditor[]
     * @throws Zend_Db_Statement_Exception
     */
    public function getCopyEditors(bool $active = true, bool $getCASdata = false): array
    {
        if (empty($this->_copyEditors) || $getCASdata) {
            $copyEditors = Episciences_PapersManager::getCopyEditors($this->getDocid(), $active, $getCASdata);
            $this->_copyEditors = $copyEditors;
        }

        return $this->_copyEditors;
    }

    /**
     * @param array $values
     * @return array
     * @throws Zend_Exception
     */
    public function updatePaper(array $values): array
    {
        $status = $this->getStatus(); // previous status
        try {
            $update = [];
            $update['code'] = 0;
            $translator = Zend_Registry::get('Zend_Translate');
            $message = $translator->translate("Aucune modification n'a été enregistrée");
            // current submission
            $paper = new Episciences_Paper([
                'identifier' => $values['search_doc']['docId'],
                'version' => (float)$values['search_doc']['version'],
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
                    $this->canBeReplaced() ||
                    ($status === self::STATUS_REFUSED && Episciences_PapersManager::renameIdentifier($this->getIdentifier(), $this->getIdentifier() . '-REFUSED'))) {

                    if (isset($values['isEpiNotify']) && $values['isEpiNotify']) {
                        return ['code' => 1, 'message' => 'Okay for the update...'];
                    }

                    $submit = new Episciences_Submit();
                    $result = $submit->saveDoc($values);
                    if ($result['code'] === 0) {
                        $message = $result['message'];
                    } else {
                        $message = $translator->translate("La nouvelle version de votre article a bien été enregistrée.");
                    }

                    $update['code'] = 1;
                    // Pass the docId to allow redirect to the detail page.
                    if (isset($result['docId'])) {
                        $update['docId'] = $result['docId'];
                    }
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
    public function getAllTitles(): ?array
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

    public function getDataDescriptors(): array|null
    {
        return $this->_data_descriptors;
    }

    public function setDataDescriptors(array $data_descriptors = null): self
    {
        $this->_data_descriptors = $data_descriptors;
        return $this;
    }

    public function getLatestDataDescriptor(): null|\Episciences\Paper\DataDescriptor
    {
        $allDd = $this->getDataDescriptors();

        if (empty($allDd)) {
            return null;
        }

        return $allDd[array_key_first($allDd)];

    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function ratingRefreshPaperStatus(): void
    {
        $oldStatus = $this->getStatus();

        $ignoredStatus = [
            self::STATUS_OBSOLETE,
            self::STATUS_REFUSED,
            self::STATUS_WAITING_FOR_MINOR_REVISION,
            self::STATUS_WAITING_FOR_MAJOR_REVISION
        ];

        $ignoredStatus = array_merge($ignoredStatus, self::$_canBeAssignedDOI);

        if (!in_array($oldStatus, $ignoredStatus, true)) {

            // new paper status
            $status = ($this->isReviewed()) ? self::STATUS_REVIEWED : self::STATUS_BEING_REVIEWED;

            if ($oldStatus !== $status) {

                $this->setStatus($status);
                $this->save();
                // log new paper status
                $this->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => $status]);

            }

        }
    }

    public function isAcceptedSubmission(): bool
    {
        return in_array($this->getStatus(), self::ACCEPTED_SUBMISSIONS, true);
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
     * Returns the last known paper's status  of the last action to stop the publishing process
     * @throws Zend_Exception
     *
     * //loadLastAbandonActionDetail // getLastStatusAtTimeOfAbandon
     */
    public function loadLastAbandonActionDetail() : int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $this->loadHistoryQuery('DETAIL')
            ->where('ACTION = ?', Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS)
            ->order('DOCID DESC')
            ->order('DATE DESC')
            ->order('LOGID DESC')
            ->limit(1);

        $jsonDetail = $db?->fetchOne($sql);

        // Lors de la reprise de la publication d'un article, une exception est levée si aucune trace de l'abandon du processus de publication précédemment effectué n'est trouvée
        if (!$jsonDetail){
            throw new Zend_Exception(sprintf('no sign of abandonment of the publication process [%s]', Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS));
        }

        try {
            $detail = json_decode($jsonDetail, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
        }

        return isset($detail['lastStatus']) ? (int) $detail['lastStatus'] : self::STATUS_SUBMITTED;
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
     * @param int $rvId
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getInvitations($status = null, bool $sorted = false, int $rvId = RVID): array
    {
        if (!isset($this->_invitations)) {
            $invitations = Episciences_PapersManager::getInvitations($this->getDocid(), $status, $sorted, $rvId);
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
     * @param string|null $locale
     * @return string
     * @throws Zend_Exception
     */
    public function formatAuthorsMetadata(string $locale = null): string
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
    public function getPublicationYear(string $yearFormat = 'Y'): string
    {
        $year = date($yearFormat);
        if ($this->isPublished()) {
            $date = DateTime::createFromFormat("Y-m-d H:i:s", $this->getPublication_date());
            $year = $date->format($yearFormat);
        }
        return $year;
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

    public function getDatasetsFromEnrichment()
    {

        $notFormatedDatasets = $this->getDatasets();
        $formatedDatasets = [];
        $sourcesList = [];
        $iSourceList = 1;
        foreach ($notFormatedDatasets as $unorderedDatasets) {
            /** @var Episciences_Paper_Dataset $unorderedDatasets */
            $typeLd = '';
            if (((string)$unorderedDatasets->getSourceId() === Episciences_Repositories::EPI_USER_ID
                    && $unorderedDatasets->getCode() !== null && $unorderedDatasets->getCode() !== "swhidId_s")
                || ((string)$unorderedDatasets->getSourceId() === Episciences_Repositories::SCHOLEXPLORER_ID)) {
                $typeLd = $unorderedDatasets->getCode();
            } else {
                $typeLd = $unorderedDatasets->getName();
            }
            $sourceLabel = $unorderedDatasets->getSourceLabel($unorderedDatasets->getSourceId());
            if (!array_key_exists($sourceLabel, $sourcesList)) {
                $sourcesList[$sourceLabel] = $iSourceList;
                $iSourceList++;
            }
            switch ($typeLd) {
                case 'publication' :
                case 'dataset' :
                case 'software':
                    if ($unorderedDatasets->getRelationship() !== null) {
                        $formatedDatasets[$typeLd][ucfirst($unorderedDatasets->getRelationship())][$unorderedDatasets->getSourceId()][] = $unorderedDatasets;
                    } else {
                        $formatedDatasets[$typeLd]['Other'][$unorderedDatasets->getSourceId()][] = $unorderedDatasets;
                    }
                    break;
                default :
                    if ($unorderedDatasets->getRelationship() !== null) {
                        $formatedDatasets["publication"][ucfirst($unorderedDatasets->getRelationship())][$unorderedDatasets->getSourceId()][] = $unorderedDatasets;
                    } else {
                        $formatedDatasets["publication"]['Other'][$unorderedDatasets->getSourceId()][] = $unorderedDatasets;
                    }
                    break;
            }
        }
        if (isset($formatedDatasets['publication'])) {
            $formatedDatasets['publication'] = Episciences_Paper_DatasetsManager::putUserLdFirst($formatedDatasets['publication']);
        }
        if (isset($formatedDatasets['dataset'])) {
            $formatedDatasets['dataset'] = Episciences_Paper_DatasetsManager::putUserLdFirst($formatedDatasets['dataset']);
        }
        if (isset($formatedDatasets['software'])) {
            $formatedDatasets['software'] = Episciences_Paper_DatasetsManager::putUserLdFirst($formatedDatasets['software']);
        }
        if (!empty($formatedDatasets)) {
            $formatedDatasets["listSources"] = $sourcesList;
        }
        return $formatedDatasets;
    }

    public function getDatasetByValue(string $value): ?Episciences_Paper_Dataset
    {

        return Episciences_Paper_DatasetsManager::findByValue($this->_docId, $value);

    }

    /**
     * Safely parse a date string to DateTime object
     *
     * @param string|null $date Date string in 'Y-m-d H:i:s' format
     * @return DateTime|null DateTime object or null if parsing fails or input is null
     */
    private function parseDateSafely(?string $date): ?DateTime
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date);
            if ($dateTime === false) {
                // Try alternative format without time
                $dateTime = DateTime::createFromFormat('Y-m-d', $date);
            }
            return $dateTime !== false ? $dateTime : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if paper is explicitly marked as imported via flag
     *
     * @return bool True if flag is 'imported'
     */
    private function isExplicitlyImported(): bool
    {
        return $this->getFlag() === 'imported';
    }

    /**
     * Check if publication date is before or equal to submission date (data inconsistency)
     *
     * @return bool True if publication_date <= submission_date
     */
    private function isPublicationBeforeSubmission(): bool
    {
        $publicationDate = $this->parseDateSafely($this->getPublication_date());
        $submissionDate = $this->parseDateSafely($this->getSubmission_date());

        if ($publicationDate === null || $submissionDate === null) {
            return false;
        }

        return $publicationDate <= $submissionDate;
    }

    /**
     * Check if submission or publication year is before 2013 (legacy data)
     *
     * @return bool True if either date is before 2013
     */
    private function isBeforeYear2013(): bool
    {
        $publicationDate = $this->parseDateSafely($this->getPublication_date());
        $submissionDate = $this->parseDateSafely($this->getSubmission_date());

        if ($submissionDate !== null && (int)$submissionDate->format('Y') < 2013) {
            return true;
        }

        if ($publicationDate !== null && (int)$publicationDate->format('Y') < 2013) {
            return true;
        }

        return false;
    }

    /**
     * Check for date inconsistencies with the paper creation date (WHEN) for published papers
     *
     * @return bool True if published paper has date inconsistencies
     */
    private function hasDateInconsistencies(): bool
    {
        if ($this->getStatus() !== self::STATUS_PUBLISHED) {
            return false;
        }

        $whenDate = $this->parseDateSafely($this->getWhen());
        if ($whenDate === null) {
            return false;
        }

        $submissionDate = $this->parseDateSafely($this->getSubmission_date());
        $publicationDate = $this->parseDateSafely($this->getPublication_date());

        // Submission date after creation date (inconsistent)
        if ($submissionDate !== null && $submissionDate > $whenDate) {
            return true;
        }

        // Publication date before creation date (inconsistent)
        if ($publicationDate !== null && $publicationDate < $whenDate) {
            return true;
        }

        return false;
    }

    /**
     * Determine if this paper was imported from another system
     *
     * A paper is considered imported if:
     * 1. It has the 'imported' flag explicitly set, OR
     * 2. It exhibits data inconsistencies characteristic of imported legacy data:
     *    - Publication date is before or equal to submission date
     *    - Either date is from before 2013 (legacy data era)
     *    - For published papers: dates are inconsistent with the paper creation date
     *
     * @return bool True if paper is imported
     */
    public function isImported(): bool
    {
        return $this->isExplicitlyImported()
            || $this->isPublicationBeforeSubmission()
            || $this->isBeforeYear2013()
            || $this->hasDateInconsistencies();
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

    /**
     * @param bool $onlyConfirmed
     * @param bool $sortedByAnswer
     * @return array [Episciences_Paper_Conflict]
     */
    public function getConflicts(bool $onlyConfirmed = false, bool $sortedByAnswer = false): array
    {

        if ($this->_conflicts) {
            $this->loadConflicts();
        }


        if ($onlyConfirmed) {

            $this->_conflicts = array_filter($this->_conflicts, static function ($oConflict) {
                /** @var Episciences_Paper_Conflict $oConflict */
                return $oConflict->getAnswer() === Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'];
            });
        }

        if ($sortedByAnswer) {
            $result = [
                Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'] => [],
                Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'] => []
            ];

            /**
             * @var  $index int
             * @var  $oConflict Episciences_Paper_Conflict
             */
            foreach ($this->_conflicts as $oConflict) {

                if ($oConflict->getAnswer() === Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']) {
                    $result[Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']][] = $oConflict;
                } elseif ($oConflict->getAnswer() === Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']) {
                    $result[Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']][] = $oConflict;
                }

            }

            return $result;
        }

        return $this->_conflicts;
    }

    /**
     * @param array $conflicts
     * @return Episciences_Paper
     */
    public function setConflicts(array $conflicts): self
    {
        $this->_conflicts = $conflicts;
        return $this;
    }

    public function isExcluded(): bool
    {
        if (in_array($this->getStatus(), self::DO_NOT_SORT_THIS_KIND_OF_PAPERS, true)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isReportsVisibleToAuthor(): bool
    {
        return $this->isOwner() && !in_array($this->getStatus(), [self::STATUS_SUBMITTED, self::STATUS_OK_FOR_REVIEWING, self::STATUS_BEING_REVIEWED, self::STATUS_REVIEWED, self::STATUS_WAITING_FOR_COMMENTS]);


    }

    public function isOwner(): bool
    {
        return Episciences_Auth::getUid() === $this->getUid() || Episciences_Auth::getOriginalIdentity() === $this->getUid();
    }

    public function isAlreadyAcceptedWaitingForAuthorFinalVersion(): bool
    {
        return $this->getStatus() === self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION;

    }

    /**
     * @return void
     */
    public function setRevisionDeadline(): void
    {

        $revisionDeadline = null;

        $revision_requests = Episciences_CommentsManager::getRevisionRequests($this->getDocid());

        if (!empty($revision_requests) && !array_key_exists('replies', current($revision_requests))) {
            $currentDemand = array_shift($revision_requests);
            $revisionDeadline = $currentDemand['DEADLINE'];
        }

        $this->_revisionDeadline = $revisionDeadline;
    }

    /**
     * has
     * @return bool
     */
    public function isFromZenodo(): bool
    {
        return (($this->getRepoid() === (int)Episciences_Repositories::ZENODO_REPO_ID) || ($this->isTmp() && $this->getConcept_identifier()));
    }

    public function isFormattingCompleted(): bool
    {
        return in_array($this->getStatus(), [self::STATUS_CE_REVIEW_FORMATTING_DEPOSED, self::STATUS_CE_AUTHOR_FORMATTING_DEPOSED, self::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION], true);
    }

    /**
     * @return array [Episciences_Paper_Authors]
     * @throws JsonException
     */
    public function getAuthors(): array
    {
        $this->_authors = Episciences_Paper_AuthorsManager::getArrayAuthorsAffi($this->getPaperid());

        if (!is_array($this->_authors)) {
            throw new InvalidArgumentException(sprintf("Paper docid: %d getAuthors() expects an array", $this->getDocid()));
        }

        return $this->_authors;
    }

    public function getAuthorsWithAffiNumeric(): array
    {
        $this->_authors = Episciences_Paper_AuthorsManager::filterAuthorsAndAffiNumeric($this->getPaperid());

        return $this->_authors;
    }

    /**
     * @return string Episciences_Paper_Licence
     */
    public function getLicence(): string
    {
        $this->_licence = Episciences_Paper_LicenceManager::getLicenceByDocId($this->getDocid());
        return $this->_licence;
    }

    /**
     * @return array [Episciences_Paper_Projects]
     * @throws JsonException
     */
    public function getFundings(): array
    {
        if (!$this->getPaperid()) {
            return [];
        }

        $this->_fundings = Episciences_Paper_ProjectsManager::getProjectWithDuplicateRemoved($this->getPaperid());

        return $this->_fundings;
    }

    /**
     * @return array [Episciences_Paper_Dataset]
     */

    public function getLinkedData(): array
    {
        if (!$this->getDocid()) {
            return [];
        }
        $this->_linkedData = Episciences_Paper_DatasetsManager::getByDocId($this->getDocid());
        return $this->_linkedData;
    }


    public function getLinkedDataByRelation(string $relation = 'isDocumentedBy'): ?\Episciences_Paper_Dataset
    {
        return Episciences_Paper_DatasetsManager::findByrelation($this->getDocid(), $relation);
    }

    /**
     * @throws Zend_Exception
     */
    public function isContributorCanShareArXivPaperPwd(): bool
    {

        return $this->isOwner() && ($this->isOptionalPaperPwd() || $this->isRequiredPaperPwd());

    }

    /**
     * @return bool
     * @throws Zend_Exception
     */
    public function isOptionalPaperPwd(): bool
    {

        $journalSettings = Zend_Registry::get('reviewSettings');

        return
            (
                isset($journalSettings[Episciences_Review::SETTING_ARXIV_PAPER_PASSWORD]) &&
                (int)$journalSettings[Episciences_Review::SETTING_ARXIV_PAPER_PASSWORD] === 1
            ) &&
            empty($this->getPassword()) &&
            $this->getRepoid() === (int)Episciences_Repositories::ARXIV_REPO_ID;
    }

    /**
     * @return bool
     * @throws Zend_Exception
     */

    public function isRequiredPaperPwd(): bool
    {

        $journalSettings = Zend_Registry::get('reviewSettings');

        return
            (
                isset($journalSettings[Episciences_Review::SETTING_ARXIV_PAPER_PASSWORD]) &&
                (int)$journalSettings[Episciences_Review::SETTING_ARXIV_PAPER_PASSWORD] === 2
            ) &&
            empty($this->getPassword()) &&
            $this->getRepoid() === (int)Episciences_Repositories::ARXIV_REPO_ID;
    }

    /**
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getCoAuthors(): array
    {
        return $this->_coAuthors = Episciences_PapersManager::getCoAuthors($this->getDocid());
    }

    public function isCoauthor(): bool
    {
        try {
            $coAuthors = $this->getCoAuthors();
        } catch (Zend_Db_Statement_Exception $e) {
            $coAuthors = [];
            Episciences_View_Helper_Log::log($e->getMessage(), Psr\Log\LogLevel::CRITICAL);
        }

        return
            isset($coAuthors[Episciences_Auth::getUid()]) ||
            (Episciences_Auth::getOriginalIdentity() && isset($coAuthors[Episciences_Auth::getOriginalIdentity()?->getUid()]));
    }


    public function isEditableVersion(): bool
    {
        return in_array($this->getStatus(), self::EDITABLE_VERSION_STATUS, true);

    }

    public function getBibRef(string $rvCode = null): array
    {

        if (!$rvCode && !Ccsd_Tools::isFromCli()) {
            $rvCode = RVCODE;
        }

        if (
            (isset(EPISCIENCES_BIBLIOREF['ENABLE']) && EPISCIENCES_BIBLIOREF['ENABLE']) &&
            $this->getDocid() &&
            (
                $this->getStatus() === self::STATUS_CE_READY_TO_PUBLISH ||
                $this->getStatus() === self::STATUS_PUBLISHED
            )
        ) {
            $urlPdf = SERVER_PROTOCOL . '://' . $rvCode . '.' . DOMAIN . '/' . $this->getDocid() . '/pdf';
            return Episciences_BibliographicalsReferencesTools::getBibRefFromApi($urlPdf);
        }
        return [];
    }

    public function getTypeWithKey(string $key = null): string
    {

        $strType = '';

        if ($key) {
            return $this->_type[$key] ?? $strType;
        }

        if (isset($this->_type[self::TITLE_TYPE])) {
            $strType = $this->_type[self::TITLE_TYPE];
        } elseif (isset($this->_type[self::TYPE_TYPE])) {
            $strType = $this->_type[self::TYPE_TYPE];
        } elseif (isset($this->_type[self::TYPE_SUBTYPE])) {
            $strType = $this->_type[self::TYPE_SUBTYPE];
        }

        return $strType;

    }

    /**
     * @param bool $strict : [true] only if the document has already been assigned (excepted for secretary and owner)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */

    public function isAllowedToManageOrcidAuthor(bool $strict = false): bool
    {

        if ($this->isOwner() || Episciences_Auth::isSecretary()) {
            return true;
        }

        if ($strict) {
            $paper = Episciences_PapersManager::get($this->getLatestVersionId(), false, RVID);
            return $paper->isEditor(Episciences_Auth::getUid()) || $paper->getCopyEditor(Episciences_Auth::getUid());

        }

        return Episciences_Auth::isCopyEditor() || Episciences_Auth::isEditor();

    }

    public function isLatestVersion(): bool
    {
        return $this->getDocid() === (int)$this->getLatestVersionId();
    }

    /**
     * @return string | null
     */
    public function getGraphical_abstract($docId): ?string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(`DOCUMENT`, " . $db->quote(self::JSON_PATH_ABS_FILE) . ")) FROM " . T_PAPERS . " WHERE DOCID = ?", [$docId]);
        try {
            foreach ($query->fetch() as $val) {
                if (!is_null($val)) {
                    return trim($val);
                }
            }
        } catch (Zend_Db_Statement_Exception $e) {
            return null;
        }
        return null;
    }

    public function updateDocument(): Episciences_Paper
    {
        return $this;

    }

    public function displayXml(string $output): bool
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $loadResult = $dom->loadXML($output);

        $dom->encoding = 'utf-8';
        $dom->xmlVersion = '1.0';

        if ($loadResult) {
            $output = $dom->saveXML();
            echo $output;
            return true;
        }

        echo '<error>Error loading XML source. Please report to Journal Support.</error>';
        trigger_error('XML Fail in export: ' . $output, E_USER_WARNING);
        return false;
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
                $citation .= ', ' . $volume->getName($locale);
            }
        }


        return $citation;
    }

    /**
     *
     * get acceptance date form paper log
     * @return string|null
     */
    public function getAcceptanceDate(): ?string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $this->loadHistoryByStatuesQuery([self::STATUS_TMP_VERSION_ACCEPTED, self::STATUS_ACCEPTED], 'date');
        $sql->limit(1);
        $result = $db?->fetchOne($sql);
        return $result ?: null;
    }

    /**
     * Get an array of abstracts
     * @return array
     */
    public function getAbstractsCleaned()
    {
        $abstracts = [];
        foreach ($this->getAllAbstracts() as $locale => $abstract) {
            if (is_array($abstract)) {
                $abstractLang = array_key_first($abstract);
                $abstractText = array_shift($abstract);
                $abstractText = $this->cleanAbstract($abstractText);
                if ($abstractText !== 'International audience') {
                    $abstracts[][$abstractLang] = $abstractText;
                }
            } else {
                $abstract = $this->cleanAbstract($abstract);
                if ($abstract !== 'International audience') {
                    $abstracts[$locale] = $abstract;
                }
            }
        }
        return $abstracts;
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

        if (!is_array($this->_metadata['description'])) {
            throw new InvalidArgumentException(sprintf("Paper docid: %d getAllAbstracts() expects an array", $this->getDocid()));
        }

        return $this->_metadata['description'];
    }

    /**
     * @param string $abstract
     * @return string
     */
    private function cleanAbstract(string $abstract): string
    {
        return trim(preg_replace("/\r|\n/", " ", $abstract));
    }

    public function forceType(): self
    {
        if ($this->isPublished()) {
            $type = $this->getType();
            if (empty($type) || (isset($type[self::TITLE_TYPE]) && $this->isPreprint())) {
                $this->setType([self::TITLE_TYPE => self::ARTICLE_TYPE_TITLE]);
            }

        }

        return $this;

    }

    public function getClassifications(bool $isSerialized = false): array
    {
        $classificationsList = Episciences_Paper_ClassificationsManager::getClassificationByDocId($this->getDocid());
        $classificationCollection = [];

        foreach ($classificationsList as $classification) {
            $classificationName = $classification['classification_name'];
            if ($classificationName !== msc2020::$classificationName || $classificationName !== jel::$classificationName) {
                $current = ($classificationName === msc2020::$classificationName) ? new msc2020($classification) : new jel($classification);
                $classificationCollection[$classificationName][] = !$isSerialized ? $current : $current->jsonSerialize(false);
            }
        }

        return $classificationCollection;

    }


    public function getDocumentPrivate(): ?array
    {
        return $this->_document_private;
    }


    public function setDocumentPrivate(string $privateDocument = null): self
    {
        if ($privateDocument) {
            try {
                $privateDocument = json_decode($privateDocument, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                trigger_error($e->getMessage());
            }

        }

        $this->_document = $privateDocument;
        return $this;
    }

    private function getJsonV2(): ?string
    {
        try {
            return $this->toJson();
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }
        return null;
    }

    private function processProjects(array &$data): void
    {
        $projectsInfo = Episciences_Paper_ProjectsManager::getProjectsByPaperId($this->getPaperid());
        foreach ($projectsInfo as $pInfo) {
            if (isset($pInfo['funding'])) {
                try {
                    $pInfoToArray = json_decode($pInfo['funding'], true, 512, JSON_THROW_ON_ERROR);
                    foreach ($pInfoToArray as $index => $values) {
                        if (isset($data[Episciences_Paper_XmlExportManager::BODY_KEY][Episciences_Paper_XmlExportManager::JOURNAL_KEY][Episciences_Paper_XmlExportManager::JOURNAL_ARTICLE_KEY]['program'][$index])) {
                            $currentProgram = &$data[Episciences_Paper_XmlExportManager::BODY_KEY][Episciences_Paper_XmlExportManager::JOURNAL_KEY][Episciences_Paper_XmlExportManager::JOURNAL_ARTICLE_KEY]['program'][$index];
                            if (isset($currentProgram['@name'], $values['projectTitle']) && $currentProgram['@name'] === 'fundref') {
                                $currentProgram['assertion']['assertion'][] = ['@name' => 'project_title', '#' => $values['projectTitle']];
                            }
                        }

                    }
                } catch (JsonException $e) {
                    trigger_error($e->getMessage());
                }
            }
        }

    }


    private function loadConflicts(): void
    {

        $allConflicts = Episciences_Paper_ConflictsManager::findByPaperId($this->getPaperid(), $this->getRvid());
        $this->_conflicts = $allConflicts;

    }

    public function loadDataDescriptors(bool $force = false): void
    {
        if ($this->isDataSetOrSoftware()) {

            if ($force || !$this->_data_descriptors) {
                $this->setDataDescriptors(DataDescriptorManager::getByDocId($this->getDocid()));
            }
        }
    }

    public function isDataSetOrSoftware(): bool
    {
        return $this->isDataset() || $this->isSoftware();
    }

    public function isSoftware(): bool
    {
        return $this->getType()[self::TITLE_TYPE] === self::SOFTWARE_TYPE_TITLE;
    }

    public function isDataset(): bool
    {
        return
            Episciences_Repositories::isDataverse($this->getRepoid()) ||
            $this->getType()[self::TITLE_TYPE] === self::DATASET_TYPE_TITLE;
    }

    public function isPreprint(): bool
    {
        return in_array($this->_type[self::TITLE_TYPE], self::PREPRINT_TYPES, true);
    }


    public function getOwner(): ?Episciences_User
    {

        $owner = new Episciences_User();
        try {
            $owner->find($this->getUid());
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }

        if (!$owner->getUid()) {
            return null;
        }

        return $owner;

    }

    /**
     * returns the repository url to the main paper's file
     * @return string|null
     */

    public function getMainPaperUrl(): ?string
    {
        if ($this->isTmp()) {
            return null;
        }

        if ($this->isDataSetOrSoftware()) {
            return $this->getDataDescriptorUrl();
        }

        if ($this->hasHook) {

            $files = $this->getFiles();
            /** @var Episciences_Paper_File $file */

            foreach ($files as $file) {

                if (($file->getFileType() === 'pdf')) {
                    return Episciences_Repositories::isDataverse($this->getRepoid()) ? $file->getDownloadLike() : $file->getSelfLink();
                }
            }
        } else {
            return $this->getPaperUrl();
        }

        return null;
    }


    /**
     * returns the repository DD url file
     * @return string|null
     */

    public function getDataDescriptorUrl(): ?string
    {
        if (!$this->isDataSetOrSoftware()) {
            return null;
        }

        $linkedData = $this->getLinkedDataByRelation();

        if ($linkedData && strtoupper($linkedData->getName()) === Episciences_Repositories::HAL_LABEL) {
            return Episciences_Repositories::getPaperUrl(Episciences_Repositories::HAL_REPO_ID, $linkedData->getValue());
        }

        return null;

    }

    public function canBeReplaced(): bool
    {
        return in_array($this->getStatus(), [self::STATUS_SUBMITTED, self::STATUS_OK_FOR_REVIEWING, self::STATUS_CE_READY_TO_PUBLISH, self::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION], true);
    }

    private function combineDocUrlLabel(): string
    {

        $docUrlLabel = 'Voir la page du document sur';

        if ($this->isDataSetOrSoftware()) {
            if ($this->isSoftware()) {
                $docUrlLabel = 'Voir le logiciel sur';
            } else {
                $docUrlLabel = 'Voir le jeu de données sur';
            }
        }

        $docUrlLabel = Ccsd_Tools::translate($docUrlLabel);
        $docUrlLabel .= ' ';
        $docUrlLabel .= Episciences_Repositories::getLabel($this->getRepoid());
        return $docUrlLabel;

    }

    public function getStatusLabelFromDictionary(): string
    {
        return self::STATUS_DICTIONARY[$this->getStatus()] ?? 'unknown';
    }

    private function postPaperStatus(): void
    {

        $journal = Episciences_ReviewsManager::find($this->getRvid());
        // This parameter must be activated directly in the database
        $isPostStatusEnabled = $journal->getSetting(Episciences_Review::SETTING_POST_PAPER_STATUS) === '1';

        if (!$isPostStatusEnabled){
            return;
        }

        $data = [
            'docid' => $this->getDocid(),
            'paperid' => $this->getPaperid(),
            'version' => $this->getVersion(),
            'status' => $this->getStatus(),
            'statusLabel' => $this->getStatusLabelFromDictionary()
        ];

        $queue = new QueueMessage([
            'rvcode' => $journal->getCode(),
            'message' => $data,
            'type' => QueueMessageManager::TYPE_STATUS_CHANGED
        ]);

        $queue->send();
    }
}





