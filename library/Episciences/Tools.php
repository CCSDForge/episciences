<?php

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\HTMLToMarkdown\HtmlConverter;

class Episciences_Tools
{

    public const DEFAULT_MKDIR_PERMISSIONS = 0770;

    /**
     * Bidirectional mapping between ISO 639-2/T and ISO 639-2/B codes
     */
    private const ISO639_BIDIRECTIONAL_MAP = [
        'alb' => 'sqi', 'sqi' => 'alb', // Albanian
        'arm' => 'hye', 'hye' => 'arm', // Armenian
        'baq' => 'eus', 'eus' => 'baq', // Basque
        'bur' => 'mya', 'mya' => 'bur', // Burmese
        'chi' => 'zho', 'zho' => 'chi', // Chinese
        'cze' => 'ces', 'ces' => 'cze', // Czech
        'dut' => 'nld', 'nld' => 'dut', // Dutch
        'fre' => 'fra', 'fra' => 'fre', // French
        'geo' => 'kat', 'kat' => 'geo', // Georgian
        'ger' => 'deu', 'deu' => 'ger', // German
        'gre' => 'ell', 'ell' => 'gre', // Greek (modern)
        'ice' => 'isl', 'isl' => 'ice', // Icelandic
        'mac' => 'mkd', 'mkd' => 'mac', // Macedonian
        'mao' => 'mri', 'mri' => 'mao', // Maori
        'may' => 'msa', 'msa' => 'may', // Malay
        'per' => 'fas', 'fas' => 'per', // Persian
        'rum' => 'ron', 'ron' => 'rum', // Romanian
        'slo' => 'slk', 'slk' => 'slo', // Slovak
        'tib' => 'bod', 'bod' => 'tib', // Tibetan
        'wel' => 'cym', 'cym' => 'wel', // Welsh
    ];
    public static $bashColors = [
        'red' => "\033[0;31m",
        'blue' => "\033[0;34m",
        'green' => "\033[0;32m",
        'cyan' => "\033[0;36m",
        'purple' => "\033[0;35m",
        'light_gray' => "\033[0;37m",
        'dark_gray' => "\033[1;30m",
        'light_blue' => "\033[1;34m",
        'light_green' => "\033[1;32m",
        'light_cyan' => "\033[1;36m",
        'light_red' => "\033[1;31m",
        'light_purple' => "\033[1;35m",
        'yellow' => "\033[1;33m",
        'bold' => "\033[1m",
        'default' => "\033[0m"
    ];

    public static $latex2utf8 = [
        //cedilla
        "\\c{c}" => 'ç',
        "\\c c" => 'ç',
        //ogonek
        "\\k{a}" => 'ą',
        "\\k a" => 'ą',
        //barred l (l with stroke)
        "\\l{}" => 'ł',
        "\\l " => 'ł',
        //dot under the letter
        "\\d{u}" => 'ụ',
        "\\d u" => 'ụ',
        //ring over the letter (for å there is also the special command \aa)
        "\\r{a}" => 'å',
        "\\r a" => 'å',
        //caron/háček ("v") over the letter
        "\\v{s}" => 'š',
        "\\v s" => 'š',
        "\\v{r}" => 'ř',
        "\\v r" => 'ř',
        // git #270 : (circumflex)
        '\\^a' => 'â',

        // a
        //acute accent
        "\\'{a}" => 'á',
        "\\'a" => 'á',
        // grave accent
        "\\`{a}" => 'à',
        "\\`a" => 'à',
        "\\u{a}" => 'ă',
        "\\u a" => 'ă',
        // a trema
        "\\\"{a}" => 'ä',
        "\\\"a" => 'ä',


        // e
        //grave accent
        "\\`{e}" => 'è',
        "\\`e" => 'è',

        //acute accent
        "\\'{e}" => 'é',
        "\\'e" => 'é',
        //circumflex
        "\\^{e}" => 'ê',
        "\\^e" => 'ê',
        //umlaut, trema or dieresis
        "\\\"{e}" => 'ë',
        "\\\"e" => 'ë',

        // i
        "\\`i" => "ì",

        // o
        //grave accent
        "\\`{o}" => 'ò',
        "\\`o" => 'ò',
        //acute accent
        "\\'{o}" => 'ó',
        "\\'o" => 'ó',
        // c with acute accent (Polish)
        "\\'{c}" => 'ć',
        "\\'c" => 'ć',
        // n with acute accent (Polish)
        "\\'{n}" => 'ń',
        "\\'n" => 'ń',
        // y with acute accent (Czech/Slovak)
        "\\'{y}" => 'ý',
        "\\'y" => 'ý',
        //circumflex
        "\\^{o}" => 'ô',
        "\\^o" => 'ô',
        //umlaut, trema or dieresis
        "\\\"{o}" => 'ö',
        "\\\"o" => 'ö',
        //long Hungarian umlaut (double acute)
        "\\H{o}" => 'ő',
        "\\H o" => 'ő',
        //tilde
        "\\~{o}" => 'õ',
        "\\~o" => 'õ',
        //macron accent (bar over the letter)
        "\\={o}" => 'ō',
        "\\=o" => 'ō',
        //bar under the letter
        "\\b{o}" => 'o',
        "\\b o" => 'o',
        //dot over the letter
        "\\.{o}" => 'ȯ',
        "\\.o" => 'ȯ',
        //breve over the letter
        "\\u{o}" => 'ŏ',
        "\\u o" => 'ŏ',
        //"tie" (inverted u) over the two letters
        "\\t{oo}" => 'o͡o',
        "\\t oo" => 'o͡o',
        //slashed o (o with stroke)
        //"\\o" => 'ø',

        // u
        //long Hungarian umlaut (double acute)
        "\\H{u}" => "ű",
        "\\H u" => "ű",
        //umlaut, trema or dieresis
        "\\\"{u}" => 'ü',
        "\\\"u" => 'ü',

    ];

    public const APPLICATION_OCTET_STREAM = 'application/octet-stream';


    // check if string is a valid sha1 (40 hexadecimal characters)
    public static function isSha1($string): bool
    {
        return (bool)preg_match('/^[0-9a-f]{40}$/i', $string);
    }

