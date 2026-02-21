<?php

declare(strict_types=1);

/**
 * HTML rendering for paper projects / funding information.
 * No DB access — single responsibility.
 */
class Episciences_Paper_Projects_ViewFormatter
{
    private const UNIDENTIFIED = Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED;

    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_MAX_DEPTH    = 512;

    private const KEY_PROJECT_TITLE     = 'projectTitle';
    private const KEY_FUNDER_NAME       = 'funderName';
    private const KEY_CODE              = 'code';
    private const KEY_CALL_ID           = 'callId';
    private const KEY_PROJECT_FINANCING = 'projectFinancing';
    private const KEY_URL               = 'url';

    /**
     * Build the HTML funding block for a paper.
     *
     * @throws JsonException
     */
    public static function formatForView(int $paperId): array
    {
        $rawInfo = Episciences_Paper_Projects_Repository::getByPaperId($paperId);
        if (empty($rawInfo)) {
            return [];
        }

        $translator = Zend_Registry::get('Zend_Translate');

        // Group by source name
        $rawFunding = [];
        foreach ($rawInfo as $value) {
            $rawFunding[$value['source_id_name']][] = json_decode(
                $value['funding'],
                true,
                self::JSON_MAX_DEPTH,
                self::JSON_DECODE_FLAGS
            );
        }

        $html = '';
        foreach ($rawFunding as $sourceName => $fundingInfo) {
            $html .= self::renderSourceBlock($sourceName, $fundingInfo, $translator);
        }

        return ['funding' => $html];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function renderSourceBlock(string $sourceName, array $rows, Zend_Translate $tr): string
    {
        $html  = "<ul class='list-unstyled'>";
        $html .= " <small class='label label-default'>"
            . $tr->translate('Source :') . ' '
            . htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8')
            . "</small>";

        foreach ($rows as $funding) {
            foreach ($funding as $vfunding) {
                $html .= self::renderFundingEntry($vfunding, $tr);
            }
        }

        $html .= "</ul>";
        return $html;
    }

    private static function renderFundingEntry(array $vfunding, Zend_Translate $tr): string
    {
        $unidentified = self::UNIDENTIFIED;
        $hasTitle     = isset($vfunding[self::KEY_PROJECT_TITLE]) && $vfunding[self::KEY_PROJECT_TITLE] !== $unidentified;
        $hasFunder    = isset($vfunding[self::KEY_FUNDER_NAME])   && $vfunding[self::KEY_FUNDER_NAME]   !== $unidentified;
        $html = '';

        // Opening <li> with optional projectTitle / funderName
        if ($hasTitle) {
            $html .= '<li><em>' . htmlspecialchars($vfunding[self::KEY_PROJECT_TITLE], ENT_QUOTES, 'UTF-8') . "</em>";
            if ($hasFunder) {
                $html .= "; " . $tr->translate("Funder") . ": "
                    . htmlspecialchars($vfunding[self::KEY_FUNDER_NAME], ENT_QUOTES, 'UTF-8');
            }
        } elseif ($hasFunder) {
            $html .= "<li>" . $tr->translate("Funder") . ": "
                . htmlspecialchars($vfunding[self::KEY_FUNDER_NAME], ENT_QUOTES, 'UTF-8');
        } else {
            return '';
        }

        // Code
        if (
            isset($vfunding[self::KEY_CODE]) && $vfunding[self::KEY_CODE] !== $unidentified
            && ($hasFunder || $hasTitle)
        ) {
            $html .= "; Code: " . htmlspecialchars($vfunding[self::KEY_CODE], ENT_QUOTES, 'UTF-8');
        }

        // callId
        if (isset($vfunding[self::KEY_CALL_ID]) && $vfunding[self::KEY_CALL_ID] !== $unidentified) {
            $html .= "; " . $tr->translate("callId") . ": "
                . htmlspecialchars($vfunding[self::KEY_CALL_ID], ENT_QUOTES, 'UTF-8');
        }

        // projectFinancing
        if (isset($vfunding[self::KEY_PROJECT_FINANCING]) && $vfunding[self::KEY_PROJECT_FINANCING] !== $unidentified) {
            $html .= "; " . $tr->translate("projectFinancing") . ": "
                . htmlspecialchars($vfunding[self::KEY_PROJECT_FINANCING], ENT_QUOTES, 'UTF-8');
        }

        // URL
        if (isset($vfunding[self::KEY_URL]) && $vfunding[self::KEY_URL] !== '') {
            $safeUrl = htmlspecialchars($vfunding[self::KEY_URL], ENT_QUOTES, 'UTF-8');
            $html .= '; <a href="' . $safeUrl . '">' . $safeUrl . '</a>';
        }

        $html .= "</li>";
        return $html;
    }
}
