<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Funding;

use Episciences\Paper\Funding\FundingReference;
use Episciences\Paper\Funding\FundingReferenceFactory;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Episciences_Paper::processProjects() rebuilds the "fundref" program that toJson() decodes
 * from the Crossref export, so that every project title lands in its own fundgroup.
 */
final class PaperFundrefToJsonTest extends TestCase
{
    /**
     * Decode a Crossref-like journal_article the way toJson() does.
     *
     * @return array<string, mixed>
     */
    private function decodedCrossref(string $fundref): array
    {
        $xml = '<doi_batch><body><journal><journal_article>'
            . $fundref
            . '<program name="AccessIndicators"><free_to_read/></program>'
            . '<program><related_item/></program>'
            . '</journal_article></journal></body></doi_batch>';

        return (new XmlEncoder())->decode($xml, 'xml');
    }

    /**
     * @param array<string, mixed> $data
     * @param list<FundingReference> $references
     * @return array<string, mixed>
     */
    private function process(array $data, array $references): array
    {
        $method = new ReflectionMethod(Episciences_Paper::class, 'processProjects');
        $method->invokeArgs(new Episciences_Paper(), [&$data, $references]);

        return $data;
    }

    public function testEachProjectTitleStaysInItsOwnFundgroup(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['funderName' => 'European Commission', 'code' => '101017452', 'projectTitle' => 'OpenAIRE-Nexus'],
            ['funderName' => 'French National Research Agency (ANR)', 'code' => 'ANR-22-CE45-0024', 'projectTitle' => 'ENERGENCE'],
            ['fundingStatement' => 'Some regional programme'],
        ]);
        $data = $this->decodedCrossref(
            '<program name="fundref"><assertion name="fundgroup"/><assertion name="fundgroup"/></program>'
        );

        $programs = $this->process($data, $references)['body']['journal']['journal_article']['program'];

        self::assertSame([
            '@name' => 'fundref',
            'assertion' => [
                [
                    '@name' => 'fundgroup',
                    'assertion' => [
                        ['@name' => 'funder_name', '#' => 'European Commission'],
                        ['@name' => 'award_number', '#' => '101017452'],
                        ['@name' => 'project_title', '#' => 'OpenAIRE-Nexus'],
                    ],
                ],
                [
                    '@name' => 'fundgroup',
                    'assertion' => [
                        ['@name' => 'funder_name', '#' => 'French National Research Agency (ANR)'],
                        ['@name' => 'award_number', '#' => 'ANR-22-CE45-0024'],
                        ['@name' => 'project_title', '#' => 'ENERGENCE'],
                    ],
                ],
            ],
        ], $programs[0]);
        self::assertSame('AccessIndicators', $programs[1]['@name'], 'other programs untouched');
        self::assertCount(3, $programs);
    }

    public function testSingleFundgroupKeepsTheDecodedObjectShape(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['funderName' => 'European Commission', 'projectTitle' => 'OpenAIRE-Nexus'],
        ]);
        $data = $this->decodedCrossref('<program name="fundref"><assertion name="fundgroup"/></program>');

        $fundref = $this->process($data, $references)['body']['journal']['journal_article']['program'][0];

        self::assertSame('fundgroup', $fundref['assertion']['@name']);
        self::assertSame(
            [['@name' => 'funder_name', '#' => 'European Commission'], ['@name' => 'project_title', '#' => 'OpenAIRE-Nexus']],
            $fundref['assertion']['assertion']
        );
    }

    public function testNothingChangesWithoutFundrefProgram(): void
    {
        $data = $this->decodedCrossref('');

        self::assertSame($data, $this->process($data, FundingReferenceFactory::fromStoredList([
            ['funderName' => 'European Commission'],
        ])));
    }
}
