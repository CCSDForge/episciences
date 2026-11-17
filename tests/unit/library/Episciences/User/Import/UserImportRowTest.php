<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Import;

use Episciences\User\Import\UserImportRow;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\Import\UserImportRow
 */
final class UserImportRowTest extends TestCase
{
    private const array ROLES = ['member', 'editor', 'reviewer', 'epiadmin'];
    private const array LANGS = ['fr', 'en'];

    /**
     * @param array<string, string> $record
     */
    private static function row(array $record): UserImportRow
    {
        return UserImportRow::fromRecord(5, $record + ['firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.org'], self::ROLES, self::LANGS);
    }

    public function testValuesAreTrimmed(): void
    {
        $row = self::row(['firstname' => ' Ada ', 'lastname' => ' Lovelace ', 'email' => ' ada@example.org ', 'role' => ' editor , reviewer ', 'lang' => ' FR ']);

        self::assertSame(5, $row->line);
        self::assertSame('Ada', $row->firstname);
        self::assertSame('Lovelace', $row->lastname);
        self::assertSame('ada@example.org', $row->email);
        self::assertSame(['editor', 'reviewer'], $row->roles);
        self::assertSame('fr', $row->lang);
    }

    public function testRepeatedRolesAreKeptOnce(): void
    {
        self::assertSame(['editor'], self::row(['role' => 'editor,editor,'])->roles);
    }

    public function testTheFirstnameIsOptional(): void
    {
        self::assertSame('', self::row(['firstname' => ''])->firstname);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidRecords(): array
    {
        return [
            'no lastname' => [['lastname' => '  '], 'missing lastname'],
            'invalid email' => [['email' => 'ada.example.org'], 'invalid email "ada.example.org"'],
            'empty email' => [['email' => ''], 'invalid email ""'],
            'email the sanitisation would change' => [['email' => 'ad a@example.org'], 'invalid email "ad a@example.org"'],
            'unknown role' => [['role' => 'editor,redactor'], 'unknown role "redactor"'],
            'unavailable language' => [['lang' => 'de'], 'unavailable language "de"'],
        ];
    }

    /**
     * @param array<string, string> $record
     */
    #[DataProvider('invalidRecords')]
    public function testInvalidValuesAreRefused(array $record, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::row($record);
    }
}
