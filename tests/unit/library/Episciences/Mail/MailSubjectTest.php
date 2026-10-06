<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail;
use PHPUnit\Framework\TestCase;

/**
 * The subject of a mail is a header: it must reach the recipient as typed, without HTML
 * entities (a subject "A & B" must not arrive as "A &amp; B"). Escaping is the job of the
 * views that display it (datatable_history and view of administratemail use htmlentities()).
 */
final class MailSubjectTest extends TestCase
{
    private Episciences_Mail $mail;

    protected function setUp(): void
    {
        // The constructor needs the database
        $this->mail = $this->getMockBuilder(Episciences_Mail::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testSpecialCharactersAreNotEncodedTwice(): void
    {
        $this->mail->setSubject('Review of "A & B" <draft>');

        self::assertSame('Review of "A & B" <draft>', $this->mail->getSubject());
    }

    public function testAlreadyEncodedTextIsLeftAsTyped(): void
    {
        $this->mail->setSubject('R&amp;D');

        self::assertSame('R&amp;D', $this->mail->getSubject());
    }

    public function testTagsOfTheSubjectAreReplaced(): void
    {
        $this->mail->addTag('%%ARTICLE_TITLE%%', 'Ice & Fire');
        $this->mail->setSubject('New paper: %%ARTICLE_TITLE%%');

        self::assertSame('New paper: Ice & Fire', $this->mail->getSubject());
    }

    public function testBodyIsStillEscapedForTheHistory(): void
    {
        $this->mail->setRawBody('<script>alert(1)</script> & co');

        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt; &amp; co', $this->mail->getBody());
    }
}
