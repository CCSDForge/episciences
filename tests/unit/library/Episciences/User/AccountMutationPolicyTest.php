<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\AccountMutationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\AccountMutationPolicy
 */
final class AccountMutationPolicyTest extends TestCase
{
    public function testCanMutateAccountAllowsOwnerWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canMutateAccount(42, 42, false));
    }

    public function testCanMutateAccountDeniesThirdParty(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(100, 42, false));
    }

    public function testCanMutateAccountDeniesWhenImpersonatingEvenForOwnUid(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(42, 42, true));
    }

    public function testCanMutateAccountDeniesWhenImpersonatingTarget(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(100, 42, true));
    }

    public function testCanMutateAccountDeniesAnonymous(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(42, null, false));
        self::assertFalse(AccountMutationPolicy::canMutateAccount(42, 0, false));
        self::assertFalse(AccountMutationPolicy::canMutateAccount(42, -1, false));
    }

    public function testCanEditProfileAllowsSelfWithExplicitMatchingUid(): void
    {
        self::assertTrue(AccountMutationPolicy::canEditProfile(42, 42, false));
    }

    public function testCanEditProfileAllowsSelfWithNullOrZeroRequestedUid(): void
    {
        self::assertTrue(AccountMutationPolicy::canEditProfile(null, 42, false));
        self::assertTrue(AccountMutationPolicy::canEditProfile(0, 42, false));
    }

    public function testCanEditProfileDeniesThirdPartyRequestedUid(): void
    {
        self::assertFalse(AccountMutationPolicy::canEditProfile(999, 42, false));
    }

    public function testCanEditProfileDeniesWhenImpersonating(): void
    {
        self::assertFalse(AccountMutationPolicy::canEditProfile(42, 42, true));
        self::assertFalse(AccountMutationPolicy::canEditProfile(null, 42, true));
        self::assertFalse(AccountMutationPolicy::canEditProfile(999, 42, true));
    }

    public function testCanEditProfileDeniesAnonymous(): void
    {
        self::assertFalse(AccountMutationPolicy::canEditProfile(42, null, false));
        self::assertFalse(AccountMutationPolicy::canEditProfile(null, null, false));
    }

    public function testCanDeletePhotoAllowsOnlyOwnerWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canDeletePhoto(42, 42, false));
        self::assertFalse(AccountMutationPolicy::canDeletePhoto(100, 42, false));
        self::assertFalse(AccountMutationPolicy::canDeletePhoto(42, 42, true));
        self::assertFalse(AccountMutationPolicy::canDeletePhoto(42, null, false));
    }

    public function testCanChangeEmailAllowsOnlyOwnerWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canChangeEmail(42, 42, false));
        self::assertFalse(AccountMutationPolicy::canChangeEmail(100, 42, false));
        self::assertFalse(AccountMutationPolicy::canChangeEmail(42, 42, true));
        self::assertFalse(AccountMutationPolicy::canChangeEmail(42, null, false));
    }

    public function testCanMutateAccountAllowsRootForThirdPartyWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canMutateAccount(100, 42, false, true));
    }

    public function testCanMutateAccountDeniesRootWhenImpersonating(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(100, 42, true, true));
        self::assertFalse(AccountMutationPolicy::canMutateAccount(42, 42, true, true));
    }

    public function testCanMutateAccountDeniesRootWhenAnonymous(): void
    {
        self::assertFalse(AccountMutationPolicy::canMutateAccount(100, null, false, true));
        self::assertFalse(AccountMutationPolicy::canMutateAccount(100, 0, false, true));
    }

    public function testCanEditProfileAllowsRootForThirdPartyWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canEditProfile(999, 42, false, true));
    }

    public function testCanEditProfileDeniesRootWhenImpersonating(): void
    {
        self::assertFalse(AccountMutationPolicy::canEditProfile(999, 42, true, true));
        self::assertFalse(AccountMutationPolicy::canEditProfile(42, 42, true, true));
    }

    public function testCanDeletePhotoAllowsRootForThirdPartyWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canDeletePhoto(100, 42, false, true));
        self::assertFalse(AccountMutationPolicy::canDeletePhoto(100, 42, true, true));
    }

    public function testCanChangeEmailAllowsRootForThirdPartyWhenNotImpersonating(): void
    {
        self::assertTrue(AccountMutationPolicy::canChangeEmail(100, 42, false, true));
        self::assertFalse(AccountMutationPolicy::canChangeEmail(100, 42, true, true));
    }
}
