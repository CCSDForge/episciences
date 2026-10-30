<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Closure;
use Episciences\Api\DelegatedTokenClient;
use Episciences_Acl;
use Episciences_Review;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use StatsController;
use unit\modules\ControllerTestCase;
use unit\modules\support\ApiStubStatsController;
use unit\modules\support\DispatchResult;
use Zend_Registry;
use Zend_Session_Namespace;

/**
 * Requests of the statistics page to the Episciences API (StatsController::indexAction()): the papers of the figures
 * with a delegated token for the journal administrators only, the statistics without the papers otherwise, and all the
 * data without the start date of the statistics for the journal administrators only.
 */
final class StatsControllerIndexActionTest extends ControllerTestCase
{
    private const DASHBOARD_PATH = 'journals/stats/dashboard/';
    private const DASHBOARD = [
        'value' => [
            'nbSubmissions' => 2,
            'nbAccepted' => 1,
            'nbRefused' => 1,
            'nbOtherStatus' => 0,
            'nbPublished' => 1,
        ],
        'details' => [],
    ];

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $apiRequests = [];

    private bool $papersAvailable = true;

    private bool $dashboardAvailable = true;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'StatsController');
    }

    protected function setUp(): void
    {
        $this->givenJournal();
        $this->givenSettings([]);
        Zend_Registry::set('hideStatistics', false);
        Zend_Registry::set('appLogger', new Logger('test'));
        ApiStubStatsController::$handler = Closure::fromCallable([$this, 'answerApi']);
    }

    protected function tearDown(): void
    {
        ApiStubStatsController::$handler = null;
    }

    /**
     * @param array<string, mixed> $settings settings of the journal
     */
    private function givenSettings(array $settings): void
    {
        Zend_Registry::set('reviewSettings', $settings);
    }

    /**
     * Answer of the API: a token, the dashboard (with the papers when asked, unless they are not available) or the
     * evaluation figures
     * @param array<string, mixed> $options
     */
    private function answerApi(RequestInterface $request, array $options): Response
    {
        $this->apiRequests[] = ['request' => $request, 'options' => $options];
        $path = $request->getUri()->getPath();

        if (str_ends_with($path, DelegatedTokenClient::ENDPOINT)) {
            return new Response(200, [], '{"token":"jwt"}');
        }

        if (str_contains($path, self::DASHBOARD_PATH)) {
            if (!$this->dashboardAvailable) {
                return new Response(503);
            }

            if (!isset($this->query($request)[StatsController::WITH_PAPERS_PARAM])) {
                return new Response(200, [], json_encode(self::DASHBOARD, JSON_THROW_ON_ERROR));
            }

            if (!$this->papersAvailable) {
                return new Response(500);
            }

            $dashboard = self::DASHBOARD;
            $dashboard['value']['papers'] = [StatsController::NB_ACCEPTED => [201]];

            return new Response(200, [], json_encode($dashboard, JSON_THROW_ON_ERROR));
        }

        return new Response(200, [], '[]');
    }

    /**
     * @return array<string, string>
     */
    private function query(RequestInterface $request): array
    {
        return Query::parse($request->getUri()->getQuery());
    }

    /**
     * @return list<RequestInterface> the requests of the dashboard, in order
     */
    private function dashboardRequests(): array
    {
        return array_values(array_filter(
            array_column($this->apiRequests, 'request'),
            static fn(RequestInterface $request): bool => str_contains($request->getUri()->getPath(), self::DASHBOARD_PATH)
        ));
    }

    private function tokenWasAsked(): bool
    {
        foreach ($this->apiRequests as $apiRequest) {
            if (str_ends_with($apiRequest['request']->getUri()->getPath(), DelegatedTokenClient::ENDPOINT)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function displayStatistics(array $query = []): DispatchResult
    {
        return $this->dispatch(ApiStubStatsController::class, 'index', $this->getRequest($query));
    }

    public function testTheStatisticsCannotBeRetrieved_ServiceUnavailable(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $this->dashboardAvailable = false;

        self::assertSame(503, $this->displayStatistics()->statusCode());
    }

    public function testAYearWithoutStatistics_NotFound(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertSame(404, $this->displayStatistics(['year' => '1990'])->statusCode());
    }

    public function testAnEditorGetsTheStatisticsWithoutToken(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $this->displayStatistics();

        self::assertFalse($this->tokenWasAsked());
        $dashboards = $this->dashboardRequests();
        self::assertCount(1, $dashboards);
        self::assertArrayNotHasKey(StatsController::WITH_PAPERS_PARAM, $this->query($dashboards[0]));
        self::assertFalse($dashboards[0]->hasHeader('Authorization'));
    }

    public function testTheAdministratorsGetThePapersWithTheirToken(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->displayStatistics();

        self::assertTrue($this->tokenWasAsked());
        $dashboards = $this->dashboardRequests();
        self::assertCount(1, $dashboards);
        self::assertSame('true', $this->query($dashboards[0])[StatsController::WITH_PAPERS_PARAM]);
        self::assertSame('Bearer jwt', $dashboards[0]->getHeaderLine('Authorization'));

        $stored = (new Zend_Session_Namespace(StatsController::PAPERS_SESSION_NAMESPACE))->{StatsController::getPapersSessionKey(null)};
        self::assertSame(self::UID, $stored['uid']);
        self::assertSame([201], $stored['lists'][StatsController::NB_ACCEPTED]['docIds']);
    }

    public function testWithoutThePapers_TheStatisticsWithoutToken(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->papersAvailable = false;

        $this->displayStatistics();

        $dashboards = $this->dashboardRequests();
        self::assertCount(2, $dashboards);
        self::assertArrayNotHasKey(StatsController::WITH_PAPERS_PARAM, $this->query($dashboards[1]));
        self::assertFalse($dashboards[1]->hasHeader('Authorization'));
    }

    public function testReservedStatistics_TheTokenIsSentWithoutThePapers(): void
    {
        $this->givenSettings([Episciences_Review::SETTING_DISPLAY_STATISTICS => StatsController::DISPLAY_STATISTICS_RESERVED]);
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->papersAvailable = false;

        $this->displayStatistics();

        $dashboards = $this->dashboardRequests();
        self::assertCount(2, $dashboards);
        self::assertArrayNotHasKey(StatsController::WITH_PAPERS_PARAM, $this->query($dashboards[1]));
        self::assertSame('Bearer jwt', $dashboards[1]->getHeaderLine('Authorization'));
    }

    public function testReservedStatistics_AnEditorGetsThemWithoutThePapers(): void
    {
        $this->givenSettings([Episciences_Review::SETTING_DISPLAY_STATISTICS => StatsController::DISPLAY_STATISTICS_RESERVED]);
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $this->displayStatistics();

        $dashboards = $this->dashboardRequests();
        self::assertCount(1, $dashboards);
        self::assertArrayNotHasKey(StatsController::WITH_PAPERS_PARAM, $this->query($dashboards[0]));
        self::assertSame('Bearer jwt', $dashboards[0]->getHeaderLine('Authorization'));
    }

    public function testAllTheData_OnlyForTheAdministratorsOfTheJournal(): void
    {
        $this->givenSettings(['startStatsAfterDate' => '2020-01-01']);
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $this->displayStatistics([StatsController::FULL_HISTORY_PARAM => '1']);

        self::assertSame('2020-01-01', $this->query($this->dashboardRequests()[0])['startAfterDate']);
    }

    public function testAllTheData_TheAdministratorsGetThemWithoutTheStartDate(): void
    {
        $this->givenSettings(['startStatsAfterDate' => '2020-01-01']);
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->displayStatistics([StatsController::FULL_HISTORY_PARAM => '1']);

        self::assertArrayNotHasKey('startAfterDate', $this->query($this->dashboardRequests()[0]));
        // kept apart from the lists of the page with the start date
        $session = new Zend_Session_Namespace(StatsController::PAPERS_SESSION_NAMESPACE);
        self::assertNotNull($session->{StatsController::getPapersSessionKey(null, true)});
        self::assertNull($session->{StatsController::getPapersSessionKey(null)});
    }

    public function testTheRequestsToTheApiDoNotWaitIndefinitely(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $this->displayStatistics();

        self::assertNotEmpty($this->apiRequests);
        foreach ($this->apiRequests as $apiRequest) {
            self::assertSame(StatsController::API_CONNECT_TIMEOUT, $apiRequest['options']['connect_timeout']);
            self::assertSame(StatsController::API_TIMEOUT, $apiRequest['options']['timeout']);
        }
    }
}
