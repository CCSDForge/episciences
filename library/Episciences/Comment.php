<?php

/**
 * Class Episciences_Comment
 */
class Episciences_Comment
{
    /**
     * @var int
     */
    protected $_pcid;

    /**
     * @var int
     */
    protected $_parentId;


    /**
     * @var int
     */
    protected int $_type = 0;


    /**
     * @var int
     */
    protected int $_docId = 0;
    /**
     * @var int
     */
    protected $_uid;

    /**
     * @var string|null
     */
    protected ?string$_message = null;


    /**
     * @var string
     */
    protected $_file;

    protected $_deadline = null;


    protected $_when;
    protected $_options = [];

    /**
     * @var string
     */
    protected $_path;
    protected $_translations;
    protected $_body;
    protected $_name;
    protected $_subject;
    protected $_isCopyEditingComment = false;

    protected $_excludedCommentsTypes = [
        Episciences_CommentsManager::TYPE_REVISION_REQUEST
    ];

    /**
     * Episciences_Comment constructor.
     * @param array|null $values
     * @throws Zend_Json_Exception
     */
    public function __construct(array $values = null)
    {
        if (is_array($values)) {
            $this->populate($values);
        }
    }

    /**
     * @param array $values
     * @return $this
     * @throws Zend_Json_Exception
     */
    public function populate(array $values)
    {
        $methods = get_class_methods($this);
        foreach ($values as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);

            if (in_array($method, $methods)) {
                if (Episciences_Tools::isJson($value)) {
                    $value = Zend_Json::decode($value);
                }
                $this->$method($value);
            }
        }

