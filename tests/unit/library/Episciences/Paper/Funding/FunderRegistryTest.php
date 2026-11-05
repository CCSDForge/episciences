<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Funding;

use Episciences\Paper\Funding\FunderRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FunderRegistryTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function funderProvider(): array
    {
        return [
            'HAL ANR name' => ['French National Research Agency (ANR)', null, 'ANR'],
            'French ANR name, other case and spacing' => ['  agence   nationale de la RECHERCHE ', null, 'ANR'],
            'HAL European name' => ['European Commission', null, 'EC'],
            'OpenAIRE prefix wins over an unknown name' => ['Some label', 'corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898', 'EC'],
            'OpenAIRE prefix without name' => [null, 'anr_________::0001f07df68762a26a20dbcf2019af4c', 'ANR'],
            'OpenAIRE DFG prefix' => [null, 'dfgf________::abc', 'DFG'],
            'unknown funder' => ['Défi Clé CeBBOc program of Occitanie Region', null, null],
            'unknown prefix, known name' => ['Wellcome Trust', 'unknown_____::abc', 'WT'],
            'nothing' => [null, null, null],
        ];
    }

    #[DataProvider('funderProvider')]
    public function testFindKey(?string $name, ?string $openaireId, ?string $expected): void
    {
        self::assertSame($expected, FunderRegistry::findKey($name, $openaireId));
    }

    public function testIdentifiersAreUrls(): void
    {
        self::assertSame('https://doi.org/10.13039/501100001665', FunderRegistry::crossrefFunderId('ANR'));
        self::assertSame('https://ror.org/00rbzpz17', FunderRegistry::ror('ANR'));
        self::assertSame('https://doi.org/10.13039/501100000780', FunderRegistry::crossrefFunderId('EC'));
        self::assertSame('https://ror.org/00k4n6c32', FunderRegistry::ror('EC'));
    }

    public function testUnknownKeyHasNoIdentifier(): void
    {
        self::assertNull(FunderRegistry::crossrefFunderId(null));
        self::assertNull(FunderRegistry::ror('NOPE'));
        self::assertNull(FunderRegistry::displayName('NOPE'));
    }

    public function testFamily(): void
    {
        self::assertSame('EC', FunderRegistry::family('ERC'));
        self::assertSame('EC', FunderRegistry::family('EC'));
        self::assertSame('ANR', FunderRegistry::family('ANR'));
        self::assertTrue(FunderRegistry::isPartOf('ERC', 'EC'));
        self::assertFalse(FunderRegistry::isPartOf('EC', 'ERC'));
        self::assertFalse(FunderRegistry::isPartOf(null, 'EC'));
    }

    public function testOpenairePrefix(): void
    {
        self::assertSame('corda_____he', FunderRegistry::openairePrefix('corda_____he::fad6e19d7ead030721d4ead45194cafc'));
        self::assertNull(FunderRegistry::openairePrefix('no-separator'));
        self::assertNull(FunderRegistry::openairePrefix(null));
    }
}