    public static function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string, true);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    // sort a multidimensional array from its keys
    public static function multi_ksort(&$arr)
    {
        ksort($arr);
        foreach ($arr as &$a) {
            if (is_array($a) && !empty($a)) {
                static::multi_ksort($a);
            }
        }
    }

    public static function multisort($array, $key)
    {
        uksort($array, static function ($a, $b) use ($key) {
            $a = $a[$key];
            $b = $b[$key];
            if ($a === $b) {
                $r = 0;
            } else {
                $r = ($a > $b) ? 1 : -1;
            }
            return $r;
        });

        return $array;
    }

    public static function filter_multiarray(&$input, $filter = '')
    {
        if (is_array($input)) {
            foreach ($input as $key => &$value) {

                if (!is_array($value) && $value === $filter) {
                    unset($input[$key]);
                }

                if (is_array($value) && count($value)) {
                    static::filter_multiarray($value);
                }

                if (is_array($value) && !count($value)) {
                    unset($input[$key]);
                }
            }
        }

        return $input;
    }

    public static function search_multiarray($array, $search, $keys = []): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sub = static::search_multiarray($value, $search, array_merge($keys, [$key]));
                if (count($sub)) {
                    return $sub;
                }
            } elseif ($value === $search) {
                return array_merge($keys, [$key]);
            }
        }

        return [];
    }

    public static function preg_array_key_exists($pattern, $array): int
    {
        $keys = array_keys($array);
        return (int)preg_grep($pattern, $keys);
    }

    /**
     * upload form files to specified path
     * @param $path : folder where the files will be stored
     * @param array $replace : if $replace is defined, delete files having the same id before upload
     * @return array

     */
    public static function uploadFiles($path, array $replace = []): array
    {
        $results = [];
        $upload = new Zend_File_Transfer_Adapter_Http();
        $files = $upload->getFileInfo();

        if (count($files) && !is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            trigger_error('Upload file failed: directory "%s" was not created', $path);
        }

        foreach ($files as $file => $info) {

            if (!$info['error'] && $info['size']) {

                // delete previous file version (if there is one)
                if (!empty($replace) && array_key_exists($file, $replace) && file_exists($path . $replace[$file])) {
                    unlink($path . $replace[$file]);
                }

                $filename = Ccsd_Tools::cleanFileName($info['name']);
                //rename file
                if (array_key_exists('file_unique_id', $replace) && !empty($replace['file_unique_id'])) {
                    $explode = explode('.', $filename);
                    if (!empty($explode[count($explode) - 1])) {
                        $filename = $replace['file_unique_id'] . '_' . $file . '.' . $explode[count($explode) - 1];
                    }

                }
                $filename = self::filenameRotate($path, $filename);
                // save file
                try {
                    $upload->addFilter('Rename', $path . $filename, $file);
                    $results[$file]['name'] = $filename;
                    $results[$file]['errors'] = (!$upload->receive($file)) ? $upload->getMessages() : null;
                } catch (Zend_File_Transfer_Exception $e) {
                    trigger_error($e->getMessage());
                }
            }
        }
        return $results;
    }

    public static function getLastQuery()
    {
        $profiler = Zend_Db_Table::getDefaultAdapter()->getProfiler();
        if ($profiler->getTotalNumQueries()) {
            $lastQueryProfile = $profiler->getLastQueryProfile();
            $lastQuery = $lastQueryProfile->getQuery();
            $lastQueryParams = $lastQueryProfile->getQueryParams();
            foreach ($lastQueryParams as $param) {
                $lastQuery = substr_replace($lastQuery, "'" . $param . "'", strpos($lastQuery, '?'), 1);
            }
            return ($lastQuery === '') ? "---" : $lastQuery;
        }
        return false;
    }


    public static function getLocale(): ?string
    {
        try {
            return Zend_Registry::get("Zend_Translate")->getLocale();
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }

        return null;
    }

    /**
     * @return array
     * @throws Zend_Exception
     */
    public static function getLanguages(): array
    {
        $languages = (Zend_Registry::isRegistered('languages')) ? Zend_Registry::get('languages') : [];
        return array_intersect_key(Ccsd_Locale::getLanguage(), array_flip($languages));
    }

    /**
     * @return array
     * @throws Zend_Exception
     */
    public static function getRequiredLanguages(): array
    {
        return array_keys(static::getLanguages());
    }

    /**
     * @param $code
     * @param null $locale
     * @return bool
     * @throws Zend_Exception
     */
    public static function getLanguageLabel($code, $locale = null): bool
    {
        if (!isset($locale)) {
            $locale = Zend_Registry::get('lang');
        }

        $languages = Zend_Locale::getTranslationList('language', $locale);

        if (array_key_exists($code, $languages)) {
            return $languages[$code];
        }

        return false;
    }

    /**
     * @param $languages
     * @param null $locale
     * @return array|bool
     * @throws Zend_Exception
     */
    public static function sortLanguages($languages, $locale = null)
    {
        if (empty($languages)) {
            return false;
        }

        if (!isset($locale)) {
            $locale = Zend_Registry::get('lang');
        }

        $translated = [];
        foreach ($languages as $code) {
            $translated[$code] = static::getLanguageLabel($code, $locale);
        }
        asort($translated);

        return array_keys($translated);
    }

    /**
     * @param $translations
     * @param $path
     * @param $file
     * @return bool|false|int
     */
    public static function writeTranslations($translations, $path, $file)
    {
        if (!is_dir($path)) {
            return false;
        }

        static::multi_ksort($translations);
        $langs = array_keys($translations);

        $totalBytesWritten = 0;
        foreach ($langs as $lang) {

            // Fix temporaire pour éviter de perdre les traductions en cas de bug sur les langues envoyées
            if (is_numeric($lang)) {
                continue;
            }

            if (!is_dir($path . $lang) && !mkdir($concurrentDirectory = $path . $lang) && !is_dir($concurrentDirectory)) {
                trigger_error('Write translation failed: directory "%s" was not created', $concurrentDirectory);
            }

            $filePath = $path . $lang . '/' . $file;
            $result = '<?php' . PHP_EOL . 'return ' . var_export($translations[$lang], true) . ';';

            $target = fopen($filePath, 'wb');
            $bytesWritten = fwrite($target, $result);
            $totalBytesWritten += $bytesWritten;
            fclose($target);
        }

        return $totalBytesWritten;
    }

    public static function addTranslations($newTranslations, $path, $file)
    {
        $tmp = static::getTranslations($path, $file);

        foreach ($newTranslations as $lang => $translations) {
            $tmp[$lang] += $translations;
        }

        static::writeTranslations($tmp, $path, $file);
    }

    /**
     * Lit le contenu d'un fichier de traduction
     * Peut filtrer le résultat par langue et par expression régulière
     * @param $file
     * @param null $lang
     * @param bool $pattern
     * @return array
     */
    public static function readTranslation($file, $lang = null, $pattern = false): array
    {
        $res = [];

        if (is_file($file)) {

            try {
                /** @var Zend_Translate_Adapter $translation */
                $translation = new Zend_Translate('array', $file, $lang, ['disableNotices' => true]);
            } catch (Zend_Translate_Exception $e) {
                return $res;
            }

            $list = $translation->getList();

            if (is_array($list)) {

                if ($lang !== null && in_array($lang, $list, true)) {
                    foreach ($translation->getMessages($lang) as $key => $value) {
                        if (!$pattern || preg_match($pattern, $key)) {
                            $res[$key] = $value;
                        }
                    }
                } else if (count($list)) {
                    foreach ($translation->getList() as $l) {
                        foreach ($translation->getMessages($l) as $key => $value) {
                            if (!$pattern || preg_match($pattern, $key)) {
                                $res[$key][$l] = $value;
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    /**
     * Charge les traductions du dossier passé en paramètre
     * Si un fichier est passé en paramètre, on ne charge que celui-ci
     * Sinon, on charge tous les fichiers contenus dans le dossier
     * @param $path
     * @param null $file
     * @return mixed
     * @throws Zend_Exception
     */
    public static function loadTranslations($path, $file = null)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $locale = $translator->getLocale();

        if (is_dir($path)) {
            $langDir = opendir($path);
            while ($lang = readdir($langDir)) {
                // loop through all language folders
                if ($lang !== '.' && $lang !== '..' && $lang !== '.svn' && is_dir($path . $lang)) {
                    $langs[] = $lang;

                    // only fetch translations for specified file
                    if ($file) {
                        if (file_exists($path . $lang . '/' . $file)) {
                            $translator->addTranslation($path . $lang . '/' . $file, $lang);
                        }
                    } // load all translations
                    else {
                        $dir = opendir($path . $lang);
                        while ($file = readdir($dir)) {
                            if ($file !== '.' && $file !== '..' && !is_dir($path . $lang . '/' . $file)) {
                                $translator->addTranslation($path . $lang . '/' . $file, $lang);
                            }
                        }
                    }
                }
            }
        }

        $translator->setLocale($locale);
        return $translator;
    }

    /** return requested translations, in every language
     * @param $path
     * @param null $file
     * @param bool $pattern
     * @return array
     */
    public static function getTranslations($path, $file = null, $pattern = false): array
    {
        $translations = [];
        if (is_dir($path)) {
            $langDir = opendir($path);
            while ($lang = readdir($langDir)) {
                // loop through all language folders
                if ($lang !== '.' && $lang !== '..' && is_dir($path . $lang)) {

                    // only fetch translations for specified file
                    if ($file) {
                        if (file_exists($path . $lang . '/' . $file)) {
                            $translations[$lang] = self::readTranslation($path . $lang . '/' . $file, $lang, $pattern);
                        }
                    } // fetch translations for all files
                    else {
                        $dir = opendir($path . $lang);
                        $tmp = [];
                        while ($file = readdir($dir)) {
                            if ($file !== '.' && $file !== '..' && !is_dir($path . $lang . '/' . $file)) {
                                $tmp += static::readTranslation($path . $lang . '/' . $file, $lang, $pattern);
                            }
                        }
                        $translations[$lang] = $tmp;
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Renvoie les traductions qui ne correspondent pas au pattern passé en paramètre
     * Peut scanner tous les fichiers du répertoire, ou chercher celui passé en paramètre
     * @param $path
     * @param $file
     * @param $pattern
     * @return array
     */
    public static function getOtherTranslations($path, $file, $pattern): array
    {

        if (!empty($file) && !preg_match('#(.*).php#', $file)) {
            $file .= '.php';
        }

        $translations = static::getTranslations($path, $file);
        // Filtre les traductions en fonction du pattern
        if (!empty($translations)) {
            foreach ($translations as $lang => $currentTranslations) {
                self::processTranslations($currentTranslations, $lang, $file, $pattern);
            }
        }

        return $translations;
    }

    public static function extension($filename): string
    {
        if (is_file($filename)) {
            $path_info = pathinfo($filename);
            $ext = Ccsd_Tools::ifsetor($path_info['extension'], '');
        } else {
            $ext = substr($filename, (strrpos($filename, '.') + 1));
        }
        return strtolower($ext);
    }


    /**
     * Retourne le type MIME d'une ressource
     * @param string $filename
     * @return string $mime
     */
    public static function getMimeType($filename): string
    {
        if (!is_readable($filename)) {
            trigger_error(sprintf("Unable to read file: %s", $filename), E_USER_WARNING);
            return '';
        }
        $finfo = new finfo(FILEINFO_MIME);
        $mime = $finfo->file($filename);
        if (str_contains($mime, 'zip')) {
            return static::getMimeFileZip($filename);
        }

        if (str_contains($mime, 'htm')) {
            $mime = 'application/octet-stream';
        }
        return $mime;
    }

    public static function getMimeFileZip($filename): string
    {
        $ext = static::extension($filename);
        $mime = null;

        if (in_array($ext, ['odt', 'ott', 'odp', 'otp', 'ods', 'ots', 'sxw'])) {
            $mime = "application/opendocument";
        } elseif (in_array($ext, ['pptx', 'ppsx'])) {
            $mime = "application/vnd.ms-powerpoint";
        } elseif (in_array($ext, ['docx', 'dotx'])) {
            $mime = "application/msword";
        } elseif ($ext === 'xlsx') {
            $mime = "application/vnd.ms-excel";
        } else {
            $mime = "application/zip";
        }

        return $mime;
    }

    /**
     * retourne un nouveau nom de fichier si le nom fourni existe déjà dans le répertoire donné en paramètre
     * @param $dir
     * @param $filename
     * @return string|null (NULL en cas d'erreur)
     */
    public static function filenameRotate($dir, $filename)
    {
        while (is_file($dir . $filename)) {
            $filename = preg_replace_callback('/(?>_(\d*))?(\.\w*)?$/', static function ($matches) {
                $i = (isset($matches[1])) ? '_' . ((int)$matches[1] + 1) : '';
                $ext = $matches[2] ?? '';

                return $i . $ext;
            }, $filename);
        }
        return $filename;
    }


    public static function startTimer()
    {
        return microtime(true);
    }

    public static function getTimer($timer, $msg = null, $display = true)
    {
        $time = microtime(true) - $timer;

        $result = "<div>";
        $result .= ($msg) ? $msg . ' : ' : "running time: ";
        $result .= number_format($time, 3);
        $result .= "s</div>";

        if ($display) {
            echo $result;
        }

        return $result;
    }

    /**
     * Curl sur le serveur de requêtes de solr
     *
     * @param string $queryString
     *            requête du type q=docid:19
     * @param string $core
     *            par défaut hal
     * @param string $handler
     *            par défaut select
     * @param boolean $addDefaultFilters
     *            par défaut false
     * @return mixed string boolean du GET ou curl_error()
     * @throws Exception
     * @see /library/Ccsd/Search/Solr/configs/endpoints.ini
     */
    public static function solrCurl(string $queryString, string $core = 'episciences', string $handler = 'select', bool $addDefaultFilters = false)
    {
        if ($addDefaultFilters) {
            //Ajout des filtres par defaut de l'environnement
            $queryString .= Episciences_Search_Solr_Search::getDefaultFiltersAsURL(Episciences_Settings::getConfigFile('solr.episciences.defaultFilters.json'));
        }
        return Ccsd_Tools::solrCurl($queryString, $core, $handler);
    }

    /**
     * @param string $xml_string
     * @param string $path
     * @param bool $force_array
     * @param bool $overwriteLanguageValues
     * @return array|bool|mixed
     */
    public static function xpath($xml_string, $path, $force_array = false, $overwriteLanguageValues = true)
    {
        if (!$xml_string || !$path) {
            return false;
        }

        $out = [];
        $xml = new DOMDocument();

        set_error_handler('\Ccsd\Xml\Exception::HandleXmlError');
        $xml->loadXML($xml_string);
        restore_error_handler();
        $xpath = new DOMXPath($xml);
        foreach (Ccsd_Tools::getNamespaces($xml->documentElement) as $id => $ns) {
            $xpath->registerNamespace($id, $ns);
        }

        foreach ($xpath->query($path) as $entry) {
            $lang = $entry->getAttribute('xml:lang');
            if ($lang) {
                if (!$overwriteLanguageValues) {
                    // eg multiple keywords with the same language
                    $out[][$lang] = $entry->nodeValue;
                } else {
                    $out[$lang] = $entry->nodeValue;
                }
            } else {
                $out[] = $entry->nodeValue;
            }
        }

        if ($force_array) {
            return $out;
        }

        switch (count($out)) {
            case 0:
                return false;
            case 1:
                return array_shift($out);
            default:
                return $out;
        }

    }

    public static function getTitleFromIndexedPaper($doc, $locale)
    {
        if (array_key_exists($locale . '_paper_title_t', $doc)) {
            $title = $doc[$locale . '_paper_title_t'];
        } elseif (array_key_exists('language_s', $doc) && array_key_exists($doc['language_s'] . '_paper_title_t', $doc)) {
            $title = $doc[$doc['language_s'] . '_paper_title_t'];
        } elseif (is_array($doc['paper_title_t'])) {
            $title = $doc['paper_title_t'][0];
        } else {
            $title = $doc['paper_title_t'];
        }


        return static::decodeLatex($title);
    }

    /**
     * @param $doc
     * @param $locale
     * @return array|string|string[]
     */
    public static function getAbstractFromIndexedPaper($doc, $locale)
    {
        $abstractInLocale = $locale . '_abstract_t';

        if (array_key_exists('language_s', $doc)) {
            $documentLocale = $doc['language_s'];
            $abstractInDocumentLocale = $documentLocale . '_abstract_t';
        } else {
            $documentLocale = '';
            $abstractInDocumentLocale = '';
        }

        if (array_key_exists('abstract_t', $doc)) {
            $documentAbstract = $doc['abstract_t'];
        } else {
            $documentAbstract = '';
        }

        if (array_key_exists($abstractInLocale, $doc)) {
            $abstract = $doc[$abstractInLocale];
        } elseif ($abstractInDocumentLocale !== '' && array_key_exists($abstractInDocumentLocale, $doc)) {
            $abstract = $doc[$abstractInDocumentLocale];
        } elseif ($documentAbstract !== '') {
            if (is_array($documentAbstract)) {
                $abstract = $documentAbstract[0];
            } else {
                $abstract = $documentAbstract;
            }

        } else {
            return ''; // mission failed
        }
        return static::decodeLatex($abstract);
    }

    public static function getSectionFromIndexedPaper($doc, $locale)
    {
        if (array_key_exists($locale . '_section_title_t', $doc)) {
            $abstract = $doc[$locale . '_section_title_t'];

        } elseif (isset($doc['language_s']) && array_key_exists($doc['language_s'] . '_section_title_t', $doc)) {
            $abstract = $doc[$doc['language_s'] . '_section_title_t'];

        } elseif (array_key_exists('section_title_t', $doc)) {

            if (is_array($doc['section_title_t'])) {
                $abstract = $doc['section_title_t'][0];
            } else {
                $abstract = $doc['section_title_t'];
            }

        } else {
            $abstract = null;
        }

        return $abstract;
    }

    public static function addDateInterval($date, $interval, $format = 'Y-m-d')
    {
        $result = date_create($date);
        date_add($result, date_interval_create_from_date_string($interval));
        return date_format($result, $format);
    }

    public static function isValidDate($date, $format): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public static function isValidSQLDate($datestring): bool
    {
        return static::isValidDate($datestring, 'Y-m-d');
    }

    public static function isValidSQLDateTime($datestring): bool
    {
        return static::isValidDate($datestring, 'Y-m-d H:i:s');
    }


    public static function getValidSQLDate($datestring)
    {
        $result = null;
        if (static::isValidSQLDate($datestring)) {
            $result = $datestring;
        } elseif (static::isValidDate($datestring, 'Y-m')) {
            $result = $datestring . '-01';
        } elseif (static::isValidDate($datestring, 'Y')) {
            $result = $datestring . '-01-01';
        }
        return $result;
    }

    /**
     * @param $datestring
     * @return mixed|string|null
     */
    public static function getValidSQLDateTime($datestring)
    {
        $result = null;
        if (static::isValidSQLDateTime($datestring)) {
            $result = $datestring;
        } elseif ($datestring = static::getValidSQLDate($datestring)) {
            $result = $datestring . ' 00:00:00';
        }
        return $result;
    }

    public static function formatUser($firstname = "", $lastname = "", $civ = ""): string
    {

        $name = (($civ && is_string($civ)) ? $civ . " " : "");
        $name .= (($firstname && is_string($firstname)) ? ucfirst(mb_strtolower($firstname, 'UTF-8')) . " " : "");
        $name .= (($lastname && is_string($lastname)) ? ucfirst(mb_strtolower($lastname, 'UTF-8')) : "");

        return trim($name);
    }

    public static function decodeLatex($string, $preserveLineBreaks = false): string
    {
        $result = str_replace(array_keys(static::$latex2utf8), array_values(static::$latex2utf8), $string);

        if ($preserveLineBreaks) {
            // First handle double line breaks (clear paragraph breaks)
            $result = preg_replace('/\n\s*\n/', '<br /><br />', $result);

            // Then handle single line breaks more intelligently:
            // Convert single line breaks to <br> EXCEPT when they appear to be text wrapping

            // Text wrapping patterns (convert to spaces):
            // 1. Line breaks after short words (articles, prepositions, conjunctions)
            $result = preg_replace('/(\b\w{1,3})\s*\n/', '$1 ', $result);
            // 2. Line breaks in the middle of sentences (not after punctuation)
            $result = preg_replace('/([^.!?:;])\s*\n(?!\s*[-*•])/', '$1 ', $result);

            // Convert remaining single line breaks to <br> (intentional paragraph breaks)
            $result = preg_replace('/\n/', '<br />', $result);
        }

        return self::decodeAmpersand($result);
    }

    /**
     * Check if a language code represents a right-to-left language
     *
     * @param string|null $langCode Language code (e.g., 'ar', 'he', 'fa')
     * @return bool True if the language is RTL, false otherwise
     */
    public static function isRtlLanguage(?string $langCode): bool
    {
        if (empty($langCode)) {
            return false;
        }

        $rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps', 'syr', 'dv', 'ku', 'yi', 'arc'];
        return in_array(strtolower(trim($langCode)), $rtlLanguages, true);
    }

    // check if an url begins with http:// or https://. if not, add http at the beginning of the string.
    public static function checkUrl($url): string
    {
        if (!preg_match("#^http(s*)://#", $url)) {
            $url = 'http://' . $url;
        }
        return $url;
    }

    /**
     * recursively delete a folder and its content
     * @param $directory : directory to be deleted
     * @return bool: true on success or false on failure
     */
    public static function deleteDir($directory): bool
    {
        if (!file_exists($directory)) {
            return false;
        }

        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                static::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        return rmdir($directory);
    }

    /**
     * return a translation from a set of translations, given a set of preferred lang
     * @param $translations
     * @param $preferred
     * @param bool $forceResult
     * @return string
     */
    public static function getTranslation($translations, $preferred, $forceResult = true)
    {
        $result = null;

        if (!is_array($translations)) {
            $translations = [$translations];
        }
        if (!is_array($preferred)) {
            $preferred = [$preferred];
        }

        foreach ($preferred as $lang) {
            if (array_key_exists($lang, $translations)) {
                $result = $translations[$lang];
                break;
            }
        }

        if (!$result && $forceResult) {
            $result = array_shift($translations);
        }

        return $result;
    }

    /**
     * Spécifie l'en-tête HTTP string lors de l'envoi des fichiers HTML
     * @param string $str
     * @param int $responseCode
     * @param bool $replace
     */

    public static function header(string $str, int $responseCode = 0, bool $replace = true): void
    {
        header($str, $replace, $responseCode);
    }

    /***
     * Analyse variable pour trouver l'expression regEx et met les résultats dans matches
     * Pour preg_mach_all : http://php.net/manual/fr/function.preg-match-all.php
     * @param string $regEx
     * @param string $variable
     * @return array
     */
    public static function extractPattern(string $regEx, string $variable): array
    {

        if (!isset($regEx, $variable) || !is_string($regEx) || !is_string($variable)) {
            return [];
        }

        if (!preg_match_all($regEx, $variable, $matches)) {
            return [];
        }

        return $matches[0];
    }

    /**
     * Retourne les données pour un Datatable :
     * La source de données principale utilisée pour un DataTable doit toujours être un tableau
     * Chaque élément de ce tableau définira une ligne à afficher.
     * N.B . Il est fortement recommandé, pour des raisons de sécurité, de convertir le paramètre "draw" en entier,
     *       plutôt que de simplement rappeler au client ce qu'il a envoyé dans le paramètre draw,
     *       afin d'éviter les attaques XSS (Cross Site Scripting)
     * @param string $tbody
     * @param int $draw : Le compteur de dessin.
     * @param int $recordsTotal : Nombre total d'enregistrements avant filtrage
     * @param int $recordsFiltred : Nombre total d'enregistrements, après filtrage
     * @return false|string
     */
    public static function getDataTableData(string $tbody = '', int $draw = 1, int $recordsTotal = 0, int $recordsFiltred = 0)
    {
        // Les données à afficher dans la table
        /** @var string[] $data */
        $data = [];

        if ($tbody !== '') {
            /** @var string $tbody */
            $tbody = Ccsd_Tools::spaces2Space(preg_replace("/\\t(\\r)?/i", " ", $tbody));
            $tbody = mb_convert_encoding($tbody, 'UTF-8');

            $matches_tr = self::extractPattern('#<tr[^>]*>(.*?)</tr>#is', $tbody);

            foreach ($matches_tr as $td) {
                $data[] = self::extractPattern('#<td[^>]*>(.*?)</td>#is', $td);
            }
        }

        return json_encode(
            [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltred,
                'data' => $data
            ]
        );

    }

    /**
     * Retourne les colonnes sur lesquelles l'ordre est exécuté et le sens de l'ordre.
     * @param array $requestOrder
     * @param array $columns
     * @return array
     */
    public static function dataTableOrder(array $requestOrder = [], array $columns = []): array
    {
        $order = [];
        if (!empty($columns)) {
            foreach ($requestOrder as $columnOrder) {
                $column = $columnOrder['column'];
                $direction = $columnOrder['dir'];
                if (!empty($columns[$column])) {
                    if (is_array($columns[$column])) {
                        foreach ($columns[$column] as $value) {
                            $order[] = $value . ' ' . $direction;
                        }
                    } else {
                        $order[] = $columns[$column] . ' ' . $direction;
                    }
                }
            }
        }
        return $order;
    }

    /**
     * Normalize text for HTML display by converting tabs and 4+ spaces to non‑breaking spaces and preserving line breaks.
     */
    public static function formatText(?string $text = null): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        $formatted = str_replace("\t", $htmlTab, $text);
        $formatted = preg_replace('/ {4,}/', $htmlTab, $formatted);

        return nl2br($formatted);
    }


    /**
     * Compare les deux tableaux + Calcule l'intersection et la différence entre eux
     * @param array $tab1
     * @param array $tab2
     * @return array
     */
    public static function checkArrayEquality(array $tab1, array $tab2): array
    {

        $result = ['equality' => false, 'arrayDiff' => [], 'arrayIntersect' => []];

        $arrayIntersectTab1 = array_intersect($tab1, $tab2);
        $arrayIntersectTab2 = array_intersect($tab2, $tab1);

        if ($tab1 === $arrayIntersectTab1 && $tab2 === $arrayIntersectTab2) { //$tab1 === $tab2
            $result['equality'] = true;
        }

        $result['arrayDiff'][0] = array_diff($tab1, $arrayIntersectTab1);
        $result['arrayDiff'][1] = array_diff($tab2, $arrayIntersectTab2);
        $result['arrayIntersect'] = $arrayIntersectTab1;

        return $result;
    }

    /**
     * @param array $filesList
     * @param string $source
     * @param string $dest
     * @param bool $storeDestinationPathInSession
     * @return bool
     */
    public static function cpFiles(
        array  $filesList,
        string $source,
        string $dest,
        bool   $storeDestinationPathInSession = false
    ): bool
    {

        $nbFilesNotCopied = 0;

        self::recursiveMkdir($dest);

        if ($storeDestinationPathInSession) {
            Episciences_Auth::setCurrentAttachmentsPathInSession($dest);
        }


        foreach ($filesList as $file) {
            if (!copy($source . $file, $dest . $file)) {
                trigger_error('FAILED_TO_COPY_FILE_ERROR: ' . $file . '( SOURCE: ' . $source . ', DESTINATION: ' . $dest . ' )');
                $nbFilesNotCopied++;
            }
        }

        return $nbFilesNotCopied !== count($filesList); // return true if all files were copied

    }

    /**
     * Enlève les traces d'un login
     * @param string $body
     * @return string|string[]|null
     */
    public static function cleanBody(string $body = '')
    {
        return preg_replace('#<span class="username">(.*)<\/span>#', '', $body);
    }

    /**
     * @param array $arr
     * @return int|string|null
     * @deprecated use php 7.4 native function array_key_first
     */
    public static function epi_array_key_first(array $arr)
    {
        if (function_exists('array_key_first')) {
            return array_key_first($arr);
        }

        foreach ($arr as $key => $unused) {
            return $key;
        }

        return NULL;
    }

    /**
     * formats the file sizes
     * @param $bytes
     * @param int $precision
     * @return string
     */
    public static function toHumanReadable($bytes, $precision = 2): string
    {
        if ($bytes === 0) {
            return "0.00 B";
        }

        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $e = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $e), $precision) . ' ' . $unit[$e];
    }

    /**
     * build attached files description
     * @param array|string[] $extensions
     * @param string $additionalDescription : additional description
     * @param bool $forceAdditionalTranslation [true: translate also $additionalDescription]
     * @return string
     */
    public static function buildAttachedFilesDescription(array $extensions = ALLOWED_EXTENSIONS, string $additionalDescription = '', bool $forceAdditionalTranslation = false): string
    {

        $implode_extensions = implode(', ', $extensions);

        try {
            $translator = Zend_Registry::get('Zend_Translate');
            $additionalDescription = $forceAdditionalTranslation ? $translator->translate($additionalDescription) : $additionalDescription;
            $description = $translator->translate('Extensions autorisées : ') . $implode_extensions . $additionalDescription;
            $description .= '<br>';
            $description .= $translator->translate('Taille maximale des fichiers que vous pouvez télécharger');
            $description .= $translator->translate(' :');
            return ($description . ' <strong>' . self::toHumanReadable(MAX_FILE_SIZE) . '</strong>');

        } catch (Zend_Exception $e) {
            trigger_error('ZEND_TRANSLATE_EXCEPTION: ' . $e->getMessage());
        }
        return $additionalDescription;
    }

    /**
     * @param string $mail
     * @return array
     */
    public static function postMailValidation(string $mail)
    {
        $result = [];
        $tmp = html_entity_decode(trim($mail));
        $tmp = explode(' ', $tmp);
        $email = array_pop($tmp);
        $result['email'] = str_replace(['<', '>'], '', $email);
        $result['name'] = (!empty($tmp)) ? implode(' ', $tmp) : null;
        return $result;
    }

    /**
     *  Supprime les éléments (chaines vides) d'un tableau
     * @param array $attachments
     * @return array
     */
    public static function arrayFilterEmptyValues(array $attachments): array
    {
        return array_filter($attachments, static function ($value) {
            return '' !== $value;
        });
    }

    /**
     * Construit les liens vers les fichiers joints à une réponse avec une version temporaire
     * @param array $domElements
     * @return string
     */
    public static function buildHtmlTmpDocUrls(array $domElements): string
    {
        $text = '';
        /** @var DOMElement $firstElement */
        $firstElement = $domElements[0];
        try {
            $paper = Episciences_PapersManager::get($firstElement->nodeValue, false);

        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error('(Zend_Db_Statement_Exception: ' . $e->getMessage());
            return $text;
        }

        if (!$paper->isTmp()) { //  reverification
            return $text;
        }

        try {
            $translator = Zend_Registry::get('Zend_Translate');
            $fileExp = $translator->translate('Fichier');
        } catch (Zend_Exception $e) {
            $fileExp = 'Fichier';
            trigger_error('Expression "%s" was not translated', $fileExp . ': ' . $e->getMessage());
        }

        $paperId = (string)$paper->getPaperid();

        $identifier = $paper->getIdentifier();
        // Extract file(s) name
        $subStr = substr($identifier, (strlen($paperId) + 1));

        try {
            $result = !self::isJson($subStr) ? (array)$subStr : json_decode($subStr, true, 512, JSON_THROW_ON_ERROR);
            $result = self::arrayFilterEmptyValues($result);
        } catch (JsonException $e) {
            $result = [];
            trigger_error($e->getMessage());
        }

        if (empty($result)) {
            trigger_error('No file(s) attached to the tmp version (docId = ' . $paper->getDocid() . "): the upload of the file(s) failed when responding to a revision request !");
            return $text;
        }

        $cHref = '/tmp_files/' . $paperId . '/';

        foreach ($result as $index => $fileName) {
            $href = $cHref . $fileName;
            $text .= '<a target="_blank" href="' . $href . '">';
            $text .= $fileExp . ' ' . ($index + 1) . ' > ' . $fileName;
            $text .= '</a>';
            $text .= '</br>';
        }

        return $text;
    }

    /**
     * Convert to bytes (from human readable size)
     * @param string $humanReadableVal
     * @return int
     * @throws Exception
     */
    public static function convertToBytes(string $humanReadableVal): int
    {
        $availableUnits = ['b', 'k', 'm', 'g', 't', 'p', 'e'];

        $humanReadableVal = trim($humanReadableVal);
        $unit = ($humanReadableVal !== '') ? strtolower($humanReadableVal[strlen($humanReadableVal) - 1]) : 'b';
        $val = (int)$humanReadableVal;

        if (!in_array($unit, $availableUnits, true)) {
            throw new Exception('Conversion from { ' . $unit . ' } to { bytes } is not available.');
        }

        switch ($unit) {
            case 'e' :
                $val *= 1024;
            case 'p' :
                $val *= 1024;
            case 't' :
                $val *= 1024;
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
            case 'b':
        }
        return $val;
    }

    public static function convertToCamelCase(string $string, string $separator = '_', bool $capitalizeFirstCharacter = false, string $stringToRemove = '')
    {

        if ($stringToRemove !== '') {
            $string = str_replace($stringToRemove, '', $string);
        }

        if (self::isInUppercase($string, $separator)) {
            $string = strtolower($string);
        }

        $str = str_replace($separator, '', ucwords($string, $separator));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }


        return $str;
    }

    /**
     * @param string $authorString
     * @param bool $protectLatex
     * @return string
     */
    public static function reformatOaiDcAuthor(string $authorString, bool $protectLatex = false): string
    {
        $fistname = '';
        $lastname = '';

        $authAsArray = explode(',', $authorString);

        if (!empty($authAsArray[1])) {
            $fistname = trim($authAsArray[1]);
        }

        if (!empty($authAsArray[0])) {
            $lastname = trim($authAsArray[0]);
        }

        if ($protectLatex) {
            $fistname = Ccsd_Tools::protectLatex($fistname);
            $lastname = Ccsd_Tools::protectLatex($lastname);
        }

        return sprintf("%s %s", $fistname, $lastname);
    }

    /**
     * Reset mb_internal_encoding to server selection
     * @see https://developer.wordpress.org/reference/functions/mbstring_binary_safe_encoding/
     */
    public static function resetMbstringEncoding()
    {
        self::mbstringBinarySafeEncoding(true);
    }

    /**
     * Set mb_internal_encoding to safe encoding for curl
     * mbstring.func_overload is enabled and body length is calculated incorrectly.
     * @see https://developer.wordpress.org/reference/functions/mbstring_binary_safe_encoding/
     * @param false $reset
     */
    public static function mbstringBinarySafeEncoding($reset = false)
    {
        static $encodings = [];
        static $overloaded = null;

        if (is_null($overloaded)) {
            $overloaded = function_exists('mb_internal_encoding') && (ini_get('mbstring.func_overload') & 2);
        }

        if (false === $overloaded) {
            return;
        }

        if (!$reset) {
            $encoding = mb_internal_encoding();
            $encodings[] = $encoding;
            mb_internal_encoding('ISO-8859-1');
        }

        if ($reset && $encodings) {
            $encoding = array_pop($encodings);
            mb_internal_encoding($encoding);
        }
    }

    /**
     *
     * Check if string is a valid ROR identifier (Research Organization Registry)
     * @param $string
     * @return bool
     */
    public static function isRorIdentifier($string): bool
    {
        $pattern = '/^((https?:\/\/)?ror\.org\/)?([A-Za-z0-9]){9}$/i';
        return (bool)preg_match($pattern, $string);
    }


    /**
     * @param string $url
     * @param array $options
     * @return false|array|string
     * @throws GuzzleException
     */

    public static function callApi(string $url, array $options = [])
    {

        $result = false;

        $jsonMimeType = 'application/json';

        if (empty($options)) {
            $options['headers'] = ['Content-type' => $jsonMimeType];
        }

        $client = new Client($options);

        try {
            $response = $client->get($url);

            if (isset($options['headers']['Content-type']) && $options['headers']['Content-type'] === $jsonMimeType) {

                try {
                    $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    trigger_error($e->getMessage());
                }

            } else {
                $result = $response->getBody()->getContents();
            }


        } catch (GuzzleException $e) {

            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return $result;

    }

    /**
     * @param array|null $input
     * @param int $type
     * @param int $options
     * @return array|null
     */

    public static function arrayFilterString(array $input = null, int $type = FILTER_DEFAULT, int $options = FILTER_FLAG_NO_ENCODE_QUOTES): ?array
    {

        if (empty($input)) {
            return null;
        }

        $tmp = [];

        foreach ($input as $value) {

            $value = filter_var(trim($value), $type, $options);

            if (!$value || in_array($value, $tmp, true)) {
                continue;
            }

            $tmp[] = $value;
        }

        return !empty($tmp) ? $tmp : null;
    }

    /**
     * Remove accents from a string by converting accented characters to their base form
     *
     * Uses Unicode normalization (NFD) to decompose characters and then removes diacritical marks.
     * Falls back to transliteration if Normalizer is not available.
     *
     * @param string $str The string to process
     * @return string The string with accents removed
     */
    public static function replaceAccents(string $str): string
    {
        if (empty($str)) {
            return $str;
        }

        // Method 1: Use Normalizer if available (most efficient and comprehensive)
        if (class_exists('Normalizer')) {
            // Normalize to NFD (decomposed form) and remove combining diacritical marks
            $normalized = Normalizer::normalize($str, Normalizer::FORM_D);
            if ($normalized !== false) {
                // Remove combining diacritical marks (Unicode category Mn)
                return preg_replace('/\p{Mn}/u', '', $normalized);
            }
        }

        // Method 2: Use iconv transliteration (fallback)
        if (function_exists('iconv')) {
            $result = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
            if ($result !== false) {
                // Clean up any remaining unwanted characters from transliteration
                return preg_replace('/[\'`"^~]/', '', $result);
            }
        }

        // Method 3: Legacy fallback using htmlentities (original method)
        $str = htmlentities($str, ENT_COMPAT, "UTF-8");
        $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde|ring|slash);/', '$1', $str);
        return html_entity_decode($str);
    }

    /**
     * @deprecated Use replaceAccents() instead
     */
    public static function replace_accents($str): string
    {
        trigger_error('replace_accents() is deprecated. Use replaceAccents() instead.', E_USER_DEPRECATED);
        return self::replaceAccents($str);
    }

    /**
     * @param $html
     * @param array $allowedElements
     * @return string
     */

    public static function epi_html_decode($html, array $allowedElements = ['HTML.AllowedElements' => ['p', 'b', 'u', 'i', 'a', 'strong', 'em', 'span', 'br']]): string
    {
        if (empty($html)) {
            return '';
        }
        return (new Episciences_HTMLPurifier($allowedElements))->purifyHtml(html_entity_decode($html, ENT_QUOTES, ENCODING_TYPE));

    }

    /**
     * @param $string
     * @param string $separator
     * @return bool
     */
    public static function isInUppercase($string, string $separator = '_'): bool
    {

        $latestSubString = '';


        foreach (explode($separator, $string) as $str) {

            $latestSubString = $str;

            if (ctype_lower($str)) {
                return false;
            }
        }

        return ctype_upper($latestSubString);


    }

    public static function translateToICU(string $string): string
    {
        if ($string === 'en' || $string === 'eng') {
            return 'en_GB';
        } elseif ($string === 'fr' || $string === 'fra') {
            return 'fr_FR';
        } elseif ($string === 'de') {
            return 'de_DE';
        } elseif ($string === 'it') {
            return 'it_IT';
        } elseif ($string === 'es') {
            return 'es_ES';
        }
        return '';
    }

    /**
     * @param string $plainText
     * @param Key $key
     * @return string
     * @throws EnvironmentIsBrokenException
     */
    public static function encryptWithKey(string $plainText, key $key): string
    {
        return Crypto::encrypt($plainText, $key);
    }

    /**
     * @param string $cipherText
     * @param Key $key
     * @return string
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public static function decryptWithKey(string $cipherText, Key $key): string
    {
        return Crypto::decrypt($cipherText, $key);

    }


    /**
     * @param string $plainText
     * @return string
     * @throws EnvironmentIsBrokenException
     * @throws JsonException
     * @throws Zend_Exception
     * @throws \Defuse\Crypto\Exception\BadFormatException
     */
    public static function encrypt(string $plainText): string
    {

        $cipherText = '';
        $path = Episciences_Review::getCryptoFile();

        if (!empty($path)) {

            $cryptoFile = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (array_key_exists('key', $cryptoFile)) {
                $sKey = $cryptoFile['key'];
                $cipherText = Episciences_Tools::encryptWithKey($plainText, Defuse\Crypto\Key::loadFromAsciiSafeString($sKey));
            }


        } else {
            throw new Zend_Exception('Fatal error: missing file: ' . $path);
        }

        return $cipherText;

    }

    /**
     * @param string $string
     * @return string
     */
    public static function getMastodonUrl(string $string): string
    {
        $explode = self::getMastodonSeparatedInfo($string);
        return "https://" . $explode[2] . "/@" . $explode[1];
    }

    public static function getBlueskyUrl(string $socialMedia): string
    {
        if (str_starts_with($socialMedia, 'https://bsky.app/profile/')) {
            return $socialMedia;
        }
        $socialMedia = str_replace('@', '', $socialMedia);
        return 'https://bsky.app/profile/' . $socialMedia;
    }

    /**
     * @param string $string
     * @return array
     */
    public static function getMastodonSeparatedInfo(string $string): array
    {
        return explode('@', $string, 3);
    }


    /**
     * retrieves from the session the last randomly generated path of the attached files for the current mail
     * or generate a new one
     *
     * @param string|null $root
     * @param bool $forceMkDir
     * @param int $randomBytesLength
     * @param int $strSplitLength
     * @return string
     * @throws Exception
     */
    public static function getAttachmentsPath(
        string $root = null,
        bool   $forceMkDir = false,
        int    $randomBytesLength = 6,
        int    $strSplitLength = 2
    ): string
    {


        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        if (isset($session->currentAttachmentsPath)) {
            return $session->currentAttachmentsPath;
        }


        if (empty($root)) {
            $root = Episciences_Mail_Send::FROM_MAILING;
        }

        $folders = str_split(bin2hex(random_bytes($randomBytesLength)), $strSplitLength);

        if (!is_array($folders)) {
            $folders = [];
        }

        $path = REVIEW_FILES_PATH;

        $path .= Episciences_Mail_Send::ATTACHMENTS;
        $path .= DIRECTORY_SEPARATOR;
        $path .= $root;
        $path .= DIRECTORY_SEPARATOR;

        foreach ($folders as $val) {
            $path .= $val . DIRECTORY_SEPARATOR;

        }

        if ($forceMkDir) {
            self::recursiveMkdir($path);
        }

        return $path;

    }

    public static function startsWithNumber(string $string): bool
    {
        return $string !== '' && ctype_digit($string[0]);
    }

    /**
     * @param string $path
     * @param int $permissions
     * @return string
     */
    public static function recursiveMkdir(string $path, int $permissions = self::DEFAULT_MKDIR_PERMISSIONS): string
    {

        if (!is_dir($path) && !mkdir($path, $permissions, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        return $path;

    }


    /**
     * @param string $markdown
     * @param array $options
     * @param string $converterType
     * @return \League\CommonMark\Output\RenderedContentInterface|string
     */
    public static function convertMarkdownToHtml(
        string $markdown,
        array  $options = [],
        string $converterType = 'commonMark'
    )
    {

        $options = empty($options) ? [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ] : $options;


        if ($converterType === 'commonMark') {

            $converter = new CommonMarkConverter($options);

        } elseif ($converterType === 'gitHub') {

            $converter = new GithubFlavoredMarkdownConverter($options);

        } else {
            $message = "Invalid Converter type: available converters: 'commonMark' and 'gitHub'";
            throw new InvalidArgumentException($message);
        }

        try {
            return $converter->convert($markdown);
        } catch (\League\CommonMark\Exception\CommonMarkException $e) {
            trigger_error($e->getMessage());
            return $markdown;
        }
    }


    /**
     * @param string $html
     * @param array $options : the options to be enabled
     * @return string
     */
    public static function convertHtmlToMarkdown(string $html, array $options = []): string
    {

        $converter = new HtmlConverter();
        $config = $converter->getConfig();

        if (empty($options)) {

            //By default, HTML To Markdown preserves HTML tags without Markdown equivalents, like <span> and <div>.
            //To strip HTML tags that don't have a Markdown equivalent while preserving the content inside them

            $config->setOption('strip_tags', true);

        } else {
            foreach ($options as $key) {
                $config->setOption($key, true);
            }
        }

        return $converter->convert($html);


    }


    public static function isDoi(string $doi = ''): bool
    {
        return !($doi === '' || !preg_match("/^10.\d{4,9}\/[-._;()\/:A-Z0-9]+$/i", $doi));
    }

    /**
     * Validate DOI format and length with proper error handling
     *
     * @param string $doi The DOI to validate
     * @param int $maxLength Maximum allowed length (default 200)
     * @return string The validated and trimmed DOI
     * @throws InvalidArgumentException If DOI is invalid or too long
     */
    public static function validateDoi(string $doi, int $maxLength = 200): string
    {
        $doi = trim($doi);

        if (empty($doi)) {
            throw new InvalidArgumentException('DOI cannot be empty');
        }

        if (!self::isDoi($doi)) {
            throw new InvalidArgumentException('Invalid DOI format: ' . $doi);
        }

        if (strlen($doi) > $maxLength) {
            throw new InvalidArgumentException("DOI exceeds maximum length of {$maxLength} characters");
        }

        return $doi;
    }

    /**
     * @param string $strDoi
     * @return bool
     */
    public static function isDoiWithUrl(string $strDoi): bool
    {
        $pattern = '~^((https?://)?(dx.)?doi\.org/)?10.\d{4,9}/[-._;()\/:A-Z0-9]+$~i';
        return (bool)preg_match($pattern, $strDoi);
    }

    /**
     * Validate ORCID identifier format
     *
     * ORCID format: 0000-0002-1825-0097
     * Four groups of four digits separated by hyphens
     * Last digit can be 0-9 or X (checksum)
     *
     * @param string $orcid The ORCID identifier to validate
     * @return bool True if valid ORCID format, false otherwise
     */
    public static function isValidOrcid(string $orcid): bool
    {
        $orcid = trim($orcid);

        if (empty($orcid)) {
            return false;
        }

        // ORCID format: 4 groups of 4 digits separated by hyphens, last digit can be X
        return (bool)preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/', $orcid);
    }

    /**
     * @param string $halId
     * @return bool
     */
    public static function isHal(string $halId): bool
    {
        return (bool)preg_match("/^[a-z]+[_-][0-9]{8}(v[0-9]*)?/", $halId);
    }

    /**
     * @param string $halId
     * @return array
     */
    public static function getHalIdAndVer(string $halId): array
    {
        $matches = [];
        preg_match("/([a-z]+[_-][0-9]{8})(v[0-9]*)?/", $halId, $matches);
        return $matches;
    }

    /**
     * @param string $url
     * @return array
     */
    public static function getHalIdInString(string $url): array
    {

        $matches = [];
        preg_match("~[a-z]+[_-][0-9]{8}(v[0-9]*)?~", $url, $matches);
        return $matches;
    }

    /**
     * Check if a string is a HAL URL containing a valid HAL identifier
     * @param string $url
     * @return bool
     */
    public static function isHalUrl(string $url): bool
    {
        // Check if it's a URL that contains a HAL identifier
        if (preg_match('~^https?://.*hal~', $url)) {
            $matches = self::getHalIdInString($url);
            return !empty($matches) && self::isHal($matches[0]);
        }
        return false;
    }

    /**
     * @param string $swhid
     * @return bool
     */
    public static function isSoftwareHeritageId(string $swhid): bool
    {
        return (bool)preg_match("/^swh:1:(cnt|dir|rel|rev|snp):[0-9a-f]{40}(;(origin|visit|anchor|path|lines)=\S+)*$/", $swhid);
    }

    /**
     * @param string $swhid
     * @return array
     */
    public static function getSoftwareHeritageDirId(string $swhid): array
    {
        $matches = [];
        preg_match("/swh:1:dir:[0-9a-f]{40}(;(origin|visit|anchor|path|lines)=\S+)*$/", $swhid, $matches);
        return $matches;
    }


    /**
     * Extracts a raw Handle string from a full Handle URL, excluding DOIs.
     *
     * @param string $input Input string
     * @return string Cleaned handle (original string if not a Handle URL or if it’s a DOI)
     */
    public static function cleanHandle(string $input): string
    {
        $input = trim($input);

        // Skip cleaning if it’s a DOI URL
        if (preg_match('~^https?://doi\.org/~i', $input)) {
            return $input;
        }

        // Clean Handle.net URLs (with or without protocol)
        return preg_replace(
            '~^(https?:\/\/)?hdl\.handle\.net\/~i',
            '',
            $input
        );
    }

    /**
     * Validates whether a string is a Handle (Handle.net) identifier.
     * DOIs (starting with "10.") are explicitly excluded.
     *
     * @param string $handle The handle to validate
     * @param bool $clean Whether to clean the handle first (removing URL prefixes)
     * @return bool True if valid handle, false otherwise
     */
    public static function isHandle(string $handle, bool $clean = true): bool
    {
        if ($clean) {
            $handle = self::cleanHandle($handle);
        }

        // Exclude DOIs (common DOI prefix pattern)
        if (preg_match('/^10\.\d{4,9}\//', $handle)) {
            return false;
        }

        // Basic Handle regex
        $pattern = '/^[0-9]+(\.[0-9]+)*\/[^\s]+$/u';
        return (bool) preg_match($pattern, $handle);
    }

    /**
     * @param string $arxiv
     * @return bool
     */
    public static function isArxiv(string $arxiv): bool
    {
        return (bool)preg_match("/^([0-9]{4}\.[0-9]{4,5})|([a-zA-Z\.-]+\/[0-9]{7})$/", $arxiv);
    }

    public static function checkIsArxivUrl(string $url)
    {
        $matches = [];
        preg_match("/^https?:\/\/arxiv\.org\/abs\/((?:\d{4}.\d{4,5}|[a-z\-]+(?:\.[A-Z]{2})?\/\d{7})(?:v\d+)?)/"
            , $url, $matches);
        return $matches;
    }


    /**
     * @param string $doi
     * @return array
     */
    public static function checkIsDoiFromArxiv(string $doi)
    {
        $matches = [];
        preg_match("~/arxiv\.~i", $doi, $matches);
        return $matches;
    }


    public static function checkValueType($value): bool|string
    {
        if (empty($value) || !is_string($value)) {
            return false;
        }

        $checks = [
            'hal' => fn($val) => self::isHalUrl($val) || self::isHal($val),
            'doi' => fn($val) => self::isDoi($val),
            'software' => fn($val) => self::isSoftwareHeritageId($val),
            'arxiv' => fn($val) => self::isArxiv($val),
            'handle' => fn($val) => self::isHandle($val),
            'url' => fn($val) => Zend_Uri::check($val)
        ];

        foreach ($checks as $type => $checkFunction) {
            if ($checkFunction($value)) {
                return $type;
            }
            }

        return false;
    }

    public static function getCleanedUuid(string $uuid = null): string
    {
        if (!self::isUuid($uuid)) {
            return '';
        }

        return str_replace('-', '', $uuid);
    }


    public static function isUuid(string $uuid = null): bool
    {

        if (empty(trim((string)$uuid))) {
            return false;
        }

        return \Ramsey\Uuid\Uuid::isValid($uuid);

    }

    public static function reduceXmlSize(string $xml): string
    {
        $dom = new DOMDocument();

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    /**
     * Trim the regex pattern to remove delimiters.
     * @param string $pattern
     * @param string $delimiter
     * @return string
     */
    public static function trimPattern(string $pattern, string $delimiter = '#'): string
    {
        return str_replace([sprintf('%s^', $delimiter), $delimiter], '', $pattern);
    }


    /**
     * Process translations and remove keys based on conditions.
     * @param array $translations
     * @param string $lang
     * @param string $file
     * @param string $pattern
     * @return void
     */
    private static function processTranslations(array &$translations, string $lang, string $file, string &$pattern): void
    {
        foreach ($translations as $key => $translation) {
            if ($file === Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME) { // @see RT#228342: to avoid renaming the keys in the database and on the server
                self::handleTemplateFile($translations, $key, $lang, $pattern);
            } elseif (preg_match($pattern, $key)) {
                unset($translations[$key]);
            }
        }
    }

    /**
     * @param array $translations
     * @param string $key
     * @param string $lang
     * @param string $pattern
     * @return void
     */
    private static function handleTemplateFile(array &$translations, string $key, string $lang, string &$pattern): void
    {

        $cleanedPattern = self::trimPattern($pattern);
        $cleanedKey = Episciences_Mail_TemplatesManager::cleanKey($key);

        if ($cleanedKey === $cleanedPattern) {
            unset($translations[$key]);
        }
    }


    /**
     * Convert between ISO 639-2/T and ISO 639-2/B codes.
     *
     * @param string $code ISO 639-2 code (either /T or /B).
     * @return string Converted code (or the same code if no mapping exists or invalid input).
     */
    public static function convertIso639Code(string $code): string
    {
        if (empty($code) || strlen($code) !== 3) {
            return $code;
        }

        $code = strtolower($code);

        return self::ISO639_BIDIRECTIONAL_MAP[$code] ?? $code;
    }

    /**
     * Clean whitespace and control characters from strings or arrays (PHP 8.1+ compatible)
     *
     * This method normalizes whitespace in strings or recursively processes arrays.
     * It removes excessive spaces, tabs, newlines, and optionally BR tags and UTF-8 special characters.
     *
     * @param string|array|null $input The input to clean (string, array, or null)
     * @param bool $stripBr Whether to convert BR tags to spaces before processing (default: true)
     * @param bool $allUtf8 Whether to remove all UTF-8 special whitespace characters (default: false)
     * @return string|array Empty string for null string input, empty array for null/empty array input,
     *                     cleaned string otherwise, or array with cleaned values
     *
     * @example
     * // String cleaning
     * spaceCleaner("  hello   world  ") // Returns: "hello world"
     * spaceCleaner(null) // Returns: ""
     * spaceCleaner("hello<br>world") // Returns: "hello world"
     * spaceCleaner("hello<br>world", false) // Returns: "hello<br>world"
     *
     * // Array cleaning
     * spaceCleaner(["  test  ", null, "  value  "]) // Returns: ["test", "", "value"]
     * spaceCleaner(null, true, false) // Returns: ""
     */
    public static function spaceCleaner(
        string|array|null $input,
        bool $stripBr = true,
        bool $allUtf8 = false
    ): string|array {
        // Handle null input
        if ($input === null) {
            return '';
        }

        // Handle array input recursively
        if (is_array($input)) {
            $result = [];
            foreach ($input as $value) {
                $cleaned = self::spaceCleaner($value, $stripBr, $allUtf8);
                if ($cleaned !== null) {
                    $result[] = $cleaned;
                }
            }
            return array_filter($result, static fn($val) => $val !== null);
        }

        // Handle empty strings
        if ($input === '') {
            return '';
        }

        // Strip BR tags if requested
        if ($stripBr) {
            $input = preg_replace("/<br[[:space:]]*\/?[[:space:]]*>/i", " ", $input);
        }

        // Normalize regular whitespace (spaces, tabs, newlines, carriage returns)
        // Keep non-breaking spaces and other UTF-8 spaces intact unless $allUtf8 is true
        $input = preg_replace('/[\n\t\r ]+/', ' ', $input);

        // Remove control characters (ASCII 1-31, excluding those already handled)
        $input = preg_replace("/[\x1-\x1f]/", "", $input);

        // Optionally remove all UTF-8 special whitespace characters
        if ($allUtf8) {
            // Remove various UTF-8 whitespace and control characters
            // \x7F-\xA0: DEL and non-breaking space range
            // \xAD: soft hyphen
            // \x{2009}: thin space
            $input = preg_replace('/[\x7F-\xA0\xAD\x{2009}]/u', '', $input);
        }

        // Remove duplicate spaces that may have been created
        $input = preg_replace('/\s\s+/u', ' ', $input);

        return trim($input);
    }


    public static function isIPv6(string $ipv6): bool{
        return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * To decode only &amp; to & (without affecting other HTML entities)
     * @param string $string
     * @return string
     */

    public static function decodeAmpersand(string $string): string
    {
        return str_replace('&amp;', '&', $string);
    }

}
