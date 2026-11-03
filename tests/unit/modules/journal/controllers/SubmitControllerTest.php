<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Review;
use Episciences_Submit;
use ReflectionMethod;
use SubmitController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Form;
use Zend_Form_Element_Text;

/**
 * SubmitController::handleCoverLetterValidation(): the cover letter file required by the journal.
 */
final class SubmitControllerTest extends ControllerTestCase
{
    /** @var array<mixed> */
    private array $previousFiles;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'SubmitController');
    }

    protected function setUp(): void
    {
        $this->previousFiles = $_FILES;
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_FILES = $this->previousFiles;
    }

    /**
     * The missing-file error belongs to the file element, not to the optional comment textarea.
     */
    public function testMissingRequiredCoverLetterIsReportedOnTheFileElement(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_COVER_LETTER_REQUIREMENT => Episciences_Review::COVER_LETTER_REQUIREMENT_REQUIRED]);
        $form = self::submissionForm();

        self::assertFalse($this->handleCoverLetterValidation($form));
        self::assertNotEmpty($form->getElement(Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME)->getMessages());
        self::assertSame([], $form->getElement(Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME)->getMessages());
    }

    public function testAttachedRequiredCoverLetterIsAccepted(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_COVER_LETTER_REQUIREMENT => Episciences_Review::COVER_LETTER_REQUIREMENT_REQUIRED]);
        $_FILES[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME] = ['name' => 'letter.pdf'];
        $form = self::submissionForm();

        self::assertTrue($this->handleCoverLetterValidation($form));
        self::assertSame([], $form->getMessages());
    }

    public function testOptionalCoverLetterMayBeMissing(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_COVER_LETTER_REQUIREMENT => Episciences_Review::COVER_LETTER_REQUIREMENT_OPTIONAL]);
        $form = self::submissionForm();

        self::assertTrue($this->handleCoverLetterValidation($form));
        self::assertSame([], $form->getMessages());
    }

    private function handleCoverLetterValidation(Zend_Form $form): bool
    {
        $controller = new SubmitController($this->postRequest(), new Zend_Controller_Response_HttpTestCase());

        return (new ReflectionMethod(SubmitController::class, 'handleCoverLetterValidation'))
            ->invoke($controller, $form, []);
    }

    private static function submissionForm(): Zend_Form
    {
        $form = new Zend_Form();
        $form->addElement(new Zend_Form_Element_Text(Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME));
        $form->addElement(new Zend_Form_Element_Text(Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME));

        return $form;
    }
}