        return $this;
    }

    public function setOption($name, $value)
    {
        $this->_options[$name] = $value;
    }

    public function getOption($name)
    {
        return $this->_options[$name] ?? null;
    }


    /**
     * find a comment from id, and populate object
     * @param $id
     * @return mixed|null
     * @throws Zend_Json_Exception
     */
    public function find($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()->from(T_PAPER_COMMENTS)->where('PCID = ? ', $id);
        $result = $db->fetchRow($sql);

        if ($result) {

            $this->setPcid($result['PCID']);
            $this->setParentId($result['PARENTID']);
            $this->setType($result['TYPE']);
            $this->setDocid($result['DOCID']);
            $this->setUid($result['UID']);
            $this->setMessage($result['MESSAGE']);
            $this->setFile($result['FILE']);

            if ($result['OPTIONS']) {
                $this->setOptions(Zend_Json::decode($result['OPTIONS']));
            }

            $this->setWhen($result['WHEN']);

            if (isset($result['FILE'])) {
                if ($this->isCopyEditingComment()) {
                    $path = Episciences_PapersManager::buildDocumentPath($this->getDocid());
                    $path .= DIRECTORY_SEPARATOR;
                    $path .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
                    $path .= DIRECTORY_SEPARATOR;
                    $path .= $this->getPcid();
                    $path .= DIRECTORY_SEPARATOR;
                    $this->setFilePath($path);
                } else {
                    $this->setFilePath(sprintf('%s/comments/', Episciences_PapersManager::buildDocumentPath($this->getDocid())));
                }

            }

            return $result;

        }

        return null;
    }


    /**
     * @return array
     */
    public function toArray()
    {
        $result = [];

        $fields = [
            'pcid',
            'parentId',
            'type',
            'docid',
            'uid',
            'message',
            'file',
            'when'
        ];

        foreach ($fields as $key) {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                $result[$key] = $this->$method();
            }
        }

        return $result;
    }

    /**
     * save a comment
     * strict = false pour :
     * L'insertion d'un commentaire à la soumission d'un article.
     * Eviter aussi  l'écrasement de l'ancien fichier lors de l'edition de ce dernier.
     * @param bool $strict
     * @param int|null $uid
     * @param bool $ignoreUpload // [true] the attached file has already been uploaded
     * @return bool
     */
    public function save(bool $strict = false, int $uid = null, bool $ignoreUpload = false): bool
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $result = false;

        if (!$ignoreUpload) {
            $this->uploadFileComment($strict);
        }

        try {
            if (!$this->getPcid()) { // INSERT

                try {
                    if ($this->insertComment($db, $uid)) {
                        $this->setPcid($db->lastInsertId());
                        $this->find($this->getPcid());
                        $result = true;
                    }
                } catch (Zend_Db_Adapter_Exception|Zend_Json_Exception  $e) {
                    trigger_error($e->getMessage());
                }

            } elseif ($this->updateComment($db)) {
                $result = true;
            }
        } catch (Zend_Db_Adapter_Exception $e) {
            trigger_error($e->getMessage());
        }
        // not log here if copy editing comment
        if (
            $result &&
            (!$this->isCopyEditingComment() && !in_array($this->getType(), $this->_excludedCommentsTypes, true))
        ) {
            $this->logComment();
        }

        return $result;
    }

    /**
     * Delete by comment ID
     * @return bool
     */
    public function delete()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        try {
            $db->delete(T_PAPER_COMMENTS, ['PCID = ?' => $this->getPcid()]);
        } catch (Zend_Db_Statement_Exception $exception) {
            return false;
        }

        return true;
    }


    /**
     * Delete by Docid
     * @return bool
     */
    public function deleteByDocid()
    {
        return Episciences_CommentsManager::deleteByDocid($this->getDocid());
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->_path;
    }

    /**
     * @param string $path
     * @return Episciences_Comment
     */
    public function setFilePath(string $path): Episciences_Comment
    {
        $this->_path = $path;
        return $this;
    }

    /**
     * @return int
     */
    public function getPcid()
    {
        return $this->_pcid;
    }

    /**
     * @return int
     */
    public function getParentid()
    {
        return $this->_parentId;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->_type;
    }

    /**
     * @return int
     */
    public function getDocid() :int
    {
        return $this->_docId;
    }

    /**
     * @return int
     */
    public function getUid()
    {
        return $this->_uid;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        if ($this->_message !== null) {
            return Episciences_Tools::epi_html_decode(html_entity_decode($this->_message));
        }

        return null;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * @return null | string
     */
    public function getDeadline()
    {
        return $this->_deadline;
    }

    public function getWhen()
    {
        return $this->_when;
    }

    public function isCopyEditingComment(): bool
    {

        if (!$this->_isCopyEditingComment && in_array($this->getType(), array_merge(Episciences_CommentsManager::$_copyEditingRequestTypes, Episciences_CommentsManager::$_copyEditingAnswerTypes), true)) {
            $this->setCopyEditingComment(true);
        }

        return $this->_isCopyEditingComment;
    }

    /**
     * @param string $file
     * @return Episciences_Comment
     */
    public function setFile($file): Episciences_Comment
    {
        $this->_file = $file;
        return $this;
    }

    /**
     * @param string $deadline
     * @return Episciences_Comment
     */
    public function setDeadline(?string $deadline = null): Episciences_Comment
    {
        $this->_deadline = $deadline;
        return $this;
    }

    public function hasOptions()
    {
        return (!empty($this->getOptions()));
    }

    public function getOptions()
    {
        return $this->_options;
    }

    public function setOptions($options)
    {
        $this->_options = $options;
    }

    /**
     * @param int $pcid
     * @return Episciences_Comment
     */
    public function setPcid(int $pcid): Episciences_Comment
    {
        $this->_pcid = $pcid;
        return $this;
    }

    /**
     * @param $parentId
     * @return $this
     */
    public function setParentid($parentId)
    {
        $this->_parentId = $parentId;
        return $this;
    }

    /**
     * @param int $type
     * @return Episciences_Comment
     */
    public function setType(int $type): Episciences_Comment
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * @param int $docId
     * @return Episciences_Comment
     */
    public function setDocid(int $docId): Episciences_Comment
    {
        $this->_docId = $docId;
        return $this;
    }

    /**
     * @param int $uid
     * @return Episciences_Comment
     */
    public function setUid(int $uid): Episciences_Comment
    {
        $this->_uid = $uid;
        return $this;
    }

    /**
     * @param string|null $message
     * @return Episciences_Comment
     */
    public function setMessage(?string $message = null): Episciences_Comment
    {
        if ($message !== null) {
            $this->_message = htmlspecialchars(Episciences_Tools::epi_html_decode(trim($message)));
        }

        return $this;
    }

    public function setWhen($when)
    {
        $this->_when = $when;
        return $this;
    }

    public function setCopyEditingComment(bool $isCopyEditingComment): Episciences_Comment
    {
        $this->_isCopyEditingComment = $isCopyEditingComment;
        return $this;
    }

    /**
     * log comment
     */

    public function logComment(): void
    {
        $type = $this->getType();
        $detail = ['user' => Episciences_Auth::getUser()->toArray(), 'comment' => $this->toArray()];

        try {
            /** @var Episciences_Paper $paper */
            $paper = Episciences_PapersManager::get($this->getDocid(), false);


            if ($this->isCopyEditingComment() && !in_array($this->getType(), Episciences_CommentsManager::$_copyEditingAnswerTypes)) { // user == submitter
                // add contributor infos
                $submitter = new Episciences_User();
                $submitter->findWithCAS($paper->getUid());
                $detail['submitter'] = $submitter->toArray();
            }

            // Copy editing comments
            switch ($type) {
                case Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST :
                    $action = Episciences_Paper_Logger::CODE_CE_AUTHOR_SOURCES_REQUEST;
                    break;
                case Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER:
                    $action = Episciences_Paper_Logger::CODE_CE_AUTHOR_SOURCES_DEPOSED;
                    break;
                case Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST:
                    $action = Episciences_Paper_Logger::CODE_CE_AUTHOR_FINALE_VERSION_REQUEST;
                    break;
                case Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER :
                    $action = Episciences_Paper_Logger::CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED;
                    break;
                case Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST :
                    $action = Episciences_Paper_Logger::CODE_CE_READY_TO_PUBLISH;
                    break;
                case Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST:
                    $action = Episciences_Paper_Logger::CODE_CE_REVIEW_FORMATTING_DEPOSED;
                    break;
                case Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED:
                    $action = Episciences_Paper_Logger::CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED;
                    break;
                case  Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION :
                    $action = Episciences_Paper_Logger::CODE_REVISION_REQUEST_NEW_VERSION;
                    break;
                case Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION :
                    $action = Episciences_Paper_Logger::CODE_REVISION_REQUEST_TMP_VERSION;
                    break;
                case Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT :
                    $action = Episciences_Paper_Logger::CODE_REVISION_REQUEST_ANSWER;
                    break;
                case Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED:
                    $action = Episciences_Paper_Logger::CODE_MONITORING_REFUSED;
                    break;
                case Episciences_CommentsManager::TYPE_AUTHOR_COMMENT:
                    $action = Episciences_Paper_Logger::CODE_AUTHOR_COMMENT_COVER_LETTER;
                    break;

                case Episciences_CommentsManager::TYPE_INFO_REQUEST:
                    $action = Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR;
                    break;

                case Episciences_CommentsManager::TYPE_INFO_ANSWER :
                    $action = Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER;
                    break;

                case Episciences_CommentsManager::TYPE_EDITOR_COMMENT:
                    $action = Episciences_Paper_Logger::CODE_EDITOR_COMMENT;
                    break;

                case Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION:
                    $action = Episciences_Paper_Logger::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION;
                    break;

                default: // todo vérifier les anciennes actions et les logs dans les controlleurs pour eviter la duplication de ces dernier ; aussi les autres actions à personaliser
                    $action = Episciences_Paper_Logger::CODE_NEW_PAPER_COMMENT; // default action log
            }

            if (!$paper->log($action, Episciences_Auth::getUid(), $detail)) {
                try {
                    $data = json_encode($this->toArray(), JSON_THROW_ON_ERROR);

                } catch (Exception $e) {
                    $data = '';
                    trigger_error($e->getMessage());
                }

                trigger_error('FAILED_TO_LOG_COMMENT_DETAILS: ' . $data);
            }
        } catch (Exception $exp) {
            trigger_error('NO_PAPER_ASSOCIATED_WITH_COMMENT_ID: ' . $this->getPcid());
        }
    }

    /**
     * @param Zend_Db_Adapter_Abstract $db
     * @param int|null $uid
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    private function insertComment(Zend_Db_Adapter_Abstract $db, int $uid = null): bool
    {
        $values = [
            'PARENTID' => $this->getParentid(),
            'TYPE' => $this->getType(),
            'DOCID' => $this->getDocid(),
            'UID' => !$uid ? Episciences_Auth::getUid() : $uid,
            'MESSAGE' => $this->getMessage(),
            'FILE' => $this->getFile(),
            'DEADLINE' => $this->getDeadline(),
            'OPTIONS' => ($this->hasOptions()) ? Zend_Json::encode($this->getOptions()) : null,
            'WHEN' => new Zend_Db_Expr('NOW()')
        ];

        if (!$db->insert(T_PAPER_COMMENTS, $values)) {
            return false;
        }

        return true;
    }

    /**
     * @param Zend_Db_Adapter_Abstract $db
     * @return bool
     *
     * @throws Zend_Db_Adapter_Exception
     */
    private function updateComment(Zend_Db_Adapter_Abstract $db): bool
    {
        $values = [
            'MESSAGE' => $this->getMessage(),
            'FILE' => $this->getFile(),
            'WHEN' => new Zend_Db_Expr('NOW()')
        ];

        if ($this->getDeadline()) {

            $values['DEADLINE'] = $this->getDeadline();

            $options = $this->getOptions();

            if (!empty($options)){
                $values['OPTIONS'] = Zend_Json::encode($options);

            }

        }

        if (!$db->update(T_PAPER_COMMENTS, $values, ['PCID = ?' => $this->getPcid()])) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $strict
     */
    private function uploadFileComment(bool $strict): void
    {
        if (!$strict && !$this->_isCopyEditingComment && !empty($path = $this->getFilePath())) {
            $uploads = Episciences_Tools::uploadFiles($path);
            if ($uploads) {
                $file = array_shift($uploads);
                if (!$file['errors']) {
                    $this->setFile($file['name']);
                }
            }
        }

    }

    public function isSuggestion(): bool
    {
        return in_array($this->getType(), Episciences_CommentsManager::$suggestionTypes, true);
    }

    public function isEditorComment(): bool
    {
        return $this->getType() === Episciences_CommentsManager::TYPE_EDITOR_COMMENT;
    }


}
