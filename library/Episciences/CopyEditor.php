<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 08/03/19
 * Time: 13:00
 */

class  Episciences_CopyEditor extends Episciences_User
{

    /** @var string */
    protected $_when;
    /** @var string */
    protected $_status;

    /** @var Episciences_Section[] $__assignedSections */
    protected $_assignedSections = [];
    protected $_assignments = [];
    protected $_assignedPapers = [];

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
        parent::__construct($options);
    }

    /**
     * @return string
     */
    public function getWhen()
    {
        return $this->_when;
    }

    /**
     * @param string $when
     * @return Episciences_CopyEditor
     */
    public function setWhen($when)
    {
        $this->_when = $when;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @param string $status
     * @return Episciences_CopyEditor
     */
    public function setStatus($status)
    {
        $this->_status = $status;
        return $this;
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

    /**
     * load assigned sections list
     * @return array
     */
    public function loadAssignedSections()
    {
        $sections = [];

        $settings['RVID'] = RVID;
        $settings['ITEM'] = Episciences_User_Assignment::ITEM_SECTION;
        $settings['ROLEID'] = Episciences_User_Assignment::ROLE_COPY_EDITOR;
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

    /**
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

    // Charge les assignations (éventuellement par type d'item)

    /**
     * @param array $settings
     * @param bool $isFilterInfos : lorsqu'un utilisateur filtre les informations dans une table,
     * un message est ajouté pour donner une idée de la force du filtrage.
     * @param bool $isLimit
     * @return array
     * @throws Zend_Exception
     */
    public function loadAssignedPapers(array $settings = [], bool $isFilterInfos = false, bool $isLimit = true)
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

    public function hasAssignments($type = null)
    {
        if ($this->getAssignments($type)) {
            return true;
        }
        return false;

    }

    public function getAssignments($type = null)
    {
        if ($type) {
            return (array_key_exists($type, $this->_assignments)) ? $this->_assignments[$type] : [];
        } else {
            return $this->_assignments;
        }

    }

    public function setAssignments(array $assignments, $type)
    {
        $this->_assignments[$type] = $assignments;
        return $this;
    }

    public function loadAssignments($type = null)
    {
        if (!$this->getUid()) {
            return;
        }

        if (!$type || $type == Episciences_User_Assignment::ITEM_PAPER) {
            $params = [
                'item' => Episciences_User_Assignment::ITEM_PAPER,
                'roleid' => Episciences_User_Assignment::ROLE_COPY_EDITOR,
                'rvid' => RVID,
                'uid' => $this->getUid(),
                'status' => Episciences_User_Assignment::STATUS_ACTIVE];
            $assignments = Episciences_User_AssignmentsManager::getList($params);
            $this->setAssignments($assignments, Episciences_User_Assignment::ITEM_PAPER);
        }

        if (!$type || $type == Episciences_User_Assignment::ITEM_SECTION) {
            $params = [
                'item' => Episciences_User_Assignment::ITEM_SECTION,
                'roleid' => Episciences_User_Assignment::ROLE_COPY_EDITOR,
                'rvid' => RVID,
                'uid' => $this->getUid(),
                'status' => Episciences_User_Assignment::STATUS_ACTIVE];
            $assignments = Episciences_User_AssignmentsManager::getList($params);
            $this->setAssignments($assignments, Episciences_User_Assignment::ITEM_SECTION);
        }
    }


}