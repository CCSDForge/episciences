<?php

namespace unit\library\Episciences;

use Episciences_Paper_Authors_ViewFormatter;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_Authors_ViewFormatterTest extends TestCase
{
    public function testFormatAuthorsWithFullData(): void
    {
        $authors = [
            [
                'fullname' => 'John Doe',
                'orcid' => '0000-0001-2345-678X',
                'affiliation' => [
                    ['name' => 'University A', 'id' => [['id' => 'https://ror.org/1', 'id-type' => 'ROR']]]
                ]
            ],
            [
                'fullname' => 'Jane Smith',
                'affiliation' => [
                    ['name' => 'University A', 'id' => [['id' => 'https://ror.org/1', 'id-type' => 'ROR']]],
                    ['name' => 'University B']
                ]
            ]
        ];

        $result = Episciences_Paper_Authors_ViewFormatter::formatAuthors($authors);

        // Check author list text
        self::assertEquals('John Doe;Jane Smith', $result['authorsList']);

        // Check ORCID text
        self::assertEquals('0000-0001-2345-678X##NULL', $result['orcid']);

        // Check Affiliation List HTML
        $this->assertStringContainsString('University A', $result['listAffi']);
        $this->assertStringContainsString('University B', $result['listAffi']);
        // Verify deduplication (University A should appear once in list)
        self::assertEquals(1, substr_count($result['listAffi'], 'University A'));
    }

    public function testFormatAuthorsEscapesXssInAcronym(): void
    {
        $authors = [
            [
                'fullname' => 'Hacker',
                'affiliation' => [
                    [
                        'name' => 'Evil Corp',
                        'id' => [
                            [
                                'id' => 'https://ror.org/evil',
                                'id-type' => 'ROR',
                                'acronym' => '<script>alert(1)</script>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = Episciences_Paper_Authors_ViewFormatter::formatAuthors($authors);

        self::assertStringNotContainsString('<script>', $result['listAffi']);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result['listAffi']);
    }

    public function testFormatAuthorsHandlesEmptyInput(): void
    {
        $result = Episciences_Paper_Authors_ViewFormatter::formatAuthors([]);

        self::assertEquals('', $result['template']);
        self::assertEquals('', $result['orcid']);
        self::assertEquals('', $result['listAffi']);
        self::assertEquals('', $result['authorsList']);
    }

    public function testSuperscriptLogic(): void
    {
        $authors = [
            [
                'fullname' => 'Author 1',
                'affiliation' => [
                    ['name' => 'A'], // Should be 1
                    ['name' => 'B']  // Should be 2
                ]
            ],
            [
                'fullname' => 'Author 2',
                'affiliation' => [
                    ['name' => 'B'], // Should be 2
                    ['name' => 'C']  // Should be 3
                ]
            ]
        ];

        $result = Episciences_Paper_Authors_ViewFormatter::formatAuthors($authors);

        // Author 1 superscripts: 1,2
        $this->assertStringContainsString('<sup>1,</sup><sup>2</sup>', $result['template']);

        // Author 2 superscripts: 2,3
        $this->assertStringContainsString('<sup>2,</sup><sup>3</sup>', $result['template']);
    }
}
