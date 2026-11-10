<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_ReviewsManager;
use ReviewController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Form;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Journal settings page (review/settings): the form built by Episciences_Review::settingsForm(),
 * ReviewController::settingsAction() and the review/settings.phtml view.
 */
final class ReviewControllerSettingsTest extends ControllerTestCase
{
    private const DOI_PREFIX = '10.12345';
    private const LICENSE = 'CC-BY-4.0';

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'ReviewController');
    }

    protected function setUp(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->clearFlashMessages();
    }

    protected function tearDown(): void
    {
        $this->clearFlashMessages();
    }

    private function clearFlashMessages(): void
    {
        foreach (['warning', 'success', 'danger', 'error'] as $namespace) {
            (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace($namespace)->clearCurrentMessages();
        }
    }

    private function journal(): Episciences_Review
    {
        $journal = Episciences_ReviewsManager::find(RVID);
        self::assertInstanceOf(Episciences_Review::class, $journal);
        $journal->loadSettings();

        return $journal;
    }

    private function settingsForm(): Zend_Form
    {
        $this->givenJournal();

        return $this->journal()->settingsForm();
    }

    /**
     * A valid post of the form: required fields and deadlines with their unit.
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function validPost(array $values = []): array
    {
        return array_merge([
            'submit' => 'submit',
            Episciences_Review::SETTING_REPOSITORIES => ['1'],
            Episciences_Review::SETTING_INVITATION_DEADLINE => '1',
            Episciences_Review::SETTING_INVITATION_DEADLINE_UNIT => 'month',
            Episciences_Review::SETTING_RATING_DEADLINE => '2',
            Episciences_Review::SETTING_RATING_DEADLINE_UNIT => 'month',
            Episciences_Review::SETTING_RATING_DEADLINE_MIN => '1',
            Episciences_Review::SETTING_RATING_DEADLINE_MIN_UNIT => 'month',
            Episciences_Review::SETTING_RATING_DEADLINE_MAX => '6',
            Episciences_Review::SETTING_RATING_DEADLINE_MAX_UNIT => 'month',
        ], $values);
    }

    /**
     * @return list<string>
     */
    private function warnings(): array
    {
        return (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('warning')->getCurrentMessages();
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    public function testTheSectionsOfTheFormAreTheDeclaredOnesInTheSameOrder(): void
    {
        $form = $this->settingsForm();

        self::assertSame(array_keys(Episciences_Review::SETTINGS_FORM_SECTIONS), array_keys($form->getDisplayGroups()));

        foreach (Episciences_Review::SETTINGS_FORM_SECTIONS as $id => $section) {
            self::assertSame($section['elements'], array_keys($form->getDisplayGroup($id)->getElements()), $id);
            self::assertSame($section['legend'], $form->getDisplayGroup($id)->getLegend());
        }
    }

    /**
     * Every setting of the form is shown in a section: none is left out of the page.
     */
    public function testEverySettingOfTheFormIsInASection(): void
    {
        $form = $this->settingsForm();
        $inSections = array_merge(...array_column(Episciences_Review::SETTINGS_FORM_SECTIONS, 'elements'));

        self::assertSame([], array_values(array_diff(array_keys($form->getElements()), $inSections)));
        self::assertSame(count($inSections), count(array_unique($inSections)), 'a setting is in a single section');
    }

    public function testTheLicensesAndTheDoiSettingsHaveTheirOwnPage(): void
    {
        $form = $this->settingsForm();

        self::assertNull($form->getElement(Episciences_Review::SETTING_LICENSE_DEFAULT));
        self::assertNull($form->getElement(Episciences_Review::SETTING_LICENSES_ALLOWED));
        self::assertNull($form->getElement(Episciences_Review_DoiSettings::SETTING_DOI_PREFIX));
        self::assertNull($form->getElement(Episciences_Review_DoiSettings::SETTING_DOI_FORMAT));
    }

    public function testTheCodeShownInTheEmailsIsAContactSetting(): void
    {
        $form = $this->settingsForm();

        self::assertArrayHasKey(Episciences_Review::SETTING_MAIL_DISPLAY_CODE, $form->getDisplayGroup('contacts')->getElements());
    }

    /**
     * @dataProvider issnProvider
     */
    public function testIssnFormat(string $issn, bool $valid): void
    {
        $element = $this->settingsForm()->getElement(Episciences_Review::SETTING_ISSN);

        self::assertSame($valid, $element->isValid($issn));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function issnProvider(): array
    {
        return [
            'digits' => ['1234-5678', true],
            'final X' => ['2105-570X', true],
            'final lowercase x' => ['2105-570x', true],
            'surrounding spaces' => [' 1234-5678 ', true],
            'without hyphen' => ['12345678', false],
            'space instead of hyphen' => ['1234 5678', false],
            'letters' => ['ABCD-EFGH', false],
        ];
    }

    /**
     * @dataProvider requiredReviewersProvider
     */
    public function testRequiredReviewersIsANumber(string $value, bool $valid): void
    {
        $element = $this->settingsForm()->getElement(Episciences_Review::SETTING_REQUIRED_REVIEWERS);

        self::assertSame($valid, $element->isValid($value));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function requiredReviewersProvider(): array
    {
        return [
            'none' => ['0', true],
            'two' => ['2', true],
            'negative' => ['-1', false],
            'text' => ['two', false],
            'decimal' => ['1.5', false],
        ];
    }

    /**
     * The three settings forms warn about unsaved changes (public/js/review/settings.js)
     */
    public function testTheSettingsFormsWarnAboutUnsavedChanges(): void
    {
        $this->givenJournal();
        $journal = $this->journal();

        foreach ([
            $journal->settingsForm(),
            $journal->licensesSettingsForm(),
            \Episciences_Review_DoiSettingsManager::getSettingsForm(),
        ] as $form) {
            self::assertSame('1', $form->getAttrib('data-unsaved-warning'), (string)$form->getAttrib('id'));
        }
    }

    public function testDependentSettingsNameTheSettingTheyDependOn(): void
    {
        $form = $this->settingsForm();

        self::assertSame(
            Episciences_Review::SETTING_CAN_PICK_EDITOR,
            $form->getElement(Episciences_Review::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION)->getAttrib('data-depends-on')
        );
        self::assertSame(
            Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED,
            $form->getElement(Episciences_Review::SETTING_SYSTEM_COI_COMMENTS_TO_EDITORS_ENABLED)->getAttrib('data-depends-on')
        );
    }

    // ------------------------------------------------------------------
    // Action
    // ------------------------------------------------------------------

    /**
     * Unchecked groups of checkboxes are not posted: they are stored as empty lists, as the checked ones are stored as lists.
     */
    public function testUncheckedGroupsOfCheckboxesAreEmptyLists(): void
    {
        $this->givenJournal([
            Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS => json_encode([Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES]),
        ]);

        $this->dispatch(ReviewController::class, 'settings', $this->postRequest($this->validPost()));

        self::assertSame([], $this->warnings(), 'the form is valid');
        $journal = $this->journal();
        foreach (ReviewController::MULTI_CHECKBOX_SETTINGS as $setting) {
            self::assertSame([], $journal->getSetting($setting), $setting);
        }
    }

    public function testCheckedGroupsOfCheckboxesAreLists(): void
    {
        $this->givenJournal();

        $this->dispatch(ReviewController::class, 'settings', $this->postRequest($this->validPost([
            Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS => [Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS],
        ])));

        self::assertSame([], $this->warnings(), 'the form is valid');
        self::assertSame(
            [Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS],
            $this->journal()->getSetting(Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS)
        );
    }

    public function testUncheckedCheckboxesAreDisabled(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1']);

        $this->dispatch(ReviewController::class, 'settings', $this->postRequest($this->validPost()));

        self::assertSame('0', $this->journal()->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS));
    }

    public function testAnInvalidIssnIsNotApplied(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_ISSN => '1234-5678']);

        $this->dispatch(ReviewController::class, 'settings', $this->postRequest($this->validPost([
            Episciences_Review::SETTING_ISSN => '12345678',
        ])));

        self::assertNotSame([], $this->warnings(), 'the form has errors');
        self::assertSame('1234-5678', $this->journal()->getSetting(Episciences_Review::SETTING_ISSN));
    }

    public function testTheLicensesAreKeptWhenSavingTheSettings(): void
    {
        $this->givenJournal([
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::DOI_PREFIX,
        ]);

        $this->dispatch(ReviewController::class, 'settings', $this->postRequest($this->validPost()));

        $journal = $this->journal();
        self::assertSame(self::LICENSE, $journal->getDefaultLicense());
        self::assertSame(self::DOI_PREFIX, $journal->getDoiSettings()->getDoiPrefix());
    }

    // ------------------------------------------------------------------
    // View
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $settings
     */
    private function renderPage(array $settings = [], bool $canEditDoiSettings = false, string $language = 'fr'): string
    {
        $previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        $content = $language === 'en' ? require APPLICATION_PATH . '/languages/en/views.php' : ['x' => 'x'];
        Zend_Registry::set('Zend_Translate', new Zend_Translate(['adapter' => 'array', 'content' => $content, 'locale' => $language]));

        try {
            $this->givenJournal($settings);
            $journal = $this->journal();
            $form = $journal->settingsForm();
            $form->setDefaults($journal->getSettings());

            $view = new Zend_View();
            $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
            $view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
            $form->setView($view);
            $view->form = $form;
            $view->sections = Episciences_Review::SETTINGS_FORM_SECTIONS;
            $view->doiSettings = $journal->getDoiSettings();
            $view->canEditDoiSettings = $canEditDoiSettings;
            $view->defaultLicense = $journal->getDefaultLicense();
            $view->allowedLicenses = $journal->getAllowedLicenses();

            return $view->render('review/settings.phtml');
        } finally {
            if ($previousTranslate !== null) {
                Zend_Registry::set('Zend_Translate', $previousTranslate);
            } else {
                Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
            }
        }
    }

    /**
     * The page tells which changes apply to the articles in progress and which ones do not
     */
    public function testThePageExplainsWhenAChangeTakesEffect(): void
    {
        $html = $this->renderPage();

        self::assertStringContainsString('<details class="journal-settings-effects">', $html);
        self::assertStringContainsString('y compris ceux en cours ou déjà publiés', $html);
        self::assertStringContainsString('À la prochaine action concernée', $html);
        self::assertStringContainsString('Jamais rétroactivement', $html);
        self::assertStringNotContainsString('elles ne changent pas les articles déjà traités', $html);
    }

    public function testTheTableOfContentsLinksToEverySection(): void
    {
        $html = $this->renderPage();

        foreach (Episciences_Review::SETTINGS_FORM_SECTIONS as $id => $section) {
            self::assertStringContainsString('href="#fieldset-' . $id . '"', $html);
            self::assertStringContainsString('id="fieldset-' . $id . '"', $html);
        }
    }

    public function testTheDoiSettingsAreSummedUp(): void
    {
        $html = $this->renderPage([
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::DOI_PREFIX,
            Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE => Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL,
        ]);

        self::assertStringContainsString('<code>' . self::DOI_PREFIX . '</code>', $html);
        self::assertStringContainsString('Manuel', $html);
        self::assertStringContainsString(
            '<code>' . Episciences_Review_DoiSettings::SETTING_DOI_DEFAULT_DOI_FORMAT . '</code>',
            $html
        );
    }

    public function testOnlyTheUsersAllowedToEditTheDoiSettingsGetTheLink(): void
    {
        self::assertStringNotContainsString('href="/doi/settings"', $this->renderPage());
        self::assertStringContainsString('href="/doi/settings"', $this->renderPage([], true));
    }

    public function testAJournalWithoutDoiPrefixIsReported(): void
    {
        self::assertStringContainsString('aucun DOI ne peut être attribué', $this->renderPage());
    }

    public function testTheLicensesAreSummedUp(): void
    {
        $html = $this->renderPage([
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
            Episciences_Review::SETTING_LICENSES_ALLOWED => json_encode([self::LICENSE, 'CC0-1.0']),
        ]);

        self::assertStringContainsString('<code>CC-BY-4.0</code>, <code>CC0-1.0</code>', $html);
        self::assertStringContainsString('href="/review/licenses"', $html);
    }

    public function testWithoutAllowedLicensesAllTheRecommendedOnesAreOffered(): void
    {
        self::assertStringContainsString('Toutes les licences recommandées', $this->renderPage());
    }

    public function testEveryCheckboxIsInsideItsLabel(): void
    {
        $html = $this->renderPage();

        self::assertMatchesRegularExpression(
            '#<label[^>]*>\s*<input type="hidden" name="encapsulateEditors" value="0"\s*/?>\s*<input type="checkbox" name="encapsulateEditors"#',
            $html
        );
    }

    public function testThePageIsTranslatedIntoEnglish(): void
    {
        $html = $this->renderPage([], false, 'en');

        foreach (['Journal identity', 'Contents', 'When does a change take effect?', 'Never retroactively', 'Article DOIs', 'Conflicts of interest', 'Statistics start date', 'Forward to the reviewers the rejection message sent to the authors'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('Identité de la revue', $html);
    }

    /**
     * @dataProvider pagesLinkedToTheSettingsProvider
     * @param list<string> $expectedTabs
     */
    public function testThePagesSummedUpShareTheSettingsTabs(string $script, string $activeUrl, array $expectedTabs): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
        $view->form = '<form></form>';

        $html = $view->render($script);

        self::assertSame($expectedTabs, $this->tabUrls($html));
        self::assertSame([$activeUrl], $this->activeTabUrls($html));
        self::assertLessThan(strpos($html, 'journal-settings-intro'), strpos($html, 'journal-settings-tabs'), 'the tabs come first');
        self::assertStringContainsString('<p class="journal-settings-unsaved alert alert-warning" role="status" hidden>', $html);
        self::assertContains('/js/review/settings.js', $view->jQuery()->getJavascriptFiles());
    }

    public function testTheLicensesPageShowsTheDoiTabToThoseWhoCanEditTheDoiSettings(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
        $view->form = '<form></form>';
        $view->canEditDoiSettings = true;

        self::assertSame(['/review/settings', '/review/licenses', '/doi/settings'], $this->tabUrls($view->render('review/licenses.phtml')));
    }

    public function testTheSettingsPageShowsTheDoiTabOnlyToThoseWhoCanEditTheDoiSettings(): void
    {
        $html = $this->renderPage();
        self::assertSame(['/review/settings', '/review/licenses'], $this->tabUrls($html));
        self::assertSame(['/review/settings'], $this->activeTabUrls($html));

        self::assertSame(['/review/settings', '/review/licenses', '/doi/settings'], $this->tabUrls($this->renderPage([], true)));
    }

    /**
     * @return list<string>
     */
    private function tabUrls(string $html): array
    {
        preg_match('#<nav class="journal-settings-tabs".*?</nav>#s', $html, $nav);
        preg_match_all('#<a href="([^"]+)"#', $nav[0] ?? '', $matches);

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function activeTabUrls(string $html): array
    {
        preg_match_all('#<li class="active">\s*<a href="([^"]+)" aria-current="page">#', $html, $matches);

        return $matches[1];
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function pagesLinkedToTheSettingsProvider(): array
    {
        return [
            // the DOI tab is shown to those who can edit the DOI settings, hence always on the DOI page
            'licenses' => ['review/licenses.phtml', '/review/licenses', ['/review/settings', '/review/licenses']],
            'DOI settings' => ['doi/settings.phtml', '/doi/settings', ['/review/settings', '/review/licenses', '/doi/settings']],
        ];
    }
}
