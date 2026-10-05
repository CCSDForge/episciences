<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Auth_Plugin
 */
class Episciences_Auth_PluginTest extends TestCase
{
    private Episciences_Auth_Plugin $plugin;

    protected function setUp(): void
    {
        $acl = new Zend_Acl();
        foreach (['user-findusers', 'api-openaire-metrics', 'file-index', 'my-ctrl-index', 'dup-openaire-metrics', 'dup-openairemetrics'] as $resource) {
            $acl->addResource(new Zend_Acl_Resource($resource));
        }

        $this->plugin = new Episciences_Auth_Plugin();
        $property = new ReflectionProperty(Ccsd_Auth_Plugin::class, '_acl');
        $property->setAccessible(true);
        $property->setValue($this->plugin, $acl);
    }

    public function testExactMatchIsReturnedAsIs(): void
    {
        self::assertSame('user-findusers', $this->plugin->resolveResource('user', 'findusers'));
    }

    /**
     * @dataProvider variantProvider
     */
    public function testVariantsResolveToTheProtectedResource(string $controller, string $action, string $expected): void
    {
        self::assertSame($expected, $this->plugin->resolveResource($controller, $action));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function variantProvider(): array
    {
        return [
            'case variant' => ['user', 'findUsers', 'user-findusers'],
            'upper case controller' => ['User', 'FINDUSERS', 'user-findusers'],
            'delimiter variant' => ['user', 'find-users', 'user-findusers'],
            'dot and underscore variant' => ['user', 'find.users', 'user-findusers'],
            'camel case for hyphenated key' => ['api', 'openaireMetrics', 'api-openaire-metrics'],
        ];
    }

    public function testHyphenatedControllerVariantResolves(): void
    {
        self::assertSame('my-ctrl-index', $this->plugin->resolveResource('myCtrl', 'index'));
    }

    public function testAmbiguousVariantFailsClosed(): void
    {
        self::assertSame('dup-OpenaireMetrics', $this->plugin->resolveResource('dup', 'OpenaireMetrics'));
    }

    public function testExactMatchWinsOverAmbiguity(): void
    {
        self::assertSame('dup-openairemetrics', $this->plugin->resolveResource('dup', 'openairemetrics'));
    }

    public function testUnknownResourceKeepsRawKey(): void
    {
        self::assertSame('foo-bar', $this->plugin->resolveResource('foo', 'bar'));
    }

    public function testNormalizeName(): void
    {
        self::assertSame('findusers', Episciences_Auth_Plugin::normalizeName('Find-Users'));
        self::assertSame('a1b', Episciences_Auth_Plugin::normalizeName('A.1_b'));
    }
}
