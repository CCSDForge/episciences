<?php

class Episciences_Reviewer_Reviewing
{
    // possible reviewing status
    const STATUS_PENDING = 0;       // reviewing not started yet
    const STATUS_WIP = 1;           // reviewing in progress
    const STATUS_COMPLETE = 2;      // reviewing completed
    const STATUS_UNANSWERED = 3;    // unanswered reviewing invitation
    const STATUS_OBSOLETE = 4;      // reviweing invitation is obsolete (article doed not need reviewing anymore)
    const STATUS_DECLINED = 5;      // declined reviewing invitation
    public const STATUS_NOT_NEED_REVIEWING = 6; // new version requested, not need reviewing anymore

    /**
     * reviewing status
     * @var int
     */
    protected $_status;

    /**
     * reviewing invitation
     * @var Episciences_User_Invitation
     */
    protected $_invitation;

    /**
     * reviewing assignment
     * @var Episciences_User_Assignment
     */
    protected $_assignment;

    /**
     * reviewing rating
     * @var Episciences_Rating_Report
     */
    protected $_rating;

    /**
     * reviewed paper
     * @var Episciences_Paper
     */
    protected $_paper;


    // reviewing status labels
    public static $_statusLabel = array(
        self::STATUS_PENDING => 'relecture en attente',
        self::STATUS_WIP => 'relecture en cours',
        self::STATUS_COMPLETE => 'relecture terminée',
        self::STATUS_UNANSWERED => 'invitation en attente',
        self::STATUS_OBSOLETE => 'invitation obsolète',
        self::STATUS_DECLINED => "invitation déclinée",
        self::STATUS_NOT_NEED_REVIEWING => 'relecture obsolète'
    );

    // reviewing status color codes
    public static $_statusColors = array(
        self::STATUS_PENDING => 'lightgrey',
        self::STATUS_WIP => 'orange',
        self::STATUS_COMPLETE => 'green',
        self::STATUS_UNANSWERED => 'lightergrey',
        self::STATUS_OBSOLETE => 'darkgrey'
    );


    public function setInvitation($invitation)
    {
        $this->_invitation = $invitation;
    }

    public function getInvitation()
    {
        return $this->_invitation;
    }

    public function setPaper(Episciences_Paper $paper)
    {
        $this->_paper = $paper;
    }

    public function loadPaper($docid)
    {
        $this->setPaper(Episciences_PapersManager::get($docid));
    }

    public function getPaper()
    {
        if (!$this->_paper) {
            $this->loadPaper($this->getAssignment()->getItemid());
        }
        return $this->_paper;
    }

    public function loadAssignment($params = array())
    {
        $params['item'] = Episciences_User_Assignment::ITEM_PAPER;
        $assignment = Episciences_User_AssignmentsManager::find($params);
        if ($assignment) {
            $this->setAssignment($assignment);
        }
        return $this;
    }

    public function loadAssignmentById($id)
    {
        $this->loadAssignment(array('id' => $id));
    }

    public function loadRating($docid, $uid)
    {
        $rating = Episciences_Rating_Report::find($docid, $uid);
        if ($rating) {
            $this->setRating($rating);
        }

        return $this;
    }

    public function getRvid()
    {
        if ($this->hasAssignment()) {
            return $this->getAssignment()->getRvid();
        }

        return null;
    }

    public function loadStatus()
    {
        $result = null;

        if ($this->hasRating()) {

            if (!$this->getPaper()->canBeReviewed()) {
                $result = self::STATUS_NOT_NEED_REVIEWING;

            } else {
                $report = $this->getRating();
                if ($report->isCompleted()) {
                    $result = self::STATUS_COMPLETE;
                } elseif ($report->isInProgress()) {
                    $result = self::STATUS_WIP;
                } elseif ($report->isPending()) {
                    $result = self::STATUS_PENDING;
                }
            }

        } else {
            $status = self::STATUS_UNANSWERED;
            $assignment = $this->getAssignment();
            if ($assignment->getStatus() === Episciences_User_Assignment::STATUS_DECLINED) {
                $status = self::STATUS_DECLINED;
            }
            $result = ($this->getPaper()->canBeReviewed()) ? $status : self::STATUS_OBSOLETE;
        }
        $this->setStatus($result);
    }

    public function setStatus($status)
    {
        $this->_status = (int)$status;
    }

    /**
     * fetch reviewing status code
     * @return int|null
     */
    public function getStatus()
    {
        if ($this->_status === null) {
            $this->loadStatus();
        }

        return $this->_status;
    }

    /**
     * fetch reviewing assignment date
     * @return datetime|NULL
     */
    public function getAssignmentDate()
    {
        if ($this->hasAssignment()) {
            return $this->getAssignment()->getWhen();
        }

        return null;
    }

    /**
     * fetch reviewing last update date
     * @return datetime|NULL
     */
    public function getUpdateDate()
    {
        if ($this->hasRating()) {
            return $this->getRating()->getUpdate_date();
        }

        return null;
    }

    public function getRating()
    {
        return $this->_rating;
    }

    public function getAssignment()
    {
        return $this->_assignment;
    }

    /**
     * fetch a list of reviewing possible status
     * @return array
     */
    public static function getStatusList()
    {
        return self::$_statusLabel;
    }

    /**
     * fetch reviewing status label
     * @param $status
     * @return mixed
     */
    public static function getStatusLabel($status)
    {
        return array_key_exists($status, self::$_statusLabel) ? self::$_statusLabel[$status] : $status;
    }

    /**
     * fetch reviewing status color code
     * @param $status
     * @return mixed
     */
    public static function getStatusColor($status)
    {
        return array_key_exists($status, self::$_statusColors) ? self::$_statusColors[$status] : $status;
    }

    public function hasAssignment()
    {
        return ($this->getAssignment());
    }

    public function hasRating()
    {
        return ($this->getRating());
    }

    public function setRating(Episciences_Rating_Report $rating)
    {
        $this->_rating = $rating;
    }

    public function setAssignment(Episciences_User_Assignment $assignment)
    {
        $this->_assignment = $assignment;
    }


}
