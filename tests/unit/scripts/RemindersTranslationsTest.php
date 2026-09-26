<?php

namespace unit\scripts;

use Episciences\Translation\TranslatorFactory;
use Episciences_Mail_Reminder;
use Episciences_Mail_Template;
use Episciences_Tools;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;

/**
 * Guards the language and the journal of the reminder e-mails sent by scripts/reminders.php.
 *
 * The script builds one translator per run (TranslatorFactory, as in the script), adds the
 * journal translations, then each reminder reads:
 *  - a custom subject from the translator (reminder_<id>_mail_subject, per language);
 *  - a custom body from <journal>/languages/<lang>/emails/*.phtml (Episciences_Mail_Template::loadBody()).
 *
 * Fixtures: two journals defining the same keys with different texts.
 */
final class RemindersTranslationsTest extends TestCase
{
    private const JOURNALS = __DIR__ . '/data/reminders';
    private const SCRIPT = __DIR__ . '/../../../scripts/reminders.php';

    private mixed $previousTranslator = null;
    private mixed $previousLanguages = null;
    private mixed $previousLocale = null;

    protected function setUp(): void
    {
        $this->previousTranslator = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        $this->previousLanguages = Zend_Registry::isRegistered('languages') ? Zend_Registry::get('languages') : null;
        $this->previousLocale = Zend_Registry::isRegistered('Zend_Locale') ? Zend_Registry::get('Zend_Locale') : null;
        Zend_Registry::set('languages', ['fr', 'en']);
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('Zend_Translate', $this->previousTranslator);
        Zend_Registry::set('languages', $this->previousLanguages);
        Zend_Registry::set('Zend_Locale', $this->previousLocale);
    }

    // =========================================================================
    // Translator built as in scripts/reminders.php
    // =========================================================================

    public function testJournalReminderSubjectIsTranslatedInEachLanguage(): void
    {
        $translator = $this->buildScriptTranslator('journal-a');

        self::assertSame('Revue A : votre relecture est attendue (FR)', $translator->translate('reminder_12_mail_subject', 'fr'));
        self::assertSame('Journal A: your review is due (EN)', $translator->translate('reminder_12_mail_subject', 'en'));
    }

    public function testOtherJournalTextsAreNeverLoaded(): void
    {
        $translator = $this->buildScriptTranslator('journal-a');

        foreach (['fr', 'en'] as $lang) {
            $messages = (string)json_encode($translator->getAdapter()->getMessages($lang), JSON_UNESCAPED_UNICODE);
            self::assertStringNotContainsString('Journal B', $messages);
            self::assertStringNotContainsString('Revue B', $messages);
        }
    }

    public function testJournalEmailBodiesAreNotLoadedAsDictionaries(): void
    {
        $translator = $this->buildScriptTranslator('journal-a');

        foreach (['fr', 'en'] as $lang) {
            $messages = (string)json_encode($translator->getAdapter()->getMessages($lang), JSON_UNESCAPED_UNICODE);
            self::assertStringNotContainsString('FIXTURE_REMINDER_BODY', $messages);
        }
    }

    public function testApplicationTextsKeepTheirLanguageAfterAddingTheJournal(): void
    {
        $translator = $this->buildScriptTranslator('journal-a');

        self::assertSame('Annuler', $translator->translate('Annuler', 'fr'));
        self::assertSame('Cancel', $translator->translate('Annuler', 'en'));
    }

    // =========================================================================
    // Custom template (subject + body) loaded as Episciences_Mail_Reminder::loadTranslations() does
    // =========================================================================

    public function testCustomTemplateSubjectAndBodyMatchTheRequestedLanguage(): void
    {
        Zend_Registry::set('Zend_Translate', TranslatorFactory::create(PATH_TRANSLATION, null, 'en'));

        $template = $this->journalTemplate('journal-a', 'reminder_12');
        $template->loadTranslations(Episciences_Tools::getLanguages(), 'journal-a');

        self::assertSame('Revue A : votre relecture est attendue (FR)', $template->getSubject('fr'));
        self::assertSame('Journal A: your review is due (EN)', $template->getSubject('en'));
        self::assertStringContainsString('revue A (FR)', (string)$template->getBody('fr'));
        self::assertStringContainsString('Journal A custom reminder body (EN)', (string)$template->getBody('en'));
    }

    public function testLoadingTemplateTranslationsKeepsTheTranslatorLocale(): void
    {
        $translator = TranslatorFactory::create(PATH_TRANSLATION, null, 'en');
        Zend_Registry::set('Zend_Translate', $translator);

        $this->journalTemplate('journal-a', 'reminder_12')->loadTranslations(Episciences_Tools::getLanguages(), 'journal-a');

        self::assertSame('en', (string)$translator->getLocale());
    }

    // =========================================================================
    // Language picked for a recipient
    // =========================================================================

    public function testRecipientLanguageSelectsSubjectAndBody(): void
    {
        $reminder = new Episciences_Mail_Reminder();
        $reminder->setSubject(['fr' => 'Sujet FR', 'en' => 'Subject EN']);
        $reminder->setBody(['fr' => 'Corps FR', 'en' => 'Body EN']);

        self::assertSame('Sujet FR', $reminder->getSubject('fr'));
        self::assertSame('Corps FR', $reminder->getBody('fr'));
        self::assertSame('Subject EN', $reminder->getSubject('en'));
        self::assertSame('Body EN', $reminder->getBody('en'));
    }

    // =========================================================================
    // scripts/reminders.php (source guards: the script runs at include time)
    // =========================================================================

    public function testScriptProcessesASingleJournalPerRun(): void
    {
        $source = (string)file_get_contents(self::SCRIPT);

        self::assertStringContainsString("die('ERROR: MISSING RVCODE'", $source);
        self::assertStringContainsString("\$settings = ['is' => ['code' => \$opts->rvcode]];", $source);
        self::assertStringContainsString("if ((int)\$data['RVID'] !== \$review->getRvid()) {", $source);
    }

    public function testScriptSendsEachRecipientTheirLanguage(): void
    {
        $source = (string)file_get_contents(self::SCRIPT);

        self::assertStringContainsString("\$mail->setSubject(\$reminder->getSubject(\$recipient['lang']));", $source);
        self::assertStringContainsString("\$mail->setRawBody(\$reminder->getBody(\$recipient['lang']));", $source);
    }

    public function testScriptBuildsTheTranslatorWithTheFactoryAndAddsTheJournalTranslations(): void
    {
        $source = (string)file_get_contents(self::SCRIPT);

        self::assertStringContainsString('TranslatorFactory::create(PATH_TRANSLATION', $source);
        self::assertStringContainsString("Zend_Registry::get('Zend_Translate')->addTranslation(\$journalTranslationPath);", $source);
    }

    /**
     * Same calls as scripts/reminders.php: factory, then addTranslation(<journal>/languages/).
     */
    private function buildScriptTranslator(string $journal): Zend_Translate
    {
        $translator = TranslatorFactory::create(PATH_TRANSLATION, null, 'auto');
        $translator->addTranslation(self::JOURNALS . '/' . $journal . '/languages/');
        Zend_Registry::set('Zend_Translate', $translator);

        return $translator;
    }

    private function journalTemplate(string $journal, string $key): Episciences_Mail_Template
    {
        $template = new class extends Episciences_Mail_Template {
            public string $folder = '';

            public function getTranslationsFolder(?string $rvCode = null)
            {
                return $this->folder;
            }
        };
        $template->folder = self::JOURNALS . '/' . $journal . '/languages/';
        $template->setKey($key);

        return $template;
    }
}
