<?php

namespace unit\library\Episciences\user;

use Episciences_User_InvitationAnswer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_InvitationAnswer
 *
 * Tests pure logic: setters/getters, detail sanitization (strip_tags,
 * htmlspecialchars, trim), getDetail/setDetail, constants.
 * save() requires DB and is not tested here.
 *
 * @covers Episciences_User_InvitationAnswer
 */
class Episciences_User_InvitationAnswerTest extends TestCase
{
    private Episciences_User_InvitationAnswer $answer;

    protected function setUp(): void
    {
        $this->answer = new Episciences_User_InvitationAnswer();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testAnswerConstants(): void
    {
        $this->assertSame('yes', Episciences_User_InvitationAnswer::ANSWER_YES);
        $this->assertSame('no',  Episciences_User_InvitationAnswer::ANSWER_NO);
    }

    public function testDetailConstants(): void
    {
        $this->assertSame('delay',            Episciences_User_InvitationAnswer::DETAIL_DELAY);
        $this->assertSame('reviewer_suggest', Episciences_User_InvitationAnswer::DETAIL_SUGGEST);
        $this->assertSame('comment',          Episciences_User_InvitationAnswer::DETAIL_COMMENT);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testDefaultConstructorCreatesEmptyAnswer(): void
    {
        $this->assertNull($this->answer->getId());
        $this->assertNull($this->answer->getAnswer());
        $this->assertNull($this->answer->getAnswer_date());
    }

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $answer = new Episciences_User_InvitationAnswer([
            'id'     => 5,
            'answer' => Episciences_User_InvitationAnswer::ANSWER_YES,
        ]);

        $this->assertSame(5, $answer->getId());
        $this->assertSame(Episciences_User_InvitationAnswer::ANSWER_YES, $answer->getAnswer());
    }

    // -------------------------------------------------------------------------
    // id
    // -------------------------------------------------------------------------

    public function testSetAndGetId(): void
    {
        $this->answer->setId(12);
        $this->assertSame(12, $this->answer->getId());
    }

    // -------------------------------------------------------------------------
    // answer
    // -------------------------------------------------------------------------

    public function testSetAndGetAnswer(): void
    {
        $this->answer->setAnswer(Episciences_User_InvitationAnswer::ANSWER_NO);
        $this->assertSame(Episciences_User_InvitationAnswer::ANSWER_NO, $this->answer->getAnswer());
    }

    // -------------------------------------------------------------------------
    // answer_date
    // -------------------------------------------------------------------------

    public function testSetAndGetAnswerDate(): void
    {
        $this->answer->setAnswer_date('2024-03-01 14:30:00');
        $this->assertSame('2024-03-01 14:30:00', $this->answer->getAnswer_date());
    }

    // -------------------------------------------------------------------------
    // setDetails / getDetails — cleanDetailValue sanitization
    // -------------------------------------------------------------------------

    public function testSetDetailsStripsHtmlTags(): void
    {
        $this->answer->setDetails([
            Episciences_User_InvitationAnswer::DETAIL_COMMENT => '<b>Bold</b> comment',
        ]);
        $details = $this->answer->getDetails();
        $this->assertStringNotContainsString('<b>', $details[Episciences_User_InvitationAnswer::DETAIL_COMMENT]);
        $this->assertStringContainsString('Bold', $details[Episciences_User_InvitationAnswer::DETAIL_COMMENT]);
    }

    public function testSetDetailsEscapesHtmlEntities(): void
    {
        // Note: cleanDetailValue is applied in both setDetails() and getDetails(),
        // causing double HTML-encoding: '&' → '&amp;' → '&amp;amp;', '>' → '&gt;' → '&amp;gt;'.
        $this->answer->setDetails([
            Episciences_User_InvitationAnswer::DETAIL_COMMENT => 'A & B > C',
        ]);
        $details = $this->answer->getDetails();
        $value = $details[Episciences_User_InvitationAnswer::DETAIL_COMMENT];

        // Raw special characters must not appear in the output
        $this->assertStringNotContainsString(' & ', $value);
        $this->assertStringNotContainsString(' > ', $value);
        // After double encoding, '&' becomes '&amp;amp;'
        $this->assertStringContainsString('&amp;amp;', $value);
    }

    public function testSetDetailsTrimsWhitespace(): void
    {
        $this->answer->setDetails([
            Episciences_User_InvitationAnswer::DETAIL_COMMENT => '  trimmed  ',
        ]);
        $details = $this->answer->getDetails();
        $this->assertSame('trimmed', $details[Episciences_User_InvitationAnswer::DETAIL_COMMENT]);
    }

    public function testSetDetailsWithMultipleKeys(): void
    {
        $this->answer->setDetails([
            Episciences_User_InvitationAnswer::DETAIL_DELAY   => '2 weeks',
            Episciences_User_InvitationAnswer::DETAIL_COMMENT => 'Happy to review',
        ]);
        $details = $this->answer->getDetails();
        $this->assertArrayHasKey(Episciences_User_InvitationAnswer::DETAIL_DELAY, $details);
        $this->assertArrayHasKey(Episciences_User_InvitationAnswer::DETAIL_COMMENT, $details);
        $this->assertSame('2 weeks', $details[Episciences_User_InvitationAnswer::DETAIL_DELAY]);
        $this->assertSame('Happy to review', $details[Episciences_User_InvitationAnswer::DETAIL_COMMENT]);
    }

    public function testSetDetailsWithEmptyArray(): void
    {
        $this->answer->setDetails([]);
        $this->assertSame([], $this->answer->getDetails());
    }

    // -------------------------------------------------------------------------
    // getDetail / setDetail
    // -------------------------------------------------------------------------

    public function testSetDetailAndGetDetail(): void
    {
        $this->answer->setDetail(Episciences_User_InvitationAnswer::DETAIL_SUGGEST, 'John Doe');
        $result = $this->answer->getDetail(Episciences_User_InvitationAnswer::DETAIL_SUGGEST);
        $this->assertSame('John Doe', $result);
    }

    public function testGetDetailReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->answer->getDetail('nonexistent_key'));
    }

    public function testSetDetailStripsTagsFromValue(): void
    {
        $this->answer->setDetail(Episciences_User_InvitationAnswer::DETAIL_COMMENT, '<script>alert(1)</script>safe');
        $result = $this->answer->getDetail(Episciences_User_InvitationAnswer::DETAIL_COMMENT);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('safe', $result);
    }

    public function testSetDetailTrimsValue(): void
    {
        $this->answer->setDetail(Episciences_User_InvitationAnswer::DETAIL_DELAY, '  3 days  ');
        $result = $this->answer->getDetail(Episciences_User_InvitationAnswer::DETAIL_DELAY);
        $this->assertSame('3 days', $result);
    }

    public function testGetDetailsDefaultIsEmptyArray(): void
    {
        $this->assertSame([], $this->answer->getDetails());
    }
}
