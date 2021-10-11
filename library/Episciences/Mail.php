<?php

class Episciences_Mail extends Zend_Mail
{
    /**
     * We're fine
     */
    const STATUS_SUCCESS = 1;
    /**
     * invalid working directory
     */
    const STATUS_FAILED_INVALID_DIR = 2;
    /**
     * no recipients
     */
    const STATUS_FAILED_NO_RECIPIENTS = 3;
    /**
     * mail storage folder creation failed
     */
    const STATUS_FAILED_MAIL_STORAGE_CREATION = 4;
    /**
     * xml file creation failed
     */
    const STATUS_FAILED_XML_FILE_CREATION = 5;
    /**
     * xml file could not be written
     */
    const STATUS_FAILED_XML_FILE_NO_WRITTEN = 6;
    const HEADER_REPLY_TO = 'Reply-To';
    const HEADER_RETURN_PATH = 'Return-Path';
    const HEADER_TO = 'To';
    const HEADER_CC = 'Cc';
    const HEADER_BCC = 'Bcc';
    const HEADER_DISPOSITION_NOTIFICATION_TO = 'Disposition-Notification-To';
    const HEADER_FROM = 'From';
    const STATUS_FAILED_DB_LOG = 7;
    protected $path = null;
    protected $attachments = [];
    protected $_templatePath;
    protected $_templateName;
    protected $tags = [];
    private $_id;
    private $_rvid;
    private $_docid;
    private $_sendDate;
    private $_rawBody;

