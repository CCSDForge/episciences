<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_ReviewsManager;
use ReviewController;
use unit\modules\ControllerTestCase;

/**
 * ReviewController::licensesAction (journal licenses page).
 */
final class ReviewControllerLicensesTest extends ControllerTestCase
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
    }

    public function testUnknownJournalIsNotFound(): void
    {
        $result = $this->dispatch(ReviewController::class, 'licenses', $this->getRequest());

        self::assertSame(404, $result->statusCode());
    }

    /**
     * The journal is the instance cached by Episciences_ReviewsManager: saving its licenses
     * must not reset its other settings, such as the DOI ones.
     */
    public function testSavingTheLicensesKeepsTheDoiSettingsOfTheJournal(): void
    {
        $this->givenJournal([
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::DOI_PREFIX,
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
        ]);
        $request = $this->postRequest([
            'submit' => 'submit',
            Episciences_Review::SETTING_LICENSE_DEFAULT => self::LICENSE,
        ]);

        $this->dispatch(ReviewController::class, 'licenses', $request);

        $journal = Episciences_ReviewsManager::find(RVID);
        self::assertInstanceOf(Episciences_Review::class, $journal);
        self::assertSame(self::DOI_PREFIX, $journal->getDoiSettings()->getDoiPrefix());
        self::assertSame(self::LICENSE, $journal->getDefaultLicense());
        self::assertSame([], $journal->getAllowedLicenses(), 'an empty selection is stored as no restriction');
    }
}
