<?php

namespace unit\library\Episciences\View\Helper;

use Episciences\Paper\Spdx\License;
use Episciences_View_Helper_LicenseFormSelect;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Unit tests for Episciences_View_Helper_LicenseFormSelect
 *
 * @covers Episciences_View_Helper_LicenseFormSelect
 */
class LicenseFormSelectTest extends TestCase
{
    private function createHelper(array $licenses, array $names = []): Episciences_View_Helper_LicenseFormSelect
    {
        $helper = new class ($licenses, $names) extends Episciences_View_Helper_LicenseFormSelect {
            public function __construct(private array $licenses, private array $names)
            {
            }

            public function exposeBuildOptions(?string $currentValue = null): array
            {
                return $this->buildOptions($currentValue);
            }

            protected function fetchRecommendedLicenses(): array
            {
                return array_map(static fn(array $data) => new License($data), $this->licenses);
            }

            protected function fetchLicenseName(string $code): ?string
            {
                return $this->names[$code] ?? null;
            }
        };

        $helper->setView(new Zend_View());

        return $helper;
    }

    private function recommended(): array
    {
        return [
            ['code' => 'MIT', 'name' => 'MIT License'],
            ['code' => 'CC-BY-4.0', 'name' => 'Creative Commons Attribution 4.0 International'],
            ['code' => 'Apache-2.0', 'name' => 'Apache License 2.0'],
        ];
    }

    public function testEmptyOptionIsFirstAndOptionsAreSortedByName(): void
    {
        $options = $this->createHelper($this->recommended())->exposeBuildOptions();

        $this->assertSame(
            ['', 'Apache-2.0', 'CC-BY-4.0', 'MIT'],
            array_keys($options)
        );
        $this->assertSame(Episciences_View_Helper_LicenseFormSelect::EMPTY_LABEL, $options['']);
    }

    public function testNonRecommendedCurrentLicenseIsAdded(): void
    {
        $options = $this->createHelper($this->recommended(), ['GPL-3.0-only' => 'GNU General Public License v3.0 only'])
            ->exposeBuildOptions('GPL-3.0-only');

        $this->assertSame('GNU General Public License v3.0 only (GPL-3.0-only)', $options['GPL-3.0-only']);
        $this->assertSame(['', 'Apache-2.0', 'CC-BY-4.0', 'GPL-3.0-only', 'MIT'], array_keys($options));
    }

    public function testUnknownCurrentLicenseFallsBackToCode(): void
    {
        $options = $this->createHelper($this->recommended())->exposeBuildOptions('Unknown-1.0');

        $this->assertSame('Unknown-1.0', $options['Unknown-1.0']);
    }

    public function testLabelsContainNameAndCode(): void
    {
        $options = $this->createHelper($this->recommended())->exposeBuildOptions();

        $this->assertSame('MIT License (MIT)', $options['MIT']);
        $this->assertSame('Apache License 2.0 (Apache-2.0)', $options['Apache-2.0']);
    }

    public function testFormatLabel(): void
    {
        $this->assertSame('MIT License (MIT)', Episciences_View_Helper_LicenseFormSelect::formatLabel('MIT License', 'MIT'));
        $this->assertSame('MIT', Episciences_View_Helper_LicenseFormSelect::formatLabel('mit', 'MIT'));
        $this->assertSame('MIT', Episciences_View_Helper_LicenseFormSelect::formatLabel('  ', 'MIT'));
    }

    public function testRecommendedCurrentLicenseIsNotDuplicated(): void
    {
        $options = $this->createHelper($this->recommended())->exposeBuildOptions('MIT');

        $this->assertCount(4, $options);
    }

    public function testNoLicensesReturnsOnlyEmptyOption(): void
    {
        $options = $this->createHelper([])->exposeBuildOptions();

        $this->assertSame([''], array_keys($options));
    }

    public function testRendersSelectWithIdClassAndSelectedValue(): void
    {
        $html = $this->createHelper($this->recommended())
            ->licenseFormSelect('license', 'CC-BY-4.0', ['id' => 'license-value-42', 'class' => 'form-control']);

        $this->assertStringContainsString('name="license"', $html);
        $this->assertStringContainsString('id="license-value-42"', $html);
        $this->assertStringContainsString('class="form-control"', $html);
        $this->assertMatchesRegularExpression('/value="CC-BY-4.0"[^>]*selected="selected"/', $html);
    }

    public function testDefaultIdAndClassAreDerivedFromName(): void
    {
        $html = $this->createHelper($this->recommended())->licenseFormSelect('license', '  ');

        $this->assertStringContainsString('id="license"', $html);
        $this->assertStringContainsString('class="license-select"', $html);
        $this->assertStringNotContainsString('selected="selected"', $html);
    }

    public function testLabelsAreEscaped(): void
    {
        $html = $this->createHelper([['code' => 'X', 'name' => '<script>alert(1)</script>']])
            ->licenseFormSelect('license');

        $this->assertStringNotContainsString('<script>', $html);
    }
}
