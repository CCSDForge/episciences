<?php
/**
 * Episciences automatic reminders
 */


define('DEFAULT_ENV', 'development');
define('APPLICATION_PATH', __DIR__ . '/../application');
define('SCRIPT_NAME', basename($_SERVER['PHP_SELF']));

$localopts = [
    'date=s' => 'Fetch reminders for the specified date (default date is today)',
    'rvcode=s' => 'Fetch reminders for the specified journal (default: all)'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}

/**
 *  display errors
 * @param Zend_Console_Getopt $opts
 * @param $msg
 */
function displayError(Zend_Console_Getopt $opts, $msg)
{
    echo PHP_EOL . PHP_EOL;
    echo $opts->getUsageMessage();
    echo PHP_EOL . PHP_EOL;
    echo $msg;
    echo PHP_EOL . PHP_EOL;
    die();
}

/**
 * display messages
 * @param $msg
 * @param string $color
 * @param bool $localDebug
 * @param bool $logInFile
 */
function displayMessage($msg, string $color = 'default', bool $localDebug = false, bool $logInFile = true)
{
    echo $localDebug ? Episciences_Tools::$bashColors[$color] . $msg . Episciences_Tools::$bashColors['default'] . PHP_EOL : '';

    if ($logInFile && $msg) {
        $msg .= PHP_EOL;
        file_put_contents('/tmp/' . substr(SCRIPT_NAME, 0, (strlen(SCRIPT_NAME) - 4)) . '_' . APPLICATION_ENV . '.log', $msg, FILE_APPEND);
    }

}


// Autoloader
require_once('Zend/Loader/Autoloader.php');
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);


$db = Zend_Db_Table_Abstract::getDefaultAdapter();


// init translator
$default = 'fr';
try {
    $translator = new Zend_Translate(Zend_Translate::AN_ARRAY, PATH_TRANSLATION, null, [
        'scan' => Zend_Translate::LOCALE_DIRECTORY,
        'locale' => $default,
        'disableNotices' => true
    ]);

    Zend_Registry::set('Zend_Translate', $translator);
    Zend_Registry::set('Zend_Locale', new Zend_Locale($translator->getLocale()));
    Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));

    $date = new Zend_Date();

    displayMessage(PHP_EOL . PHP_EOL, 'default', true);
    displayMessage("********* Starting reminders script ***********", 'bold', true);
    displayMessage("********* " . $date . " ***********" . PHP_EOL, 'default', true);

    if (!isset($opts->rvcode)) {
        die('ERROR: MISSING RVCODE' . PHP_EOL);
    }

    defineJournalConstants($opts->rvcode);

