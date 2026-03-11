<?php

namespace unit\library\Episciences\Paper;

use Episciences_Comment;
use Episciences_Paper_MessageSubmissionResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper_MessageSubmissionResult
 *
 * This value object encapsulates the result of a message submission in the
 * author-editor communication system. It carries:
 * - The result type (success, error, CSRF error, validation error, unauthorized, not found)
 * - The flash message key
 * - Optionally the created comment and its PCID
 *
 * @covers Episciences_Paper_MessageSubmissionResult
 */
class Episciences_Paper_MessageSubmissionResultTest extends TestCase
{
    // =========================================================================
    // Type Constants Tests
    // =========================================================================

    public function testTypeConstantsAreDefined(): void
    {
        $this->assertSame('success',          Episciences_Paper_MessageSubmissionResult::TYPE_SUCCESS);
        $this->assertSame('error',            Episciences_Paper_MessageSubmissionResult::TYPE_ERROR);
        $this->assertSame('validation_error', Episciences_Paper_MessageSubmissionResult::TYPE_VALIDATION_ERROR);
        $this->assertSame('csrf_invalid',     Episciences_Paper_MessageSubmissionResult::TYPE_CSRF_INVALID);
        $this->assertSame('unauthorized',     Episciences_Paper_MessageSubmissionResult::TYPE_UNAUTHORIZED);
        $this->assertSame('not_found',        Episciences_Paper_MessageSubmissionResult::TYPE_NOT_FOUND);
    }

    // =========================================================================
    // success() factory method
    // =========================================================================

    public function testSuccessFactoryCreatesSuccessType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('msg.key');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_SUCCESS, $result->getType());
    }

    public function testSuccessFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('My success message');
        $this->assertSame('My success message', $result->getMessageKey());
    }

    public function testSuccessFactoryWithNullPcid(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('msg');
        $this->assertNull($result->getPcid());
    }

    public function testSuccessFactoryWithPcid(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('msg', 42);
        $this->assertSame(42, $result->getPcid());
    }

    public function testSuccessFactoryWithNullComment(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('msg');
        $this->assertNull($result->getComment());
    }

    public function testSuccessFactoryWithComment(): void
    {
        $comment = $this->createMock(Episciences_Comment::class);
        $result = Episciences_Paper_MessageSubmissionResult::success('msg', 99, $comment);
        $this->assertSame($comment, $result->getComment());
    }

    // =========================================================================
    // error() factory method
    // =========================================================================

    public function testErrorFactoryCreatesErrorType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('error.msg');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_ERROR, $result->getType());
    }

    public function testErrorFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('Erreur générale');
        $this->assertSame('Erreur générale', $result->getMessageKey());
    }

    public function testErrorFactoryHasNullPcidAndComment(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('msg');
        $this->assertNull($result->getPcid());
        $this->assertNull($result->getComment());
    }

    // =========================================================================
    // validationError() factory method
    // =========================================================================

    public function testValidationErrorFactoryCreatesCorrectType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::validationError('Le commentaire est vide.');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_VALIDATION_ERROR, $result->getType());
    }

    public function testValidationErrorFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::validationError('Champ requis');
        $this->assertSame('Champ requis', $result->getMessageKey());
    }

    // =========================================================================
    // csrfInvalid() factory method
    // =========================================================================

    public function testCsrfInvalidFactoryCreatesCorrectType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::csrfInvalid('Token invalide.');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_CSRF_INVALID, $result->getType());
    }

    public function testCsrfInvalidFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::csrfInvalid('CSRF error');
        $this->assertSame('CSRF error', $result->getMessageKey());
    }

    // =========================================================================
    // unauthorized() factory method
    // =========================================================================

    public function testUnauthorizedFactoryCreatesCorrectType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::unauthorized('Non autorisé.');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_UNAUTHORIZED, $result->getType());
    }

    public function testUnauthorizedFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::unauthorized('Accès refusé');
        $this->assertSame('Accès refusé', $result->getMessageKey());
    }

    // =========================================================================
    // notFound() factory method
    // =========================================================================

    public function testNotFoundFactoryCreatesCorrectType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::notFound('Parent introuvable.');
        $this->assertSame(Episciences_Paper_MessageSubmissionResult::TYPE_NOT_FOUND, $result->getType());
    }

    public function testNotFoundFactoryStoresMessageKey(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::notFound('Message parent non trouvé');
        $this->assertSame('Message parent non trouvé', $result->getMessageKey());
    }

    // =========================================================================
    // isSuccess() Tests
    // =========================================================================

    public function testIsSuccessReturnsTrueForSuccessType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('ok');
        $this->assertTrue($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForErrorType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('fail');
        $this->assertFalse($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForValidationError(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::validationError('fail');
        $this->assertFalse($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForCsrfInvalid(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::csrfInvalid('fail');
        $this->assertFalse($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForUnauthorized(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::unauthorized('fail');
        $this->assertFalse($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForNotFound(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::notFound('fail');
        $this->assertFalse($result->isSuccess());
    }

    // =========================================================================
    // getFlashNamespace() Tests
    // =========================================================================

    public function testGetFlashNamespaceReturnsSuccessForSuccessType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('ok');
        $this->assertSame('success', $result->getFlashNamespace());
    }

    public function testGetFlashNamespaceReturnsErrorForErrorType(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('fail');
        $this->assertSame('error', $result->getFlashNamespace());
    }

    public function testGetFlashNamespaceReturnsErrorForValidationError(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::validationError('fail');
        $this->assertSame('error', $result->getFlashNamespace());
    }

    public function testGetFlashNamespaceReturnsErrorForCsrfInvalid(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::csrfInvalid('fail');
        $this->assertSame('error', $result->getFlashNamespace());
    }

    public function testGetFlashNamespaceReturnsErrorForUnauthorized(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::unauthorized('fail');
        $this->assertSame('error', $result->getFlashNamespace());
    }

    public function testGetFlashNamespaceReturnsErrorForNotFound(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::notFound('fail');
        $this->assertSame('error', $result->getFlashNamespace());
    }

    // =========================================================================
    // shouldRedirect() Tests
    // =========================================================================

    public function testShouldRedirectAlwaysReturnsTrueForSuccess(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('ok');
        $this->assertTrue($result->shouldRedirect());
    }

    public function testShouldRedirectAlwaysReturnsTrueForError(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::error('fail');
        $this->assertTrue($result->shouldRedirect());
    }

    public function testShouldRedirectAlwaysReturnsTrueForUnauthorized(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::unauthorized('fail');
        $this->assertTrue($result->shouldRedirect());
    }

    // =========================================================================
    // Immutability / readonly Tests
    // =========================================================================

    /**
     * Two calls with the same args produce independent instances (no shared state).
     */
    public function testFactoryMethodsReturnNewInstances(): void
    {
        $a = Episciences_Paper_MessageSubmissionResult::success('msg');
        $b = Episciences_Paper_MessageSubmissionResult::success('msg');
        $this->assertNotSame($a, $b);
    }

    /**
     * A success result with pcid=0 stores 0 (not null).
     */
    public function testSuccessWithZeroPcidStoresZero(): void
    {
        $result = Episciences_Paper_MessageSubmissionResult::success('msg', 0);
        $this->assertSame(0, $result->getPcid());
    }
}
