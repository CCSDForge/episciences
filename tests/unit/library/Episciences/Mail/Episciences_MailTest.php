<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Mail.
 *
 * The constructor requires a live DB (calls Episciences_ReviewsManager::find()).
 * All tests use getMockBuilder()->disableOriginalConstructor()->onlyMethods([])->getMock()
 * to obtain a real instance without running the constructor.
 *
 * DB-dependent methods (getHistory, getHistoryQuery, log, find) are covered via
 * source-inspection tests that verify security fixes without requiring a real DB.
 *
 * Bugs documented:
 *   S1 — getHistoryQuery(): implode(',', $docIds) injected into sprintf() without
 *        sanitisation → SQL injection if $docIds contains non-numeric strings.
 *        Fix: array_map('intval', array_filter($docIds, 'is_numeric')) before implode.
 *
 * @covers Episciences_Mail
 */
final class Episciences_MailTest extends TestCase
{
    private Episciences_Mail $mail;

    protected function setUp(): void
    {
        $this->mail = $this->getMockBuilder(Episciences_Mail::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function testStatusSuccessConstant(): void
    {
        self::assertSame(1, Episciences_Mail::STATUS_SUCCESS);
    }

    public function testStatusFailedInvalidDirConstant(): void
    {
        self::assertSame(2, Episciences_Mail::STATUS_FAILED_INVALID_DIR);
    }

    public function testStatusFailedNoRecipientsConstant(): void
    {
        self::assertSame(3, Episciences_Mail::STATUS_FAILED_NO_RECIPIENTS);
    }

    public function testStatusFailedMailStorageCreationConstant(): void
    {
        self::assertSame(4, Episciences_Mail::STATUS_FAILED_MAIL_STORAGE_CREATION);
    }

    public function testStatusFailedXmlFileCreationConstant(): void
    {
        self::assertSame(5, Episciences_Mail::STATUS_FAILED_XML_FILE_CREATION);
    }

    public function testStatusFailedXmlFileNoWrittenConstant(): void
    {
        self::assertSame(6, Episciences_Mail::STATUS_FAILED_XML_FILE_NO_WRITTEN);
    }

    public function testStatusFailedDbLogConstant(): void
    {
        self::assertSame(7, Episciences_Mail::STATUS_FAILED_DB_LOG);
    }

    // =========================================================================
    // Header constants
    // =========================================================================

    public function testHeaderConstantsHaveExpectedValues(): void
    {
        self::assertSame('Reply-To', Episciences_Mail::HEADER_REPLY_TO);
        self::assertSame('Return-Path', Episciences_Mail::HEADER_RETURN_PATH);
        self::assertSame('To', Episciences_Mail::HEADER_TO);
        self::assertSame('Cc', Episciences_Mail::HEADER_CC);
        self::assertSame('Bcc', Episciences_Mail::HEADER_BCC);
        self::assertSame('Disposition-Notification-To', Episciences_Mail::HEADER_DISPOSITION_NOTIFICATION_TO);
        self::assertSame('From', Episciences_Mail::HEADER_FROM);
    }

    // =========================================================================
    // addTag / getTags / clearTags
    // =========================================================================

    public function testDefaultTagsIsEmptyArray(): void
    {
        self::assertSame([], $this->mail->getTags());
    }

    public function testAddTagStoresKeyValue(): void
    {
        $this->mail->addTag('%%FOO%%', 'bar');
        self::assertSame(['%%FOO%%' => 'bar'], $this->mail->getTags());
    }

    public function testAddTagOverwritesDuplicateKey(): void
    {
        $this->mail->addTag('%%KEY%%', 'first');
        $this->mail->addTag('%%KEY%%', 'second');
        self::assertSame(['%%KEY%%' => 'second'], $this->mail->getTags());
    }

    public function testAddMultipleTags(): void
    {
        $this->mail->addTag('%%A%%', '1');
        $this->mail->addTag('%%B%%', '2');
        self::assertSame(['%%A%%' => '1', '%%B%%' => '2'], $this->mail->getTags());
    }

    public function testClearTagsResetsToEmptyArray(): void
    {
        $this->mail->addTag('%%FOO%%', 'bar');
        $this->mail->clearTags();
        self::assertSame([], $this->mail->getTags());
    }

    public function testClearTagsOnEmptyArrayIsNoop(): void
    {
        $this->mail->clearTags();
        self::assertSame([], $this->mail->getTags());
    }

    // =========================================================================
    // cleanRemainingTags — pure regex, no DB
    // =========================================================================

    public function testCleanRemainingTagsRemovesUnreplacedPatterns(): void
    {
        $result = $this->mail->cleanRemainingTags('Hello %%UNKNOWN_TAG%% world');
        self::assertSame('Hello  world', $result);
    }

    public function testCleanRemainingTagsRemovesMultiplePatterns(): void
    {
        $result = $this->mail->cleanRemainingTags('%%A%% foo %%B_C%%');
        self::assertSame(' foo ', $result);
    }

    public function testCleanRemainingTagsLeavesNormalTextUntouched(): void
    {
        $result = $this->mail->cleanRemainingTags('No tags here.');
        self::assertSame('No tags here.', $result);
    }

    public function testCleanRemainingTagsReturnsEmptyStringAsIs(): void
    {
        $result = $this->mail->cleanRemainingTags('');
        self::assertSame('', $result);
    }

    public function testCleanRemainingTagsDoesNotRemovePartialPattern(): void
    {
        // Single % delimiters are not the %%...%% pattern
        $result = $this->mail->cleanRemainingTags('%NOT_A_TAG%');
        self::assertSame('%NOT_A_TAG%', $result);
    }

    // =========================================================================
    // replaceTags — replaces known tags, cleans remainder
    // =========================================================================

    public function testReplaceTagsSubstitutesKnownTags(): void
    {
        $this->mail->addTag('%%NAME%%', 'Alice');
        $result = $this->mail->replaceTags('Hello %%NAME%%!');
        self::assertStringContainsString('Alice', $result);
    }

    public function testReplaceTagsRemovesUnknownTags(): void
    {
        $result = $this->mail->replaceTags('Text %%GHOST_TAG%% end');
        self::assertStringNotContainsString('%%GHOST_TAG%%', $result);
    }

    public function testReplaceTagsReturnsFalsyUnchanged(): void
    {
        // replaceTags() only processes truthy $text
        self::assertFalse($this->mail->replaceTags(false));
        self::assertNull($this->mail->replaceTags(null));
        self::assertSame('', $this->mail->replaceTags(''));
    }

    // =========================================================================
    // getRawBody / setRawBody
    // =========================================================================

    public function testGetRawBodyReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getRawBody());
    }

