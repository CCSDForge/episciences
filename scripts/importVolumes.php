<?php
/**
 * Import Volumes
 * 1st Line of CSV File:
 * status;current_issue;special_issue;bib_reference;title_en;title_fr;description_en;description_fr
 * 2nd Line of CSV File example:
 * 0;0;0;;Volume I Issue 1;;Autumn 2007;
 */

$localopts = [
    'rvid=i' => "journal id",
    'file=s' => "csv file path (for multiple imports)",
];


require_once __DIR__ . '/loadHeader.php';


session_start();
require_once "JournalScript.php";


class UpdateVolumes extends JournalScript
{

    // csv file column positions
    public const COL_POSITION = 0;
    public const COL_STATUS = 1;
    public const COL_CURRENT_ISSUE = 2;
    public const COL_SPECIAL_ISSUE = 3;
    public const COL_BIB_REFERENCE = 4;
    public const COL_TITLE_EN = 5;
    public const COL_TITLE_FR = 6;
    public const COL_DESC_EN = 7;
    public const COL_DESC_FR = 8;

    /** @var $_review Episciences_Review */
    protected $_review = null;

    public function __construct($localopts)
    {
        // missing required parameters will be asked later
        $this->setRequiredParams([]);

        $this->displayTrace('*** Updating papers', true);
        $this->setArgs(array_merge($this->getArgs(), $localopts));

        parent::__construct();


    }

    public function run()
    {

        $this->checkAppEnv();

        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        $this->checkImportMethod();
        $this->checkRvid();


        $this->_review = Episciences_ReviewsManager::find($this->getParam('rvid'));

        if (!$this->_review) {
            $this->displayError("Invalid journal ID / RVID");
        }else {
            Zend_Registry::set('reviewSettingsDoi', $this->_review->getDoiSettings());
        }

        defineJournalConstants($this->_review->getCode());

        // load review translation files
        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages')) > 2) {
            Zend_Registry::get("Zend_Translate")->addTranslation(REVIEW_PATH . 'languages');
        }


        if ($this->hasParam('file')) {
            $this->process_csv_file($this->getParam('file'));
            $this->getProgressBar()->stop();
            $this->displayInfo("Update completed. Good Bye ! =)", true);
        }


    }

    /**
     *
     */
    private function checkImportMethod(): void
    {
        // if no csv file and no paper identifier, ask to choose import method
        if (!$this->hasParam('file')) {
            $input = $this->ask("Do you want to process a CSV file ?", ['CSV file']);

            if ($input === 0) {
                $file_path = $this->ask("Please enter the file path:");
                // check file path
                while (!file_exists($file_path)) {
                    $this->displayError("Invalid file path");
                    $file_path = $this->ask("Please enter the file path: ");
                }
                // check that file is CSV
                while (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'csv') {
                    $file_path = $this->ask("This is not a CSV file. Please try again:");
                }

                $this->setParam('file', $file_path);
            }
        }
    }


    /**
     * import volumes from a CSV file
     * @param $path
     * @param null $max
     * @throws Zend_Exception
     */
    private function process_csv_file($path, $max = null)
    {
        $this->getProgressBar()->start();

        // check that this is a CSV file
        while (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv') {
            $path = $this->ask("This is not a CSV file. Please try again:");
        }
        $this->displayInfo("*** Processing CSV file");

        if (!($file = fopen($path, 'rb'))) {
            throw new Zend_Exception("File could not be opened");
        }

        // count total number of lines
        $total_lines = 0;
        while ($data = fgetcsv($file, $max, ';')) {
            $total_lines++;
        }

        $this->displayInfo($total_lines . " lines");

        $line = 0;

        $csv = fopen($path, 'rb');
        while ($data = fgetcsv($csv, $max, ';')) {

            $line++;

            // pass first line
            if (strtolower($data[0]) === 'position') {
                continue;
            }

            $titles = [];

            $colTitleEn = $this->get_col($data, static::COL_TITLE_EN);
            if ($colTitleEn !== '') {
                $titles['en'] = $colTitleEn;
            }

            $colTitleFr = $this->get_col($data, static::COL_TITLE_FR);
            if ($colTitleFr !== '') {
                $titles['fr'] = $colTitleFr;
            }

            if ($colTitleEn === '' && $colTitleFr === '') {
                $this->displayError("Skipped line: " . $line . ' NO TITLE');
                continue;
            }

            $descriptions = [];
            $colDescEn = $this->get_col($data, static::COL_DESC_EN);
            if ($colDescEn !== '') {
                $descriptions['en'] = $colDescEn;
            }

            $colDescFr = $this->get_col($data, static::COL_DESC_FR);
            if ($colDescFr !== '') {
                $descriptions['fr'] = $colDescFr;
            }

            // prepare import
            $params = [
                Episciences_Volume::SETTING_STATUS => $this->get_col($data, static::COL_STATUS),
                Episciences_Volume::SETTING_CURRENT_ISSUE => $this->get_col($data, static::COL_CURRENT_ISSUE),
                Episciences_Volume::SETTING_SPECIAL_ISSUE => $this->get_col($data, static::COL_SPECIAL_ISSUE),
                'title' => $titles
            ];

            $bib_reference = $this->get_col($data, static::COL_BIB_REFERENCE);
            if (!empty($bib_reference)) {
                $params['bib_reference'] = $bib_reference;
            }

            if (!empty($descriptions)) {
                $params['description'] = $descriptions;
            }


            $this->displayInfo("** processing line $line/$total_lines");

            try {
                $this->processSingleVolume($params);
            } catch (Zend_Exception $e) {
                $this->displayError($e->getMessage());
            }

            // set progress bar
            $progress = round(($line * 100) / $total_lines);
            $this->getProgressBar()->setProgress($progress);
            $this->displayProgressBar();
        }
    }

    /**
     * @param $data
     * @param $col
     * @return string
     */
    private function get_col($data, $col): string
    {
        $value = '';
        if (array_key_exists($col, $data) && trim($data[$col]) !== '') {
            $value = trim($data[$col]);
        }

        return $value;
    }


    /**
     * @param $params
     * @return bool
     * @throws Zend_Exception
     */
    private function processSingleVolume($params)
    {

        $this->displayInfo("** importing volume " . implode(' ; ', $params['title']), false);


        if (!$this->isDebug()) {
            $volume = new Episciences_Volume();
            if ($volume->save($params)) {
                $this->displaySuccess("Volume was successfully saved");
            } else {
                throw new Zend_Exception("SNAFU");
            }

        }

        return true;
    }

}

$script = new UpdateVolumes($localopts);
$script->run();
