<?php

use Html2Text\Html2Text;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

define('MAX_RETRIES', 5); // Nombre max de tentatives d'envoi du mail avant de le déplacer dans /log
define('SENTMAILDIR', 'sent');
define('UNSENTMAILDIR', 'unsent');
ini_set('sendmail_from', 'contact@' . DOMAIN);

class Episciences_Mail_Sender
{
    const MAIL_ERRORS = 'errors';
    const MAIL = 'mail';
    const NAME = 'name';
    public $mail;
    private $_path;

    /**
     * Send All emails
     * @return bool
     */
    public function sendAll()
    {

        $path = $this->setPath(EPISCIENCES_MAIL_PATH);

        if ($path == '') {
            echo EPISCIENCES_MAIL_PATH . " : Ce chemin n'existe pas\n";
            return false;
        }

        $mail_list = $this->scan($this->getPath() . UNSENTMAILDIR);

        if ($mail_list) {
            foreach ($mail_list as $mail_directory) {
                $message = $this->send($mail_directory);
                $this->updateLog(date("d/m/Y - H:i:s") . ' - ' . $message);
            }
        }
        // Message envoye
        return true;
    }

    /**
     * Scanne un dossier, et renvoie la liste des dossiers qu'il contient
     * @param string $path
     * @return array
     */
    private function scan(string $path): array
    {
        $dirList = [];

        if (!is_dir($path)) {
            return [];
        }

        $dir = new DirectoryIterator($path);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $dirList[] = $fileinfo->getFilename();
            }
        }

        return $dirList;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }


    public function setPath(string $path): string
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            return '';
        }

        $this->_path = $path;
        return $path;
    }

    /**
     * Procède à l'envoi du mail
     * Le paramètre est le chemin du dossier contenant le mail
     * Status Codes
     * 2.1 : Le chemin spécifié n'existe pas
     * 2.2 : Le fichier XML n'existe pas
     * 2.3 : Impossible d'ouvrir le fichier XML
     * 2.4 : Le fichier est déjà verrouillé
     *
     * @param $mail_directory
     * @return string
     */
    public function send($mail_directory)
    {

        $mailPath = $this->getPath() . UNSENTMAILDIR . '/' . $mail_directory;

        // Chargement du XML ****************************************************
        if (!is_dir($mailPath)) {
            return "Le chemin spécifié n'existe pas : " . $mailPath;
        }

        $xmlfilename = $mailPath . '/mail.xml';
        if ((!is_file($xmlfilename)) || (!is_readable($xmlfilename))) {
            rmdir($mailPath);
            return $xmlfilename . " : ERROR Cannot Read, check permissions";
        }

        $fileStream = fopen($xmlfilename, 'r+');

        if (!$fileStream) {
            return $mailPath . " : ERROR Cannot open XML File";
        }

        // Chargement du xml
        libxml_use_internal_errors(true);
        $this->mail = simplexml_load_file($xmlfilename);

        // Controle de la validité du XML
        try {
            if ($this->mail === false) {
                $message = $mailPath . ' : ERROR Cannot load XML file';
                foreach (libxml_get_errors() as $error) {
                    $message .= "\t";
                    $message .= $error->message . ' (';
                    $message .= 'line ' . $error->line;
                    $message .= ', column ' . $error->column;
                    $message .= ')';
                    $message .= "\n";
                    $message .= file_get_contents($xmlfilename, false, null);
                }
                throw new Exception($message);
            }

        } catch (Exception $e) {
            $this->moveDirectory($mailPath, $this->getPath() . 'log/' . $mail_directory);
            return $e->getMessage();
        }

        // Verrouillage du fichier
        if (!flock($fileStream, LOCK_EX | LOCK_NB)) {
            return $mailPath . " : ERROR file is locked, probably by another process";
        }

        try {

            $mailer = new PHPMailer(true);
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->XMailer = DOMAIN;
            $mailer->isSMTP();
            $mailer->Host = 'localhost';
            $mailer->SMTPAuth = false;

            // Initialisation *******************************************************
            $subject = ($this->mail->subject) ? htmlspecialchars_decode($this->mail->subject) : '';
            $mailer->Subject = $subject;

            $mailer->isHTML(true);
            $bodyHtml = ($this->mail->bodyHtml) ? htmlspecialchars_decode($this->mail->bodyHtml) : '';

            $mailer->msgHTML($bodyHtml, '', function ($bodyHtml) {
                $converter = new Html2Text($bodyHtml);
                return $converter->getText();
            });

            $to = $this->getAddressList('to');

            if (empty($to)) {
                $this->moveDirectory($mailPath, $this->getPath() . 'log/' . $mail_directory);
                return $mailPath . ' : Error: No recipient';
            }

            foreach ($to as $TO_recipientArray) {
                $resAddTo = $mailer->addAddress($TO_recipientArray[self::MAIL], $TO_recipientArray[self::NAME]);
                if (!$resAddTo) {
                    error_log('Error addTo: ' . $TO_recipientArray[self::MAIL]);
                }
            }


            $cc = $this->getAddressList('cc');
            if ($cc) {
                foreach ($cc as $CC_recipientArray) {
                    $resAddCc = $mailer->addCC($CC_recipientArray[self::MAIL], $CC_recipientArray[self::NAME]);
                    if (!$resAddCc) {
                        error_log('Error addCC: ' . $CC_recipientArray[self::MAIL]);
                    }
                }
            }

            $bcc = $this->getAddressList('bcc');
            if ($bcc) {
                foreach ($bcc as $BCC_recipientArray) {
                    $resAddBcc = $mailer->addBCC($BCC_recipientArray[self::MAIL], $BCC_recipientArray[self::NAME]);
                    if (!$resAddBcc) {
                        error_log('Error addBCC: ' . $BCC_recipientArray[self::MAIL]);
                    }
                }
            }


            $from = $this->getAddress('from');
            $defaultFrom = 'contact@' . DOMAIN;


            $return_path = $this->getAddress('return-path');

            $reply_to = $this->getAddress('reply-to');

            $notification = $this->getAddress('disposition-notification-to');


            if ($from) {
                $mailer->setFrom($from[self::MAIL], $from[self::NAME]);
            } else {
                $mailer->setFrom($defaultFrom);
            }

            if ($reply_to) {
                $mailer->addReplyTo($reply_to[self::MAIL], $reply_to[self::NAME]);
            }

            if ($notification) {
                $mailer->ConfirmReadingTo = $notification[self::MAIL];
            }

            if ($return_path) {
                $mailer->Sender = $return_path[self::MAIL];
            } else {
                $review = Episciences_ReviewsManager::find(RVID);
                $review->loadSettings();
                $mailError = $review->getSetting(Episciences_Review::SETTING_CONTACT_ERROR_MAIL);
                if ($mailError === false || $mailError === "0") {
                    $mailer->Sender = 'error@' . DOMAIN;
                } else {
                    $mailer->Sender = $review->getCode().'-error@'.DOMAIN;
                }
            }

            // Construction du message ************************************************

            // Pièces jointes ********************************************************
            $filesList = $this->getAttachments();
            if ($filesList) {
                foreach ($filesList as $attachment) {
                    $mailer->addAttachment($mailPath . '/' . $attachment);
                }
            }

            if (empty($subject) && empty($bodyHtml) && empty($filesList)) {
                $this->moveDirectory($mailPath, $this->getPath() . 'log/' . $mail_directory);
                return $mailPath . ' : ERROR: Message without Content';
            }

            if (APPLICATION_ENV == ENV_DEV) {
                Zend_Debug::dump($mailer);
            } else {
                $mailer->send();
            }

            // Fermeture du fichier et déverrouillage
            if ($fileStream) {
                flock($fileStream, LOCK_UN);
                fclose($fileStream);
            }

            // Transfert du mail dans le dossier "sent"
            if (!is_dir($this->getPath() . SENTMAILDIR . '/')) {
                mkdir($this->getPath() . SENTMAILDIR . '/', 0777);
            }
            if (APPLICATION_ENV != ENV_DEV) {
                $this->moveDirectory($mailPath, $this->getPath() . SENTMAILDIR . '/' . $mail_directory);
            }
            $message = $mailPath . " : envoi réussi.";
        } catch (Exception $e) {
            // Fermeture du fichier et déverrouillage
            if ($fileStream) {
                flock($fileStream, LOCK_UN);
                fclose($fileStream);
            }

            $message = $mailPath . " : ERREUR - échec de l'envoi (" . ($this->mail[self::MAIL_ERRORS] + 1) . '/' . MAX_RETRIES . ')';

            if ($this->mail[self::MAIL_ERRORS] < MAX_RETRIES) {
                $this->updateErrorsCount($mailPath . '/mail.xml');
            } else {
                $this->moveDirectory($mailPath, $this->getPath() . 'log/' . $mail_directory);
                $message = $mailPath . " : ERREUR - Nombre maximum de tentatives atteint.";
            }
            $message .= " Message could not be sent. Mailer Error: {$mailer->ErrorInfo}";
            $message .= ': Exception message: ' . $e->getMessage();

        }

        return $message;
    }

    /**
     * @param $oldPath string
     * @param $newPath string
     * @return bool
     */
    private function moveDirectory($oldPath, $newPath)
    {
        if (!is_dir($newPath)) {
            return rename($oldPath, $newPath);
        }
        return false;
    }

    /**
     * Récupération d'une liste d'adresses pour le header
     * Le paramètre est le nom de la liste à récupérer (to, cc_, bcc)
     */
    private function getAddressList($listname): bool|array
    {
        if (empty($this->mail->{$listname . '_list'}->$listname)) {
            return false;
        }

        $res = [];
        foreach ($this->mail->{$listname . '_list'}->$listname as $item) {
            if (!empty($item->mail)) {

                $mailAddress = strval($item->mail);

                if ($item->name) {
                    $name = htmlspecialchars_decode(strval($item->name));
                } else {
                    $name = $mailAddress;
                }
                $res[] = [self::MAIL => $mailAddress, self::NAME => $name];
            }
        }


        return $res;
    }

    /**
     * Récupération d'adresse pour le header
     *Le paramètre est le nom du champ à récupérer (from, return-path, reply-to)
     *
     * @param $fieldname string
     * @return bool|array
     */
    private function getAddress($fieldname)
    {


        $res = false;
        if (!empty($this->mail->$fieldname)) {
            $item = $this->mail->$fieldname;

            if (!empty($item->mail)) {
                $mailAddress = strval($item->mail);
                if ($item->name) {
                    $name = strval($item->name);
                } else {
                    $name = $mailAddress;
                }

                $res[self::MAIL] = $mailAddress;
                $res[self::NAME] = $name;
            }
        }
        return $res;
    }

    /**
     * Renvoie la liste des pièces jointes
     * @return array|bool
     */
    private function getAttachments()
    {
        $files = false;
        if ($this->mail->files_list->file) {
            foreach ($this->mail->files_list->file as $file) {
                $files[] = strval($file);
            }
        }
        return $files;
    }

    /**
     * Met à jour le compteur d'erreurs du fichier XML
     * @param $file string
     */
    private function updateErrorsCount($file)
    {
        $errorsCount = $this->mail[self::MAIL_ERRORS];
        $headersCharset = ($this->mail['charset']) ? 'UTF-8' : $this->mail['charset'];

        $buffer = file($file);
        $buffer[1] = '<mail errors="' . ($errorsCount + 1) . '" charset="' . $headersCharset . '">' . PHP_EOL;
        $buffer = implode('', $buffer);

        $fileStream = fopen($file, 'w');
        fwrite($fileStream, $buffer);
        fclose($fileStream);
    }

    /**
     * Met à jour le log
     * @param $message string
     */
    private function updateLog($message): void
    {
        file_put_contents($this->getPath() . 'log/log.txt', $message . PHP_EOL, FILE_APPEND);
    }

}