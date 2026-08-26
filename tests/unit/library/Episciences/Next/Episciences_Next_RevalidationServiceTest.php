<?php

namespace unit\library\Episciences\Next;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Episciences\Next\RevalidationService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;

require_once __DIR__ . '/../Messenger/Enqueue/SpyMessageBus.php';
require_once __DIR__ . '/../Messenger/Enqueue/SpyEnqueueFailureStore.php';
require_once __DIR__ . '/../Messenger/Enqueue/RestoresStaticQueuePort.php';

/**
 * Unit tests for Episciences\Next\RevalidationService — the seam every
 * trigger call site (Paper, PapersManager, Volume, Section, User,
 * JournalNews, AdministratepaperController, ...) uses to enqueue a Next.js
 * cache revalidation.
 *
 * Token resolution is covered by TokenResolverTest; the HTTP taxonomy by
 * RevalidateTagMessageHandlerTest. This class only covers the facade's own
 * responsibility: isEnabled() gating and delegation to the injected port.
 *
 * EPISCIENCES_ENABLE_NEXT_FRONT is never defined anywhere else in this test
 * process — the "flag off" tests below rely on that directly; the "flag on"
 * tests run in their own process via #[RunInSeparateProcess] so defining it
 * there doesn't leak into unrelated tests.
 *
 * @covers \Episciences\Next\RevalidationService
 */
class Episciences_Next_RevalidationServiceTest extends TestCase
{
    use RestoresStaticQueuePort;

    protected function setUp(): void
    {
        $this->captureStaticQueuePort(RevalidationService::class);
    }

    protected function tearDown(): void
    {
        $this->restoreStaticQueuePort(RevalidationService::class);
    }

    // =========================================================================
    // isEnabled() gating — EPISCIENCES_ENABLE_NEXT_FRONT unset in this process
    // =========================================================================

    public function testEnqueueTagIsANoopWhenFeatureFlagIsOff(): void
    {
        $bus = new SpyMessageBus();
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        RevalidationService::enqueueTag('epijinfo', 'article-42');

        self::assertSame([], $bus->dispatched);
    }

    public function testEnqueueTagsIsANoopWhenFeatureFlagIsOff(): void
    {
        $bus = new SpyMessageBus();
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        RevalidationService::enqueueTags('epijinfo', ['article-42', 'volumes-epijinfo']);

        self::assertSame([], $bus->dispatched);
    }

    // =========================================================================
    // getPort() / setPort() — unconditional, no flag involved
    // =========================================================================

    public function testGetPortReturnsTheInjectedInstance(): void
    {
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore()));
        RevalidationService::setPort($port);

        self::assertSame($port, RevalidationService::getPort());
    }

    // =========================================================================
    // Delegation to the port — EPISCIENCES_ENABLE_NEXT_FRONT forced on
    // =========================================================================

    #[RunInSeparateProcess]
    public function testEnqueueTagDelegatesToTheInjectedPortWhenFeatureFlagIsOn(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $bus = new SpyMessageBus();
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        RevalidationService::enqueueTag('epijinfo', 'article-42');

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(RevalidateTagMessage::class, $bus->dispatched[0]);
        self::assertSame('epijinfo', $bus->dispatched[0]->rvcode);
        self::assertSame('article-42', $bus->dispatched[0]->tag);
    }

    #[RunInSeparateProcess]
    public function testEnqueueTagsDelegatesToTheInjectedPortWhenFeatureFlagIsOn(): void
    {
        define('EPISCIENCES_ENABLE_NEXT_FRONT', true);
        $bus = new SpyMessageBus();
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        RevalidationService::enqueueTags('epijinfo', ['article-42', 'volumes-epijinfo']);

        self::assertCount(2, $bus->dispatched);
    }
}
