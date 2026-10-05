<?php

namespace unit\library\Episciences\Next\Messenger\Message;

use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RevalidateTagMessageTest extends TestCase
{
    public function testConstructsWithRvcodeAndTag(): void
    {
        $message = new RevalidateTagMessage('epijinfo', 'article-42');

        self::assertSame('epijinfo', $message->rvcode);
        self::assertSame('article-42', $message->tag);
    }

    public function testRejectsEmptyRvcode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RevalidateTagMessage('', 'article-42');
    }

    public function testRejectsBlankRvcode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RevalidateTagMessage('   ', 'article-42');
    }

    public function testRejectsEmptyTag(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RevalidateTagMessage('epijinfo', '');
    }

    public function testRejectsBlankTag(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RevalidateTagMessage('epijinfo', '   ');
    }
}
