<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Configuration consistency checks for the `journal` module dispatch coverage.
 *
 * Access control is applied at dispatch time over the union of acl.ini and the
 * navigation JSON files: the dispatcher only applies role rules to an action that
 * is declared there. Every public controller action of the journal module must
 * therefore be declared in acl.ini or in a versioned navigation file — otherwise
 * its role rules silently stop applying.
 *
 * A small fixed list of menu pages is provided at runtime by the per-journal
 * navigation (not versioned in this repository); those are public by design and
 * are listed in RUNTIME_NAVIGATION_PAGES. Any new undeclared action must either
 * be added to acl.ini or knowingly added to that list.
 *
 * acl.ini is parsed as a flat INI (parse_ini_file) so the test carries no Zend
 * dependency; keys keep their literal `allow.<controller>-<action>` form within
 * each role section.
 *
 * @coversNothing
 */
final class JournalModuleAclCoverageTest extends TestCase
{
    /**
     * Menu pages served by the per-journal runtime navigation (not versioned).
     * Public by design — do not extend this list without checking that the new
     * entry is really meant to be reachable without a role rule.
     */
    private const RUNTIME_NAVIGATION_PAGES = [
        'browse-accepted-docs',
        'browse-author',
        'browse-currentissues',
        'browse-date',
        'browse-latest',
        'browse-regularissues',
        'browse-section',
        'browse-specialissues',
        'browse-volumes',
        'doi-index',
        'index-index',
        'index-notfound', // target of the dispatcher fallback for unknown resources
        'review-index',
        'review-staff',
        'rights-index',
    ];

    /** @var array<string, array<string, mixed>> role => key => value */
    private static array $acl = [];

    /** @var array<string, true> resource => true */
    private static array $declaredResources = [];

    public static function setUpBeforeClass(): void
    {
        $parsed = parse_ini_file(APPLICATION_PATH . '/configs/acl.ini', true);
        self::assertIsArray($parsed, 'acl.ini must be a parsable INI file');
        self::$acl = $parsed;

        $resources = [];
        foreach ($parsed as $entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach (array_keys($entries) as $key) {
                if (str_starts_with((string)$key, 'allow.')) {
                    $resources[substr((string)$key, 6)] = true;
                }
            }
        }

        // Versioned navigation files contribute to the known-resources union.
        foreach (['journal.navigation.json', 'journal.guest.navigation.json'] as $file) {
            $data = json_decode(
                (string)file_get_contents(APPLICATION_PATH . '/configs/' . $file),
                true
            );
            self::assertIsArray($data, "$file must be parsable JSON");
            $stack = [$data];
            while ($stack) {
                $node = array_pop($stack);
                if (!is_array($node)) {
                    continue;
                }
                if (isset($node['controller'], $node['action'])) {
                    $resources[$node['controller'] . '-' . $node['action']] = true;
                }
                foreach ($node as $value) {
                    if (is_array($value)) {
                        $stack[] = $value;
                    }
                }
            }
        }

        self::$declaredResources = $resources;
    }

