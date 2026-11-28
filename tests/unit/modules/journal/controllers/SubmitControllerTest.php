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

    public function testFormerZSubmitHandOverDoesNotPrefillTheForm(): void
    {
        $repositories = [
            (string)Episciences_Repositories::HAL_REPO_ID,
            (string)Episciences_Repositories::ZENODO_REPO_ID,
        ];
        $this->givenJournal();
        Zend_Registry::set('reviewSettings', ['repositories' => $repositories]);

        $request = $this->postRequest([
            'episciences_form' => [
                'repoid' => (string)Episciences_Repositories::ZENODO_REPO_ID,
                'doi_show' => '10.5281/zenodo.123456',
                'ci' => '123455',
            ],
        ]);
        $response = new Zend_Controller_Response_HttpTestCase();
        $controller = new SubmitController($request, $response);
        $controller->indexAction();

        self::assertFalse($response->isRedirect());
        $form = $controller->view->form;
        self::assertInstanceOf(Zend_Form::class, $form);

        $searchDoc = $form->getSubForm('search_doc');
        self::assertNotNull($searchDoc);

        $repoId = $searchDoc->getElement('repoId');
        $docId = $searchDoc->getElement('docId');
        self::assertNotNull($repoId);
        self::assertNotNull($docId);

        self::assertEmpty($docId->getValue());
        self::assertNull($docId->getAttrib('readonly'));
        self::assertNull($docId->getAttrib('disabled'));

        self::assertNotSame((string)Episciences_Repositories::ZENODO_REPO_ID, (string)$repoId->getValue());
        self::assertNull($repoId->getAttrib('readonly'));
        self::assertNull($repoId->getAttrib('disabled'));
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
