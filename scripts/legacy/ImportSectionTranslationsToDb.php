<?php

use League\HTMLToMarkdown\HtmlConverter;

$localopts = [];


require_once "JournalScript.php";


class ImportSectionTranslationsToDb extends JournalScript
{
    public const TRANSLATION_FILE = 'sections.php';
    public const SECTION_TITLE = 'title';
    public const SECTION_DESCRIPTION = 'description';

    public function __construct($localopts)
    {
        $msg = '*** Importing Sections translations form';
        $msg .= " journals translation files";
        $msg .= ' to DB ***';
        // missing required parameters will be asked later
        $this->setRequiredParams([]);
        $this->displayTrace($msg, true);
        $this->setArgs(array_merge($this->getArgs(), $localopts));

        parent::__construct();

    }


    /**
     * @throws Zend_Translate_Exception
     * @throws Zend_Exception
     * @throws Zend_Locale_Exception
     */
    public function run()
    {
        defineSQLTableConstants();

        $this->initApp(false);
        $this->initDb();
        $this->initTranslator();
        $this->updatingProcess();
    }

    private function updatingProcess(): void
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $rvCode = !$this->hasParam(self::PARAM_RVCODE) ?
            null :
            $this->getParam(self::PARAM_RVCODE);

        $sectionsTranslations = $this->getSectionsTranslations($rvCode);

        $sql = '';

        foreach ($sectionsTranslations as $translations) {
            foreach ($translations as $sId => $languages) {

                $sectionTitles = [];
                $sectionDescriptions = [];

                foreach ($languages as $lang => $value) {


                    if (isset($value[self::SECTION_TITLE])) {
                        $sectionTitles[$lang] = $value[self::SECTION_TITLE];
                    }

                    if (isset($value[self::SECTION_DESCRIPTION])) {
                        $sectionDescriptions[$lang] = $value[self::SECTION_DESCRIPTION];
                    }
                }

                if (!empty($sectionTitles)) {
                    $sql .= $this->getSqlUpdateStatement(
                        T_SECTIONS,
                        'titles',
                        ['values' => $sectionTitles, 'id' => $sId]
                    );

                    unset($sectionTitles);
                }


                if (!empty($sectionDescriptions)) {
                    $sql .= $this->getSqlUpdateStatement(
                        T_SECTIONS,
                        'descriptions',
                        ['values' => $sectionDescriptions, 'id' => $sId]
                    );

                    unset($sectionDescriptions);

                }

            }
        }

        if ($this->isVerbose()) {

            $message = 'SQL UPDATE STATEMENTS > ' . $sql;
            $this->isDebug() ? $this->displayTrace($message) : $this->displaySuccess($message);
        }

        if (!$this->isDebug()) {

            $update = $db->prepare($sql);

            try {
                $update->execute();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
    }

    /**
     * @param string|null $rvCode
     * @return array
     */
    private function getSectionsTranslations(string $rvCode = null): array
    {

        $converter = new HtmlConverter();

        //By default, HTML To Markdown preserves HTML tags without Markdown equivalents, like <span> and <div>.
        //To strip HTML tags that don't have a Markdown equivalent while preserving the content inside them

        $converter->getConfig()->setOption('strip_tags', true);


        $allSectionTranslations = [];
        $journals = !$rvCode ? Episciences_ReviewsManager::getList() : [Episciences_ReviewsManager::find($rvCode)];

        foreach ($journals as $journal) {

            $isEnabled = (int)$journal->getStatus() === 1;

            if (!$isEnabled || $journal->getCode() === 'portal') {

                if (!$isEnabled) {
                    $this->displayWarning(
                        sprintf('%s ignored > status = %s', $journal->getCode(), $journal->getStatus()), true
                    );
                }

                continue;
            }

            $rvId = $journal->getRvid();

            $journalPath = APPLICATION_PATH . '/../data/' . $journal->getCode() . '/';
            $languagesPath = $journalPath . 'languages/';

            // load review translation files
            if (is_dir($languagesPath) && count(scandir($languagesPath)) > 2) {

                $currentTranslations = Episciences_Tools::getOtherTranslations($languagesPath, self::TRANSLATION_FILE, '#section_[\d]+_[\W]+#');

                foreach ($currentTranslations as $lang => $translations) {

                    foreach ($translations as $vKey => $translation) {

                        $translation = $converter->convert($translation);

                        if (false === preg_match('#section_[\d]+#', $vKey, $matches)) {
                            continue;
                        }

                        $vid = (int)filter_var($matches[0], FILTER_SANITIZE_NUMBER_INT);
                        $prefix = 'section_' . $vid . '_';

                        if (preg_match('#' . $prefix . self::SECTION_TITLE . '#', $vKey)) {
                            $allSectionTranslations[$rvId][$vid][$lang][self::SECTION_TITLE] = $translation;
                        } elseif (preg_match('#' . $prefix . self::SECTION_DESCRIPTION . '#', $vKey)) {
                            $allSectionTranslations [$rvId][$vid][$lang][self::SECTION_DESCRIPTION] = $translation;
                        }
                    }
                }
            } else {
                $this->displayWarning(sprintf('%s not found', $languagesPath), true);
            }
        }

        return $allSectionTranslations;
    }

    private function getSqlUpdateStatement(string $table, string $column, array $options = []): string
    {

        if (!isset($options['id'], $options['values'])) {
            return '';
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = 'UPDATE ';
        $sql .= $db->quoteIdentifier($table);
        $sql .= ' SET ';

        $sql .= $db->quoteIdentifier($column);
        $sql .= ' = ';

        try {
            $sql .= $db->quote(json_encode($options['values'], JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
            return '';
        }

        $sql .= ' WHERE SID =  ';
        $sql .= $options['id'];
        $sql .= ';';
        return $sql;
    }
}


$script = new ImportSectionTranslationsToDb($localopts);
$script->run();







