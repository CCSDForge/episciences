<?php

/**
 * Class Episciences_Reviewer
 */
class Episciences_Reviewer extends Episciences_User
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_UNINVITED = 'uninvited';
    public const STATUS_INACTIVE = 'inactive';

    protected $_when;
    protected $_status;

    private $_assignedPapers = [];
    private $_assignments = [];
    private $_ratings = [];
    private $_reviewings = [];
    private $_comments = [];
    private $_invitations = [];


    /**
     * Episciences_Reviewer constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {

        parent::__construct($options);

        if (is_array($options)) {
            $this->setOptions($options);
        }
    }


    /**
     * @return array
     */
    public function toArray()
    {
        $result = parent::toArray();
        $result['aliases'] = $this->getAliases();
        $result['status'] = $this->getStatus();
        $result['when'] = $this->getWhen();

        // Papiers assignés au relecteur
        if (!empty($this->_assignedPapers)) {
            $papers = $this->_assignedPapers;
            foreach ($papers as &$paper) {
                $paper = $paper->toArray();
            }
            unset($paper);
            $result['assignedPapers'] = $papers;
        }

        // Evaluations du relecteur
        if (!empty($this->_ratings)) {
            $ratings = $this->_ratings;
            foreach ($ratings as &$rating) {
                $rating = $rating->toArray();
            }
            $result['ratings'] = $ratings;
        }

        return $result;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function setStatus($_status)
    {
        $this->_status = $_status;
        return $this;
    }


    public function getWhen()
    {
        return $this->_when;
    }

    public function setWhen($_when)
    {
        $this->_when = $_when;
        return $this;
    }


    /**
     * @param int $docId
     * @return mixed|null
     */
    public function getInvitation($docId, $rvId = RVID)
    {
        if (empty($this->_invitations)) {
            $this->loadInvitations($rvId);
        }
        return $this->_invitations[$docId] ?? null;
    }


    public function loadAssignments()
    {
        if (!$this->getUid()) {
            return;
        }

        $params = ['item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'uid' => $this->getUid(),
            'status' => [
                Episciences_User_Assignment::STATUS_ACTIVE,
                Episciences_User_Assignment::STATUS_PENDING]];

        $assignments = Episciences_User_AssignmentsManager::getList($params);
        $this->setAssignments($assignments);
    }


    public function getAssignment($docId)
    {
        return $this->_assignments[$docId];
    }


    /**
     * @param int $docid
     * @return bool|Episciences_Rating_Report
     */
    public function getReport($docid)
    {
        $sql = $this->_db->select()
            ->from(['r' => T_REVIEWER_REPORTS])
            ->where('DOCID = ?', $docid)
            ->where('UID = ?', $this->getUid());

        $row = $this->_db->fetchRow($sql);
        if ($row) {
            return new Episciences_Rating_Report($row);
        }

        return false;

    }

    /**
     * @param int $rvid
     * @return array
     */
    public function getReviewingsByRvid($rvid)
    {
        $result = [];
        foreach ($this->getReviewings() as $oReviewing) {
            if ($oReviewing->getRvid() == $rvid) {
                $result[] = $oReviewing;
            }
        }
        return $result;
    }


    public function getReviewings()
    {
        return $this->_reviewings;
    }


    public function setReviewings($reviewings)
    {
        $this->_reviewings = $reviewings;
    }

    public function hasAssignments()
    {
        return (!empty($this->getAssignments()));
    }


    public function getAssignments()
    {
        return $this->_assignments;
    }

    public function setAssignments(array $assignments)
    {
        $this->_assignments = $assignments;
        return $this;
    }

    public function assign($docId, $params = [])
    {
        $params = [
            'rvid' => $params['rvid'] ?? RVID,
            'itemid' => $docId,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'deadline' => $params['deadline'] ?? null,
            'status' => $params['status'] ?? Episciences_User_Assignment::STATUS_ACTIVE
        ];

        return Episciences_UsersManager::assign($this->getUid(), $params);
    }

    /**
     * @param int $docId
     * @param array $params
     * @return array|bool
     */
    public function unassign($docId, $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $docId,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_INACTIVE
        ];

        return Episciences_UsersManager::unassign($this->getUid(), $params);
    }

    /**
     * @param array $settings
     * @param bool $loadInvitations
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getAssignedPapers(array $settings = [], bool $loadInvitations = false, bool $isFilterInfos = false, $isLimit = true)
    {
        if ($isFilterInfos || empty($this->_assignedPapers)) {
            $this->loadAssignedPapers($settings, $loadInvitations, $isFilterInfos, $isLimit);
        }
        return $this->_assignedPapers;
    }


    public function setAssignedPapers($papers)
    {
        $this->_assignedPapers = $papers;
    }

    /**
     * @param array $settings
     * @param bool $loadInvitations
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @return array|Episciences_Paper[]
     * @throws Zend_Db_Select_Exception
     */
    public function loadAssignedPapers(array $settings = [], bool $loadInvitations = false, bool $isFilterInfos = false, $isLimit = true): array
    {

        // fetch paper reviewer active assignments
        /** @var Zend_Db_Select $select */
        $select = $this->fetchPaperReviewerAssignmentsQuery();

        $docIds = $this->_db->fetchAssoc($select);

        if ($loadInvitations) {
            // load reviewer invitations
            $this->loadInvitations();
            // only keep declined and pending invitations (unexpired)
            $invitations = $this->getInvitations(
                ['status' => [Episciences_User_Invitation::STATUS_PENDING, Episciences_User_Invitation::STATUS_DECLINED], 'hasExpired' => false]
            );
            // add unanswered and declined invitations to papers list
            foreach ($invitations as $docId => $invitation) {
                if (!array_key_exists($docId, $docIds)) {
                    $docIds[$docId] = ['ITEMID' => $docId, 'STATUS' => 'unanswered', 'WHEN' => $invitation->getSending_date()];
                    //$this->getReviewing($docId);
                    $this->loadReviewingById($invitation->getAid());
                }
            }
        }

        // Filtrage des ids (si statut de relecture passé en param)   ********************************
        if (isset($settings['is']['ratingStatus'])) {

            foreach (array_keys($docIds) as $docId) {

                // Statut actuel de la relecture
                $status = $this->getReviewing($docId)->getStatus();

                // Filtrage
                if (is_array($settings['is']['ratingStatus'])) {
                    if (!in_array($status, $settings['is']['ratingStatus'])) {
                        unset($docIds[$docId]);
                    }
                } else {
                    if ($status != $settings['is']['ratingStatus']) {
                        unset($docIds[$docId]);
                    }
                }
            }
            unset($settings['is']['ratingStatus']);
        }

        if (count($docIds)) {
            $settings['is']['docid'] = array_keys($docIds);
            $papers = Episciences_PapersManager::getList($settings, $isFilterInfos, $isLimit);
        } else {
            $papers = [];
        }

        $this->setAssignedPapers($papers);

        return $papers;
    }


    /**
     * @param int $rvid
     */
    public function loadInvitations($rvid = RVID)
    {
        if (!$this->getUid()) {
            return;
        }

        if (!is_numeric($rvid)) {
            $rvid = RVID;
        }

        /*

        ## Requete pour récupérer toutes les invitations de relecture d'un utilisateur (invitations + assignations)
        ######################################################################"

        # ua2 : infos de l'assignation la plus récente
        # ui1 : infos de l'invitation d'origine

        SELECT
        ua2.ID AS AID2, # id de la dernière assignation (acceptation). récupérer aussi id de la 1ère assignation ?
        ua2.INVITATION_ID AS IID2,
        ua2.RVID,
        ua2.ITEMID AS DOCID,
        ua2.UID,
        ua2.`WHEN` AS ASSIGNMENT_DATE,
        ua2.`STATUS`,
        ui1.`SENDING_DATE` AS INVITATION_DATE,
        ui1.`EXPIRATION_DATE`

        # Récupération de toutes les infos concernant la dernière assignation à un article
        FROM `USER_ASSIGNMENT` ua2

        # Récupération de l'id de la dernière assignation à un article
        INNER JOIN
        (
            SELECT ITEMID AS DOCID, MAX(ID) AS MAXID
            FROM `USER_ASSIGNMENT`
            WHERE ITEM = 'paper'
            AND ROLEID = 'reviewer'
            AND UID = '187579'
            AND RVID = 1
            GROUP BY ITEMID
        ) AS ua0 ON ua2.ID = ua0.MAXID

        # Récupération de l'id de l'assignation d'origine
        INNER JOIN
        (
            SELECT ID, AID
            FROM `USER_INVITATION`
        ) AS ui2 ON ui2.ID = ua2.INVITATION_ID

        # Récupération de l'assignation d'origine
        INNER JOIN
        (
            SELECT ID, INVITATION_ID
            FROM `USER_ASSIGNMENT`
        ) AS ua1 ON ua1.ID = ui2.AID

        # Récupération de l'invitation d'origine
        INNER JOIN
        (
            SELECT *
            FROM `USER_INVITATION`
        ) AS ui1 ON ui1.ID = ua1.INVITATION_ID

         */

        $oInvitations = [];

        $sql = $this->loadInvitationsQuery($rvid);

        $result = $this->_db->fetchAll($sql);

        foreach ($result as $invitation) {
            $oInvitations[$invitation['DOCID']] = new Episciences_User_Invitation($invitation);
        }

        $this->_invitations = $oInvitations;

    }

    public function getInvitations($filters = []): array
    {
        $invitations = $this->_invitations;
        if (!empty($filters)) {
            $invitations = $this->filterInvitations($invitations, $filters);
        }
        return $invitations;
    }

    protected function filterInvitations(array $invitations, array $filters): array
    {
        /**
         * @var  int $docId
         * @var  Episciences_User_Invitation $invitation
         */
        foreach ($invitations as $docId => $invitation) {

            // Filtre sur le statut
            if (array_key_exists('status', $filters)) {
                if (is_array($filters['status'])) {
                    if (!in_array($invitation->getStatus(), $filters['status'], true)) {
                        unset($invitations[$docId]);
                    }
                } else if ($invitation->getStatus() !== $filters['status']) {
                    unset($invitations[$docId]);
                }
            }

            // Filtre sur la date d'expiration
            if (array_key_exists('hasExpired', $filters) && $invitation->hasExpired() !== $filters['hasExpired']) {
                unset($invitations[$docId]);
            }
        }
        return $invitations;
    }

    public function loadReviewingById($id)
    {
        $oReviewing = new Episciences_Reviewer_Reviewing();

        // Charge l'assignation
        $oReviewing->loadAssignment(['id' => $id]);

        // Charge l'évaluation
        $docId = $oReviewing->getAssignment()->getItemid();
        $oReviewing->loadRating($docId, $this->getUid());

        // Met à jour le status
        $oReviewing->loadStatus();

        $reviewings[$docId] = $oReviewing;
        $this->_reviewings = $reviewings;
    }

    public function getReviewing($docId)
    {
        if (!array_key_exists($docId, $this->_reviewings)) {
            $this->loadReviewing($docId);
        }

        return ($this->_reviewings[$docId]);
    }

    public function loadReviewing($docId)
    {
        // reviewing init
        $oReviewing = new Episciences_Reviewer_Reviewing();

        // load assignment
        $params = [
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'itemid' => $docId,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'uid' => $this->getUid(),
            //'status'=>	Episciences_User_Assignment::STATUS_ACTIVE
        ];
        $oReviewing->loadAssignment($params);

        // load rating report
        $oReviewing->loadRating($docId, $this->getUid());

        // update status
        $oReviewing->loadStatus();

        // update reviewer reviewing
        $reviewings = $this->getReviewings();
        $reviewings[$docId] = $oReviewing;
        $this->setReviewings($reviewings);
    }

    public function getComments(int $docId): array
    {
        if (empty($this->_comments)) {
            $this->_comments = Episciences_CommentsManager::getList($docId, ['UID' => $this->getUid()]);
        }

        return $this->_comments;
    }

    public function setRatings($ratings): \Episciences_Reviewer
    {
        $this->_ratings = $ratings;
        return $this;
    }

    /**
     * fetch paper reviewer active assignments
     * @return Zend_Db_Select
     */
    private function fetchPaperReviewerAssignmentsQuery(): \Zend_Db_Select
    {

        $subquery = $this->_db
            ->select()
            ->from(T_ASSIGNMENTS, ['ITEMID', 'MAX(`WHEN`) AS WHEN'])
            ->where('UID = ? ', $this->getUid())
            ->where('ITEM = ?', 'paper')
            ->where('ROLEID = ?', 'reviewer')
            ->where('RVID = ?', RVID)
            ->group('ITEMID');

        return $this->_db->select()
            ->from(['a' => T_ASSIGNMENTS], ['ITEMID', 'STATUS', 'WHEN'])
            ->join(['b' => $subquery], 'a.ITEMID = b.ITEMID AND a.`WHEN` = b.`WHEN`', [])
            ->where('a.STATUS IN (?)', [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE, Episciences_User_Assignment::STATUS_DECLINED]);
    }

    /**
     * @param int $rvId
     * @return Zend_Db_Select
     */
    private function loadInvitationsQuery(int $rvId = RVID): \Zend_Db_Select
    {
        // Inner Join 1
        $ua0 = $this->_db
            ->select()
            ->from(T_ASSIGNMENTS, ['DOCID' => 'ITEMID', 'MAXID' => 'MAX(`ID`)'])
            ->where('ITEM = ?', 'paper')
            ->where('ROLEID = ?', 'reviewer')
            ->where('UID = ?', $this->getUid())
            ->where('RVID = ?', $rvId)
            ->group('ITEMID');

        // Inner Join 2
        $ui2 = $this->_db->select()
            ->from(T_USER_INVITATIONS,
                ['ID', 'AID']);

        // Inner Join 3
        $ua1 = $this->_db->select()
            ->from(T_ASSIGNMENTS,
                ['ID', 'INVITATION_ID'])
            ->where('ITEM = ?', 'paper')
            ->where('ROLEID = ?', 'reviewer')
            ->where('RVID = ?', $rvId);

        $ui1 = $this->_db->select()
            ->from(T_USER_INVITATIONS);

        return $this->_db->select()
            ->from(['ua2' => T_ASSIGNMENTS], [
                // 'ID', 			// Id de l'assignation la plus récente
                // 'INVITATION_ID', // Id de l'invitation la plus récente
                // 'UID',			// Id de l'utilisateur assigné
                'RVID',
                'DOCID' => 'ITEMID',
                'ASSIGNMENT_DATE' => 'WHEN',
                'STATUS',
                'AID' => 'ID'
            ])
            ->joinInner(['ua0' => $ua0], 'ua2.ID = ua0.MAXID', [])
            ->joinInner(['ui2' => $ui2], 'ui2.ID = ua2.INVITATION_ID', [])
            ->joinInner(['ua1' => $ua1], 'ua1.ID = ui2.AID', [])
            ->joinInner(['ui1' => $ui1], 'ui1.ID = ua1.INVITATION_ID', [
                'ID',                // Id de l'invitation d'origine
                'SENDER_UID',
                'INVITATION_DATE' => 'SENDING_DATE',
                'EXPIRATION_DATE'
            ]);

    }
}
