<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_MedRxiv_Hooks.
 *
 * MedRxiv_Hooks only sets the server in its constructor.
 * Inherited logic is tested in Episciences_Repositories_BioMedRxivTest.
 *
 * @covers Episciences_Repositories_MedRxiv_Hooks
 */
class Episciences_Repositories_MedRxiv_HooksTest extends TestCase
{
    public function testInstantiationSetsServer(): void
    {
        $hooks = new Episciences_Repositories_MedRxiv_Hooks();
        self::assertInstanceOf(Episciences_Repositories_BioMedRxiv::class, $hooks);
    }

    public function testServerIsSet(): void
    {
        $hooks = new Episciences_Repositories_MedRxiv_Hooks();
        $server = $hooks->getServer();
        self::assertNotEmpty($server);
        self::assertIsString($server);
        self::assertStringContainsString('medrxiv', strtolower($server));
    }

    public function testServerDiffersFromBioRxiv(): void
    {
        $bio = new Episciences_Repositories_BioRxiv_Hooks();
        $med = new Episciences_Repositories_MedRxiv_Hooks();
        self::assertNotSame($bio->getServer(), $med->getServer());
    }
}
