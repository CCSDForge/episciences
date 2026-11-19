<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Ccsd_Form;
use Episciences\Reviewer\NextVersionRequestType;
use Episciences_PapersManager;
use Episciences_Review;
use Episciences_User;
use unit\modules\ControllerTestCase;

/**
 * Revision request form: type of the new request made to the reviewers on the next version (#319, lot B).
 *
 * Extends ControllerTestCase for its database stub, which answers the request types of the journal.
 */
final class PapersManagerRevisionFormNextRequestTypeTest extends ControllerTestCase
{
    private const DOCID = 12;

    protected function setUp(): void
    {
        $this->logInAs([\Episciences_Acl::ROLE_SECRETARY]);
    }

    /**
     * @param list<array<string, mixed>> $types rows of the enabled request types of the journal
     */
    private function revisionForm(array $types, string $type = 'minor', bool $withAutoReassignment = true): Ccsd_Form
    {
        $this->db->respondTo('/FROM `review_request_type`/', $types);

        $author = new Episciences_User(['uid' => 99, 'firstname' => 'Ada', 'lastname' => 'Author', 'email' => 'ada@example.org']);
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getRvid')->willReturn(RVID);

        return Episciences_PapersManager::getRevisionForm(
            ['id' => self::DOCID, 'author' => $author, 'subject' => 'Revision', 'body' => 'Please revise.'],
            $type,
            $journal,
            $withAutoReassignment,
            self::DOCID
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function typeRows(): array
    {
        return [
            ['id' => 11, 'rvid' => RVID, 'default_type' => 1, 'labels' => '{"fr":"Rapport de relecture","en":"Review report"}', 'enabled' => 1],
            ['id' => 20, 'rvid' => RVID, 'default_type' => null, 'labels' => '{"fr":"Vérification de la version révisée","en":"Revised version check"}', 'enabled' => 1],
        ];
    }

    public function testTheTypeOfTheNewRequestIsChosenNextToTheReassignment(): void
    {
        $form = $this->revisionForm(self::typeRows());

        $select = $form->getElement(NextVersionRequestType::OPTION);
        self::assertNotNull($select);
        self::assertSame(['', 11, 20], array_keys($select->getMultiOptions()));
        // the "same type" choice is the default: without a choice, nothing changes
        self::assertSame('', (string)$select->getValue());
        self::assertSame(NextVersionRequestType::OPTION . '_minor-description', $select->getAttrib('aria-describedby'));
        self::assertNotNull($form->getElement('auto_reassign'));
    }

    public function testTheChoiceIsCheckedAgainstTheListedTypes(): void
    {
        $select = $this->revisionForm(self::typeRows(), 'major')->getElement(NextVersionRequestType::OPTION);
        self::assertNotNull($select);

        self::assertTrue($select->isValid(''));
        self::assertTrue($select->isValid('20'));
        self::assertFalse($select->isValid('999'));
    }

    public function testNoChoiceWithoutAnyEnabledType(): void
    {
        self::assertNull($this->revisionForm([])->getElement(NextVersionRequestType::OPTION));
    }

    public function testNoChoiceWhenAskingTheFinalVersionOfAnAcceptedPaper(): void
    {
        self::assertNull($this->revisionForm(self::typeRows(), 'minor', false)->getElement(NextVersionRequestType::OPTION));
    }
}
