<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\ImpersonationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\ImpersonationPolicy
 */
final class ImpersonationPolicyTest extends TestCase
{
    public function testEvaluateAllowedForSecretaryToRegularUser(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: true,
            rvid: 1,
            actingUid: 10,
            targetUid: 20,
            isTargetRoot: false,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::ALLOWED, $result);
    }

    public function testEvaluateAllowedForRootToAnyUser(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: true,
            isCallerSecretary: false,
            rvid: 1,
            actingUid: 1,
            targetUid: 20,
            isTargetRoot: false,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::ALLOWED, $result);
    }

    public function testEvaluateAllowedForRootToAnotherRoot(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: true,
            isCallerSecretary: false,
            rvid: 1,
            actingUid: 1,
            targetUid: 2,
            isTargetRoot: true,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::ALLOWED, $result);
    }

    public function testEvaluateDeniedInsufficientPrivilegesWhenNeitherRootNorSecretary(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: false,
            rvid: 1,
            actingUid: 10,
            targetUid: 20,
            isTargetRoot: false,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::DENIED_INSUFFICIENT_PRIVILEGES, $result);
    }

    public function testEvaluateDeniedInsufficientPrivilegesOnPortalWithoutRoot(): void
    {
        // On portal (rvid === 0), only root has privilege, not a journal secretary
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: true,
            rvid: 0,
            actingUid: 10,
            targetUid: 20,
            isTargetRoot: false,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::DENIED_INSUFFICIENT_PRIVILEGES, $result);
    }

    public function testEvaluateDeniedWhenAlreadyImpersonating(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: true,
            rvid: 1,
            actingUid: 10,
            targetUid: 20,
            isTargetRoot: false,
            isCurrentlyImpersonating: true
        );

        self::assertSame(ImpersonationPolicy::DENIED_ALREADY_IMPERSONATING, $result);
    }

    public function testEvaluateDeniedWhenTargetingSelf(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: true,
            rvid: 1,
            actingUid: 10,
            targetUid: 10,
            isTargetRoot: false,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::DENIED_SELF_TARGET, $result);
    }

    public function testEvaluateDeniedWhenSecretaryEscalatesToRoot(): void
    {
        $result = ImpersonationPolicy::evaluate(
            isCallerRoot: false,
            isCallerSecretary: true,
            rvid: 1,
            actingUid: 10,
            targetUid: 1,
            isTargetRoot: true,
            isCurrentlyImpersonating: false
        );

        self::assertSame(ImpersonationPolicy::DENIED_ESCALATION_TO_ROOT, $result);
    }
}
