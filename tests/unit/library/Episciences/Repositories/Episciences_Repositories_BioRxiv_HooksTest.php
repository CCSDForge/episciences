<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_BioRxiv_Hooks.
 *
 * BioRxiv_Hooks only sets the server in its constructor.
 * Inherited logic is tested in Episciences_Repositories_BioMedRxivTest.
 *
 * @covers Episciences_Repositories_BioRxiv_Hooks
 */
class Episciences_Repositories_BioRxiv_HooksTest extends TestCase
{
    public function testInstantiationSetsServer(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        self::assertInstanceOf(Episciences_Repositories_BioMedRxiv::class, $hooks);
    }

    public function testServerIsSet(): void
    {
        $hooks = new Episciences_Repositories_BioRxiv_Hooks();
        $server = $hooks->getServer();
        // BioRxiv server should be the biorxiv.org API URL
        self::assertNotEmpty($server);
        self::assertIsString($server);
        self::assertStringContainsString('biorxiv', strtolower($server));
    }
}
