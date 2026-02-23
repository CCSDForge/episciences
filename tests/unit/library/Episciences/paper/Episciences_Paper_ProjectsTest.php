<?php

namespace unit\library\Episciences;

use Episciences_Paper_Projects;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Paper_Projects
 */
final class Episciences_Paper_ProjectsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Bug #6: KEY_ALIASES in setOptions()
    // -------------------------------------------------------------------------

    public function testSetOptionsWithIdprojectKeyMapsToSetProjectId(): void
    {
        $project = new Episciences_Paper_Projects(['idproject' => 42]);
        self::assertSame(42, $project->getProjectId());
    }

    public function testSetOptionsWithUppercaseIdprojectKeyMapsToSetProjectId(): void
    {
        // DB column may come back as 'IDPROJECT' — alias is case-insensitive
        $project = new Episciences_Paper_Projects(['IDPROJECT' => 99]);
        self::assertSame(99, $project->getProjectId());
    }

    public function testSetOptionsWithPaperidKeyMapsToPaperId(): void
    {
        $project = new Episciences_Paper_Projects(['paperid' => 7]);
        self::assertSame(7, $project->getPaperId());
    }

    // -------------------------------------------------------------------------
    // Bug #1 + #2: $_dateUpdated default and return type
    // -------------------------------------------------------------------------

    public function testDateUpdatedIsNullByDefault(): void
    {
        $project = new Episciences_Paper_Projects();
        self::assertNull($project->getDateUpdated());
    }

    public function testSetDateUpdatedParsesStringToDateTime(): void
    {
        $project = new Episciences_Paper_Projects();
        $project->setDateUpdated('2024-06-15 10:30:00');
        $dt = $project->getDateUpdated();
        self::assertInstanceOf(\DateTime::class, $dt);
        self::assertSame('2024-06-15 10:30:00', $dt->format('Y-m-d H:i:s'));
    }

    // -------------------------------------------------------------------------
    // Bug #5: toArray() must serialize DateTime as string
    // -------------------------------------------------------------------------

    public function testToArraySerializesDateAsString(): void
    {
        $project = new Episciences_Paper_Projects();
        $project->setDateUpdated('2025-01-01 00:00:00');
        $arr = $project->toArray();
        self::assertIsString($arr['dateUpdated']);
        self::assertSame('2025-01-01 00:00:00', $arr['dateUpdated']);
    }

    public function testToArrayDateUpdatedIsNullWhenNotSet(): void
    {
        $project = new Episciences_Paper_Projects();
        $arr = $project->toArray();
        self::assertNull($arr['dateUpdated']);
    }

    // -------------------------------------------------------------------------
    // Bug #3: setFunding() must be fluent
    // -------------------------------------------------------------------------

    public function testSetFundingReturnsSelf(): void
    {
        $project = new Episciences_Paper_Projects();
        $result  = $project->setFunding('{"key":"value"}');
        self::assertSame($project, $result);
    }

    // -------------------------------------------------------------------------
    // Regression: other setters still fluent
    // -------------------------------------------------------------------------

    public function testSetProjectIdReturnsSelf(): void
    {
        $project = new Episciences_Paper_Projects();
        self::assertSame($project, $project->setProjectId(1));
    }

    public function testSetPaperIdReturnsSelf(): void
    {
        $project = new Episciences_Paper_Projects();
        self::assertSame($project, $project->setPaperId(1));
    }

    public function testSetSourceIdReturnsSelf(): void
    {
        $project = new Episciences_Paper_Projects();
        self::assertSame($project, $project->setSourceId(1));
    }
}
