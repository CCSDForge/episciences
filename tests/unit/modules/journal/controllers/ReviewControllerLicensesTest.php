<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Paper\Spdx\LicenseManager;
use Episciences_Acl;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_ReviewsManager;
use ReviewController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_Helper_FlashMessenger;

/**
 * ReviewController::licensesAction (journal licenses page).
 */
final class ReviewControllerLicensesTest extends ControllerTestCase
{
    private const DOI_PREFIX = '10.12345';
    private const LICENSE = 'CC-BY-4.0';
    private const OTHER_LICENSE = 'CC0-1.0';

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'ReviewController');
    }

    protected function setUp(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_CHIEF_EDITOR]);
        // the form caches the recommended licenses read from the fake database
        LicenseManager::clearCache();
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('warning')->clearCurrentMessages();
    }

    protected function tearDown(): void
    {
        LicenseManager::clearCache();
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('warning')->clearCurrentMessages();
    }

    public function testUnknownJournalIsNotFound(): void
    {
        $result = $this->dispatch(ReviewController::class, 'licenses', $this->getRequest());

        self::assertSame(404, $result->statusCode());
    }

    /**
     * The journal is the instance cached by Episciences_ReviewsManager: the posted licenses are applied
     * to it, and its other settings, such as the DOI ones, are kept.
     */
    public function testPostedLicensesAreAppliedAndTheDoiSettingsAreKept(): void
    {
        $this->givenJournal([
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::DOI_PREFIX,
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
            Episciences_Review::SETTING_LICENSES_ALLOWED => json_encode([self::LICENSE, self::OTHER_LICENSE]),
        ]);
        $request = $this->postRequest([
            'submit' => 'submit',
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::OTHER_LICENSE,
            Episciences_Review::SETTING_LICENSES_ALLOWED => [self::OTHER_LICENSE],
        ]);

        $this->dispatch(ReviewController::class, 'licenses', $request);

        $flash = new Zend_Controller_Action_Helper_FlashMessenger();
        self::assertSame([], $flash->setNamespace('warning')->getCurrentMessages(), 'the form is valid');
        $journal = Episciences_ReviewsManager::find(RVID);
        self::assertInstanceOf(Episciences_Review::class, $journal);
        self::assertSame(self::DOI_PREFIX, $journal->getDoiSettings()->getDoiPrefix());
        self::assertSame(self::OTHER_LICENSE, $journal->getDefaultLicense());
        self::assertSame([self::OTHER_LICENSE], $journal->getAllowedLicenses());
    }

    public function testAnEmptySelectionIsStoredAsNoRestriction(): void
    {
        $this->givenJournal([
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
            Episciences_Review::SETTING_LICENSES_ALLOWED => json_encode([self::LICENSE, self::OTHER_LICENSE]),
        ]);
        $request = $this->postRequest([
            'submit' => 'submit',
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::OTHER_LICENSE,
        ]);

        $this->dispatch(ReviewController::class, 'licenses', $request);

        $journal = Episciences_ReviewsManager::find(RVID);
        self::assertInstanceOf(Episciences_Review::class, $journal);
        self::assertSame(self::OTHER_LICENSE, $journal->getDefaultLicense());
        self::assertSame([], $journal->getAllowedLicenses());
    }
}