    /**
     * Episciences_Mail constructor.
     * @param null $charset
     * @throws Zend_Mail_Exception
     */
    public function __construct($charset = null)
    {
        if (isset($charset)) {
            parent::__construct($charset);
        }

        $this->setPath(EPISCIENCES_MAIL_PATH);


        if (defined('RVCODE')) {
            $this->addTag(Episciences_Mail_Tags::TAG_REVIEW_CODE, RVCODE);
        }
        if (defined('RVNAME')) {
            $this->addTag(Episciences_Mail_Tags::TAG_REVIEW_NAME, RVNAME);
        }
        if (php_sapi_name() !== 'cli' && Episciences_Auth::isLogged()) {
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME, Episciences_Auth::getScreenName());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, Episciences_Auth::getEmail());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, Episciences_Auth::getFullName());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FIRST_NAME, Episciences_Auth::getFirstname());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_LAST_NAME, Episciences_Auth::getLastname());

        }
        $this->setReturnPath('error@' . DOMAIN);
    }

    /**
     * Email path used for writing email
     * @param string $path
     * @throws Exception
     */
    public function setPath($path = '')
    {
        if ($path) {
            $this->path = $path;
            $this->checkAppDirectory();
        }
    }

    /**
     * check if application folders exist, and create them if they don't
     * @throws Exception
     */
    private function checkAppDirectory()
    {
        $folders = [
            $this->path,
            $this->path . 'unsent/',
            $this->path . 'sent/',
            $this->path . 'log/',
            $this->path . 'debug/'
        ];

        foreach ($folders as $folder) {
            if (!is_dir($folder) && !mkdir($folder) && !is_dir($folder)) {
                throw new Exception('Storage folder creation failed in: ' . $folder, self::STATUS_FAILED_MAIL_STORAGE_CREATION);
            }
        }
    }

    public function addTag($name, $value)
    {
        $this->tags[$name] = $value;
    }

    /**
     * set from header : rvcode@episciences.org
     * set reply-to header : noreply@episciences.org
     * @throws Zend_Mail_Exception
     */
    public function setFromReview()
    {
        $this->setFrom(RVCODE . '@' . DOMAIN, RVCODE);
        $this->setReplyTo('noreply@' . DOMAIN);
    }

    /**
     * set an unique recipient from an Episciences_User, and set recipient tags
     * @param Episciences_User $recipient
     * @return bool
     */
    public function setTo(Episciences_User $recipient)
    {
        if (empty($recipient->getEmail())) {
            return false;
        }

        $lostLoginLink = HTTP . '://' . $_SERVER['SERVER_NAME'] . '/user/lostlogin';

        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL, $recipient->getEmail());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME, $recipient->getFullName());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME, $recipient->getScreenName());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME, $recipient->getUsername());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, $lostLoginLink);
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FIRST_NAME, $recipient->getFirstname());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_LAST_NAME, $recipient->getLastname());

        parent::addTo($recipient->getEmail(), $recipient->getFullName());

        return true;

    }

    /**
     * @param array|false|string|null $rvCode
     * @param bool $debug
     * @return bool
     * @throws Zend_Mail_Exception
     */
    public function writeMail($rvCode = RVCODE, $debug = false)
    {


        if (!$this->getFrom()) {
            if (php_sapi_name() !== 'cli' && Episciences_Auth::isLogged()) {
                $this->setFromWithTags(Episciences_Auth::getUser());
            } else {
                $this->setFrom(RVCODE . '@' . DOMAIN, RVCODE);
            }
        }


        if (!$this->getReplyTo()) {
            $tmp = explode(' ', $this->getHeaders()[self::HEADER_FROM][0]);
            $mail = array_pop($tmp);
            $name = (count($tmp)) ? implode(' ', $tmp) : null;
            $this->setReplyTo($mail, $name);
        }

        if (APPLICATION_ENV === ENV_DEV && !Ccsd_Tools::isFromCli()) {
            $session = new Zend_Session_Namespace();
            $session->mail = $this;
        }

        try {
            $this->write($debug);
        } catch (Exception $e) {
            $details = [
                'headers' => $this->getHeaders(),
                'subject' => $this->getSubject(),
                'body' => ($this->hasATemplate())
                    ? $this->renderTemplate($this->getTemplatePath(), $this->getTemplateName())
                    : $this->replaceTags($this->getRawBody())];
            $message = $e->getCode() . ' - ' . $e->getMessage() . ' - ' . Zend_Json::encode($details);
            Ccsd_Log::message($message, false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . $rvCode . '.mail');
            return false;
        }

        try {
            if (!$debug) {
                $id = $this->log();
                $this->setId($id);
            }
        } catch (Exception $e) {
            Ccsd_Log::message($e->getMessage(), false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . '.mail');
        }

        return true;
    }

    /**
     * set an unique sender from an Episciences_User, set reply-tp, and set sender tags
     * @param Episciences_User $sender
     * @return bool
     * @throws Zend_Mail_Exception
     */
    public function setFromWithTags(Episciences_User $sender)
    {
        if (empty($sender->getEmail())) {
            return false;
        }

        if (!$this->getReplyTo()) {
            $this->setReplyTo($sender->getEmail(), $sender->getFullName());
        }

        $this->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, $sender->getEmail());
        $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, $sender->getFullName());
        $this->addTag(Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME, $sender->getScreenName());
        $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FIRST_NAME, $sender->getFirstname());
        $this->addTag(Episciences_Mail_Tags::TAG_SENDER_LAST_NAME, $sender->getLastname());

        $this->setFrom(RVCODE . '@' . DOMAIN, $sender->getFullName());

        return true;
    }

    /** create an email as an xml file
     * if debug is false, it will be located in path/unsent
     * if debug is true, it will be located in path/debug
     * path/unsent will be processed later by a script, which will send the mail
     * path/debug won't be processed, so the mail won't be really sent
     * @param bool $debug
     * @return bool
     * @throws Exception
     */
    public function write($debug = false)
    {
        if (null == $this->path) {
            throw new Exception('Invalid working directory', self::STATUS_FAILED_INVALID_DIR);
        }


        $headers = $this->getHeaders();

        if (!isset($headers[self::HEADER_TO]) && !isset($headers[self::HEADER_CC]) && !isset($headers[self::HEADER_BCC])) {
            throw new Zend_Mail_Exception('No recipient', self::STATUS_FAILED_NO_RECIPIENTS);
        }

        // create mail storage folder
        $storage_path = ($debug) ? $this->path . 'debug/' : $this->path . 'unsent/';
        $mailDirectory = $this->createMailDirectory($storage_path);

        if (!$mailDirectory) {
            throw new Exception('Storage folder creation failed in: ' . $storage_path, self::STATUS_FAILED_MAIL_STORAGE_CREATION);
        }

        // init XML
        $xmlString = '<?xml version="1.0"?>' . PHP_EOL;

        $xmlString .= '<mail errors="0" charset="' . $this->getHeaderEncoding() . '">' . PHP_EOL;

        if (isset($headers[self::HEADER_FROM])) {
            $xmlString .= $this->extractSingle($headers, self::HEADER_FROM);
        }
        if (isset($headers[self::HEADER_REPLY_TO])) {
            $xmlString .= $this->extractSingle($headers, self::HEADER_REPLY_TO);
        }
        if (isset($headers[self::HEADER_RETURN_PATH])) {
            $xmlString .= $this->extractSingle($headers, self::HEADER_RETURN_PATH);
        }
        if (isset($headers[self::HEADER_TO])) {
            $xmlString .= $this->extractList($headers, self::HEADER_TO);
        }
        if (isset($headers[self::HEADER_CC])) {
            $xmlString .= $this->extractList($headers, self::HEADER_CC);
        }
        if (isset($headers[self::HEADER_BCC])) {
            $xmlString .= $this->extractList($headers, self::HEADER_BCC);
        }
        if (isset($headers[self::HEADER_DISPOSITION_NOTIFICATION_TO])) {
            $xmlString .= $this->extractSingle($headers, self::HEADER_DISPOSITION_NOTIFICATION_TO);
        }
        $subject = $this->getDecodedSubject();
        if ($subject) {
            $xmlString .= "\t" . '<subject>' . htmlspecialchars($subject) . '</subject>' . PHP_EOL;
        }

        $xmlString .= "\t" . '<bodyHtml charset="UTF-8">' . $this->getBody() . '</bodyHtml>' . PHP_EOL;


        // attachments
        if (count($this->attachments)) {
            $xmlString .= "\t" . '<files_list>' . PHP_EOL;
            foreach ($this->attachments as $attachment) {
                if (is_array($attachment)) {
                    $filepath = (array_key_exists('path', $attachment)) ? $attachment['path'] : null;
                    $filename = (array_key_exists('name', $attachment)) ? $attachment['name'] : null;
                } else {
                    $filepath = $attachment;
                    $filename = pathinfo($filepath)['basename'];
                }
                if (is_file($filepath)) {
                    copy($filepath, $storage_path . $mailDirectory . '/' . $filename);
                    $xmlString .= "\t\t" . '<file>' . $filename . '</file>' . PHP_EOL;
                }
            }
            $xmlString .= "\t" . '</files_list>' . PHP_EOL;
        }

        $xmlString .= '</mail>' . PHP_EOL;

        // create xml file
        $xmlFile = fopen($storage_path . $mailDirectory . '/mail.xml', 'w');
        if ($xmlFile) {
            if (fwrite($xmlFile, $xmlString)) {
                fclose($xmlFile);
                return true;
            }

            fclose($xmlFile);
            rmdir($storage_path . $mailDirectory);
            throw new Exception("Failed to write XML file.", self::STATUS_FAILED_XML_FILE_NO_WRITTEN);
        }

        rmdir($storage_path . $mailDirectory);
        throw new Exception("Failed to create XML file.", self::STATUS_FAILED_XML_FILE_CREATION);
    }

    /**
     * create a storage folder for this e-mail, as a subfolder of given path
     * @param string $path
     * @return bool|string
     */
    private function createMailDirectory(string $path)
    {
        $mailDirectory = uniqid(gethostname() . '_', true);
        if (mkdir($concurrentDirectory = $path . $mailDirectory, 0777, true) || !is_dir($concurrentDirectory)) {
            return $mailDirectory;
        }
        return false;
    }

    /**
     * @param $value
     * @param $fieldname
     * @return string
     */
    private function extractSingle($value, $fieldname)
    {
        $value = $value[$fieldname][0];
        $xmlString = "\t";

        preg_match('#(.*)\s\s*<(.*)>#', $value, $result);
        if ($result) {
            $result[1] = iconv_mime_decode($result[1], 0, 'UTF-8');
            $result[1] = htmlspecialchars($result[1]);
            $xmlString .= '<' . strtolower($fieldname) . '><name>' . trim($result[1]) . '</name><mail>' . trim($result[2]) . '</mail></' . strtolower($fieldname) . '>';
        } else {
            $xmlString .= '<' . strtolower($fieldname) . '><mail>' . trim($value) . '</mail></' . strtolower($fieldname) . '>';
        }

        return $xmlString . PHP_EOL;
    }

    private function extractList($array, $fieldname)
    {
        $xmlString = "\t<" . strtolower($fieldname) . '_list>' . PHP_EOL;
        $tmpString = '';

        foreach ($array[$fieldname] as $key => $value) {
            if (is_numeric($key)) {
                $tmpString .= "\t\t";
                preg_match('#(.*)\s\s*<(.*)>#', $value, $result);
                if ($result) {
                    $result[1] = iconv_mime_decode($result[1], 0, 'UTF-8');
                    $result[1] = htmlspecialchars($result[1]);
                    $tmpString .= '<' . strtolower($fieldname) . '><name>' . trim($result[1]) . '</name><mail>' . trim($result[2]) . '</mail></' . strtolower($fieldname) . '>';
                } else {
                    $tmpString .= '<' . strtolower($fieldname) . '><mail>' . trim($value) . '</mail></' . strtolower($fieldname) . '>';
                }
                $tmpString .= PHP_EOL;
            }
        }
        $xmlString .= $tmpString;
        return $xmlString . "\t</" . strtolower($fieldname) . '_list>' . PHP_EOL;
    }

    public function getDecodedSubject()
    {
        return iconv_mime_decode($this->getSubject(), 0, 'UTF-8');
    }

    /**
     * Retourne le contenu du mail (pour le débug en développement)
     * @return string
     */
    public function getBody()
    {

        if ($this->hasATemplate()) {
            $body = $this->renderTemplate($this->getTemplatePath(), $this->getTemplateName());
        } else {
            $body = $this->replaceTags($this->getRawBody());
        }

        return htmlspecialchars($body);
    }

    /**
     * @return bool
     */
    protected function hasATemplate(): bool
    {
        $templatePath = $this->getTemplatePath();
        $templateName = $this->getTemplateName();

        return isset($templatePath, $templateName) && is_file($templatePath . '/' . $templateName);
    }

    public function getTemplatePath()
    {
        return $this->_templatePath;
    }

    public function getTemplateName()
    {
        return $this->_templateName;
    }

    public function renderTemplate($templatePath, $templateName)
    {
        $templateContent = file_get_contents($templatePath . '/' . $templateName);

        if (!$templateContent) {
            return null;
        }
        return $this->replaceTags($templateContent);
    }

    /**
     * @param $text
     * @return mixed
     */
    public function replaceTags($text)
    {
        $myTags = $this->getTags();
        $text = str_replace(array_keys($myTags), array_values($myTags), $text);
        $text = nl2br($text);
        $text = Ccsd_Tools::clear_nl($text);
        return $this->cleanRemainingTags($text);
    }

    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Suppression des tags non remplacés
     * @param $text
     * @return mixed
     */
    public function cleanRemainingTags($text)
    {
        return preg_replace('/%%[[:alnum:]_]+%%/', "", $text);
    }

    public function getRawBody()
    {
        if (isset($this->_rawBody)) {
            return $this->_rawBody;
        }

        return null;
    }

    public function setRawBody($body)
    {
        $this->_rawBody = $body;
    }

    /**
     * log mail to database
     * @return string
     * @throws Exception
     * @noinspection ForgottenDebugOutputInspection
     */
    private function log()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $headers = $this->getHeaders();

        // FROM
        $keys = array_flip(array_filter(array_keys($headers[self::HEADER_FROM]), static function ($k) {
            return is_int($k);
        }));
        $from = implode(';', array_intersect_key($headers[self::HEADER_FROM], $keys));

        // REPLYTO
        $keys = array_flip(array_filter(array_keys($headers[self::HEADER_REPLY_TO]), static function ($k) {
            return is_int($k);
        }));
        $replyto = implode(';', array_intersect_key($headers[self::HEADER_REPLY_TO], $keys));

        // TO
        $keys = array_flip(array_filter(array_keys($headers[self::HEADER_TO]), static function ($k) {
            return is_int($k);
        }));
        $to = implode(';', array_intersect_key($headers[self::HEADER_TO], $keys));

        // CC
        if (array_key_exists(self::HEADER_CC, $headers) && $headers[self::HEADER_CC]) {
            $keys = array_flip(array_filter(array_keys($headers[self::HEADER_CC]), static function ($k) {
                return is_int($k);
            }));
            $cc = implode(';', array_intersect_key($headers[self::HEADER_CC], $keys));
            $cc = iconv_mime_decode($cc, 0, 'UTF-8');
        } else {
            $cc = null;
        }

        // BCC
        if (array_key_exists(self::HEADER_BCC, $headers) && $headers[self::HEADER_BCC]) {
            $keys = array_flip(array_filter(array_keys($headers[self::HEADER_BCC]), static function ($k) {
                return is_int($k);
            }));
            $bcc = implode(';', array_intersect_key($headers[self::HEADER_BCC], $keys));
            $bcc = iconv_mime_decode($bcc, 0, 'UTF-8');
        } else {
            $bcc = null;
        }

        // ATTACHMENTS
        $attachments = $this->getAttachments();
        if ($attachments) {
            foreach ($attachments as &$attachment) {
                $path = (is_array($attachment)) ? $attachment['path'] : $attachment;
                $attachment = $this->getAttachmentRelativePath($path);
            }
            $attachments = Zend_Json::encode($attachments);
        } else {
            $attachments = null;
        }

        $data = [
            'RVID' => $this->getRvid(),
            'DOCID' => $this->getDocid(),
            'FROM' => iconv_mime_decode($from, 0, 'UTF-8'),
            'REPLYTO' => iconv_mime_decode($replyto, 0, 'UTF-8'),
            'TO' => iconv_mime_decode($to, 0, 'UTF-8'),
            'CC' => $cc,
            'BCC' => $bcc,
            'SUBJECT' => $this->getDecodedSubject(),
            'CONTENT' => $this->getDecodedBody(),
            'FILES' => $attachments,
            'WHEN' => new Zend_DB_Expr('NOW()')
        ];

        if ($db->insert(T_MAIL_LOG, $data)) {
            return $db->lastInsertId();
        }

        error_log('Logging email in db failed.');
        $message = "Database logging failed - " . Zend_Json::encode($data);
        throw new Exception($message, self::STATUS_FAILED_DB_LOG);
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function getAttachmentRelativePath($path)
    {
        return str_replace(REVIEW_FILES_PATH, '', realpath($path));
    }

    public function getRvid()
    {
        if ($this->_rvid) {
            return $this->_rvid;
        }
        if (defined('RVID')) {
            return RVID;
        }

        return null;
    }

    public function setRvid($rvid)
    {
        $this->_rvid = $rvid;
        return $this;
    }

    public function getDocid()
    {
        return $this->_docid;
    }

    public function setDocid($docid)
    {
        $this->_docid = $docid;

        $this->addTag(Episciences_Mail_Tags::TAG_PAPER_ID, $docid);

        if (defined('RVCODE')) {
            $baseurl = HTTP . '://' . RVCODE . '.' . DOMAIN;
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_ADMINISTRATION_URL, $baseurl . '/administratepaper/view/id/' . $docid);
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_VIEW_URL, $baseurl . '/' . $docid);
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_RATING_URL, $baseurl . '/paper/rating/id/' . $docid);
        }

        return $this;
    }

    public function getDecodedBody()
    {
        return htmlspecialchars_decode($this->getBody());
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($id)
    {
        if (is_numeric($id)) {
            $this->_id = $id;
        }
        return $this;
    }

    /**
     * @param bool $viewOnly if the returned array is used for display not saving in DB
     * @return array
     */
    public function toArray($viewOnly = false)
    {
        $result = [];
        $headers = $this->getHeaders();

        foreach ($headers as $name => $header) {
            if (array_key_exists('append', $header)) {
                unset($header['append']);
            }
            if (is_array($header)) {
                foreach ($header as $k => &$v) {
                    $v = iconv_mime_decode($v, 0, 'UTF-8');
                }
                if ($name === 'Subject') {
                    $result[$name] = $this->getDecodedSubject();
                } else {
                    $result[$name] = $header;
                }
            } else {
                $result[$name] = iconv_mime_decode($header, 0, 'UTF-8');
            }
        }

        $html = new \Html2Text\Html2Text($this->getBody());

        $result['bodyText'] = $html->getText();
        if ($viewOnly) {
            $result['bodyHtml'] = htmlspecialchars_decode($this->getBodyHtml(true));
        } else {
            $result['bodyHtml'] = htmlspecialchars_decode($this->getBody());
        }
        $result['attachments'] = $this->getAttachments();
        $result['sendDate'] = $this->getSendDate();

        return $result;
    }

    public function getSendDate()
    {
        return $this->_sendDate;
    }

    public function setSendDate($date)
    {
        $this->_sendDate = $date;
        return $this;
    }

    /**
     * Retourne l'historique des mails
     * @param null $docId
     * @param array $options
     * @param bool $isFilterInfos
     * @return array
     */

    public function getHistory($docId = null, array $options = [], bool $isFilterInfos = false): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $this->getHistoryQuery($docId, $options, false, $isFilterInfos);

        // limit
        if (array_key_exists('offset', $options) && array_key_exists('limit', $options)) {
            $sql->limit($options['limit'], $options['offset']);
        } else if (array_key_exists('limit', $options)) {
            $sql->limit($options['limit']);
        }

        // order
        if (array_key_exists('order', $options)) {
            if (is_array($options['order'])) {
                foreach ($options['order'] as $option => $value) {
                    $sql->order(strtoupper($value));
                }
            } else {
                $sql->order(strtoupper($options['order']));
            }
        }

        return $db->fetchAssoc($sql);
    }

    /**
     * @param null $docId
     * @param array $options
     * @param bool $isCount
     * @param bool $isFilterInfos
     * @return Zend_Db_Select
     */
    private function getHistoryQuery($docId = null, array $options = [], bool $isCount = false, bool $isFilterInfos = false): \Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $isCoiEnabled = isset($options['isCoiEnabled']) && $options['isCoiEnabled'];

        $sql = (!$isCount) ?
            $db->select()->from(T_MAIL_LOG) :
            $db->select()->from(T_MAIL_LOG, [new Zend_Db_Expr("COUNT(*)")]);

        $sql->where('RVID = ?', $this->getRvid());

        if (is_array($docId) && !empty($docId)) {

            if (!$isCoiEnabled) {

                $sql->where('DOCID IS NULL OR DOCID IN (?)', $docId);

            } else {
                $sql->where('DOCID IN (?)', $docId);
            }

        } elseif ($docId) {

            if ($isCoiEnabled) {
                $sql->where('DOCID IS NULL OR DOCID = ?', $docId);

            } else {
                $sql->where('DOCID = ?', $docId);
            }

        } else {
            (!$isCoiEnabled) ? $sql->where('DOCID IS NULL') : $sql->where('DOCID = ?', 0); // fix Empty IN clause parameter list in MySQL
            $sql = $this->dataTableMailsSearchQuery($sql, $options['search']);
        }

        return $sql;
    }

    /**
     * @param Zend_Db_Select $select
     * @param String $word
     * @return Zend_Db_Select
     */
    private function dataTableMailsSearchQuery(Zend_Db_Select $select, string $word = ''): Zend_Db_Select
    {
        $where = "SUBJECT LIKE '%$word%' OR `TO` LIKE '%$word%' OR CC LIKE '%$word%' OR BCC LIKE '%$word%' OR CONVERT(`WHEN`, CHAR) LIKE '%$word%'";
        $select->where($where);
        return $select;
    }

    /**
     * Retourne le nombre de lignes selectionnées
     * @param array $docIds
     * @param array $options
     * @param bool $isFilterInfos
     * @return int
     */
    public function getCountHistory($docIds, array $options = [], bool $isFilterInfos = false): int
    {
        $select = $this->getHistoryQuery($docIds, $options, true, $isFilterInfos);
        return (int)Zend_Db_Table_Abstract::getDefaultAdapter()->fetchOne($select);
    }

    /**
     * @param $id
     * @return $this|null
     * @throws Zend_Mail_Exception
     */
    public function find($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_MAIL_LOG)->where('ID = ?', $id);
        $data = $db->fetchRow($sql);

        if ($data) {

            $recipients = explode(';', $data['TO']);
            foreach ($recipients as $recipient) {
                $tmp = explode(' ', $recipient);
                $mail = array_pop($tmp);
                $name = (count($tmp)) ? implode(' ', $tmp) : null;
                $this->addTo($mail, $name);
            }

            if ($data['CC']) {
                $recipients = explode(';', $data['CC']);
                foreach ($recipients as $recipient) {
                    $tmp = explode(' ', $recipient);
                    $mail = array_pop($tmp);
                    $name = (count($tmp)) ? implode(' ', $tmp) : null;
                    $this->addCc($mail, $name);
                }
            }

            if ($data['BCC']) {
                $recipients = explode(';', $data['BCC']);
                foreach ($recipients as $recipient) {
                    $tmp = explode(' ', $recipient);
                    $mail = array_pop($tmp);
                    $this->addBcc($mail);
                }
            }

            if ($data['FROM']) {
                $tmp = explode(' ', $data['FROM']);
                $mail = array_pop($tmp);
                $name = (count($tmp)) ? implode(' ', $tmp) : null;
                $this->setFrom($mail, $name);
            }

            if ($data['REPLYTO']) {
                $tmp = explode(' ', $data['REPLYTO']);
                $mail = array_pop($tmp);
                $name = (count($tmp)) ? implode(' ', $tmp) : null;
                $this->setReplyTo($mail, $name);
            }

            if (isset($data['FILES'])) {
                $attachments = json_decode($data['FILES'], true);
                foreach ($attachments as $attachment) {
                    $this->addAttachedFile(REVIEW_FILES_PATH . $attachment);
                }
            }

            $this->setSubject($data['SUBJECT']);
            $this->setBodyHtml($data['CONTENT']);
            $this->setSendDate($data['WHEN']);
            return $this;

        }

        return null;
    }

    public function addTo($email, $name = '')
    {
        if (empty($email)) {
            return false;
        }
        parent::addTo($email, $name);
    }

    public function addCc($email, $name = '')
    {
        if (empty($email)) {
            return false;
        }
        parent::addCc($email, $name);
    }

    public function addBcc($email)
    {
        if (empty($email)) {
            return false;
        }
        parent::addBcc($email);
    }

    /**
     * Ajout d'un fichier joint au mail
     * @param $attachment string || array
     * $attachment peut être soit un string (chemin du fichier),
     * soit un array : ['name'=>$name, 'path'=>$path]
     */
    public function addAttachedFile($attachment)
    {
        $this->attachments[] = $attachment;
    }

    public function setSubject($subject, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE)
    {
        $subject = htmlspecialchars($subject);
        $subject = $this->replaceTags($subject);
        parent::setSubject($subject);
    }

    /**
     * Delete all mail history of a paper
     * @return bool
     */
    public function deleteByDocid()
    {
        return Episciences_Mail_LogManager::deleteByDocid($this->getDocid());
    }

    public function clearTags()
    {
        $this->tags = [];
    }

    public function setTemplate($templatePath, $templateName)
    {
        $this->_templatePath = $templatePath;
        $this->_templateName = $templateName;
    }


}
