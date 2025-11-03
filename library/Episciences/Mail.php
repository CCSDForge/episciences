<?php

use Episciences\Trait\UrlBuilder;

class Episciences_Mail extends Zend_Mail
{
    use UrlBuilder;
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
    protected bool $_isAutomatic = false;
    private ?int $uid = null;

    /**
     * Episciences_Mail constructor.
     * @param null $charset
     * @throws Zend_Mail_Exception
     * @throws Exception
     */
    public function __construct($charset = null, $rvCode = RVCODE)
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
        if (PHP_SAPI !== 'cli' && Episciences_Auth::isLogged()) {
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME, Episciences_Auth::getScreenName());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, Episciences_Auth::getEmail());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, Episciences_Auth::getFullName());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_FIRST_NAME, Episciences_Auth::getFirstname());
            $this->addTag(Episciences_Mail_Tags::TAG_SENDER_LAST_NAME, Episciences_Auth::getLastname());

        }
        $review = Episciences_ReviewsManager::find($rvCode);
        $review->loadSettings();
        $mailError = $review->getSetting(Episciences_Review::SETTING_CONTACT_ERROR_MAIL);
        if ($mailError === false || $mailError === "0") {
            $this->setReturnPath('error@' . DOMAIN);
        } else {
            $this->setReturnPath($review->getCode() . '-error@' . DOMAIN);
        }
    }

    /**
     * Email path used for writing email
     * @param string $path
     * @throws Exception
     */
    public function setPath(string $path = ''): void
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
    private function checkAppDirectory(): void
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
    public function setFromReview($rvCode = RVCODE): void
    {
        $this->setFrom($rvCode . '@' . DOMAIN, $rvCode);
        $this->setReplyTo('noreply@' . DOMAIN);
    }

    /**
     * set a unique recipient from an Episciences_User, and set recipient tags
     * @param Episciences_User $recipient
     * @param array $options
     * @return bool
     */
    public function setTo(Episciences_User $recipient, array $options = []): bool
    {
        if (empty($recipient->getEmail())) {
            return false;
        }

        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL, $recipient->getEmail());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME, $recipient->getFullName());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME, $recipient->getScreenName());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME, $recipient->getUsername());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, self::buildLostLoginUrl());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FIRST_NAME, $recipient->getFirstname());
        $this->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_LAST_NAME, $recipient->getLastname());

        parent::addTo($recipient->getEmail(), $recipient->getFullName());

        return true;

    }

    /**
     * @param array|false|string|null $rvCode
     * @param int $rvId
     * @param bool $debug
     * @return bool
     * @throws Zend_Mail_Exception
     */
    public function writeMail($rvCode = RVCODE, int $rvId = RVID, bool $debug = false): bool
    {

        if (!$this->getFrom()) {
            if (PHP_SAPI !== 'cli' && Episciences_Auth::isLogged()) {
                $this->setFromWithTags(Episciences_Auth::getUser(), $rvCode);
            } else {
                $this->setFrom($rvCode . '@' . DOMAIN, $rvCode);
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
                $id = $this->log($rvId);
                $this->setId($id);
            }
        } catch (Exception $e) {
            Ccsd_Log::message($e->getMessage(), false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . '.mail');
            throw $e;
        }

        return true;
    }

    /**
     * set an unique sender from an Episciences_User, set reply-tp, and set sender tags
     * @param Episciences_User $sender
     * @param string $rvCode
     * @return bool
     * @throws Zend_Mail_Exception
     */
    public function setFromWithTags(Episciences_User $sender, $rvCode = RVCODE): bool
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

        $this->setFrom($rvCode . '@' . DOMAIN, $sender->getFullName());

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
    public function write($debug = false): bool
    {
        if (null === $this->path) {
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
    private function createMailDirectory(string $path): bool|string
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
    private function extractSingle($value, $fieldname): string
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

    private function extractList($array, $fieldname): string
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

    public function getDecodedSubject(): bool|string
    {
        return iconv_mime_decode($this->getSubject(), 0, 'UTF-8');
    }

    /**
     * Retourne le contenu du mail (pour le débug en développement)
     * @return string
     */
    public function getBody(): string
    {

        if ($this->hasATemplate()) {
            $body = $this->renderTemplate($this->getTemplatePath(), $this->getTemplateName());
        } else {
            $body = $this->replaceTags($this->getRawBody());
        }

        return $body ? htmlspecialchars($body) : '';
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

        if ($text) {
            $myTags = $this->getTags();
            $text = str_replace(array_keys($myTags), array_values($myTags), $text);
            $text = nl2br($text);
            $text = Ccsd_Tools::clear_nl($text);
            $text = $this->cleanRemainingTags($text);
        }

        return $text;
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
     * @param int $rvId
     * @return string
     * @throws Zend_Db_Adapter_Exception
     * @noinspection ForgottenDebugOutputInspection
     */
    private function log(int $rvId = RVID)
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
            unset($attachment);
            $attachments = Zend_Json::encode($attachments);
        } else {
            $attachments = null;
        }

        $data = [
            'UID' => $this->getUid(),
            'RVID' => $rvId ?: $this->getRvid(),
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

        if (defined('APPLICATION_URL')) {
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_ADMINISTRATION_URL, APPLICATION_URL . '/administratepaper/view/id/' . $docid);
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_VIEW_URL, APPLICATION_URL . '/' . $docid);
            $this->addTag(Episciences_Mail_Tags::TAG_PAPER_RATING_URL, APPLICATION_URL . '/paper/rating/id/' . $docid);
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
        $result[Episciences_Mail_Send::ATTACHMENTS] = $this->getAttachments();
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
     * @param array $docIds
     * @param array $options
     * @param bool $isFilterInfos
     * @return array
     */

    public function getHistory(array $docIds = [], array $options = [], bool $isFilterInfos = false): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $this->getHistoryQuery($docIds, $options, false, $isFilterInfos);

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
     * @param array $docIds
     * @param array $options
     * @param bool $isCount
     * @param bool $isFilterInfos : search filter
     * @return Zend_Db_Select
     */
    private function getHistoryQuery(array $docIds = [], array $options = [], bool $isCount = false, bool $isFilterInfos = false): \Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // related to the conflicts of interest option: @sse AdministratemailController::historyProcessing()
        // display the history of articles for which an explicit declaration of no conflict has been reported
        // Note that logging in with another account will override this restriction (if the connected account is affected by the conflict declaration)
        $isStrict = isset($options['strict']) && $options['strict'];

        $sql = (!$isCount) ?
            $db->select()->from(T_MAIL_LOG) :
            $db->select()->from(T_MAIL_LOG, [new Zend_Db_Expr("COUNT(*)")]);

        $sql->where('RVID = ?', $this->getRvid());

        if (!empty($docIds)) {

            $implodedDocId = implode(',', $docIds);
            if (!$isStrict) {
                $sql->where(sprintf('DOCID IS NULL OR DOCID IN (%s) OR UID = %s', $implodedDocId, Episciences_Auth::getUid()));
            } else {
                $sql->where(sprintf('DOCID IN (%s) OR UID = %s', $implodedDocId, Episciences_Auth::getUid()));
            }


        } else {
            !$isStrict ? $sql->where('DOCID IS NULL OR UID = ?', Episciences_Auth::getUid()) : $sql->where('UID = ?', Episciences_Auth::getUid());
        }

        // DataTable search
        if ($isFilterInfos && !empty($options['search'])) {
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
     * @param array $docIds
     * @param array $options
     * @param bool $isFilterInfos
     * @return int : the number of elements
     */
    public function getCountHistory(array $docIds = [], array $options = [], bool $isFilterInfos = false): int
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
     * Ajout d'une pièce jointe
     * @param $attachment string
     * $attachment peut être soit un string (chemin du fichier),
     * soit un array : ['name'=>$name, 'path'=>$path]
     */
    public function addAttachedFile(string $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    public function setSubject($subject = '', $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): void
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

    public function setTemplate($templatePath, $templateName): Episciences_Mail
    {
        $this->_templatePath = $templatePath;
        $this->_templateName = $templateName;

        $templateKey = str_replace([Episciences_Mail_Send::TEMPLATE_EXTENSION, 'custom_'], '', $templateName);

        if (in_array($templateKey, Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES, true)) {

            $this->setIsAutomatic(true);

            foreach (Episciences_Mail_Tags::SENDER_TAGS as $tag) {
                $this->removeTag($tag);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isAutomatic(): bool
    {
        return $this->_isAutomatic;
    }

    /**
     * Fixed: RT #160301:
     *    the tags [%%SENDER_FULL_NAME%%, %%SENDER_SCREEN_NAME%%, %%SENDER_EMAIL%%, %%SENDER_FIRST_NAME%%', %%SENDER_LAST_NAME%% ]
     *    concerning the user of the action are filled with the data of the user connected at the time of the action.
     *    Making these variables available in the automatic mails poses a real problem: they are filled with the data of the mail recipient.
     *    So, from now on, the tags mentioned above will no longer be available in the automatic mail templates.
     * @param string $tag
     * @return void
     */
    private function removeTag(string $tag): void
    {

        if (array_key_exists($tag, $this->getTags())) {
            unset($this->tags[$tag]);
        }

    }

    /**
     * @param bool $isAutomatic
     */
    public function setIsAutomatic(bool $isAutomatic): void
    {
        $this->_isAutomatic = $isAutomatic;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid = null): self
    {
        $this->uid = $uid;
        return $this;
    }


}
