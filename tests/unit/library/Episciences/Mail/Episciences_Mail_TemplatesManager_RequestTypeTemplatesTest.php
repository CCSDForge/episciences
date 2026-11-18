<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Tags;
use Episciences_Mail_Template;
use Episciences_Mail_TemplatesManager;
use PHPUnit\Framework\TestCase;

/**
 * Mail templates of the review request types (#319): per-type invitations and the account hint fragments
 *
 * @covers Episciences_Mail_TemplatesManager
 */
final class Episciences_Mail_TemplatesManager_RequestTypeTemplatesTest extends TestCase
{
    private const LANGS = ['fr' => 'Français', 'en' => 'English'];

    private bool $localeRegistered = false;

    protected function setUp(): void
    {
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
            $this->localeRegistered = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->localeRegistered) {
            $registry = \Zend_Registry::getInstance();
            $registry->offsetUnset('Zend_Locale');
        }
    }

    private function form(string $key): \Zend_Form
    {
        $template = new Episciences_Mail_Template();
        $template->setKey($key);
        $template->setTags(Episciences_Mail_TemplatesManager::getAvailableTagsByKey($key));

        return Episciences_Mail_TemplatesManager::getTemplateForm($template, self::LANGS);
    }

    public function testFragmentFormHasNoSubject(): void
    {
        foreach (Episciences_Mail_TemplatesManager::FRAGMENT_TEMPLATES as $key) {
            $subform = $this->form($key)->getSubForm('en');

            self::assertNotNull($subform);
            self::assertNull($subform->getElement('subject'), "$key must not have a subject");
            self::assertNotNull($subform->getElement('body'));
        }
    }

    public function testInvitationFormOfARequestTypeHasASubject(): void
    {
        $subform = $this->form(Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_QUICK_OPINION)->getSubForm('fr');

        self::assertNotNull($subform);
        self::assertNotNull($subform->getElement('subject'));
    }

    public function testCustomFragmentIsStillAFragment(): void
    {
        self::assertTrue(Episciences_Mail_TemplatesManager::isFragment('custom_' . Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_NEW_USER));
        self::assertFalse(Episciences_Mail_TemplatesManager::isFragment(Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER));
    }

    public function testTemplatesOfTheTypesCreatedByAJournalOfferTheRequestTypeTags(): void
    {
        foreach (['paper_reviewer_invitation_phase_two', 'custom_paper_reviewer_invitation_phase_two'] as $key) {
            $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey($key);

            self::assertContains(Episciences_Mail_Tags::TAG_INVITATION_ACCOUNT_HINT, $tags);
            self::assertContains(Episciences_Mail_Tags::TAG_REVIEW_REQUEST_TYPE, $tags);
            self::assertContains(Episciences_Mail_Tags::TAG_RATING_DEADLINE, $tags);
        }
    }

    public function testFragmentsOfferTheForgottenUsernameLink(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_USER, true);

        self::assertContains(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, $tags);
        self::assertNotContains(Episciences_Mail_Tags::TAG_INVITATION_ACCOUNT_HINT, $tags);
    }

    public function testEveryNewTemplateHasADefaultBodyInFrenchAndEnglish(): void
    {
        $keys = array_merge(Episciences_Mail_TemplatesManager::FRAGMENT_TEMPLATES, [
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_QUICK_OPINION,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_GENERIC,
        ]);

        foreach ($keys as $key) {
            $template = new Episciences_Mail_Template();
            $template->setKey($key);
            $bodies = $template->loadBody();

            self::assertNotEmpty($bodies['fr'] ?? '', "$key: French body");
            self::assertNotEmpty($bodies['en'] ?? '', "$key: English body");
        }
    }
}
