<?php


use GuzzleHttp\Client as client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$localopts = [
    'rvid=i' => 'RVID of a journal',
    'zo=s' => 'Zip Output'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class zbjatZipper extends JournalScript
{
    public function __construct($localopts)
    {

        // missing required parameters will be asked later
        $this->setRequiredParams([]);

        $this->setArgs(array_merge($this->getArgs(), $localopts));

        parent::__construct();
    }


    public function run(): void
    {
        $dirApp = dirname(APPLICATION_PATH);
        $this->initApp();
        $this->initDb();
        $this->initTranslator();

        if ($cliRvid = $this->getParam('rvid')) {

            $review = Episciences_ReviewsManager::findByRvid($cliRvid);
            $review->loadSettings();
            /*
             * @var $volumes Episciences_volumes
             */

            $volumes = $review->getVolumesWithPapers([]);
            $ivol = 1;
            $tabvolRepoName = [];
            foreach ($volumes as $volume) {

                echo PHP_EOL."Volume ".$volume->getVid().PHP_EOL;

                /*
                 * @var $paperList Episciences_paper
                 */
                $paperList = $volume->getSortedPapersFromVolume('object');
                $iArticle = 1;
                foreach ($paperList as $paper) {

                    /*
                     * @var $paper Episciences_paper
                     */


                        $client = new client();
                        $dirnameVol = $dirApp.'/data/'.$review->getCode().'/zbjats/volume'.$ivol.'/';
                        if (!is_dir($dirnameVol) && !mkdir($dirnameVol, 0776, true) && !is_dir($dirnameVol)) {
                            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirnameVol));
                        }
                        if ($paper->isPublished()) {
                            $resourcexml = \GuzzleHttp\Psr7\Utils::tryFopen($dirnameVol . "article" . $iArticle . '.xml', 'w');
                            $client->request('GET', 'http://' . $review->getCode() . '.episciences.org' . "/" . $paper->getDocid() . "/zbjats", ['sink' => $resourcexml]);
                            echo PHP_EOL.'get Zbjats for '.$paper->getDocid().PHP_EOL;

                            $resourcepdf = \GuzzleHttp\Psr7\Utils::tryFopen($dirnameVol . "article" . $iArticle . '.pdf', 'w');
                            $client->request('GET', 'http://' . $review->getCode() . '.episciences.org' . "/" . $paper->getDocid() . "/pdf", ['sink' => $resourcepdf]);
                            echo PHP_EOL.'get PDF for '.$paper->getDocid().PHP_EOL;

                            $iArticle++;
                        }
                }
                $tabvolRepoName[] = "volume".$ivol;
                $ivol++;
            }

            $pathdir = $dirApp.'/data/'.$review->getCode().'/zbjats/';

            if ($this->getParam('zo')){
                $zipcreated = $this->getParam('zo').$review->getCode().".zip";
            }else{
                $zipcreated = "./".$review->getCode().".zip";
            }

            $zip = new ZipArchive;

            if ($zip->open($zipcreated, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                echo PHP_EOL.'ZIP ... '.PHP_EOL;

                // Store the path into the variable
                foreach ($tabvolRepoName as $value) {
                    $dir = opendir($pathdir.$value);
                    while ($file = readdir($dir)) {
                        if (is_file($pathdir.'/'.$value.'/'.$file)) {
                            $zip->addFile($pathdir.'/'.$value.'/'.$file, $value."/".$file);
                        }
                    }
                }
                $zip->close();
            }
            echo PHP_EOL.'DONE ... '.PHP_EOL;
            exit;
        } else {
            echo 'Rvid missing !'.PHP_EOL;
        }
    }
}

$script = new zbjatZipper($localopts);
$script->run();