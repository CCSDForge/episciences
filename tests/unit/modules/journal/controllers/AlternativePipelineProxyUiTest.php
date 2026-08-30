<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

final class AlternativePipelineProxyUiTest extends TestCase
{
    public function testManagementPageOffersBothProxyWorkflows(): void
    {
        $source = (string)file_get_contents(
            APPLICATION_PATH . '/modules/journal/views/scripts/administratepaper/view.phtml'
        );

        self::assertStringContainsString('/paper/finalversiondeposit/id/', $source);
        self::assertStringContainsString('alt-author-approve-proof-modal', $source);
        self::assertStringContainsString('alt-author-reject-proof-modal', $source);
        self::assertStringNotContainsString('class="btn-group" style="margin-top: 10px; display: block;"', $source);
    }
}