// fetch reminders
    $sql = $db->select()->from(T_MAIL_REMINDERS)->order('RVID')->order('TYPE');
    $isDebug = isset($opts->debug);

    if ($isDebug) {
        displayMessage('SQL REQUEST: ');
        displayMessage($sql . PHP_EOL);
    }

    $remindersData = $db->fetchAll($sql);

    $settings = ['is' => ['code' => $opts->rvcode]];

    // loop through each journal
    $reviews = Episciences_ReviewsManager::getList($settings);


    foreach ($reviews as $review) {


        $rvCode = $review->getCode();
        $rvId = $review->getRvid();
        $status = $review->getStatus();

        $lostLoginLink = SERVER_PROTOCOL . '://';
        $lostLoginLink .= $rvCode . '.' . DOMAIN;
        $lostLoginLink .= '/user/lostlogin';

        if (empty($status)) {
            displayMessage($rvCode . ': NOT ACTIVATED ( STATUS = ' . $status . ' )', 'red', true);
            continue;
        }

        // update translator with journal translations
        $journalTranslationPath = $review->getTranslationsPath();
        if (is_readable($journalTranslationPath)) {
            echo PHP_EOL;
            displayMessage("Loading journal translations from: " . $journalTranslationPath, 'default', true);
            Zend_Registry::get('Zend_Translate')->addTranslation($journalTranslationPath);
        } else {
            displayMessage("Cannot read journal translations from: " . $journalTranslationPath . PHP_EOL, 'default', true);
        }

        displayMessage("Checking reminders for " . Episciences_Tools::$bashColors['bold'] . $rvCode . Episciences_Tools::$bashColors['default'] . PHP_EOL, 'default', true);

        displayMessage('Details: ', 'bold', true);

        // journal settings
        $website = new Ccsd_Website_Common($review->getRvid(), ['sidField' => 'SID']);
        $review->loadSettings();

        $languages = $website->getLanguages();
        if (empty($languages)) {
            displayMessage($rvCode . ': EMPTY WEBSITE LANGUAGES', 'red', true);
            continue;
        }

        Zend_Registry::set('languages', $languages);

        // loop through reminders
        foreach ($remindersData as $index => $data) {

            if ((int)$data['RVID'] !== $review->getRvid()) {
                continue;
            }

            $reminder = new Episciences_Mail_Reminder($data);
            $reminder->loadTranslations();

            displayMessage(PHP_EOL . PHP_EOL . '- ' . $reminder->getName(), 'default', true);

            // Sauf les relances de relecture dépendent du paramètre "rating_deadline"
            $ratingReminders = [Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE, Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE];

            if (in_array($reminder->getType(), $ratingReminders, false)) {
                $reminder->setDeadline($review->getSetting('rating_deadline'));
            }

            $reminder->loadRecipients($isDebug, $opts->date);
            $recipients = $reminder->getRecipients();

            displayMessage(' > ' . count($recipients) . ' mails', 'default', true);

            if (empty($recipients)) {
                unset($remindersData[$index]);
                continue;
            }

            $origin = !$opts->date ? new DateTime("now") : date_create($opts->date);
            $origin->setTime(0,0); // otherwise, the number of days before the review deadline was calculated incorrectly (one day difference).

            foreach ($recipients as $recipient) {

                $tags = (array_key_exists('tags', $recipient)) ? $recipient['tags'] : [];
                $paper = null;

                $mail = new Episciences_Mail('UTF-8');

                if (isset($tags['%%ARTICLE_ID%%'])) {
                    $paper = Episciences_PapersManager::get($tags['%%ARTICLE_ID%%']);
                    $mail->setDocid($paper->getDocid());
                    displayMessage('DOCID > #' . $paper->getDocid(), 'default', true);
                }

                $mail->setFrom($rvCode . '@' . DOMAIN, $rvCode);
                $mail->setRvid($review->getRvid());
                $mail->addTag(Episciences_Mail_Tags::TAG_REVIEW_CODE, $rvCode);
                $mail->addTag(Episciences_Mail_Tags::TAG_REVIEW_NAME, $review->getName());

                if (isset($recipient['deadline'])) {

                    displayMessage('Deadline: ' . date('Y-m-d', strtotime($recipient['deadline'])) . ')', 'default', true);

                    $target = date_create($recipient['deadline']);
                    $target->setTime(0,0);
                    $interval = $origin->diff($target, true)->format('%a'); // in days

                    $mail->addTag(Episciences_Mail_Tags::TAG_REMINDER_DELAY, $interval);
                }

                foreach ($tags as $name => $value) {
                    $mail->addTag($name, $value);
                }

                $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, $lostLoginLink);
                $mail->addTag(Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN, $lostLoginLink);

                $mail->addTo($recipient['email'], $recipient['fullname']);

                displayMessage('Recipient > to ' . $recipient['fullname'] . ' (' . $recipient['email'], 'default', true);

                $mail->setSubject($reminder->getSubject($recipient['lang']));
                $mail->setRawBody($reminder->getBody($recipient['lang']));
                $mail->writeMail($rvCode, $rvId, $isDebug);

                $msg = "reminder sent to " . $recipient['fullname'] . ' (' . $recipient['uid'] . ')';
                if (isset($tags['%%ARTICLE_ID%%'])) {
                    $msg .= ' - #' . $tags['%%ARTICLE_ID%%'];
                }

                $msg .= ' (' . $reminder->getName() . ')';
                displayMessage($msg, 'default', $isDebug);

                $mailDetails = ['id' => $mail->getId(), 'mail' => $mail->toArray()];

                // log mail
                if (!$isDebug && $paper) {
                    $paper->log(Episciences_Paper_Logger::CODE_REMINDER_SENT, null, $mailDetails);
                }

                displayMessage('Mail details: ' . PHP_EOL . Zend_Json::encode($mailDetails), 'default', $isDebug);
            }
            unset($remindersData[$index]);

        }

        // reset default translator
        Zend_Registry::set('Zend_Translate', $translator);
    }

} catch (Exception $e) {
    error_log('APPLICATION EXCEPTION : ' . $e->getCode() . ' ' . $e->getMessage());
    displayMessage('APPLICATION EXCEPTION : ' . $e->getCode() . ' ' . $e->getMessage(), 'red', true);
}

