<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Paper;
use Episciences_Rating_Manager;
use Episciences_Rating_Report;
use Episciences_Tools;
use Zend_Form;
use Zend_Form_Element_File;
use Zend_Form_Element_Select;

/**
 * Rating grid filled in without an account (#319): the answer to a review request of a "light answer" type.
 *
 * The grid is the one of the request (grid of the type, else of the volume except for a quick opinion, else the
 * default grid: Episciences_Paper::getGridPath()). Once answered, the filled grid is kept as report.xml in the folder
 * of the answer (<docid>/reports/light_<aid>/), with the files attached to its criteria. No REVIEWER_REPORT row: its
 * UID is an account, and the invited person may have none.
 */
final class LightAnswerReport
{
    public const SUBMIT = 'submitlightanswer';
    public const REPORT_FILE = 'report.xml';

    /**
     * Absolute folder of the answer of an invitation
     */
    public static function folderPath(int $docid, int $aid): string
    {
        return REVIEW_FILES_PATH . $docid . '/' . LightAnswer::folderOf($aid);
    }

    /**
     * The grid to fill in: the answer already given, else the grid of the request; null when no grid is configured
     * (or when it has no criterion)
     */
    public static function load(Episciences_Paper $paper, int $requestTypeId, int $aid): ?Episciences_Rating_Report
    {
        $folder = self::folderPath((int)$paper->getDocid(), $aid);
        $answered = $folder . self::REPORT_FILE;
        $source = is_file($answered) ? $answered : $paper->getGridPath($requestTypeId);

        if (!is_string($source) || $source === '') {
            return null;
        }

        $report = new Episciences_Rating_Report();
        $report->setDocid($paper->getDocid());
        $report->setRvid($paper->getRvid());
        $report->setRequest_type_id($requestTypeId);
        $report->setPath($folder);

        return $report->loadXML($source) && !empty($report->getCriteria()) ? $report : null;
    }

    /**
     * The filled grid of an answer, for the editorial committee; null when it cannot be read
     */
    public static function ofAnswer(LightAnswer $answer): ?Episciences_Rating_Report
    {
        $folder = self::folderPath($answer->getDocid(), $answer->getAid());

        if (!is_file($folder . self::REPORT_FILE)) {
            return null;
        }

        $report = new Episciences_Rating_Report();
        $report->setDocid($answer->getDocid());
        $report->setRvid($answer->getRvid());
        $report->setRequest_type_id($answer->getRequestTypeId());
        $report->setPath($folder);

        return $report->loadXML($folder . self::REPORT_FILE) ? $report : null;
    }

    /**
     * Form of the grid, without the buttons and the links of the rating page of an account (draft, completion,
     * deletion of an attached file): one button sends the answer, which stays editable until the due date
     */
    public static function form(Episciences_Rating_Report $report): ?Zend_Form
    {
        $form = Episciences_Rating_Manager::getRatingForm($report);

        if ($form === null) {
            return null;
        }

        foreach (['submitRatingForm', 'validateRating', 'noteGlobale', 'overallValue'] as $name) {
            $form->removeElement($name);
        }

        foreach ($form->getElements() as $element) {
            if ($element instanceof Zend_Form_Element_File) {
                // the current file is shown by the page; a new file replaces it
                $element->setDescription('<div class="col-sm-12">' . Episciences_Tools::buildAttachedFilesDescription() . '</div>');
            } elseif ($element instanceof Zend_Form_Element_Select) {
                // the overall note of the rating page is not shown here
                $element->setAttrib('onchange', null);
            }
        }

        $form->addElement('submit', self::SUBMIT, ['label' => 'Envoyer ma réponse', 'class' => 'btn btn-primary']);

        return $form;
    }

    /**
     * Names of the file fields of the grid: the only uploads accepted
     * @return list<string>
     */
    public static function fileFields(Episciences_Rating_Report $report): array
    {
        $fields = [];

        foreach ($report->getCriteria() ?? [] as $criterion) {
            if (!$criterion->isSeparator() && $criterion->allowsAttachment()) {
                $fields[] = 'file_' . $criterion->getId();
            }
        }

        return $fields;
    }

    /**
     * Fills the grid with the posted values and the uploaded files (already received: name by file field), then
     * writes it in the folder of the answer
     * @param array<string, mixed> $post
     * @param array<string, array{name: string}> $uploads
     */
    public static function save(Episciences_Rating_Report $report, array $post, array $uploads): bool
    {
        $folder = (string)$report->getPath();

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            return false;
        }

        // a criterion without a posted note keeps its note
        foreach ($report->getCriteria() ?? [] as $criterion) {
            if (!$criterion->isSeparator() && $criterion->allowsNote()) {
                $post['note_' . $criterion->getId()] ??= $criterion->getNote();
            }
        }

        if (!$report->populate(array_merge($post, $uploads)) || !$report->toXML()) {
            return false;
        }

        return file_put_contents($folder . self::REPORT_FILE, (string)$report->getXml(), LOCK_EX) !== false;
    }

    /**
     * Names of the files attached to the filled grid
     * @return list<string>
     */
    public static function attachments(Episciences_Rating_Report $report): array
    {
        return array_values(array_map('strval', $report->getAttachments()));
    }

    /**
     * The filled criteria, as HTML for the notification of the editors: label, chosen option, comment
     */
    public static function summaryHtml(Episciences_Rating_Report $report): string
    {
        $html = '';

        foreach ($report->getCriteria() ?? [] as $criterion) {
            if ($criterion->isSeparator() || $criterion->isEmpty()) {
                continue;
            }

            $parts = [];
            $note = $criterion->getNote();

            if ($criterion->allowsNote() && is_numeric($note)) {
                $parts[] = htmlspecialchars((string)($criterion->getOptionLabel($note) ?: $note), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($criterion->getComment()) {
                // purified when the grid was filled in
                $parts[] = (string)$criterion->getComment();
            }

            if ($criterion->hasAttachment()) {
                $parts[] = htmlspecialchars((string)$criterion->getAttachment(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $html .= '<p><strong>' . htmlspecialchars((string)$criterion->getLabel(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</strong><br>'
                . implode('<br>', $parts) . '</p>';
        }

        return $html;
    }
}
