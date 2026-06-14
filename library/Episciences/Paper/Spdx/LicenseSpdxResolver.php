<?php

namespace Episciences\Paper\Spdx;

final class LicenseSpdxResolver
{
    public const NO_ASSERTION = 'NOASSERTION';
    public const SPDX_LICENSE_LIST_URL = 'https://spdx.org/licenses/';
    private ?array $spdxIndex = null;

    public const LICENSE_SEPARATOR = '-';

    public function __construct(private ?LicenseProviderInterface $licenseProvider = null)
    {
        if (null === $licenseProvider) {
            $licenseProvider = new LicenseManagerWrapper();
        }

        $this->licenseProvider = $licenseProvider;
        $this->loadSpdxIndex();
    }

    public static function urlToSpdxCode(string $str): string
    {
        if (preg_match(
                '#^' . preg_quote(self::SPDX_LICENSE_LIST_URL, '#') . '([^/]+)\.html$#',
                $str,
                $matches
        )
        ) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Convert license to SPDX code
     */
    public function resolve($input)
    {

        $spdxCode = self::urlToSpdxCode($input);

        if ($spdxCode !== '') {
            return $spdxCode;
        }

        $norm = $this->normalize($input);

        $spdxCode = $this->matchSpdx($norm);

        return $spdxCode ?: self::NO_ASSERTION;
    }

    /**
     * Charge la base SPDX (JSON officiel)
     */
    private function loadSpdxIndex(): void
    {
        if (null !== $this->spdxIndex) {
            return;
        }

        $index = [];
        $licenseList = $this->licenseProvider?->loadSpdxCode();
        foreach ($licenseList as $code) {
            $index[strtolower($code)] = $code;
        }

        $this->spdxIndex = $index;
    }

    private function normalize(string $input): string
    {
        // Note that the normalization process converts strings to lowercase.
        $input = $this->normalizeUrl($input);

        // MIT direct
        if ($input === '/mit') {
            return 'mit';
        }

        // Creative Commons

        $processed = $this->processCreativeCommons($input);

        if ($processed !== null) {
            return $processed;
        }

        // opensource.org
        if (preg_match('#opensource\.org/licenses/([a-z0-9.\-]+)#', $input, $matches)) {
            return strtolower($matches[1]);
        }

        // Etalab
        if (str_contains($input, 'etalab') && preg_match('#(\d+\.\d+)#', $input, $matches)) {
            return 'etalab-' . $matches[1];
        }

        // GNU GPL
        if (str_contains($input, 'gnu.org/licenses') && str_contains($input, 'gpl-3.0')) {
            return 'gpl-3.0';
        }

        // apache.org

        if (str_contains($input, 'apache.org') && str_contains($input, 'license') && preg_match('#license-([0-9.]+)(?:\.\w+)?$#', $input, $matches)) {
            // [0-9.]+ will automatically stop before ".xxx"
            $version = $matches[1];
            return 'Apache-' . $version;
        }

        return $input;
    }

    private function matchSpdx($norm)
    {
        $this->loadSpdxIndex();
        $key = strtolower($norm);

        if (isset($this->spdxIndex[$key])) {
            return $this->spdxIndex[$key];
        }

        $key = $this->urlToSpdx($norm);

        if ($key) {
            return $this->spdxIndex[strtolower($key)] ?? null;
        }

        return null;
    }

    private function resolveCc($matches): ?string
    {

        $license = $matches[1] ?? '';

        if (isset($matches[2]) && $matches[2] === '') {
            $version = 4.0;
        } else {
            $version = $matches[2];
        }

        if(!str_contains($version, '.')){
            $version .= '.0';
        }


        $parts = explode(self::LICENSE_SEPARATOR, $license);

        if (empty($parts) || $parts[0] !== 'by') {
            return null;
        }

        // remove 'by'
        array_shift($parts);

        // Licence order
        $order = ['nc', 'nd', 'sa'];

        // sort the remaining parts
        $this->sortByPositionInOrder($parts, $order);

        $suffix = $parts
                ? self::LICENSE_SEPARATOR . implode(self::LICENSE_SEPARATOR, array_map('strtolower', $parts))
                : '';

        return sprintf('cc%sby', self::LICENSE_SEPARATOR) . $suffix . self::LICENSE_SEPARATOR . $version;

    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^http://#', 'https://', $url);
        return rtrim($url, '/');
    }

    private function specialSpdxMap(): array
    {
        return [
            // public Domain
                'https://creativecommons.org/choose/mark'
                => 'CC-PDDC',

                'https://creativecommons.org/publicdomain/mark/1.0'
                => 'CC-PDDC',

                'https://creativecommons.org/publicdomain/zero/1.0'
                => 'CC0-1.0',

            // Fonts

                'https://scripts.sil.org/cms/scripts/page.php?item_id=ofl_web'
                => 'OFL-1.1',

                'https://raw.githubusercontent.com/disic/politique-de-contribution-open-source/master/license'
                => 'Etalab-2.0',
        ];
    }

    private function urlToSpdx(string $url): ?string
    {
        $url = $this->normalizeUrl($url);

        $map = $this->specialSpdxMap();

        return $map[$url] ?? null;
    }

    private function processCreativeCommons(string $input): ?string
    {



        if (preg_match('#creativecommons\.org/licenses/([^/]+)/?([^/]*)#', $input, $matches)) {
            return $this->resolveCc($matches);
        }


        // CC0
        if (preg_match('#creativecommons\.org/publicdomain/zero/([^/]+)#', $input, $matches)) {
            return sprintf('cc0%s', self::LICENSE_SEPARATOR) . $matches[1];
        }

        return null;

    }

    /**
     * /Sort $parts based on the position of each element in $order.
     * @param array $itemsToSort
     * @param array $order
     * @return void
     */

    private function sortByPositionInOrder(array &$itemsToSort, array $order): void
    {
        usort($itemsToSort, static fn($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true)
        );
    }

    public function isValid(string $spdXCode): bool
    {
        return $this->matchSpdx($spdXCode) !== null;
    }


    // for test if necessary
    public function getSpdxIndex(array $index): ?array
    {
        return $this->spdxIndex;
    }
}
