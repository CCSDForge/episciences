<?php

declare(strict_types=1);

namespace unit\library\Episciences\Repositories;

use Episciences\Repositories\ConceptIdentifierInterface;
use Episciences\Repositories\FilesEnrichmentInterface;
use Episciences\Repositories\LinkedDataEnrichmentInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural tests: every shipped hooks class must DECLARE the capability it
 * actually IMPLEMENTS, and vice versa.
 *
 * Capability methods such as Episciences_Repositories::hasConceptIdentifier() ask
 * an interface, but the behaviour they stand for lives in a method body no
 * interface constrains — most visibly for ConceptIdentifierInterface, which is a
 * pure marker. Nothing but this test links the two: PHP cannot, and neither can
 * PHPStan. The gap has already bitten once, when Cryptology ePrint and DSpace set
 * a concept identifier through hookApiRecords() without declaring the capability,
 * so every submission from those two repositories threw InvalidArgumentException
 * in Episciences_Paper::setConcept_identifier().
 *
 * Both directions are asserted: producing without declaring silently disables the
 * capability, declaring without producing silently enables a branch that has
 * nothing to work with.
 *
 * DB-free: the classes are only reflected upon and read as source, never called,
 * so no metadata source, registry or repository lookup is involved.
 *
 * @covers \Episciences\Repositories\ConceptIdentifierInterface
 * @covers \Episciences\Repositories\FilesEnrichmentInterface
 * @covers \Episciences\Repositories\LinkedDataEnrichmentInterface
 */
final class Episciences_Repositories_CapabilityDeclarationTest extends TestCase
{
    private const HOOKS_GLOB = __DIR__ . '/../../../../../library/Episciences/Repositories/*/Hooks.php';

    /**
     * Lower bound on the number of shipped hooks classes. A moved directory or a
     * renamed file would otherwise turn every assertion below into a no-op.
     */
    private const MINIMUM_HOOK_CLASSES = 9;

    /**
     * Writes of Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY, in the two
     * shapes the hook classes use: an array element assignment
     * ($data[...KEY] = $x) and an array literal ([...KEY => $x]).
     *
     * Reads must not match, hence the assignment form excluding '==' and '=>'.
     */
    private const CONCEPT_IDENTIFIER_WRITE_PATTERNS = [
        '/\[\s*(?:[\\\\\w]+::)?CONCEPT_IDENTIFIER_KEY\s*\]\s*=(?![=>])/',
        '/(?:[\\\\\w]+::)?CONCEPT_IDENTIFIER_KEY\s*=>/',
    ];

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function hookClassProvider(): array
    {
        $cases = [];

        foreach (glob(self::HOOKS_GLOB) ?: [] as $file) {
            $className = self::classNameIn($file);
            $cases[$className] = [$className, $file];
        }

        return $cases;
    }

    public function testEveryShippedHooksClassIsDiscovered(): void
    {
        $found = self::hookClassProvider();

        self::assertGreaterThanOrEqual(
            self::MINIMUM_HOOK_CLASSES,
            count($found),
            sprintf(
                'Only %d hooks classes found under %s. Every assertion in this file is '
                . 'scoped to that list, so a moved or renamed file would make them vacuous.',
                count($found),
                self::HOOKS_GLOB
            )
        );

        foreach (array_keys($found) as $className) {
            self::assertTrue(class_exists($className), $className . ' could not be autoloaded');
        }
    }

    /**
     * ConceptIdentifierInterface carries no method, so only reading the source can
     * tell whether a hooks class really produces a concept identifier.
     *
     * @dataProvider hookClassProvider
     */
    public function testConceptIdentifierCapabilityMatchesWhatTheClassProduces(string $className): void
    {
        self::assertTrue(class_exists($className), $className . ' could not be autoloaded');

        $declares = is_a($className, ConceptIdentifierInterface::class, true);
        $produces = $this->producesConceptIdentifier($className);

        if ($produces && !$declares) {
            self::fail(sprintf(
                '%s writes Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY but does not implement %s, '
                . 'so Episciences_Repositories::hasConceptIdentifier() answers false for it and '
                . 'Episciences_Paper::setConcept_identifier() will reject the value it produces.',
                $className,
                ConceptIdentifierInterface::class
            ));
        }

        if ($declares && !$produces) {
            self::fail(sprintf(
                '%s implements %s but never writes Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY. '
                . 'Either the marker is stale, or the key is set in a way this test does not recognise '
                . '(see CONCEPT_IDENTIFIER_WRITE_PATTERNS).',
                $className,
                ConceptIdentifierInterface::class
            ));
        }

        self::assertSame($produces, $declares);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function enrichmentInterfaceProvider(): array
    {
        return [
            'files enrichment' => [FilesEnrichmentInterface::class, 'hookFilesProcessing'],
            'linked data enrichment' => [LinkedDataEnrichmentInterface::class, 'hookLinkedDataProcessing'],
        ];
    }

    /**
     * PHP enforces the interface -> method direction on its own. The reverse one is
     * the silent failure: defining the hook without declaring the interface leaves
     * hasFilesEnrichment() / hasLinkedDataEnrichment() answering false, and the
     * repository is treated as if it had no enrichment at all.
     *
     * @dataProvider enrichmentInterfaceProvider
     */
    public function testDefiningAnEnrichmentHookRequiresDeclaringItsInterface(
        string $interface,
        string $method
    ): void
    {
        $offenders = [];

        foreach (array_keys(self::hookClassProvider()) as $className) {
            self::assertTrue(class_exists($className), $className . ' could not be autoloaded');

            if (method_exists($className, $method) && !is_a($className, $interface, true)) {
                $offenders[] = $className;
            }
        }

        self::assertSame([], $offenders, sprintf(
            'These hooks classes define %s() without implementing %s, so their capability reads as false: %s',
            $method,
            $interface,
            implode(', ', $offenders)
        ));
    }

    private function producesConceptIdentifier(string $className): bool
    {
        foreach ($this->sourceOfClassAndParents($className) as $source) {
            foreach (self::CONCEPT_IDENTIFIER_WRITE_PATTERNS as $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Source of the class and of every ancestor: BioRxiv and MedRxiv hold nothing of
     * their own, everything they do comes from Episciences_Repositories_BioMedRxiv.
     *
     * @return array<int, string>
     */
    private function sourceOfClassAndParents(string $className): array
    {
        $sources = [];
        $reflection = new ReflectionClass($className);

        while ($reflection instanceof ReflectionClass) {
            $file = $reflection->getFileName();

            if (is_string($file) && is_readable($file)) {
                $sources[] = $this->stripComments((string)file_get_contents($file));
            }

            $parent = $reflection->getParentClass();
            $reflection = $parent === false ? null : $parent;
        }

        return $sources;
    }

    /**
     * Comments are dropped before matching so a docblock mentioning the key cannot
     * be mistaken for a write.
     */
    private function stripComments(string $source): string
    {
        $stripped = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $stripped .= $token[1];
                continue;
            }

            $stripped .= $token;
        }

        return $stripped;
    }

    /**
     * @return class-string
     */
    private static function classNameIn(string $file): string
    {
        $source = (string)file_get_contents($file);

        if (preg_match('/^\s*(?:final\s+|abstract\s+)*class\s+(\w+)/m', $source, $matches) !== 1) {
            self::fail('No class declaration found in ' . $file);
        }

        /** @var class-string $className */
        $className = $matches[1];

        return $className;
    }
}
