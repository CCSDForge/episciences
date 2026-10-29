<?php

namespace unit\library\Episciences\Next\Messenger;

use Episciences\Next\Messenger\TokenResolver;
use PHPUnit\Framework\TestCase;

class TokenResolverTest extends TestCase
{
    private string $dataRoot;

    protected function setUp(): void
    {
        $this->dataRoot = sys_get_temp_dir() . '/episciences-token-resolver-test-' . uniqid('', true);
        mkdir($this->dataRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dataRoot);
    }

    public function testResolvesTokenFromJournalConfig(): void
    {
        $this->writeJournalConfig('epijinfo', ['NEXT_REVALIDATION_TOKEN' => 'journal-token']);
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('journal-token', $resolver->resolve('epijinfo'));
    }

    public function testFallsBackToGlobalSecretWhenNoJournalConfigExists(): void
    {
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('global-secret', $resolver->resolve('unknown-journal'));
    }

    public function testFallsBackToGlobalSecretWhenTokenIsBlank(): void
    {
        $this->writeJournalConfig('epijinfo', ['NEXT_REVALIDATION_TOKEN' => '']);
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('global-secret', $resolver->resolve('epijinfo'));
    }

    public function testFallsBackToGlobalSecretWhenKeyIsMissing(): void
    {
        $this->writeJournalConfig('epijinfo', ['OTHER_KEY' => 'irrelevant']);
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('global-secret', $resolver->resolve('epijinfo'));
    }

    public function testFallsBackToGlobalSecretOnMalformedJson(): void
    {
        $configDir = $this->dataRoot . '/epijinfo/config';
        mkdir($configDir, 0777, true);
        file_put_contents($configDir . '/pwd.json', '{not valid json');

        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('global-secret', $resolver->resolve('epijinfo'));
    }

    public function testMemoizesResolutionPerRvcode(): void
    {
        $this->writeJournalConfig('epijinfo', ['NEXT_REVALIDATION_TOKEN' => 'first-token']);
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('first-token', $resolver->resolve('epijinfo'));

        // Overwrite after the first resolution: memoization must keep
        // serving the value read on the first call, not re-read the file.
        $this->writeJournalConfig('epijinfo', ['NEXT_REVALIDATION_TOKEN' => 'second-token']);

        self::assertSame('first-token', $resolver->resolve('epijinfo'));
    }

    public function testResolvesEachRvcodeIndependently(): void
    {
        $this->writeJournalConfig('epijinfo', ['NEXT_REVALIDATION_TOKEN' => 'token-a']);
        $this->writeJournalConfig('epirevo', ['NEXT_REVALIDATION_TOKEN' => 'token-b']);
        $resolver = new TokenResolver($this->dataRoot, 'global-secret');

        self::assertSame('token-a', $resolver->resolve('epijinfo'));
        self::assertSame('token-b', $resolver->resolve('epirevo'));
    }

    /** @param array<string, mixed> $config */
    private function writeJournalConfig(string $rvcode, array $config): void
    {
        $configDir = $this->dataRoot . '/' . $rvcode . '/config';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        file_put_contents($configDir . '/pwd.json', json_encode($config, JSON_THROW_ON_ERROR));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
