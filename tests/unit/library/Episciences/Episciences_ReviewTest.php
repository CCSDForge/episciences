<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Review.
 *
 * Focuses on pure-logic methods (constants, setters/getters, settings management,
 * static state). DB-dependent methods (find, save, loadSettings, getPapers, etc.)
 * are excluded — they belong to integration tests.
 *
 * @covers Episciences_Review
 */
class Episciences_ReviewTest extends TestCase
{
    private Episciences_Review $review;

    protected function setUp(): void
    {
        $this->review = new Episciences_Review();
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function testStatusConstants(): void
    {
        self::assertSame(0, Episciences_Review::STATUS_NOTVALID);
        self::assertSame(1, Episciences_Review::STATUS_VALID);
        self::assertSame(2, Episciences_Review::STATUS_REFUSED);
        self::assertSame('1', Episciences_Review::ENABLED);
        self::assertSame('0', Episciences_Review::DISABLED);
    }

    // =========================================================================
    // Deadline constants
    // =========================================================================

    public function testDeadlineConstants(): void
    {
        self::assertSame('1 month', Episciences_Review::DEFAULT_INVITATION_DEADLINE);
        self::assertSame('2 month', Episciences_Review::DEFAULT_RATING_DEADLINE);
        self::assertSame('2 month', Episciences_Review::DEFAULT_RATING_DEADLINE_MIN);
        self::assertSame('6 month', Episciences_Review::DEFAULT_RATING_DEADLINE_MAX);
    }

    // =========================================================================
    // Setting key constants (sample)
    // =========================================================================

    public function testSettingKeyConstants(): void
    {
        self::assertSame('invitation_deadline', Episciences_Review::SETTING_INVITATION_DEADLINE);
        self::assertSame('rating_deadline', Episciences_Review::SETTING_RATING_DEADLINE);
        self::assertSame('ISSN', Episciences_Review::SETTING_ISSN);
        self::assertSame('doiAssignMode', Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE);
    }

    // =========================================================================
    // Default language
    // =========================================================================

    public function testDefaultLang(): void
    {
        self::assertSame('en', Episciences_Review::DEFAULT_LANG);
    }

    // =========================================================================
    // RVID setter/getter
    // =========================================================================

    public function testSetAndGetRvid(): void
    {
        $this->review->setRvid(42);
        self::assertSame(42, $this->review->getRvid());
    }

    public function testSetRvidCastsToInt(): void
    {
        $this->review->setRvid('7');
        self::assertSame(7, $this->review->getRvid());
        self::assertIsInt($this->review->getRvid());
    }

    public function testDefaultRvidIsZero(): void
    {
        self::assertSame(0, $this->review->getRvid());
    }

    public function testSetRvidReturnsFluent(): void
    {
        $result = $this->review->setRvid(1);
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // Code setter/getter
    // =========================================================================

    public function testSetAndGetCode(): void
    {
        $this->review->setCode('epijinfo');
        self::assertSame('epijinfo', $this->review->getCode());
    }

    public function testSetCodeReturnsFluent(): void
    {
        $result = $this->review->setCode('test');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    public function testDefaultCodeIsEmptyString(): void
    {
        self::assertSame('', $this->review->getCode());
    }

    // =========================================================================
    // Name setter/getter
    // =========================================================================

    public function testSetAndGetName(): void
    {
        $this->review->setName('Journal of Tests');
        self::assertSame('Journal of Tests', $this->review->getName());
    }

    public function testSetNameReturnsFluent(): void
    {
        $result = $this->review->setName('test');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // Status setter/getter
    // =========================================================================

    public function testSetAndGetStatus(): void
    {
        $this->review->setStatus('1');
        self::assertSame('1', $this->review->getStatus());
    }

    // =========================================================================
    // Piwikid setter/getter
    // =========================================================================

    public function testSetAndGetPiwikid(): void
    {
        $this->review->setPiwikid(99);
        self::assertSame(99, $this->review->getPiwikid());
    }

    public function testSetPiwikidReturnsFluent(): void
    {
        $result = $this->review->setPiwikid(1);
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // setSetting / getSettings
    // =========================================================================

    public function testSetSettingAndGetSettings(): void
    {
        $this->review->setSetting('ISSN', '1234-5678');
        $settings = $this->review->getSettings();

        self::assertIsArray($settings);
        self::assertArrayHasKey('ISSN', $settings);
        self::assertSame('1234-5678', $settings['ISSN']);
    }

    public function testSetMultipleSettings(): void
    {
        $this->review->setSetting(Episciences_Review::SETTING_ISSN, '1234-5678');
        $this->review->setSetting(Episciences_Review::SETTING_INVITATION_DEADLINE, '2');

        $settings = $this->review->getSettings();

        self::assertCount(2, $settings);
        self::assertSame('1234-5678', $settings[Episciences_Review::SETTING_ISSN]);
        self::assertSame('2', $settings[Episciences_Review::SETTING_INVITATION_DEADLINE]);
    }

    public function testSetSettingReturnsFluent(): void
    {
        $result = $this->review->setSetting('key', 'value');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // getRepositories (reads from $_settings)
    // =========================================================================

    public function testGetRepositoriesReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->review->getRepositories());
    }

    public function testGetRepositoriesReturnsSetValue(): void
    {
        $repos = [1, 2, 3];
        $this->review->setSetting('repositories', $repos);
        self::assertSame($repos, $this->review->getRepositories());
    }

    // =========================================================================
    // Static currentReviewId
    // =========================================================================

    public function testSetAndGetCurrentReviewId(): void
    {
        Episciences_Review::setCurrentReviewId(5);
        self::assertSame(5, Episciences_Review::getCurrentReviewId());
    }

    public function testSetCurrentReviewIdWithZero(): void
    {
        Episciences_Review::setCurrentReviewId(0);
        self::assertSame(0, Episciences_Review::getCurrentReviewId());
    }

    // =========================================================================
    // ASSIGNMENT_EDITORS_MODE constant
    // =========================================================================

    public function testAssignmentEditorsModeKeys(): void
    {
        $mode = Episciences_Review::ASSIGNMENT_EDITORS_MODE;
        self::assertArrayHasKey('predefined', $mode);
        self::assertArrayHasKey('default', $mode);
        self::assertArrayHasKey('advanced', $mode);
        self::assertSame('0', $mode['predefined']);
        self::assertSame('1', $mode['default']);
        self::assertSame('2', $mode['advanced']);
    }

    // =========================================================================
    // forYourInformation() — pure wrapper, catches all exceptions
    // =========================================================================

    public function testForYourInformationReturnsStringWithNullDocId(): void
    {
        $result = Episciences_Review::forYourInformation(null, null, false);
        self::assertIsString($result);
    }

    // =========================================================================
    // setOptions()
    // =========================================================================

    public function testSetOptionsAppliesRvidAndCode(): void
    {
        $this->review->setOptions(['rvid' => 3, 'code' => 'myjournal']);
        self::assertSame(3, $this->review->getRvid());
        self::assertSame('myjournal', $this->review->getCode());
    }

    public function testConstructorWithOptions(): void
    {
        $review = new Episciences_Review(['rvid' => 10, 'code' => 'test-journal', 'name' => 'Test Journal']);
        self::assertSame(10, $review->getRvid());
        self::assertSame('test-journal', $review->getCode());
        self::assertSame('Test Journal', $review->getName());
    }
}
