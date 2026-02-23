<?php

class Episciences_Editor extends Episciences_User
{
    const TAG_SECTION_EDITOR = 'Section editor';
    const TAG_VOLUME_EDITOR = 'Volume editor';
    const TAG_SUGGESTED_EDITOR = 'suggested editor';
    const TAG_CHIEF_EDITOR = 'Chief editor';
    protected $_when;
    protected $_status;

    private $_assignedPapers = [];
    private $_assignments = [];
    private $_assignedSections = [];
    /**
     * @var string
     */
    private $_tag = '';

    // private $_comments = array();

    public function __construct(array $options = null)
    {
        parent::__construct($options);

        if (is_array($options)) {
            $this->setOptions($options);
        }

    }

    public function toArray()
    {
        $result = parent::toArray();
        $result['status'] = $this->getStatus();
        $result['when'] = $this->getWhen();
        $result['tag'] = $this->getTag();

        // Papiers assignés au rédacteur
        if (!empty($this->_assignedPapers)) {
            $papers = $this->_assignedPapers;
            foreach ($papers as &$paper) {
                $paper = $paper->toArray();
            }
            unset($paper);
            $result['assignedPapers'] = $papers;
        }

        return $result;
    }

    /**
     * @param array $settings
     * @param bool $isFilterInfos: lorsqu'un utilisateur filtre les informations dans une table,
     * un message est ajouté pour donner une idée de la force du filtrage.
     * @param bool $isLimit
     * @return array
     * @throws Zend_Exception
     */
    public function loadAssignedPapers(array $settings = [],  bool $isFilterInfos = false, bool $isLimit = true)
    {
        if (!$this->hasAssignments(Episciences_User_Assignment::ITEM_PAPER)) {
            $this->loadAssignments(Episciences_User_Assignment::ITEM_PAPER);
        }

        $docIds = array_keys($this->getAssignments(Episciences_User_Assignment::ITEM_PAPER));

        if (count($docIds)) {
            $settings['is']['docid'] = $docIds;
            $papers = Episciences_PapersManager::getList($settings, $isFilterInfos, $isLimit);
        } else {
            $papers = [];
        }

        $this->setAssignedPapers($papers);

        return $papers;
    }

    /**
     * fetch assigned papers list
     * @return array
     * @throws Zend_Exception
     */
    public function getAssignedPapers()
    {
        if (empty($this->_assignedPapers)) {
            $this->loadAssignedPapers();
        }
        return $this->_assignedPapers;
    }

    public function setAssignedPapers($papers)
    {
        $this->_assignedPapers = $papers;
    }

    /**
     * load assigned sections list
     * @return array
     */
    public function loadAssignedSections()
    {
        $sections = [];

        $settings['RVID'] = RVID;
        $settings['ITEM'] = Episciences_User_Assignment::ITEM_SECTION;
        $settings['ROLEID'] = Episciences_User_Assignment::ROLE_EDITOR;
        $settings['STATUS'] = Episciences_User_Assignment::STATUS_ACTIVE;
        $settings['UID'] = $this->getUid();

        $assignments = Episciences_User_AssignmentsManager::getList($settings);

        foreach ($assignments as $assignment) {
            $sid = $assignment->getItemid();
            $section = Episciences_SectionsManager::find($sid);
            if ($section) {
                $sections[$sid] = $section;
            }
        }

        $this->setAssignedSections($sections);

        return $sections;
    }

    // Renvoie la liste des rubriques assignées
    // (charge les rubriques assignées si ce n'est pas déjà fait)
    public function getAssignedSections()
    {
        if (empty($this->_assignedSections)) {
            $this->loadAssignedSections();
        }
        return $this->_assignedSections;
    }

    public function setAssignedSections($sections)
    {
        $this->_assignedSections = $sections;
    }


    public function hasAssignments($type = null)
    {
        if ($this->getAssignments($type)) {
            return true;
        }
        return false;

    }

    // Charge les assignations (éventuellement par type d'item)
    public function loadAssignments($type = null)
    {
        if (!$this->getUid()) {
            return;
        }

        if (!$type || $type == Episciences_User_Assignment::ITEM_PAPER) {
            $params = [
                'item' => Episciences_User_Assignment::ITEM_PAPER,
                'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
                'rvid' => RVID,
                'uid' => $this->getUid(),
                'status' => Episciences_User_Assignment::STATUS_ACTIVE];
            $assignments = Episciences_User_AssignmentsManager::getList($params);
            $this->setAssignments($assignments, Episciences_User_Assignment::ITEM_PAPER);
        }

        if (!$type || $type == Episciences_User_Assignment::ITEM_SECTION) {
            $params = [
                'item' => Episciences_User_Assignment::ITEM_SECTION,
                'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
                'rvid' => RVID,
                'uid' => $this->getUid(),
                'status' => Episciences_User_Assignment::STATUS_ACTIVE];
            $assignments = Episciences_User_AssignmentsManager::getList($params);
            $this->setAssignments($assignments, Episciences_User_Assignment::ITEM_SECTION);
        }
    }

    public function setAssignments(array $assignments, $type)
    {
        $this->_assignments[$type] = $assignments;
        return $this;
    }

    public function getAssignments($type = null)
    {
        if ($type) {
            return (array_key_exists($type, $this->_assignments)) ? $this->_assignments[$type] : [];
        } else {
            return $this->_assignments;
        }

    }

    // Renvoie l'assignation de relecture
    public function getAssignment($type, $docId)
    {
        return $this->_assignments[$type][$docId];
    }


    /*
    public function getComments($docId)
    {
        if (empty($this->_comments)) {
            $this->_comments = Episciences_CommentsManager::getList($docId, array('UID'=>$this->getUid()));
        }

        return $this->_comments;
    }
    */

    public function getStatus()
    {
        return $this->_status;
    }

    public function getWhen()
    {
        return $this->_when;
    }


    public function setStatus($_status)
    {
        $this->_status = $_status;
        return $this;
    }

    public function setWhen($_when)
    {
        $this->_when = $_when;
        return $this;
    }

    /**
     * @param string $tag
     * @return $this
     */
    public function setTag(string $tag)
    {
        $this->_tag = $tag;
        return $this;
    }

    /**
     * @return string
     */
    public function getTag(): string
    {
        return $this->_tag;
    }


}
