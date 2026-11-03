<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Repositories;
use Episciences_Review;
use Episciences_Submit;
use ReflectionMethod;
use SubmitController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Form;
use Zend_Form_Element_Text;
use Zend_Registry;

/**
 * SubmitController: the submission form, and the cover letter file required by the journal
 * (handleCoverLetterValidation()).
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

    /**
     * A POST shaped like the former Z-Submit hand-over ("episciences_form") is ignored:
     * the repository and the identifier are neither pre-filled nor locked.
     */
    public function testFormerZSubmitHandOverDoesNotPrefillTheForm(): void
    {
        $this->givenJournal();
        $this->logInAs(['member']);
        Zend_Registry::set('reviewSettings', ['repositories' => [Episciences_Repositories::ZENODO_REPO_ID]]);
        $request = $this->postRequest(['episciences_form' => [
            'ci' => '10.5281/zenodo.100',
            'repoid' => Episciences_Repositories::ZENODO_REPO_ID,
            'doi_show' => '10.5281/zenodo.101',
        ]]);

        $controller = new SubmitController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->indexAction();

        /** @var Zend_Form $form */
        $form = $controller->view->form;
        $searchDoc = $form->getSubForm('search_doc');
        self::assertNotNull($searchDoc);

        foreach (['repoId', 'docId'] as $name) {
            $element = $searchDoc->getElement($name);
            self::assertNotNull($element, $name);
            self::assertEmpty($element->getAttrib('disabled'), $name . ' is not locked');
        }

        self::assertEmpty($searchDoc->getElement('docId')->getValue(), 'the identifier is not pre-filled');
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
