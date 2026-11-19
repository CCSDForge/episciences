<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\LightAnswer;
use Episciences\Reviewer\LightAnswerReport;
use Episciences_Paper;
use Episciences_Rating_Criterion;
use Episciences_Rating_Grid;
use Episciences_Rating_Report;
use PHPUnit\Framework\TestCase;

/**
 * Rating grid filled in without an account (#319): the grid of the request, its form, and the answer written in the
 * folder of the invitation.
 *
 * @covers \Episciences\Reviewer\LightAnswerReport
 */
final class LightAnswerReportTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'en']));
        $this->dir = sys_get_temp_dir() . '/light-answer-report-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '{,*/}*', GLOB_BRACE) ?: [] as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        @rmdir($this->dir . 'answer');
        @rmdir($this->dir);
    }

    /**
     * A grid with one criterion: an option to choose, a comment and a file
     */
    private function gridFile(): string
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $criterion->setId('item_0');
        $criterion->setLabels(['en' => 'Relevance <b>']);
        $criterion->setOptions([
            ['value' => 0, 'label' => ['en' => 'Low']],
            ['value' => 1, 'label' => ['en' => 'High']],
        ]);
        $criterion->setComment_setting(true);
        $criterion->setAttachment_setting(true);
        $criterion->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);

        $grid = new Episciences_Rating_Grid();
        $grid->setFilename('grid_t5.xml');
        $grid->setCriteria([$criterion]);
        self::assertTrue($grid->save($this->dir));

        return $this->dir . 'grid_t5.xml';
    }

    /**
     * @return Episciences_Paper&\PHPUnit\Framework\MockObject\MockObject
     */
    private function paper(string|false $gridPath)
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(999801);
        $paper->method('getRvid')->willReturn(3);
        $paper->method('getGridPath')->willReturn($gridPath);

        return $paper;
    }

    private function report(): Episciences_Rating_Report
    {
        $report = new Episciences_Rating_Report();
        $report->setPath($this->dir . 'answer/');
        self::assertTrue($report->loadXML($this->gridFile()));

        return $report;
    }

    public function testWithoutAGridTheRequestCannotBeAnswered(): void
    {
        self::assertNull(LightAnswerReport::load($this->paper(false), 5, 70));
    }

    public function testTheGridOfTheRequestIsLoadedForTheFolderOfTheInvitation(): void
    {
        $report = LightAnswerReport::load($this->paper($this->gridFile()), 5, 70);

        self::assertNotNull($report);
        self::assertCount(1, $report->getCriteria());
        self::assertSame(5, $report->getRequest_type_id());
        self::assertStringEndsWith('999801/' . LightAnswer::folderOf(70), (string)$report->getPath());
    }

    public function testTheFormSendsTheAnswerWithoutTheButtonsOfTheRatingPage(): void
    {
        $form = LightAnswerReport::form($this->report());

        self::assertNotNull($form);
        self::assertNotNull($form->getElement(LightAnswerReport::SUBMIT));
        self::assertNotNull($form->getElement('note_item_0'));
        self::assertNotNull($form->getElement('comment_item_0'));
        self::assertNotNull($form->getElement('file_item_0'));
        self::assertNull($form->getElement('submitRatingForm'));
        self::assertNull($form->getElement('validateRating'));
        self::assertSame(['file_item_0'], LightAnswerReport::fileFields($this->report()));
    }

    public function testTheFilledGridIsWrittenInTheFolderOfTheAnswerThenReadAgain(): void
    {
        $report = $this->report();

        self::assertTrue(LightAnswerReport::save($report, ['note_item_0' => '1', 'comment_item_0' => '<p>Fine</p><script>x</script>'], [
            'file_item_0' => ['name' => 'answer.pdf'],
        ]));
        self::assertFileExists($this->dir . 'answer/' . LightAnswerReport::REPORT_FILE);

        $reloaded = new Episciences_Rating_Report();
        $reloaded->setPath($this->dir . 'answer/');
        self::assertTrue($reloaded->loadXML($this->dir . 'answer/' . LightAnswerReport::REPORT_FILE));
        self::assertSame(['answer.pdf'], LightAnswerReport::attachments($reloaded));

        $html = LightAnswerReport::summaryHtml($reloaded);
        self::assertStringContainsString('Relevance &lt;b&gt;', $html);
        self::assertStringContainsString('High', $html);
        self::assertStringContainsString('Fine', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('answer.pdf', $html);
    }

    public function testAnEditedAnswerKeepsTheFileOfACriterionWithoutANewOne(): void
    {
        $report = $this->report();
        LightAnswerReport::save($report, ['note_item_0' => '0'], ['file_item_0' => ['name' => 'first.pdf']]);

        LightAnswerReport::save($report, ['note_item_0' => '1'], []);

        self::assertSame(['first.pdf'], LightAnswerReport::attachments($report));
    }
}
