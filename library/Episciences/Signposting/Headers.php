<?php

declare(strict_types=1);

namespace Episciences\Signposting;

trait Headers
{
    /**
     * Strip CR and LF characters to prevent HTTP header injection.
     */
    private static function sanitizeHeaderValue(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    /**
     * @param bool   $paperHasDoi
     * @param string $paperUrl
     * @param string $paperDoi
     * @param array<int, string> $existingHeaderLinks
     * @return array<int, string>
     */
    public static function getPaperHeaderLinks(bool $paperHasDoi, string $paperUrl, string $paperDoi = '', array $existingHeaderLinks = []): array
    {
        $paperUrl = self::sanitizeHeaderValue($paperUrl);
        $paperDoi = self::sanitizeHeaderValue($paperDoi);

        $newHeaderLinks = $existingHeaderLinks;

        if ($paperHasDoi && $paperDoi !== '') {
            $newHeaderLinks[] = sprintf('<%s%s>; rel="cite-as"', \Episciences_DoiTools::DOI_ORG_PREFIX, $paperDoi);
        }

        $newHeaderLinks[] = '<https://schema.org/ScholarlyArticle>; rel="type"';

        $describedByTemplates = [
            'pdf'      => 'application/pdf',
            'bibtex'   => 'application/x-bibtex',
            'tei'      => 'application/xml',
            'dc'       => 'application/xml',
            'openaire' => 'application/xml',
            'crossref' => 'application/xml',
        ];

        $formats = [
            'tei'      => 'http://www.tei-c.org/ns/1.0',
            'dc'       => 'http://purl.org/dc/elements/1.1/',
            'openaire' => 'http://namespace.openaire.eu/schema/oaire/',
            'crossref' => 'http://www.crossref.org/schema/5.3.1',
        ];

        foreach ($describedByTemplates as $type => $mimeType) {
            if (isset($formats[$type])) {
                $newHeaderLinks[] = sprintf('<%s/%s>; rel="describedby"; type="%s"; formats="%s"', $paperUrl, $type, $mimeType, $formats[$type]);
            } else {
                $newHeaderLinks[] = sprintf('<%s/%s>; rel="describedby"; type="%s"', $paperUrl, $type, $mimeType);
            }
        }

        return $newHeaderLinks;
    }
}
