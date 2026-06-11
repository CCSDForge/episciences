<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the per-paper access checks of the contributor
 * endpoints in PaperController.
 *
 * These endpoints receive a document / comment id as request parameter and the
 * role rule alone (member) does not bind the user to the paper: the binding is
 * done inside each action. Source-analysis tests (ZF1 controllers are not
 * instantiable in isolation) assert that the binding stays in place.
 *
 * @covers PaperController
 */
final class PaperControllerContributorGuardTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/PaperController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in PaperController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    /**
     * Each contributor endpoint must bind the request to the paper through
     * isPaperContributorOrManager() and answer 403 otherwise.
     *
     * @dataProvider contributorEndpoints
     */
    public function testEndpointBindsTheRequestToThePaper(string $action): void
    {
        $method = $this->extractMethod($action);

        self::assertStringContainsString('isPaperContributorOrManager(', $method,
            "$action must restrict the paper chosen by request parameter to its contributor or staff");
        self::assertStringContainsString('403', $method,
            "$action must answer 403 when the user is not bound to the paper");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contributorEndpoints(): iterable
    {
        yield 'answerrequest'    => ['answerrequestAction'];
        yield 'contactrequest'   => ['contactrequestAction'];
        yield 'tmpversion'       => ['tmpversionAction'];
        yield 'newversion'       => ['newversionAction'];
        yield 'saveanswer'       => ['saveanswerAction'];
        yield 'savetmpversion'   => ['savetmpversionAction'];
        yield 'savenewversion'   => ['savenewversionAction'];
        yield 'updaterecorddata' => ['updaterecorddataAction'];
    }

    /**
     * The binding helper must check the journal and the accepted profiles
     * (contributor of the paper, or user allowed to manage papers).
     */
    public function testContributorBindingCoversJournalAndProfiles(): void
    {
        $method = $this->extractMethod('isPaperContributorOrManager');

        self::assertStringContainsString('getRvid() === RVID', $method,
            'the binding must be limited to the current journal');
        self::assertStringContainsString('isOwnerOrCoAuthor()', $method,
            'the binding must accept the contributor (owner or co-author)');
        self::assertStringContainsString('Episciences_Auth::isAllowedToManagePaper()', $method,
            'the binding must accept the users allowed to manage papers');
    }

    /**
     * Abandoning the publication process must apply the same conditions as the
     * button display: staff, owner or paper editor depending on the settings.
     */
    public function testAbandonAppliesTheButtonDisplayConditions(): void
    {
        $action = $this->extractMethod('abandonpublicationprocessAction');
        self::assertStringContainsString('isAllowedToAbandonPublicationProcess(', $action,
            'abandonpublicationprocessAction must apply the abandon conditions');
        self::assertStringContainsString('403', $action);

        $helper = $this->extractMethod('isAllowedToAbandonPublicationProcess');
        self::assertStringContainsString('Episciences_Auth::isSecretary()', $helper);
        self::assertStringContainsString(
            'SETTING_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS', $helper,
            'the owner case must depend on the journal setting');
        self::assertStringContainsString('isOwner()', $helper);
        self::assertStringContainsString(
            'SETTING_EDITORS_CAN_ABANDON_CONTINUE_PUBLICATION_PROCESS', $helper,
            'the paper-editor case must depend on the dedicated setting');
        self::assertStringContainsString('getEditors()', $helper);
    }

    /**
     * Restoring an abandoned publication process is an editorial-staff action
     * on an abandoned paper of the current journal.
     */
    public function testContinueIsRestrictedToStaffOnAbandonedPapers(): void
    {
        $method = $this->extractMethod('continuepublicationprocessAction');

        self::assertStringContainsString('isAbandoned()', $method,
            'continuepublicationprocessAction must only apply to abandoned papers');
        self::assertStringContainsString('Episciences_Auth::isSecretary()', $method,
            'continuepublicationprocessAction must be restricted to editorial staff');
        self::assertStringContainsString('getRvid() !== RVID', $method,
            'continuepublicationprocessAction must be limited to the current journal');
        self::assertStringContainsString('403', $method);
    }

    /**
     * The ORCID update must use the paper id of the authorised document, not a
     * free value taken from the request body.
     */
    public function testOrcidUpdateUsesThePaperIdOfTheAuthorisedDocument(): void
    {
        $method = $this->extractMethod('postorcidauthorAction');

        self::assertStringNotContainsString("\$data['paperid']", $method,
            'postorcidauthorAction must not take the paper id from the request body');
        self::assertStringContainsString('$paper->getPaperid()', $method,
            'postorcidauthorAction must derive the paper id from the authorised document');
    }
}
