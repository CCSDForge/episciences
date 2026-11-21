<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Import;

use Episciences\User\Import\UserCsvReader;
use Episciences\User\Import\UserImportRow;
use Episciences_Acl;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\Import\UserCsvReader
 * @covers \Episciences\User\Import\UserImportRow
 */
final class UserCsvReaderTest extends TestCase
{
    private const string SAMPLES = __DIR__ . '/../../../../../../scripts/importSamples/';
    private const string FIXTURES = __DIR__ . '/fixtures/';

    private function reader(): UserCsvReader
    {
        $roles = array_values((new Episciences_Acl())->getRolesCodes());

        return new UserCsvReader($roles, ['fr', 'en']);
    }

    public function testTheSampleFileIsValid(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::SAMPLES . 'sample-users.csv');

        self::assertSame([], $errors);
        self::assertCount(10, $rows);
        self::assertContainsOnlyInstancesOf(UserImportRow::class, $rows);
    }

    public function testAnEmptyRoleColumnMeansMember(): void
    {
        $rows = $this->reader()->read(self::SAMPLES . 'sample-users.csv')['rows'];

        self::assertSame([Episciences_Acl::ROLE_MEMBER], $rows[0]->roles);
    }

    public function testSeveralRolesAreSeparatedByCommas(): void
    {
        $rows = $this->reader()->read(self::SAMPLES . 'sample-users.csv')['rows'];

        self::assertSame(['editor', 'reviewer'], $rows[2]->roles);
        self::assertSame('en', $rows[2]->lang);
    }

    public function testAnEmptyLanguageIsLeftToTheDefault(): void
    {
        $rows = $this->reader()->read(self::SAMPLES . 'sample-users.csv')['rows'];

        self::assertNull($rows[3]->lang);
    }

    public function testEveryInvalidLineIsReportedWithItsNumber(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::SAMPLES . 'sample-users-invalid.csv');

        self::assertCount(1, $rows, 'only the first Jordan Nguyen line is valid');
        self::assertCount(5, $errors);
        self::assertStringStartsWith('line 2: invalid email', $errors[0]);
        self::assertStringStartsWith('line 3: missing lastname', $errors[1]);
        self::assertStringStartsWith('line 4: unknown role "redactor"', $errors[2]);
        self::assertStringStartsWith('line 5: unavailable language "de"', $errors[3]);
        self::assertSame('line 7: email "Jordan.Nguyen@example.org" already on line 6', $errors[4]);
    }

    public function testAUtf8BomBeforeTheHeaderIsIgnored(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::FIXTURES . 'bom.csv');

        self::assertSame([], $errors);
        self::assertSame('Ada', $rows[0]->firstname);
    }

    public function testAUtf8BomBeforeAQuotedHeaderIsIgnored(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::FIXTURES . 'bom-quoted-header.csv');

        self::assertSame([], $errors);
        self::assertSame('Ada', $rows[0]->firstname);
        self::assertSame('ada.lovelace@example.org', $rows[0]->email);
    }

    public function testColumnsAreFoundByTheirNameAndBlankLinesAreSkipped(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::FIXTURES . 'reordered-columns.csv');

        self::assertSame([], $errors);
        self::assertCount(1, $rows);
        self::assertSame('Ada', $rows[0]->firstname);
        self::assertSame('Lovelace', $rows[0]->lastname);
        self::assertSame(['editor'], $rows[0]->roles);
        self::assertSame(3, $rows[0]->line);
    }

    public function testAHeaderWithoutTheRequiredColumnsIsRefused(): void
    {
        ['rows' => $rows, 'errors' => $errors] = $this->reader()->read(self::FIXTURES . 'wrong-header.csv');

        self::assertSame([], $rows);
        self::assertCount(1, $errors);
        self::assertStringStartsWith('line 1: the header must name the columns firstname, lastname, email', $errors[0]);
    }

    public function testAMissingFileIsReported(): void
    {
        $errors = $this->reader()->read(self::FIXTURES . 'missing.csv')['errors'];

        self::assertCount(1, $errors);
        self::assertStringStartsWith('cannot read the file', $errors[0]);
    }
}
