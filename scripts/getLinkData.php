<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;



$localopts = [
    'dry-run' => 'Work with Test API',
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getLinkData extends JournalScript
{
    public const API_URL = 'http://api.scholexplorer.openaire.eu';

    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * getDoi constructor.
     * @param $localopts
     */

    public function __construct($localopts)
    {

        // missing required parameters will be asked later
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        if ($this->getParam('dry-run')) {
            $this->setDryRun(true);
        } else {
            $this->setDryRun(false);
        }
    }

    /**
     * @return mixed|void
     * @throws JsonException
     */
    public
    function run()
    {
        $dir = dirname(APPLICATION_PATH) . '/cache/scholexplorerLinkData';

        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();
        $client = new Client();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->distinct('DOI')
            ->from('PAPERS', ['DOI', 'DOCID'])
            ->where('DOI IS NOT NULL')
            ->where('DOI != ""'); // prevent empty row

        if (!file_exists($dir)) {
            $result = mkdir($dir);
            if (!$result) {
                die('Fatal error: Failed to create directory: ' . $dir);
            }
        }

        foreach ($db->fetchAll($select) as $value) {
            $docId = $value['DOCID'];
            $doiTrim = trim($value['DOI']);
            $fileName = $dir . '/' . explode("/", $doiTrim)[1] . ".json";

            try {
                $this->displayInfo('Call Scholexplorer '. self::API_URL . '/v1/linksFromPid?pid=' . $doiTrim, true);
                $apiResult = $client->get(self::API_URL . '/v1/linksFromPid?pid=' . $doiTrim, [
                    'headers' => [
                        'User-Agent' => 'CCSD Episciences support@episciences.org',
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ]
                ])->getBody()->getContents();

            } catch (GuzzleException $e) {

                trigger_error($e->getMessage());
                continue;

            }

            $flagNew = 0;
            if (file_exists($fileName) && !empty(json_decode($apiResult, true, 512, JSON_THROW_ON_ERROR))) {
                $fileNameNew = $dir . '/' . explode("/", $doiTrim)[1] . ".new.json";
                file_put_contents($fileNameNew, $apiResult);
                if (md5_file($fileNameNew) !== md5_file($fileName)) {
                    unlink($fileName);
                    rename($fileNameNew, str_replace('.new.json', '.json', $fileNameNew));
                    $flagNew = 1;
                    $this->displayInfo('File updated for ' . $doiTrim, true);
                } else {
                    unlink($fileNameNew);
                }
            } elseif (!file_exists($fileName) && !empty(json_decode($apiResult, true, 512, JSON_THROW_ON_ERROR))) {
                file_put_contents($fileName, $apiResult);
                $flagNew = 1;
            }
            if ($flagNew === 1) {
                $getTargetId = $db->select()
                    ->distinct('DOI')
                    ->from('paper_datasets', ['id_paper_datasets_meta'])
                    ->where('doc_id IS NOT NULL')
                    ->where('source_id = ?',Episciences_Repositories::SCHOLEXPLORER_ID)
                    ->where('doc_id = ? ', $docId);
                $idToDelete = $db->fetchOne($getTargetId);
                if (is_string($idToDelete)) {
                    Episciences_Paper_DatasetsMetadataManager::deleteMetaDataAndDatasetsByIdMd($idToDelete);
                    $this->displayInfo('Old values deleted for ' . $doiTrim . 'id: ' . $idToDelete, true);
                }

            }

            if (file_exists($fileName)) {
                if ($flagNew === 1) {
                    $this->displayInfo('Search Information in File : ' . $doiTrim, true);
                    $arrayResult = json_decode(file_get_contents($fileName), true, 512, JSON_THROW_ON_ERROR);
                    $relationship = $arrayResult[0]['relationship']['name'];
                    $targetString = json_encode($arrayResult[0]['target'], JSON_THROW_ON_ERROR);
                    $lastMetatextInserted = Episciences_Paper_DatasetsMetadataManager::insert(['metatext' => $targetString]);
                    foreach ($arrayResult[0]['target']['identifiers'] as $identifier) {
                        try {
                            $enrichment = Episciences_Paper_DatasetsManager::insert([[
                                'docId' => $docId,
                                'code' => "null",
                                'name' => $identifier['schema'],
                                'value' => $identifier['identifier'],
                                'link' => $identifier['schema'],
                                'sourceId' => Episciences_Repositories::SCHOLEXPLORER_ID,
                                'relationship' => $relationship,
                                'idPaperDatasetsMeta' => $lastMetatextInserted
                            ]]);
                            if ($enrichment >= 1) {
                                $this->displayInfo('DB info inserted for ' . $doiTrim, true);
                            }
                        } catch (Exception $e) {
                            $message = 'data existing ' . $e->getMessage();

                            $this->displayInfo('[:error] ' . $message, true, static::BASH_RED);

                            continue;
                        }
                    }
                } else {
                    $this->displayInfo( 'Found and already Inserted for ' . $doiTrim, true);
                }
            } else {
                $this->displayInfo('No match: ' . $doiTrim, true);
            }
            sleep(1);
        }

        $this->displayInfo('Data Enrichment completed. Good Bye ! =)', true);

    }

    /**
     * @return bool
     */
    public
    function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public
    function setDryRun(bool $dryRun)
    {
        $this->_dryRun = $dryRun;
    }
}
$script = new getLinkData($localopts);
$script->run();
