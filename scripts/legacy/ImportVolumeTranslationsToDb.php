<?php

use League\HTMLToMarkdown\HtmlConverter;

$localopts = [];


require_once "JournalScript.php";


class ImportVolumeTranslationsToDb extends JournalScript
{
    public const TRANSLATION_FILE = 'volumes.php';
    public const VOLUME_TITLE = 'title';
    public const VOLUME_DESCRIPTION = 'description';
    public const META_NAME = 'name';
    public const META_CONTENT = 'content';
    public const META_KEY = 'metadata';


    public function __construct($localopts)
    {
        $msg = '*** Importing volumes translations form';
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

        $volumesTranslations = $this->getVolumesTranslations($rvCode);

        $sql = '';

        foreach ($volumesTranslations as $translations) {
            foreach ($translations as $vId => $languages) {

                $volumeTitles = [];
                $volumeDescriptions = [];
                $volumeMetaContents = [];
                $volumeMetaTitles = [];


                foreach ($languages as $lang => $value) {


                    if (isset($value[self::VOLUME_TITLE])) {
                        $volumeTitles[$lang] = $value[self::VOLUME_TITLE];
                    }

                    if (isset($value[self::VOLUME_DESCRIPTION])) {
                        $volumeDescriptions[$lang] = $value[self::VOLUME_DESCRIPTION];
                    }

                    if (isset($value[self::META_KEY])) {

                        foreach ($value[self::META_KEY] as $metaId => $meta) {

                            if (isset($meta[self::META_NAME])) {

                                $volumeMetaTitles[$lang] = $meta[self::META_NAME];

                            }

                            if (isset($meta[self::META_CONTENT])) {
                                $volumeMetaContents[$lang] = $meta[self::META_CONTENT];
                            }

                            if (!empty($volumeMetaTitles)) {

                                $sql .= $this->getSqlUpdateStatement(
                                    T_VOLUME_METADATAS,
                                    'titles',
                                    ['values' => $volumeMetaTitles, 'id' => $metaId]
                                );

                                unset($volumeMetaTitles);


                            }

                            if (!empty($volumeMetaContents)) {

                                $sql .= $this->getSqlUpdateStatement(
                                    T_VOLUME_METADATAS,
                                    'content',
                                    ['values' => $volumeMetaContents, 'id' => $metaId]
                                );

                                unset($volumeMetaContents);
                            }
                        }

                    }
                }

                if (!empty($volumeTitles)) {
                    $sql .= $this->getSqlUpdateStatement(
                        T_VOLUMES,
                        'titles',
                        ['values' => $volumeTitles, 'id' => $vId]
                    );

                    unset($volumeTitles);
                }


                if (!empty($volumeDescriptions)) {
                    $sql .= $this->getSqlUpdateStatement(
                        T_VOLUMES,
                        'descriptions',
                        ['values' => $volumeDescriptions, 'id' => $vId]
                    );

                    unset($volumeDescriptions);

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
    private function getVolumesTranslations(string $rvCode = null): array
    {

        $converter = new HtmlConverter();

        //By default, HTML To Markdown preserves HTML tags without Markdown equivalents, like <span> and <div>.
        //To strip HTML tags that don't have a Markdown equivalent while preserving the content inside them

        $converter->getConfig()->setOption('strip_tags', true);


        $allVolumesTranslations = [];
        $journals = !$rvCode ? Episciences_ReviewsManager::getList() : [Episciences_ReviewsManager::find($rvCode)];

        foreach ($journals as $journal) {

            $isEnabled = (int)$journal->getStatus() === 1;

            if (!$isEnabled || $journal->getCode() === 'portal') {
                
                if(!$isEnabled){
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

                $currentTranslations = Episciences_Tools::getOtherTranslations($languagesPath, self::TRANSLATION_FILE, '#volume_[\d]+_[\W]+#');

                foreach ($currentTranslations as $lang => $translations) {

                    foreach ($translations as $vKey => $translation) {

                        $translation = $converter->convert($translation);

                        if (false === preg_match('#volume_[\d]+#', $vKey, $matches)) {
                            continue;
                        }

                        $vid = (int)filter_var($matches[0], FILTER_SANITIZE_NUMBER_INT);
                        $prefix = 'volume_' . $vid . '_';

                        if (preg_match('#' . $prefix . self::VOLUME_TITLE . '#', $vKey)) {
                            $allVolumesTranslations[$rvId][$vid][$lang][self::VOLUME_TITLE] = $translation;
                        } elseif (preg_match('#' . $prefix . self::VOLUME_DESCRIPTION . '#', $vKey)) {
                            $allVolumesTranslations [$rvId][$vid][$lang][self::VOLUME_DESCRIPTION] = $translation;
                        } elseif (preg_match('#' . $prefix . 'md_#', $vKey, $mMeta)) {

                            $metaStr = mb_substr($vKey, mb_strlen($mMeta[0]));
                            $metaId = (int)filter_var($metaStr, FILTER_SANITIZE_NUMBER_INT);

                            if ($metaStr === $metaId . '_' . self::META_NAME) {
                                $allVolumesTranslations[$rvId][$vid][$lang][self::META_KEY][$metaId][self::META_NAME] = $translation;

                            } elseif ($metaStr === $metaId . '_' . self::META_CONTENT) {
                                $allVolumesTranslations[$rvId][$vid][$lang][self::META_KEY][$metaId][self::META_CONTENT] = $translation;
                            }
                        }
                    }
                }
            } else {
                $this->displayWarning(sprintf('%s not found', $languagesPath), true);
            }
        }

        return $allVolumesTranslations;
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

        $sql .= ' WHERE ';

        if ($table === T_VOLUME_METADATAS) {
            $sql .= 'ID';
        } elseif ($table === T_VOLUMES) {
            $sql .= 'VID';

        }

        $sql .= ' = ' . $options['id'] . ';';

        return $sql;
    }
}


$script = new ImportVolumeTranslationsToDb($localopts);
$script->run();







