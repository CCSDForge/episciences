<?php

namespace unit\library\Episciences\Solr\Indexing;

use PHPUnit\Framework\TestCase;

/**
 * Asserts that every field name the new Build/* collaborators can emit is
 * declared (statically or via a dynamicField pattern) in the authoritative
 * Solr schema. Catches schema/builder field-name drift that pure logic unit
 * tests, which never touch the real schema.xml, cannot.
 */
class SchemaConformanceTest extends TestCase
{
    /** @var list<string> */
    private const STATIC_FIELD_NAMES = [
        'docid', 'paperid', 'doi_s', 'language_s', 'identifier_s', 'version_td',
        'es_submission_date_tdate', 'es_publication_date_tdate', 'es_doc_url_s', 'es_pdf_url_s',
        'publication_date_tdate', 'publication_date_year_fs', 'publication_date_month_fs', 'publication_date_day_fs',
        'revue_id_i', 'revue_code_t', 'revue_title_s',
        'volume_id_i', 'volume_status_i', 'volume_fs', 'volume_title_fs',
        'secondary_volume_id_i', 'secondary_volume_fs',
        'section_id_i', 'section_fs', 'section_title_fs',
        'indexing_date_tdate',
        'doc_tei', 'doc_dc', 'doc_openaire', 'doc_crossref', 'doc_zbjats', 'doc_doaj', 'doc_bibtex', 'doc_csl', 'doc_type_fs',
        'author_fullname_s', 'author_fullname_fs', 'authorLastNameFirstNamePrefixed_fs', 'authorFirstLetters_s', 'author_fullname_sort',
        'keyword_t',
        // non-localized fallback fields used when no locale key is valid
        'paper_title_t', 'abstract_t',
    ];

    /** @var list<string> example dynamic-field matches, one per dynamicField pattern the builders rely on */
    private const DYNAMIC_FIELD_EXAMPLES = [
        'en_paper_title_t',
        'en_abstract_t',
        'en_volume_title_t',
        'en_secondary_volume_title_t',
        'en_section_title_t',
    ];

    public function testAllStaticFieldNamesAreDeclaredInSchema(): void
    {
        $schema = $this->loadSchema();

        foreach (self::STATIC_FIELD_NAMES as $fieldName) {
            self::assertNotNull(
                $schema->xpath("//field[@name='{$fieldName}']")[0] ?? null,
                "Field '{$fieldName}' emitted by a Build/* collaborator is not declared in schema.xml"
            );
        }
    }

    public function testDynamicFieldExamplesMatchADeclaredPattern(): void
    {
        $schema = $this->loadSchema();
        $patterns = [];
        foreach ($schema->xpath('//dynamicField') as $dynamicField) {
            $patterns[] = (string)$dynamicField['name'];
        }

        foreach (self::DYNAMIC_FIELD_EXAMPLES as $example) {
            $matched = false;
            foreach ($patterns as $pattern) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $example) === 1) {
                    $matched = true;
                    break;
                }
            }
            self::assertTrue($matched, "'{$example}' does not match any dynamicField pattern in schema.xml");
        }
    }

    private function loadSchema(): \SimpleXMLElement
    {
        $path = dirname(__DIR__, 6) . '/src/solr/episciences/conf/schema.xml';
        self::assertFileExists($path, 'schema.xml not found at expected path: ' . $path);

        $schema = simplexml_load_file($path);
        self::assertNotFalse($schema, 'Failed to parse schema.xml');

        return $schema;
    }
}
