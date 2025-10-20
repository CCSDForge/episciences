<?php


class Episciences_Repositories_Common
{
    public const ENRICHMENT = 'enrichment';
    public const CITATIONS = 'citations'; // documents citing
    public const REFERENCES_EPI_CITATIONS = 'references'; // documents cited
    public const PROJECTS = 'projects';
    public const CONTRIB_ENRICHMENT = 'authors';
    public const LICENSE_ENRICHMENT = 'license';
    public const RESOURCE_TYPE_ENRICHMENT = 'type';
    public const TO_COMPILE_OAI_DC = 'toCompileOaiDc';
    public const FILES = 'files';

    public const AVAILABLE_ENRICHMENT = [
        self::CONTRIB_ENRICHMENT,
        self::CITATIONS,
        self::PROJECTS,
        self::RESOURCE_TYPE_ENRICHMENT
    ];


    public static function isOpenAccessRight(array $hookParams): array
    {

        $isOpenAccessRight = false;
        $pattern = '<dc:rights>info:eu-repo/semantics/openAccess</dc:rights>';

        if (array_key_exists('record', $hookParams)) {
            $isOpenAccessRight = !empty(Episciences_Tools::extractPattern('~' . $pattern . '~', $hookParams['record']));
        }


        return ['isOpenAccessRight' => $isOpenAccessRight];

    }

    public static function cleanAndPrepare(array $params = []): array
    {

        if (!isset($params['id'])) {
            return [];
        }

        $identifier = trim($params['id']);

        if (
            isset($params['repoId']) &&
            Episciences_Repositories::isDataverse($params['repoId']) &&
            !str_starts_with($identifier, Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX)) {

            $identifier = Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX . $identifier;
        }

        return ['identifier' => $identifier];
    }


    public static function isRequiredVersion(bool $isRequired = true): bool
    {
        return $isRequired;
    }


    public static function toDublinCore(array $elements = ['headers' => [], 'body' => []]): string
    {

        $result = '';

        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');

        $xml->formatOutput = false;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;


        try {

            $record = $xml->createElement('record');

            if (isset($elements['headers']) && is_array($elements['headers'])) {
                $header = $xml->createElement('header');
                $record->appendChild(self::addXmlElements($xml, $header, $elements['headers']));
            }

            $dc = $xml->createElement('oai_dc:dc');
            $dc->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:oai_dc',
                'http://www.openarchives.org/OAI/2.0/oai_dc/'
            );
            $dc->setAttributeNS('http://www.w3.org/2000/xmlns/',
                'xmlns:dc',
                'http://purl.org/dc/elements/1.1/'
            );

            $metadata = $xml->createElement('metadata');

            if (isset($elements['body']) && is_array($elements['body'])) {
                $metadata->appendChild(self::addXmlElements($xml, $dc, $elements['body'], 'dc:'));
            }

            $record->appendChild($metadata);

            $result = $xml->saveXML($record);


        } catch (DOMException $e) {
            trigger_error($e->getMessage());
        }

        return $result;

    }


    /**
     * @throws DOMException
     */
    private static function addXmlElements(
        DOMDocument $xml,
        DOMElement  $root,
        array       $xmlElements,
        string      $prefix = ''
    ): \DOMElement
    {
        $defaultLanguage = 'en';

        foreach ($xmlElements as $key => $values) {

            if (is_array($values)) {

                foreach ($values as $value) {

                    if (is_array($value)) {
                        $currentValue = $value['value'] ?? '';
                        $currentLanguage = $value['language'] ?? $defaultLanguage;
                    } else {
                        $currentValue = $value;
                        $currentLanguage = $defaultLanguage;

                    }

                    $xmlElement = $xml->createElement(
                        $prefix . $key,
                        ($key === 'identifier' && Episciences_Tools::isDoi($currentValue)) ? 'info:doi:' . $currentValue : $currentValue
                    );

                    if (($key === 'description') || ($key === 'title')) {
                        $xmlElement->setAttribute('xml:lang', lcfirst(mb_substr($currentLanguage, 0,2)));
                    }

                    $root->appendChild($xmlElement);

                }

            } else {

                $xmlElement = $xml->createElement($prefix . $key, $values);

                if (($key === 'description') || ($key === 'title')) {
                    $xmlElement->setAttribute('xml:lang', $defaultLanguage);
                }


                $root->appendChild($xmlElement);

            }

        }

        return $root;

    }

    public static function checkAndCleanRecord(string $record): string
    {

        $search = 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';
        $replace = 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';

        return str_replace($search, $replace, $record);

    }

    public static function formatReferences(array $reference = []) : array{

        $tmp = [];

        if (!empty($reference)) {

            $rawReference = $reference['authorsStr'] ?? '';
            $rawReference .= '. ';
            $rawReference .= '(';
            $rawReference .= $reference['year'] ?? '';
            $rawReference .= ')';
            $rawReference .= '. ';
            $rawReference .= $reference['title'] ?? '';
            $rawReference .= '. ';
            $rawReference .= $reference['source'] ?? '';
            $rawReference .= ', ';
            $rawReference .= $reference['volume'] ?? '';
            $rawReference .= '(';
            $rawReference .= $reference['issue'] ?? '';
            $rawReference .= ')';
            $rawReference .= ', ';
            $rawReference .= $reference['page'] ?? '';
            $rawReference .= '.';

            if (isset($reference['doi'])) {
                $rawReference .= ' ';
                $rawReference .= $reference['doi'];
                $rawReference .= '.';
                $tmp['doi'] = $reference['doi'];
            }

            if (isset($reference['link'])) {
                $rawReference .= ' ';
                $rawReference .= $reference['link'];
                $rawReference .= '.';
            }

            $tmp['raw_reference'] = $rawReference;

        }

        return $tmp;

}

}