<?php

// rating report (reviewer rating grid + reviewer rating values)
class Episciences_Rating_Report extends Episciences_Rating_Grid
{

    // rating has not started yet
    const STATUS_PENDING = 0;
    // rating is in progress
    const STATUS_WIP = 1;
    // rating is completed
    const STATUS_COMPLETED = 2;

    private $_id; // db id
    private $_path;
    private $_docid;
    private $_uid;
    private $_onbehalf_uid; // l'UID de celui qui a fait la relecture à la place de _UID
    private $_creation_date;
    private $_update_date;
    private $_score;            // average score (take into account criteria weight)
    private $_max_score = 10;    // highest possible score
    private $_status = self::STATUS_PENDING;
    private $_rvid;

    // find a rating report, for a given docid and uid
    public static function find($docid, $uid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_REVIEWER_REPORTS)
            ->where('DOCID = ?', $docid)
            ->where('UID = ?', $uid);

        $row = $db->fetchRow($sql);
        return ($row) ? new Episciences_Rating_Report($row) : false;
    }

    // find a rating report for a given report id
    public static function findById($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_REVIEWER_REPORTS)->where('ID = ?', $id);

        $row = $db->fetchRow($sql);
        return ($row) ? new Episciences_Rating_Report($row) : false;
    }

    // find rating reports for a given reviewer uid
    public static function findByUid($uid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_REVIEWER_REPORTS)->where('UID = ?', $uid);

        $reports = array();
        foreach ($db->fetchAll($sql) as $row) {
            $reports[] = new Episciences_Rating_Report($row);
        }
        return $reports;

    }

    public function __construct($values = array())
    {
        foreach ($values as $name => $value) {
            $method = 'set' . ucfirst(strtolower($name));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
        if (file_exists($this->getPath() . 'report.xml')) {
            $this->loadXML($this->getPath() . 'report.xml');
        }
    }

    // populate rating report
    public function populate($data): bool
    {

        $toolBarHtmlElements = ['p', 'span', 'strong', 'em', 'li', 'ol', 'ul'];

        if (!$this->getCriteria()) {
            return false;
        }

        // update criteria
        foreach ($this->getCriteria() as &$criterion) {

            if ($criterion->isSeparator()) {
                continue;
            }

            // criterion comment
            if ($criterion->allowsComment() && array_key_exists('comment_' . $criterion->getId(), $data)) {
                $htmlPurifier = new Episciences_HTMLPurifier();
                //TinyMCE automatically encodes all entered html code
                $content = $data['comment_' . $criterion->getId()];
                $decodedContent = html_entity_decode($content);
                //HTML encoding is done in Episciences_Rating_Report::toXML function
                $criterion->setComment($htmlPurifier->purifyHtml($decodedContent, ['HTML.AllowedElements' => $toolBarHtmlElements]));
            }

            // criterion attachment
            if ($criterion->allowsAttachment() && array_key_exists('file_' . $criterion->getId(), $data)) {
                $criterion->setAttachment($data['file_' . $criterion->getId()]['name']);
            }

            // criterion note
            if ($criterion->allowsNote()) {
                $criterion->setNote($data['note_' . $criterion->getId()]);
            }
        }

        unset($criterion);

        // update status
        if (array_key_exists('submitRatingForm', $data)) {
            $this->setStatus(self::STATUS_WIP);
        } elseif (array_key_exists('validateRating', $data)) {
            $this->setStatus(self::STATUS_COMPLETED);
        }

        return true;

    }

    public function getAttachments()
    {
        $attachments = [];
        foreach ($this->getCriteria() as $criterion) {
            if ($criterion->allowsAttachment() && $criterion->hasAttachment()) {
                $attachments['file_' . $criterion->getId()] = $criterion->getAttachment();
            }
        }
        return $attachments;
    }

    public function exists()
    {
        return file_exists($this->getPath() . 'report.xml');
    }

    /**
     * @param null $path
     * @param bool $forceDate
     * @return bool
     */
    public function save($path = null, $forceDate = false)
    {
        // update xml
        if (!$this->toXML()) {
            return false;
        }

        // write xml file
        if (file_put_contents($this->getPath() . 'report.xml', $this->getXML(), LOCK_EX) === false) {
            return false;
        }
        @chmod($this->getPath() . $this->getFilename(), 0777);

        // update table REVIEWER_REPORT
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = 'INSERT INTO ' . T_REVIEWER_REPORTS . ' (ID, UID, ONBEHALF_UID, DOCID, STATUS, CREATION_DATE)
        VALUES (:id, :uid, :onbehalf_uid, :docid, :status, :creationDate)';

        $sql .= ' ON DUPLICATE KEY UPDATE ONBEHALF_UID = :onbehalf_uid, STATUS = :status, UPDATE_DATE = :updateDate';

        $query = $db->prepare($sql);
        $query->execute([
            'id' => $this->getId(),
            'uid' => $this->getUid(),
            'onbehalf_uid' => $this->getOnbehalf_uid(),
            'docid' => $this->getDocid(),
            'status' => $this->getStatus(),
            'creationDate' => ($forceDate && !empty($this->getCreation_date())) ? $this->getCreation_date() : date("Y-m-d H:i:s"),
            'updateDate' => ($forceDate) ? $this->getUpdate_date() : date("Y-m-d H:i:s")
        ]);

        return true;
    }

    public function setPath($path)
    {
        $this->_path = $path;
    }

    public function getPath()
    {
        if (!$this->_path) {
            $this->generatePath();
        }
        return $this->_path;
    }

    public function generatePath()
    {
        if (!$this->getDocid() || !$this->getUid()) {
            return false;
        }
        $path = REVIEW_FILES_PATH . $this->getDocid() . '/reports/' . $this->getUid() . '/';
        if (!is_dir($path)) {
            $umask = umask(0); // save current umask and set it to 0
            mkdir($path, 0777, true);
            umask($umask);
        }
        $this->setPath($path);
    }

    // load rating report from xml file
    public function loadXML($filepath)
    {
        parent::loadXML($filepath);

        $xml = new Ccsd_DOMDocument();
        $xml->preserveWhiteSpace = false;
        parent::loadXML($filepath);
        if (!$xml->load($filepath)) {
            return false;
        }

        $xpath = new DOMXpath($xml);
        $xpath->registerNamespace('', "http://www.tei-c.org/ns/1.0");
        $xpath->registerNamespace('tei', "http://www.tei-c.org/ns/1.0");

        foreach ($this->getCriteria() as $criterion) {

            // skip criterion if it is a separator
            if ($criterion->isSeparator()) {
                continue;
            }

            // fetch criterion xml node
            $node = $xml->getElementById($criterion->getId());

            // load attached file path
            if ($criterion->allowsAttachment()) {
                $file = $xpath->query('.//tei:ref[@type="attachment"]', $node);
                if ($file->length != 0) {
                    $criterion->setAttachment($file->item(0)->nodeValue);
                }
            }

            // load comment
            if ($criterion->allowsComment()) {
                $comment = $xpath->query('.//tei:ab[@type="comment"]', $node);
                if ($comment->length != 0) {
                    $criterion->setComment($comment->item(0)->nodeValue);
                }
            }

            // load note
            if ($criterion->allowsNote()) {

                $list = $xpath->query('.//tei:list[@style="options"]', $node);
                if ($list->length != 0) {
                    $criterion->setNote($list->item(0)->getAttribute('select'));
                }
            }
        }

        $this->setXml($xml->saveXML());
        return true;
    }

    // export rating report to xml string
    public function toXML()
    {
        $xml = new Ccsd_DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;
        if (!$xml->loadXML($this->getXml())) {
            return false;
        }

        $xpath = new DOMXpath($xml);
        $xpath->registerNamespace('', "http://www.tei-c.org/ns/1.0");
        $xpath->registerNamespace('tei', "http://www.tei-c.org/ns/1.0");

        foreach ($this->getCriteria() as $criterion) {

            // skip criterion if it is a separator
            if ($criterion->isSeparator()) {
                continue;
            }

            // fetch criterion xml node
            $node = $xml->getElementById($criterion->getId());

            // update attached file path
            if ($criterion->allowsAttachment()) {
                $file = $xpath->query('.//tei:ref[@type="attachment"]', $node);
                if ($file->length != 0) {
                    $file->item(0)->nodeValue = $criterion->getAttachment();
                }
            }

            // update comment
            if ($criterion->allowsComment()) {
                $comment = $xpath->query('.//tei:ab[@type="comment"]', $node);
                if ($comment->length != 0) {
                    $comment->item(0)->nodeValue = Ccsd_Tools_String::stripCtrlChars(Ccsd_Tools_String::xmlSafe($criterion->getComment()));
                }
            }

            // update note
            if ($criterion->allowsNote()) {

                $list = $xpath->query('.//tei:list[@style="options"]', $node);
                if ($list->length != 0) {
                    $list->item(0)->setAttribute('select', $criterion->getNote());
                }
            }
        }

        $this->setXml($xml->saveXML());
        return $this->getXml();
    }

    public function getScore()
    {
        if (!isset($this->_score)) {
            $this->calculateScore();
        }
        return $this->_score;
    }

    public function getMax_score()
    {
        return $this->_max_score;
    }

    public function setMax_score($max_score)
    {
        $this->_max_score = $max_score;
    }

    public function setScore($score)
    {
        $this->_score = $score;
    }

    public function calculateScore($precision = 0)
    {
        $score = 0;
        $coefs = 0;

        if (!is_array($this->getCriteria())) {
            return false;
        }

        foreach ($this->getCriteria() as $criterion) {
            $coefs += $criterion->getCoefficient();
            if ($criterion->getCoefficient()) {
                $score += ($criterion->getNote() / $criterion->getMaxNote()) * $criterion->getCoefficient();
            }
        }

        if ($score != 0 && $coefs != 0) {
            $score = round(($score / $coefs) * $this->getMax_score(), $precision);
        } else {
            $score = null;
        }

        $this->setScore($score);
    }

    public function toArray()
    {
        $criteria = [];
        if (is_array($this->getCriteria())) {
            foreach ($this->getCriteria() as $oCriterion) {
                $criteria[] = $oCriterion->toArray();
            }
        }

        return [
            'id' => $this->getId(),
            'uid' => $this->getUid(),
            'docid' => $this->getDocid(),
            'creation_date' => $this->getCreation_date(),
            'update_date' => $this->getUpdate_date(),
            'status' => $this->getStatus(),
            'score' => $this->getScore(),
            'xml' => $this->getXml(),
            'criteria' => $criteria
        ];
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function getDocid()
    {
        return $this->_docid;
    }

    public function setDocid($docid)
    {
        $this->_docid = $docid;
    }

    public function getUid()
    {
        return $this->_uid;
    }

    public function getOnbehalf_uid()
    {
        return $this->_onbehalf_uid;
    }

    public function setOnbehalf_uid($onbehalf_uid)
    {
        $this->_onbehalf_uid = $onbehalf_uid;
    }

    public function setUid($uid)
    {
        $this->_uid = (int)$uid;
    }

    public function getRvid()
    {
        return $this->_rvid;
    }

    public function setRvid($rvid)
    {
        $this->_rvid = $rvid;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function getCreation_date()
    {
        return $this->_creation_date;
    }

    public function setCreation_date($date)
    {
        $this->_creation_date = $date;
    }

    public function getUpdate_date()
    {
        return $this->_update_date;
    }

    public function setUpdate_date($date)
    {
        $this->_update_date = $date;
    }

    public function isCompleted(): bool
    {
        return ($this->getStatus() === self::STATUS_COMPLETED);
    }

    public function isPending(): bool
    {
        return ($this->getStatus() === self::STATUS_PENDING);
    }

    public function isInProgress(): bool
    {
        return ($this->getStatus() === self::STATUS_WIP);
    }

    public function setStatus($status)
    {
        $this->_status = (int)$status;
    }

}