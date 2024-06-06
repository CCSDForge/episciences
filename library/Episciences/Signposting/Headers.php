<?php

namespace Episciences\Signposting;

trait Headers
{

    /**
     * @param bool $paperHasDoi
     * @param string $paperUrl
     * @param string $paperDoi
     * @param array $existingHeaderLinks
     * @return array
     */
    public static function getPaperHeaderLinks(bool $paperHasDoi, string $paperUrl, string $paperDoi = '', array $existingHeaderLinks = []): array
    {
        $newHeaderLinks = [];

        if (!empty($existingHeaderLinks)) {
            $newHeaderLinks = array_merge($newHeaderLinks, $existingHeaderLinks);
        }

        if ($paperHasDoi) {
            $newHeaderLinks[] = sprintf('<%s%s>; rel="cite-as"', \Episciences_DoiTools::DOI_ORG_PREFIX, $paperDoi);
        }

        $newHeaderLinks[] = '<https://schema.org/ScholarlyArticle>; rel="type"';


        $xmlMimeType = 'application/xml';

        $tplDescribedBy = '<%s/%s>; rel="describedby"; type="%s"';
        $newHeaderLinks[] = sprintf($tplDescribedBy, $paperUrl, 'bibtex', 'application/x-bibtex');
        $newHeaderLinks[] = sprintf($tplDescribedBy, $paperUrl, 'pdf', 'application/pdf');
        $tplDescribedByWithFormat = '<%s/%s>; rel="describedby"; type="%s"; profile="%s"';
        $newHeaderLinks[] = sprintf($tplDescribedByWithFormat, $paperUrl, 'tei', $xmlMimeType, 'http://www.tei-c.org/ns/1.0');
        $newHeaderLinks[] = sprintf($tplDescribedByWithFormat, $paperUrl, 'dc', $xmlMimeType, 'http://purl.org/dc/elements/1.1/');
        $newHeaderLinks[] = sprintf($tplDescribedByWithFormat, $paperUrl, 'datacite', $xmlMimeType, 'http://datacite.org/schema/kernel-4');
        $newHeaderLinks[] = sprintf($tplDescribedByWithFormat, $paperUrl, 'crossref', $xmlMimeType, 'http://www.crossref.org/schema/4.4.2');
        return $newHeaderLinks;
    }

}

