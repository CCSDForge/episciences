<?php

namespace unit\library\Episciences\Paper\Import;

use Episciences\Paper\Import\PublicationDateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only the reading of the publication date from a HAL TEI is unit-tested here; resolve() needs
 * a real Episciences_Paper and (for the HAL step) network access.
 */
class PublicationDateResolverTest extends TestCase
{
    private const TEI = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>'
        . '<sourceDesc><biblStruct><monogr><imprint>%s</imprint></monogr></biblStruct></sourceDesc>'
        . '</biblFull></listBibl></body></text></TEI>';

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function publicationDateProvider(): iterable
    {
        yield 'full date' => ['<date type="datePub">2012-12-04</date>', '2012-12-04 00:00:00'];
        yield 'month' => ['<date type="datePub">2012-12</date>', '2012-12-01 00:00:00'];
        yield 'year' => ['<date type="datePub">2012</date>', '2012-01-01 00:00:00'];
        yield 'not the electronic publication date' => ['<date type="dateEpub">2011-05-02</date><date type="datePub">2012</date>', '2012-01-01 00:00:00'];
        yield 'no publication date' => ['<date type="dateEpub">2011-05-02</date>', null];
        yield 'invalid date' => ['<date type="datePub">2012-02-30</date>', null];
        yield 'not a date' => ['<date type="datePub">in press</date>', null];
    }

    #[DataProvider('publicationDateProvider')]
    public function testPublicationDateIsReadFromTheImprintOfTheTei(string $imprint, ?string $expected): void
    {
        self::assertSame($expected, PublicationDateResolver::publicationDateFromTei(sprintf(self::TEI, $imprint)));
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function productionDateProvider(): iterable
    {
        // HAL preprint: <imprint/> without datePub, the search API gives the production date
        yield 'preprint' => ['', '<edition n="v6" type="current"><date type="whenProduced">2014-02-03</date></edition>', '2014-02-03 00:00:00'];
        yield 'published document' => ['<date type="datePub">2003</date>', '<edition n="v1" type="current"><date type="whenProduced">2002-05</date></edition>', '2003-01-01 00:00:00'];
        yield 'production date of a former version' => ['', '<edition n="v1"><date type="whenProduced">2013-01-01</date></edition><edition n="v2" type="current"><date type="whenProduced">2014-02</date></edition>', '2014-02-01 00:00:00'];
        yield 'no date at all' => ['', '<edition n="v1" type="current"><date type="whenSubmitted">2014-02-11 22:14:35</date></edition>', null];
        yield 'publication date that is not valid' => ['<date type="datePub">2012-02-30</date>', '<edition n="v1" type="current"><date type="whenProduced">2011-10-05</date></edition>', '2011-10-05 00:00:00'];
        yield 'publication date that is not a date' => ['<date type="datePub">in press</date>', '<edition n="v1" type="current"><date type="whenProduced">2011-10</date></edition>', '2011-10-01 00:00:00'];
    }

    #[DataProvider('productionDateProvider')]
    public function testDocumentWithoutPublicationDateHasTheProductionDateOfItsCurrentVersion(string $imprint, string $editions, ?string $expected): void
    {
        $tei = '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>'
            . '<editionStmt>' . $editions . '</editionStmt>'
            . '<sourceDesc><biblStruct><monogr><imprint>' . $imprint . '</imprint></monogr></biblStruct></sourceDesc>'
            . '</biblFull></listBibl></body></text></TEI>';

        self::assertSame($expected, PublicationDateResolver::publicationDateFromTei($tei));
    }

    public function testTeiWithoutDocumentHasNoPublicationDate(): void
    {
        self::assertNull(PublicationDateResolver::publicationDateFromTei('<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl/></body></text></TEI>'));
    }

    public function testUnavailableTeiHasNoPublicationDate(): void
    {
        self::assertNull(PublicationDateResolver::publicationDateFromTei(''));
        self::assertNull(PublicationDateResolver::publicationDateFromTei('<TEI'));
    }
}