    /**
     * @return list<string> resource ids (`controller-action`) for every public
     *                      action method of the journal module controllers
     */
    private static function journalActionResources(): array
    {
        $resources = [];
        $files = glob(APPLICATION_PATH . '/modules/journal/controllers/*Controller.php');
        self::assertNotEmpty($files, 'journal module controllers must be found');

        foreach ($files as $file) {
            $controller = strtolower(substr(basename($file, '.php'), 0, -strlen('Controller')));
            $source = (string)file_get_contents($file);
            preg_match_all('/public function (\w+)Action\s*\(/', $source, $matches);
            foreach ($matches[1] as $method) {
                // camelCase action methods are reached through dashed action names
                $action = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $method));
                $resources[] = $controller . '-' . $action;
            }
        }

        sort($resources);
        return array_values(array_unique($resources));
    }

    /**
     * Every public action of the journal module must be declared in acl.ini or
     * in a versioned navigation file, so that the dispatcher applies a role rule
     * to it. Runtime-navigation menu pages are the only accepted exceptions.
     */
    public function testEveryJournalActionIsDeclared(): void
    {
        $undeclared = [];
        foreach (self::journalActionResources() as $resource) {
            if (
                !isset(self::$declaredResources[$resource])
                && !in_array($resource, self::RUNTIME_NAVIGATION_PAGES, true)
            ) {
                $undeclared[] = $resource;
            }
        }

        self::assertSame(
            [],
            $undeclared,
            'These journal actions are not declared in acl.ini nor in a versioned navigation file. '
            . 'Add them to acl.ini with the appropriate role (or, for a public menu page provided '
            . 'by the per-journal runtime navigation, to RUNTIME_NAVIGATION_PAGES): '
            . implode(', ', $undeclared)
        );
    }

    /**
     * The allowlist must not go stale: every entry must still match an existing
     * action and must still be absent from acl.ini / versioned navigation.
     */
    public function testRuntimeNavigationListIsCurrent(): void
    {
        $actions = self::journalActionResources();
        foreach (self::RUNTIME_NAVIGATION_PAGES as $resource) {
            self::assertContains(
                $resource,
                $actions,
                "'$resource' no longer matches a journal action; remove it from RUNTIME_NAVIGATION_PAGES"
            );
            self::assertArrayNotHasKey(
                $resource,
                self::$declaredResources,
                "'$resource' is now declared in acl.ini or a navigation file; remove it from RUNTIME_NAVIGATION_PAGES"
            );
        }
    }

    /**
     * @return list<string> role sections in which the resource is declared
     */
    private function rolesFor(string $resource): array
    {
        $key = 'allow.' . $resource;
        $roles = [];
        foreach (self::$acl as $role => $entries) {
            if (is_array($entries) && array_key_exists($key, $entries)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    /**
     * File upload/removal endpoints require an authenticated user: they must stay
     * declared, and never under guest.
     *
     * @dataProvider memberFileEndpoints
     */
    public function testFileEndpointRequiresAuthenticatedRole(string $resource): void
    {
        $roles = $this->rolesFor($resource);
        self::assertNotEmpty($roles, "'$resource' must be declared in acl.ini");
        self::assertNotContains('guest', $roles, "'$resource' must not be declared for guest");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function memberFileEndpoints(): iterable
    {
        yield 'file-upload' => ['file-upload'];
        yield 'file-delete' => ['file-delete'];
    }

    /**
     * Directory/recipient lookups expose user data: editorial roles only.
     *
     * @dataProvider editorialOnlyEndpoints
     */
    public function testEndpointIsEditorialOnly(string $resource): void
    {
        $roles = $this->rolesFor($resource);
        self::assertNotEmpty($roles, "'$resource' must be declared in acl.ini");
        self::assertNotContains('guest', $roles, "'$resource' must not be declared for guest");
        self::assertNotContains('member', $roles, "'$resource' must not be declared for member");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function editorialOnlyEndpoints(): iterable
    {
        // recipient/directory lookups
        yield 'administratemail-getcontacts' => ['administratemail-getcontacts'];
        yield 'administratemail-getrecipients' => ['administratemail-getrecipients'];
        // management actions of volumes / sections / grids / review settings
        yield 'volume-delete' => ['volume-delete'];
        yield 'volume-sort' => ['volume-sort'];
        yield 'volume-saveeditors' => ['volume-saveeditors'];
        yield 'volume-editorsform' => ['volume-editorsform'];
        yield 'volume-addfile' => ['volume-addfile'];
        yield 'section-delete' => ['section-delete'];
        yield 'section-sort' => ['section-sort'];
        yield 'section-editorsform' => ['section-editorsform'];
        yield 'grid-sortcriterion' => ['grid-sortcriterion'];
        yield 'review-editorsassignation' => ['review-editorsassignation'];
        yield 'administratelinkeddata-setnewinfold' => ['administratelinkeddata-setnewinfold'];
    }
}