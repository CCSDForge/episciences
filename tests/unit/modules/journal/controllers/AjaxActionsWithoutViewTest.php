<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratelinkeddataController;
use Episciences_Repositories;
use ReflectionProperty;
use SubmitController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Registry;

/**
 * AJAX actions that have no view script: they must disable the view rendering whatever the request,
 * otherwise a direct (non-AJAX) request ends in an error because the view script does not exist.
 */
final class AjaxActionsWithoutViewTest extends ControllerTestCase
{
    private const DATAVERSE_REPO_ID = 99;

    private Zend_Controller_Action_Helper_ViewRenderer $viewRenderer;

    private bool $previousNoRender;

    /** @var array<mixed> */
    private array $previousRepositories;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'SubmitController');
        self::loadController('journal', 'AdministratelinkeddataController');
    }

    protected function setUp(): void
    {
        /** @var Zend_Controller_Action_Helper_ViewRenderer $viewRenderer */
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $this->viewRenderer = $viewRenderer;
        $this->previousNoRender = $viewRenderer->getNoRender();
        $viewRenderer->setNoRender(false);

        $this->previousRepositories = $this->repositoriesCache([]);
        Zend_Registry::set('metadataSources', [
            (int)Episciences_Repositories::HAL_REPO_ID => ['name' => 'HAL', 'type' => 'repository'],
            self::DATAVERSE_REPO_ID => ['name' => 'ADataverse', 'type' => 'dataverse'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->viewRenderer->setNoRender($this->previousNoRender);
        $this->repositoriesCache($this->previousRepositories);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function ajaxActions(): iterable
    {
        yield 'submit/ajaxhashook' => [SubmitController::class, 'ajaxhashook'];
        yield 'submit/ajaxisdataverse' => [SubmitController::class, 'ajaxisdataverse'];
        yield 'administratelinkeddata/ajaxgetldform' => [AdministratelinkeddataController::class, 'ajaxgetldform'];
    }

    /**
     * @dataProvider ajaxActions
     * @param class-string<\Zend_Controller_Action> $controller
     */
    public function testDirectRequestRendersNothing(string $controller, string $action): void
    {
        $result = $this->dispatch($controller, $action, $this->getRequest(['repoId' => self::DATAVERSE_REPO_ID]));

        self::assertSame('', $result->body());
        self::assertTrue($this->viewRenderer->getNoRender(), 'the missing view script must not be rendered');
    }

    public function testHasHookAnswersWhetherTheRepositoryRequiresAVersion(): void
    {
        $request = $this->xhrPostRequest(['repoId' => Episciences_Repositories::CWI_REPO_ID]);

        $result = $this->dispatch(SubmitController::class, 'ajaxhashook', $request);

        self::assertSame(['isRequiredVersion' => ['result' => false]], $result->json());
        self::assertTrue($this->viewRenderer->getNoRender());
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function repositories(): iterable
    {
        yield 'Dataverse repository' => [self::DATAVERSE_REPO_ID, true];
        yield 'other repository' => [(int)Episciences_Repositories::HAL_REPO_ID, false];
    }

    /**
     * @dataProvider repositories
     */
    public function testIsDataverseAnswersWhetherTheRepositoryIsADataverse(int $repoId, bool $expected): void
    {
        $result = $this->dispatch(SubmitController::class, 'ajaxisdataverse', $this->xhrPostRequest(['repoId' => $repoId]));

        self::assertSame(['isDataverse' => $expected], $result->json());
        self::assertTrue($this->viewRenderer->getNoRender());
    }

    /**
     * Repositories cached by Episciences_Repositories, replaced by $cache.
     *
     * @param array<mixed> $cache
     * @return array<mixed>
     */
    private function repositoriesCache(array $cache): array
    {
        $property = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $current = $property->getValue();
        $property->setValue(null, $cache);

        return is_array($current) ? $current : [];
    }
}
