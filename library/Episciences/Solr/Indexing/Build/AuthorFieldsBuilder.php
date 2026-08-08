<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Ccsd_Search_Solr;
use Ccsd_Tools_String;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Episciences_Tools;

/**
 * 1:1 port of the author-field logic from
 * Ccsd_Search_Solr_Indexer_Episciences (formatAuthorName/cleanAuthorName/
 * classifyAuthorFirstLetter/buildAuthorSortKey/indexOneAuthor), decomposed into a
 * standalone, dependency-free collaborator that returns a SolrDocument instead of
 * mutating a shared Solarium\Document.
 */
final class AuthorFieldsBuilder
{
    private const OTHERS_STRING_PREFIX = 'Others';
    private const AUTHOR_FIRST_LETTER_PATTERN = '/^[A-Z]$/';

    /**
     * @param list<array{family?: string, given?: string}> $authors
     */
    public function build(SolrDocument $document, array $authors): SolrDocument
    {
        if ($authors === []) {
            return $document;
        }

        $authorNames = [];
        foreach ($authors as $author) {
            $formattedName = self::formatAuthorName($author);
            $document = $this->indexOneAuthor($document, $formattedName);
            $authorNames[] = $formattedName;
        }

        return $document->withRawField('author_fullname_sort', self::buildAuthorSortKey($authorNames));
    }

    /** @param array{family?: string, given?: string} $author */
    public static function formatAuthorName(array $author): string
    {
        $family = trim($author['family'] ?? '');
        $given = trim($author['given'] ?? '');

        if ($given === '') {
            return $family;
        }
        if ($family === '') {
            return $given;
        }

        return $family . ', ' . $given;
    }

    /** @param string[] $authorNames */
    public static function buildAuthorSortKey(array $authorNames): string
    {
        $key = mb_substr(implode(' ', $authorNames), 0, 30);

        return str_replace([' ', ','], '', $key);
    }

    public static function classifyAuthorFirstLetter(string $authorCleaned): string
    {
        $firstLetter = mb_strtoupper(mb_substr($authorCleaned, 0, 1));

        return preg_match(self::AUTHOR_FIRST_LETTER_PATTERN, $firstLetter) === 1
            ? $firstLetter
            : self::OTHERS_STRING_PREFIX;
    }

    private function indexOneAuthor(SolrDocument $document, string $author): SolrDocument
    {
        $authorCleaned = self::cleanAuthorName($author);
        $document = $document->withRawField('author_fullname_fs', $authorCleaned);

        $firstLetter = self::classifyAuthorFirstLetter($authorCleaned);
        $document = $document->withRawField('authorFirstLetters_s', $firstLetter);

        $prefixedName = $firstLetter === self::OTHERS_STRING_PREFIX
            ? self::OTHERS_STRING_PREFIX . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $authorCleaned
            : $authorCleaned;
        $document = $document->withRawField('authorLastNameFirstNamePrefixed_fs', $prefixedName);

        return $document->withRawField('author_fullname_s', $authorCleaned);
    }

    public static function cleanAuthorName(string $name): string
    {
        $name = Episciences_Tools::spaceCleaner($name);
        // Legacy has no null fallback here: an invalid-UTF8 $name would make
        // preg_replace() return null and then fatally TypeError on the next line
        // (stripCtrlChars() takes a non-nullable string). Falling back to the
        // pre-replace value avoids that crash without changing output for any
        // input that legacy would have successfully processed.
        $name = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $name) ?? $name;
        $name = Ccsd_Tools_String::stripCtrlChars($name, '');
        $name = str_replace(' ,', '', $name);
        $name = rtrim($name, ',');

        return trim($name);
    }
}