    public function testSetRawBodyStoresValue(): void
    {
        $this->mail->setRawBody('<p>Hello</p>');
        self::assertSame('<p>Hello</p>', $this->mail->getRawBody());
    }

    public function testSetRawBodyAcceptsEmptyString(): void
    {
        $this->mail->setRawBody('');
        self::assertSame('', $this->mail->getRawBody());
    }

    // =========================================================================
    // getId / setId — only numeric values accepted
    // =========================================================================

    public function testGetIdReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getId());
    }

    public function testSetIdWithNumericIntegerStoresValue(): void
    {
        $this->mail->setId(42);
        self::assertSame(42, $this->mail->getId());
    }

    public function testSetIdWithNumericStringStoresValue(): void
    {
        $this->mail->setId('99');
        self::assertSame('99', $this->mail->getId());
    }

    public function testSetIdWithNonNumericStringDoesNotStore(): void
    {
        $this->mail->setId('abc');
        self::assertNull($this->mail->getId());
    }

    public function testSetIdReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Mail::class, $this->mail->setId(1));
    }

    // =========================================================================
    // setRvid / getRvid
    // =========================================================================

    public function testSetRvidStoresValue(): void
    {
        $this->mail->setRvid(10);
        self::assertSame(10, $this->mail->getRvid());
    }

    public function testSetRvidReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Mail::class, $this->mail->setRvid(5));
    }

    // =========================================================================
    // getSendDate / setSendDate
    // =========================================================================

    public function testGetSendDateReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getSendDate());
    }

    public function testSetSendDateStoresValue(): void
    {
        $this->mail->setSendDate('2024-01-15 10:00:00');
        self::assertSame('2024-01-15 10:00:00', $this->mail->getSendDate());
    }

    public function testSetSendDateReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Mail::class, $this->mail->setSendDate('2024-01-01'));
    }

    // =========================================================================
    // addAttachedFile / getAttachments
    // =========================================================================

    public function testGetAttachmentsReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->mail->getAttachments());
    }

    public function testAddAttachedFileAppendsToArray(): void
    {
        $this->mail->addAttachedFile('/path/to/file.pdf');
        self::assertSame(['/path/to/file.pdf'], $this->mail->getAttachments());
    }

    public function testAddMultipleAttachedFiles(): void
    {
        $this->mail->addAttachedFile('/a.pdf');
        $this->mail->addAttachedFile('/b.pdf');
        self::assertSame(['/a.pdf', '/b.pdf'], $this->mail->getAttachments());
    }

    // =========================================================================
    // getUid / setUid
    // =========================================================================

    public function testGetUidReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getUid());
    }

    public function testSetUidStoresInt(): void
    {
        $this->mail->setUid(42);
        self::assertSame(42, $this->mail->getUid());
    }

    public function testSetUidWithNullResetsToNull(): void
    {
        $this->mail->setUid(42);
        $this->mail->setUid(null);
        self::assertNull($this->mail->getUid());
    }

    public function testSetUidReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_Mail::class, $this->mail->setUid(1));
    }

    // =========================================================================
    // isAutomatic / setIsAutomatic
    // =========================================================================

    public function testIsAutomaticReturnsFalseByDefault(): void
    {
        self::assertFalse($this->mail->isAutomatic());
    }

    public function testSetIsAutomaticToTrue(): void
    {
        $this->mail->setIsAutomatic(true);
        self::assertTrue($this->mail->isAutomatic());
    }

    public function testSetIsAutomaticToFalse(): void
    {
        $this->mail->setIsAutomatic(true);
        $this->mail->setIsAutomatic(false);
        self::assertFalse($this->mail->isAutomatic());
    }

    // =========================================================================
    // hasATemplate / getTemplatePath / getTemplateName
    // =========================================================================

    public function testGetTemplatePathReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getTemplatePath());
    }

    public function testGetTemplateNameReturnsNullByDefault(): void
    {
        self::assertNull($this->mail->getTemplateName());
    }

    public function testHasATemplateReturnsFalseWhenNeitherSet(): void
    {
        $rm = new \ReflectionMethod(Episciences_Mail::class, 'hasATemplate');
        $rm->setAccessible(true);
        self::assertFalse($rm->invoke($this->mail));
    }

    public function testHasATemplateReturnsFalseWhenOnlyPathSet(): void
    {
        $rp = new \ReflectionProperty(Episciences_Mail::class, '_templatePath');
        $rp->setAccessible(true);
        $rp->setValue($this->mail, '/some/path');

        $rm = new \ReflectionMethod(Episciences_Mail::class, 'hasATemplate');
        $rm->setAccessible(true);
        self::assertFalse($rm->invoke($this->mail));
    }

    public function testHasATemplateReturnsFalseForNonExistentFile(): void
    {
        $rp = new \ReflectionProperty(Episciences_Mail::class, '_templatePath');
        $rp->setAccessible(true);
        $rp->setValue($this->mail, '/non/existent/path');

        $rn = new \ReflectionProperty(Episciences_Mail::class, '_templateName');
        $rn->setAccessible(true);
        $rn->setValue($this->mail, 'template.html');

        $rm = new \ReflectionMethod(Episciences_Mail::class, 'hasATemplate');
        $rm->setAccessible(true);
        self::assertFalse($rm->invoke($this->mail));
    }

    // =========================================================================
    // addTo / addCc / addBcc — empty guard (return false) — source inspection
    // =========================================================================

    public function testAddToSourceContainsEmptyGuard(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'addTo');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        self::assertStringContainsString('empty($email)', $source);
        self::assertStringContainsString('return false', $source);
    }

    public function testAddCcSourceContainsEmptyGuard(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'addCc');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        self::assertStringContainsString('empty($email)', $source);
        self::assertStringContainsString('return false', $source);
    }

    public function testAddBccSourceContainsEmptyGuard(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'addBcc');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        self::assertStringContainsString('empty($email)', $source);
        self::assertStringContainsString('return false', $source);
    }

    // =========================================================================
    // Security S1 — SQL injection via $docIds in getHistoryQuery()
    // =========================================================================

    /**
     * Regression S1: getHistoryQuery() must sanitise $docIds via array_map('intval', ...)
     * before passing them to sprintf() / raw SQL.
     */
    public function testGetHistoryQuerySanitisesDocIdsWithIntval(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'getHistoryQuery');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString(
            "array_map('intval',",
            $source,
            "Security S1: getHistoryQuery() must cast \$docIds elements to int via array_map('intval', ...)"
        );
        self::assertStringContainsString(
            "array_filter(\$docIds, 'is_numeric')",
            $source,
            "Security S1: getHistoryQuery() must filter non-numeric values via array_filter(..., 'is_numeric')"
        );
    }

    /**
     * Regression S1: UID from Episciences_Auth::getUid() must also be cast to int.
     */
    public function testGetHistoryQueryCastsUidToInt(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'getHistoryQuery');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertMatchesRegularExpression(
            '/\(int\)\s*Episciences_Auth::getUid\(\)/',
            $source,
            'Security S1: Episciences_Auth::getUid() result must be cast to (int) before SQL injection'
        );
    }

    // =========================================================================
    // Sanitisation logic (pure, no DB)
    // =========================================================================

    /**
     * Verify the sanitisation pattern itself: array_map('intval', array_filter(..., 'is_numeric'))
     * correctly drops non-numeric values and casts remaining ones to int.
     */
    public function testSanitisationPatternDropsMaliciousIds(): void
    {
        $malicious = ['1', '2 OR 1=1', "'; DROP TABLE mail_log; --", '3', ''];
        $safe      = array_map('intval', array_filter($malicious, 'is_numeric'));

        self::assertSame([1, 3], array_values($safe));
    }

    public function testSanitisationPatternPreservesValidIds(): void
    {
        $valid = ['42', '100', '7'];
        $safe  = array_map('intval', array_filter($valid, 'is_numeric'));

        self::assertSame([42, 100, 7], array_values($safe));
    }

    public function testSanitisationPatternReturnsEmptyArrayForAllInvalid(): void
    {
        $invalid = ['abc', 'OR 1=1', '--', ''];
        $safe    = array_map('intval', array_filter($invalid, 'is_numeric'));

        self::assertSame([], array_values($safe));
    }

    public function testSanitisationPatternCastsStringIntegersCorrectly(): void
    {
        $input = ['0', '1', '999'];
        $safe  = array_map('intval', array_filter($input, 'is_numeric'));

        self::assertSame([0, 1, 999], array_values($safe));
    }
}
