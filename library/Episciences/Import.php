<?php 
/**
 * @deprecated
 */
class Episciences_Import
{
    protected $_repoid;
    protected $_rvid;
    protected $_id;
    protected $_status;
    protected $_version;
    protected $_volume;
    protected $_section;
    protected $_uid;
    protected $_docid;
    protected $_doi;

    protected $_review;
    protected $_required;
    protected $_error;

    protected $_publication_date;
    protected $_position;
    protected $_editor_id;

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key=>$value) {
            $method = 'set' . ucfirst(strtolower($key));
            if (in_array($method, $methods)) {
                $this->$method($value);
            } else echo $method;
        }
    }

    public function setError($error)
    {
        $this->_error = $error;
    }

    public function getError()
    {
        return $this->_error;
    }

    public function isValid()
    {
        // check required parameters
        if ($this->getRequired()) {
            foreach ($this->getRequired() as $opt=>$msg) {

                $method = 'get' . ucfirst(strtolower($opt));
                $value = $this->$method();

                if (strpos($opt, '|')) {
                    $optArr = explode('|', $opt) ;
                    foreach ($optArr as $opt) {
                        $method = 'get' . ucfirst(strtolower($opt));
                        $value = $this->$method();
                        if (isset($value)) {
                            continue 2;
                        }
                    }
                    $this->setError($msg);
                    return false;
                } else if (!isset($value)) {
                    $this->setError($msg);
                    return false;
                }
            }
        }

        // check if the repository exists
        if (!array_key_exists($this->getRepoid(), Episciences_Repositories::getRepositories())) {
            $this->setError("ERREUR : L'archive (" . $this->getRepoid() . ") n'existe pas");
            return false;
        }

        // check if the journal exists
        $review = Episciences_ReviewsManager::find($this->getRvid());
        if (!$review) {
            $this->setError("ERREUR : La revue (" . $this->getRvid() . ") n'existe pas");
            return false;
        }

        // check that the paper status exists
        if ($this->getStatus()) {
            if (!array_key_exists($this->getStatus(), Episciences_Paper::$_statusLabel)) {
                $this->setError("ERREUR : Le statut (" . $this->getStatus() . ") n'existe pas");
                return false;
            }
        }

        // check that the user exists, if uid was given
        if ($this->getUid()) {
            $user = new Episciences_User;
            if (!$user->find($this->getUid())) {
                $this->setError("ERREUR : L'utilisateur (" . $this->getUid() . ") n'existe pas sur Episciences");
                return false;
            }
            if (!$user->hasRoles($this->getUid())) {
                $this->setError("ERREUR : L'utilisateur (" . $this->getUid() . ") n'a aucun rôle dans cette revue");
                return false;
            }
        }
        // otherwise, get default uid
        elseif (!$this->getDefaultUid()) {
            return false;
        }


        return true;
    }

    public function getDefaultUid()
    {
        // fetch chief editors
        $chiefRedactors = $this->getReview()->getChiefEditors();

        // remove root uid
        if (array_key_exists(1, $chiefRedactors)) {unset($chiefRedactors[1]);}
        if (empty($chiefRedactors)) {
            $this->setError("ERREUR : Aucun rédacteur en chef n'a été trouvé pour cette revue. Veuillez indiquer un uid.");
            return false;
        }
        // get chief editor creation date
        foreach ($chiefRedactors as $uid=>$user) {
            $uids[$uid] = $user->getTime_registered();
        }
        // get older chief editor uid
        $uid = array_search(min($uids), $uids);
        $this->setUId($uid);

        return $uid;
    }

    /**
     * @return Episciences_Paper
     * @throws Exception
     */
    public function load(){

        $id = $this->getId();
        $version = $this->getVersion();

        $metadata = Episciences_Submit::getDoc($this->getRepoid(), $id, $version);

        if (!$metadata || $metadata['status'] == 0) {
            throw new Exception("L'article (".$this->getRepoid().' - '.$this->getId().' - v'.$this->getVersion().") n'existe pas");
        }

        $paper = new Episciences_Paper();
        $paper->setDoi($this->getDoi());
        $paper->setIdentifier($this->getId());
        $paper->setRvid($this->getRvid());
        $paper->setVid(($this->getVolume()) ? $this->getVolume() : 0);
        $paper->setSid(($this->getSection()) ? $this->getSection() : 0);
        $paper->setUid($this->getUid());
        $paper->setStatus(($this->getStatus()) ? $this->getStatus() : Episciences_Paper::STATUS_PUBLISHED);
        $paper->setVersion(($this->getVersion()) ? $this->getVersion() : 1);
        $paper->setRepoid($this->getRepoid());
        
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($this->getDocid()) {

            // check that this docid does not exist in another journal, if a docid was given
            $sql = $db->select()
            ->from(T_PAPERS, array('DOCID'))
            ->where('RVID != ?', $this->getRvid())
            ->where('DOCID = ?', $this->getDocid());
            if ($db->fetchOne($sql)) {
                $this->setError("ERREUR : Ce docId (" . $this->getDocid() . ") existe déjà dans une autre revue.");
                return false;
            }

            $paper->setDocid($this->getDocid());
        }

        $paper->setRecord($metadata['record']);
        if ($paper->getStatus() == Episciences_Paper::STATUS_PUBLISHED) {

            if ($this->getPublication_date()) {
                $paper->setPublication_date($this->getPublication_date());
            } else {
                $datetime = Ccsd_Tools::xpath($metadata['record'], '//dc:date');
                if ($datetime) {
                    if (is_array($datetime)) {
                        $datetime = array_shift($datetime);
                    }
                    @list($datetime, $hour) = explode(' ', $datetime);
                    @list($date['year'], $date['month'], $date['day'], ) = explode('-', $datetime);
                    $date['month'] = Ccsd_Tools::ifsetor($date['month'], '01');
                    $date['day'] = Ccsd_Tools::ifsetor($date['day'], '01');
                    $hour = Ccsd_Tools::ifsetor($hour, '08:00:00');
                    $datetime = $date['year'].'-'.$date['month'].'-'.$date['day'].' '.$hour;

                } else {
                    $datetime = date('Y-m-d H:i:s');
                }

                $paper->setPublication_date($datetime);
            }
        }
        return $paper;

    }

    /**
     * save a new imported paper (from csv file or from script)
     * @return bool
     */
    public function save()
    {
        if (!$this->isValid()) {
            return false;
        }

        try {
            $paper = $this -> load();
            $this->paper = $paper;
        } catch (Exception $e) {
            $this->setError($e -> getMessage());
            return false;
        }

        if (!$paper->save()) {
            $this->setError("ERREUR : L'article (" . $this->getId() . ") n'a pas pu être importé.");
            return false;
        }

        // save paper position in volume
        if ($paper->getVid() && $this->getPosition()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $db->insert(T_VOLUME_PAPER_POSITION, array('VID' => $paper->getVid(), 'PAPERID' => $paper->getPaperid(), 'POSITION' => $this->getPosition()));
        }

        // save editor in charge
        if ($this->getEditor_id()) {
            $paper->assign($this->getEditor_id(), Episciences_User_Assignment::ROLE_EDITOR);
        }

        // indexation
        Ccsd_Search_Solr_Indexer::addToIndexQueue(array($paper->getDocid()), 'episciences', 'UPDATE', 'episciences');

        return true;
    }



    /**
     * get paper repository id
     * @return mixed
     */
    public function getRepoid()
    {
        return $this->_repoid;
    }

    /**
     * get paper journal id
     * @return mixed
     */
    public function getRvid()
    {
        return $this->_rvid;
    }

    /**
     * get paper review
     * @return Episciences_Review
     */
    private function getReview()
    {
        return $this->_review;
    }

    /**
     * get paper identifier
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * get paper status id
     * @return mixed
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * get paper version
     * @return mixed
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * get paper volume id
     * @return mixed
     */
    public function getVolume()
    {
        return $this->_volume;
    }

    /**
     * get paper section id
     * @return mixed
     */
    public function getSection()
    {
        return $this->_section;
    }

    /**
     * get paper contributor id
     * @return mixed
     */
    public function getUId()
    {
        return $this->_uid;
    }

    /**
     * get required parameters
     * @return mixed
     */
    public function getRequired()
    {
        return $this->_required;
    }

    public function getDocid()
    {
        return $this->_docid;
    }

    public function getDoi()
    {
        return $this->_doi;
    }

    public function getPosition()
    {
        return $this->_position;
    }

    public function getEditor_id()
    {
        return $this->_editor_id;
    }

    public function getPublication_date()
    {
        return $this->_publication_date;
    }



    // Définit l'id de l'archive où doit être récupéré l'article
    public function setRepoid($repoid)
    {
        $this->_repoid = $repoid;
        return $this;
    }

    // Définit l'id de la revue où doit être importé l'article
    public function setRvid($rvid)
    {
        $review = Episciences_ReviewsManager::find($rvid);
        if ($review) {
            $this->setReview($review);
        }

        if (!defined('RVID')) {
            define('RVID', $review->getRvid());
        }

        $this->_rvid = $rvid;
        return $this;
    }

    private function setReview(Episciences_Review $review)
    {
        $this->_review = $review;
        return $this;
    }

    // Définit l'identifiant de l'article
    public function setId($id)
    {
        $this->_id = $id;
        return $this;
    }

    // Définit le statut à attribuer à l'article au moment de son import (accepté, refusé...)
    public function setStatus($status)
    {
        $this->_status = $status;
        return $this;
    }

    // Définit la version de l'article à importer
    public function setVersion($version)
    {
        $this->_version = $version;
        return $this;
    }

    // Définit l'id du volume où doit être importé l'article
    public function setVolume($volume)
    {
        $this->_volume = $volume;
        return $this;
    }

    // Définit l'id de la rubrique où doit être importée l'article
    public function setSection($section)
    {
        $this->_section = $section;
        return $this;
    }

    // Définit l'id de l'utilisateur à qui on attribute la soumission de l'article
    public function setUid($uid)
    {
        $this->_uid = $uid;
        return $this;
    }

    // Définit les paramètres obligatoires
    public function setRequired(array $required)
    {
        $this->_required = $required;
        return $this;
    }

    public function setDocid($docid)
    {
        $this->_docid = $docid;
        return $this;
    }

    public function setDoi($doi)
    {
        $this->_doi = $doi;
        return $this;
    }

    public function setPosition($position)
    {
        $this->_position = $position;
        return $this;
    }

    public function setPublication_date($publication_date)
    {
        $this->_publication_date = $publication_date;
        return $this;
    }

    public function setEditor_id($uid)
    {
        $this->_editor_id = $uid;
        return $this;
    }
}
