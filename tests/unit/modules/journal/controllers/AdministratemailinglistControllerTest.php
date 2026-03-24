<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Security regression tests for AdministratemailinglistController.
 *
 * Strategy: source-code pattern analysis (static analysis via PHP string inspection).
 * No database or HTTP dispatch needed — tests are fast and run without side effects.
 *
 * @covers AdministratemailinglistController
 */
class AdministratemailinglistControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/AdministratemailinglistController.php'
        );
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        $this->assertNotFalse($start, "Method $methodName not found in AdministratemailinglistController");

        $end = strpos($this->source, 'function ', $start + strlen('function ' . $methodName));

        return $end === false
            ? substr($this->source, $start)
            : substr($this->source, $start, $end - $start);
    }

    // ---------------------------------------------------------------
    // getAllowedRoleIds — private helper
    // ---------------------------------------------------------------

    /**
     * getAllowedRoleIds() must exist and exclude the five restricted roles.
     * These roles are either too broad (epiadmin, member, guest) or managed
     * through separate mechanisms (reviewer, author).
     */
    public function testGetAllowedRoleIdsExistsAndExcludesRestrictedRoles(): void
    {
        $method = $this->extractMethod('getAllowedRoleIds');

        foreach (['ROLE_MEMBER', 'ROLE_GUEST', 'ROLE_REVIEWER', 'ROLE_AUTHOR', 'ROLE_ROOT'] as $role) {
            $this->assertStringContainsString(
                $role,
                $method,
                "getAllowedRoleIds() must unset Episciences_Acl::$role from the allowed set"
            );
        }
    }

    /**
     * getAllowedRoleIds() must return array_keys() of the filtered role map,
     * giving a flat list of role ID strings suitable for array_intersect().
     */
    public function testGetAllowedRoleIdsReturnsArrayKeys(): void
    {
        $method = $this->extractMethod('getAllowedRoleIds');

        $this->assertStringContainsString(
            'array_keys',
            $method,
            'getAllowedRoleIds() must return array_keys($roles) to produce a flat list of role IDs'
        );
    }

    // ---------------------------------------------------------------
    // manageAction — role filtering
    // ---------------------------------------------------------------

    /**
     * The POST branch of manageAction() must pass $rawRoles through
     * array_intersect() against getAllowedRoleIds() before array_slice().
     *
     * Without this filter, an attacker can submit arbitrary role IDs that
     * will be stored and later used in ROLEID IN (?) queries, returning
     * silently empty or unexpected member sets.
     */
    public function testManageActionFiltersRolesAgainstAllowedList(): void
    {
        $method = $this->extractMethod('manageAction');

        $this->assertStringContainsString(
            'array_intersect',
            $method,
            'manageAction() POST branch must use array_intersect() to filter submitted roles against the ACL allowed list'
        );

        $this->assertStringContainsString(
            'getAllowedRoleIds',
            $method,
            'manageAction() POST branch must call getAllowedRoleIds() to obtain the reference set for array_intersect()'
        );
    }

    /**
     * array_intersect() must be nested inside array_slice() in manageAction() so that
     * whitelist filtering happens before the count cap.
     * The expected pattern is: array_slice(array_intersect($rawRoles, ...), MAX)
     */
    public function testManageActionIntersectNestedInsideSlice(): void
    {
        $method = $this->extractMethod('manageAction');

        $this->assertMatchesRegularExpression(
            '/array_slice\s*\(\s*array_intersect\s*\(/',
            $method,
            'manageAction(): role filtering must use array_slice(array_intersect($rawRoles, getAllowedRoleIds()), MAX) — intersect must be nested inside slice so whitelist is applied before the count cap'
        );
    }

    /**
     * manageAction() POST branch must validate the CSRF token before processing
     * any submitted data.
     */
    public function testManageActionValidatesCsrfToken(): void
    {
        $method = $this->extractMethod('manageAction');

        $this->assertStringContainsString(
            'validateToken',
            $method,
            'manageAction() must call Episciences_Csrf_Helper::validateToken() before processing POST data'
        );
    }

    // ---------------------------------------------------------------
    // previewAction — role filtering
    // ---------------------------------------------------------------

    /**
     * The POST branch of previewAction() must pass $rawRoles through
     * array_intersect() against getAllowedRoleIds() before array_slice().
     *
     * Without this filter, arbitrary role IDs supplied by the client would
     * reach resolveMembers() and the ROLEID IN (?) query unchanged.
     */
    public function testPreviewActionFiltersRolesAgainstAllowedList(): void
    {
        $method = $this->extractMethod('previewAction');

        $this->assertStringContainsString(
            'array_intersect',
            $method,
            'previewAction() must use array_intersect() to filter submitted roles against the ACL allowed list'
        );

        $this->assertStringContainsString(
            'getAllowedRoleIds',
            $method,
            'previewAction() must call getAllowedRoleIds() to obtain the reference set for array_intersect()'
        );
    }

    /**
     * array_intersect() must be nested inside array_slice() in previewAction().
     * The expected pattern is: array_slice(array_intersect($rawRoles, ...), MAX)
     */
    public function testPreviewActionIntersectNestedInsideSlice(): void
    {
        $method = $this->extractMethod('previewAction');

        $this->assertMatchesRegularExpression(
            '/array_slice\s*\(\s*array_intersect\s*\(/',
            $method,
            'previewAction(): role filtering must use array_slice(array_intersect($rawRoles, getAllowedRoleIds()), MAX) — intersect must be nested inside slice so whitelist is applied before the count cap'
        );
    }

    /**
     * previewAction() must validate submitted UIDs against the journal member list
     * to prevent resolveMembers() from being called with arbitrary user IDs.
     */
    public function testPreviewActionFiltersUidsAgainstJournalMembers(): void
    {
        $method = $this->extractMethod('previewAction');

        $this->assertStringContainsString(
            'getJournalUids',
            $method,
            'previewAction() must validate submitted UIDs against MailingListsManager::getJournalUids() before passing them to resolveMembers()'
        );
    }

    /**
     * previewAction() must validate the CSRF token before processing POST data.
     */
    public function testPreviewActionValidatesCsrfToken(): void
    {
        $method = $this->extractMethod('previewAction');

        $this->assertStringContainsString(
            'validateToken',
            $method,
            'previewAction() must call Episciences_Csrf_Helper::validateToken() before processing POST data'
        );
    }

    /**
     * previewAction() must reject non-POST requests immediately.
     */
    public function testPreviewActionRejectsNonPostRequests(): void
    {
        $method = $this->extractMethod('previewAction');

        $this->assertStringContainsString(
            'isPost',
            $method,
            'previewAction() must check isPost() and reject requests that are not HTTP POST'
        );
    }

    // ---------------------------------------------------------------
    // manageAction — auto-disable on empty members (CCE #436)
    // ---------------------------------------------------------------

    /**
     * When the resolved member list is empty, manageAction() must set status
     * to 0 (disabled) and use the "automatically disabled" translation key —
     * not the legacy "closed" wording which is ambiguous alongside the Type
     * field's "Open/Members only" vocabulary.
     */
    public function testManageActionUsesDisabledLanguageWhenMembersEmpty(): void
    {
        $method = $this->extractMethod('manageAction');

        $this->assertStringContainsString(
            'automatically disabled',
            $method,
            'manageAction() must use the "automatically disabled" translation key (not "closed") when auto-disabling an empty list'
        );

        $this->assertStringNotContainsString(
            'automatically closed',
            $method,
            'manageAction() must not use the legacy "automatically closed" string; use "automatically disabled" instead'
        );
    }

    /**
     * The auto-disable comment in the source must use status 0 and reference
     * "disabled" to stay consistent with the Status field vocabulary.
     */
    public function testManageActionSetsStatusZeroOnEmptyMembers(): void
    {
        $method = $this->extractMethod('manageAction');

        $this->assertMatchesRegularExpression(
            '/setStatus\s*\(\s*0\s*\)/',
            $method,
            'manageAction() must call setStatus(0) to disable the list when it has no members'
        );
    }
}
