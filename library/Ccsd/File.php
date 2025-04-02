<?php

/**
 * Méthodes permettant d'avoir des informations sur des fichers (extension, taille, ...)
 * @author yannick
 *
 */
class Ccsd_File
{


    /**
     *
     * @var array Extensions des fichiers convertibles en jpg ou
     *      redimensionnables
     */
    protected static $_imgConverterExtAccepted = [
        'jpg',
        'jpeg',
        'jpe',
        'gif',
        'png',
        'bmp',
        'svg'
    ];

    /**
     *
     * @var array Extensions des archives
     */
    protected static $_archiveExt = [
        'zip',
        'tar',
        'tgz',
        'tar.gz',
        'tar.Z',
        'tar.bz2',
        'tbz',
        'tbz2'
    ];

    protected static $_extension = [
        'music' => [
            "aac",
            "ac3",
            "aif",
            "aifc",
            "aiff",
            "au",
            "bwf",
            "mp2",
            "mp3",
            "M4r",
            "ogg",
            "ogm",
            "ra",
            "ram",
            "wma",
            "wav"
        ],
        'film' => [
            "avi",
            "flv",
            "mov",
            "movie",
            "mp4",
            "mpe",
            "mpeg",
            "mpg",
            "qt",
            "rm",
            "rmvb",
            "rv",
            "vob",
            "wmv",
            "m4a"
        ],
        'picture' => [
            "jpg",
            "jpeg",
            "jpe",
            "jps",
            "png",
            "gif",
            "tif",
            "tiff",
            "ms3d",
            "odg",
            "otg",
            "pct",
            'svg'
        ],
        'file' => [
            "tex",
            "zip",
            "tar",
            "gz",
            "bz2",
            "tar.gz",
            "tar.bz2",
            "tgz",
            "odc",
            "ods",
            "pdf",
            "doc",
            "docx",
            "txt",
            "dot",
            "dotx",
            "rtf",
            "odf",
            "odt",
            "ott",
            "html",
            "htm",
            "ppt",
            "pptx",
            "pot",
            "potx",
            "pps",
            "ppsx",
            "pptm",
            "ppsm",
            "ps",
            "odp",
            "ots"
        ]
    ];

    /**
     * Retourne la taille d'un fichier
     *
     * @param string $filename
     * @param string $return
     * @return string
     */
    public static function getSize($filename, $return = 'string')
    {
        $bytes = filesize($filename);
        return $return == 'string' ? self::convertFileSize($bytes) : $bytes;
    }

    /**
     * Retourne la valeur sous forme texte de la taille d'un fichier
     *
     * @param int $bytes
     * @return string
     */
    public static function convertFileSize($bytes)
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' Ko';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' Mo';
        }

        if ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' Go';
        }

        if ($bytes < 1125899906842624) {
            return round($bytes / 1099511627776, 2) . ' To';
        }
        return '';
    }

    /**
     * Retourne le type de contenu d'un fichier
     *
     * @param string $filename
     * @return string
     */
    public static function getMimeType($filename)
    {
        $finfo = new finfo(FILEINFO_MIME);
        $res = $finfo->file($filename);
        $mime = substr($res, 0, strpos($res, ';'));
        if (false !== strpos($mime, "pdf")) {
            $mime = 'application/pdf';
        }
        if (!in_array($mime, ['application/pdf', 'image/gif', 'image/jpeg', 'image/png'])) {
            $extension = self::getExtension($filename);
            if ($extension == 'pdf') {
                $mime = 'application/pdf';
            } else if ($extension == 'jpg') {
                $mime = 'image/jpeg';
            } else if ($extension == 'gif') {
                $mime = 'image/gif';
            } else if ($extension == 'png') {
                $mime = 'image/png';
            }
        }

        return $mime;
    }

    /**
     * Retourne en minuscule l'extension d'un fichier
     *
     * @param string $filename
     * @return string
     */
    public static function getExtension($filename)
    {
        $path_info = pathinfo($filename);
        return (isset($path_info['extension']) ? strtolower($path_info['extension']) : '');
    }

    /**
     * Retourne le répertoire contenant le fichier $filename
     *
     * @param string $filename
     * @return string
     */
    public static function getDirectory($filename)
    {
        $dir = substr($filename, 0, strrpos($filename, '/'));
        return ($dir == '' ? '.' : $dir);
    }



    /**
     * Renommage d'un fichier
     *
     * @param string $name
     *            nom du fichier à renommer
     * @param string $path
     * @param boolean $force => false: nettoyage, true: nettoyage + renommage
     * @return string
     *
     */
    public static function renameFile(string $name, string $path = '', bool $force = true): string
    {
        $name = preg_replace('/[^a-z0-9_\.-\/\\\\]/i', '_', self::spaces2space(self::stripAccents(($name))));
        $name = preg_replace("~\.\.*~", ".", $name);
        $name = preg_replace("/__*/", "_", $name);

        if ($force && $path !== '' && strpos($name, '.')) {
            while (is_file($path . $name)) {
                $matches = [];
                // Attention, on doit forcement matche tout nom avec un .
                // sinon, risque de boucle infinie
                if (preg_match('/_?(\d*)(\.\w*)$/', $name, $matches)) {
                    $num = $matches[1];
                    $ext = $matches[2];
                    $num++;
                    $name = preg_replace('/_?\d*(\.\w*)$/', "_$num$ext", $name);
                }

            }
        }
        return $name;
    }

    /**
     * Remplace toutes séries d'espaces par un seul
     * @param string $string
     * @return string
     */
    public static function spaces2space($string)
    {
        return (preg_replace("/  +/", " ", $string));
    }

    /**
     * Remplace les lettres accentuées par une lettre non accentuée
     * TODO: A compléter selon des lettres accentuées trouvées.
     * @param $text
     * @return mixed
     */
    public static function stripAccents($text)
    {
        $text = str_replace([
            'æ',
            'Æ',
            'œ',
            'Œ',
            'ý',
            'ÿ',
            'Ý',
            'ç',
            'Ç',
            'ñ',
            'Ñ'
        ], [
            'ae',
            'AE',
            'oe',
            'OE',
            'y',
            'y',
            'Y',
            'c',
            'C',
            'n',
            'N'
        ], $text);
        $text = preg_replace("/[éèëê]/u", "e", $text);
        $text = preg_replace("/[ÈÉÊË]/u", "E", $text);
        $text = preg_replace("/[àâäáãå]/u", "a", $text);
        $text = preg_replace("/[ÀÁÂÃÄÅ]/u", "A", $text);
        $text = preg_replace("/[ïîíì]/u", "i", $text);
        $text = preg_replace("/[ÌÍÎÏ]/u", "I", $text);
        $text = preg_replace("/[üûùú]/u", "u", $text);
        $text = preg_replace("/[ÙÚÛÜ]/u", "U", $text);
        $text = preg_replace("/[ôöóòõø]/u", "o", $text);
        $text = preg_replace("/[ÒÓÔÕÖØ]/u", "O", $text);
        return $text;
    }

    /**
     * Indique si le fichier est une archive
     *
     * @param string $filename
     * @return boolean
     */
    public static function isAnArchive($filename)
    {
        return in_array(self::getExtension($filename), self::$_archiveExt);
    }

    /**
     * Décompresse une archive zip
     *
     * @param string $filename
     * @return array
     */
    public static function unarchiver($filename)
    {
        $extension = self::getExtension($filename);
        $directory = substr($filename, 0, strrpos($filename, '/') + 1);
        if ($extension === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($filename) === true) {
                $zip->extractTo($directory);
                $zip->close();
            }
        }
        // Récupération de tous les fichiers du répertoire
        $content = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($it as $file) {
            if (substr($file->getFilename(), 0, 1) != '.' && $file->getPathname() != $filename) {
                // On exclu les fichiers préfixés par le caractère '.'
                $content[] = $file->getPathname();
            }
        }
        return $content;
    }


    /**
     * Raccourci à nbChars caractères un nom de fichier
     * @param string $filename
     * @param int $nbChars
     * @return string
     */
    public static function shortenFilename($filename, $nbChars)
    {
        $newFilename = $filename;

        if (strlen($filename) > $nbChars) {
            $newFilename = substr($filename, 0, $nbChars) . "...." . self::getExtension($filename);
        }

        return $newFilename;
    }


    /**
     * Extensions des fichiers convertibles en jpg ou redimensionnables
     * @param string $filename
     * @return boolean
     */
    public static function canConvertImg($filename)
    {
        return (in_array(self::getExtension($filename), self::$_imgConverterExtAccepted));
    }

    /**
     * @param string $filename
     * @param string $toExt nouvelle extension
     * @return string
     * @deprecated
     * Change extension d'un nom de fichier
     */
    public static function changeFileExtension($filename, $toExt)
    {
        return self::replaceFileExtension($filename, $toExt);
    }

    /**
     * Change extension d'un nom de fichier
     * Case INSENSITIVE
     * @param string $filename
     * @param string $toExt nouvelle extension
     * @return string
     */
    public static function replaceFileExtension($filename, $toExt)
    {
        $name = self::getFilename($filename);
        $extension = self::getExtension($filename);
        return str_ireplace($extension, $toExt, $name);
    }

    /**
     * Retourne le nom du fichier à partir d'un path
     *
     * @param string $filepath
     * @return string
     */
    public static function getFilename($filepath)
    {
        return basename($filepath);
    }

    /**
     * Convertit une image en jpg
     *
     * @param string $filename // image
     *            source
     * @param string $dest (default null)
     *            repertoire d'enregistrement du fichier jpg
     * @param int $maxWidth (default null)
     *            à définir pour redimenssionner une image
     * @param int $maxHeight (default null)
     *            à définir pour redimenssionner une image
     * @param string $saveFileAs // nom du fichier de destination si on renomme
     * @return bool
     */
    public static function convertImg($filename, $dest = null, $maxWidth = null, $maxHeight = null, $saveFileAs = null)
    {
        // Lit les dimensions de l'image
        $size = getimagesize($filename);
        $width = $size[0];
        $height = $size[1];
        $imageType = $size[2];


        //pas un format d'image
        if (null == $imageType) {
            if (!static::isSvgFile($filename)) {
                return false;
            }

            return static::convertSvgToPng($filename, $dest, $maxWidth, $maxHeight, $saveFileAs);
        }

        // pas un soucis pour le SVG
        if (!$width || !$height) {
            return false;
        }


        if ($maxWidth != null && $maxHeight != null) {
            $fact = ((($maxWidth / $width) * $height) > $maxHeight) ? ($maxHeight / $height) : ($maxWidth / $width);
            $newWidth = round($width * $fact);
            $newHeight = round($height * $fact);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        $img = imagecreatetruecolor($newWidth, $newHeight);

        switch ($imageType) {
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filename);
                break;
            case IMAGETYPE_BMP:
                //break omitted
            case IMAGETYPE_TIFF_II:
            //break omitted
            case IMAGETYPE_TIFF_MM:
                $source = self::imagecreatefromother($filename);
                break;

            default:
                return false;
        }

        // Création de la version jpg + redimenssionnement éventuel
        imagecopyresampled($img, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if ($dest != null && is_dir($dest)) {


            //renomme éventuellement
            if ($saveFileAs != null) {
                $jpgFile = $saveFileAs . '.jpg';
            } else {
                $jpgFile = static::replaceFileExtension($filename, 'jpg');
            }

            if (imagejpeg($img, $dest . $jpgFile)) {
                return $jpgFile;
            }
            return false;
        }
        return imagejpeg($img);

    }

    /**
     * Le Fichier est probablement un fichier XML SVG
     * Si le fichier est du XML
     * Si l'élément racine est svg
     * @param $filename
     * @return bool
     */
    public static function isSvgFile($filename)
    {

        $xml = static::isWellFormedXmlFile($filename);

        if (!$xml) {
            return false;
        }

        if ('svg' != $xml->getName()) {
            return false;
        }

        return true;

    }

    /**
     * Fichier XML bien formé
     * Vérification sommaire : si le fichier XML est chargé on accepte qu'il est bien formé et on retourne
     * l'objet SimpleXMLElement
     * @param string $filename
     * @return bool|SimpleXMLElement
     */
    public static function isWellFormedXmlFile($filename)
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string(file_get_contents($filename));
        libxml_clear_errors();
        if (!$doc) {
            return false;
        }
        return $doc;
    }

    /**
     * Try to convert SVG file to PNG file,
     * Change scale when $maxWidth AND $maxHeight are given
     * Return value:
     *     If $dest is null, raw png is return as string
     *     If dest non null, the png file is written in dest dir
     *         If saveFileAs is given, use this filename to save file, else the output filename is
     *         the same a input filename except extension
     *     If can write file (whatever the reason) return false.
     *
     * @param $svgFile
     * @param string|null $dest
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @param string|null $saveFileAs
     * @return string
     */
    public static function convertSvgToPng($svgFile, $dest = null, $maxWidth = null, $maxHeight = null, $saveFileAs = null)
    {

        $image = new Imagick();
        $image->setBackgroundColor(new ImagickPixel('transparent'));
        $image->readImage($svgFile);
        $image->setImageFormat('png32');
        if (null != $maxWidth && null != $maxHeight) {
            $image->resizeImage($maxWidth, $maxHeight, imagick::FILTER_LANCZOS, 1);
        }
        // retourne l'image sans l'écrire
        if (null == $dest || !is_dir($dest)) {
            $png = base64_encode($image);
            $image->clear();
            $image->destroy();
            return $png;
        }

        /* Le repertoire ne se termine pas par /, on l'ajoute */
        if (substr($dest, -1, 1) != '/') {
            $dest .= '/';
        }
        // ecrit l'image  et retourne son nom
        //renomme éventuellement le fichier
        if ($saveFileAs != null) {
            $outputFile = $saveFileAs . '.png';
        } else {
            // ou change juste l'extension
            $outputFile = static::replaceFileExtension($svgFile, 'png');
        }
        try {
            $resultWrite = $image->writeImage($dest . $outputFile);
        } catch (Exception $e) {
            error_log('convertSvgToPng: ' . $e->getMessage() . ' dest:' . $dest . ' file:' . $outputFile);
            return false;
        }
        $image->clear();
        $image->destroy();

        if (!$resultWrite) {
            $outputFile = false;
        }
        return $outputFile;
    }

    /**
     * On converti à partir d'Image Magick
     * @param $filename
     * @return resource
     */
    public static function imagecreatefromother($filename)
    {
        $image = new Imagick();           //création d'un objet Imagick
        $image->pingImage($filename); //ping de l'image
        $image->readImage($filename); //on enregistre l'image dans l'objet
        $image->setImageFormat("jpg");    //on change de format
        $filename = str_replace(self::getExtension($filename), "jpg", $filename);
        $image->writeImage($filename);   //on crée le fichier image
        return imagecreatefromjpeg($filename);
    }

    /**
     * Compilation TeX
     * @param $dir string repertoire de travail
     * @param $sourceTex   string nom du fichier à compiler
     * @param $filenamePdf string nom du fichier pdf à créer
     * @param $stopOnError bool
     * @param $withLogFile bool
     * @param $user        string login
     * @param $password    string password
     * @param null $service
     * @return array
     */
    public static function compile($dir = '', $sourceTex = '', $filenamePdf = '', $stopOnError = true, $withLogFile = true, $user = null, $password = null, $service = null)
    {
        set_time_limit(0);
        if ($service === null) {
            // Compat
            // Pas de service explicite, on prends LATEX_COMPILE_SERVICE
            if (defined('LATEX_COMPILE_SERVICE')) {
                $service = LATEX_COMPILE_SERVICE;
            } else {
                Ccsd_Tools::panicMsg(__FILE__, __LINE__, "Constante LATEX_COMPILE_SERVICE non definie");
                return [];
            }
        }
        $curl = curl_init($service);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, "HAL Platform");
        if (!empty($user) && !empty($password)) {
            curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $password);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, ['dir' => $dir, 'source' => $sourceTex, 'fileName' => $filenamePdf, 'stopOnError' => (int)$stopOnError, 'withLogFile' => (int)$withLogFile]);
        $out = trim(curl_exec($curl));
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [
            'status' => in_array($httpStatus, [200, 201, 202]),
            'out' => $out
        ];
    }

    /**
     * Créé un répertoire à partir d'une chaine en la découpant en plusieurs répertoires
     * @param string $string
     * @param string $rootDir
     * @param int $lengthOfString
     * @param int $lenghtOfSlices
     * @param string $stringToPadWith
     * @return string
     * @example
     */
    public static function slicedPathFromString(string $string, string $rootDir = '', int $lengthOfString = 8, int $lenghtOfSlices = 2, string $stringToPadWith = '0'): string
    {
        $docPath = str_pad($string, $lengthOfString, $stringToPadWith, STR_PAD_LEFT);
        $docPath = wordwrap($docPath, $lenghtOfSlices, "/", true);
        return $rootDir . $docPath;
    }

    /**
     * Convert PDF to PDF/A with www.ghostscript.com
     * @see http://www.ghostscript.com/doc/current/VectorDevices.htm#PDFA
     * @param string $input filename to convert
     * @param string|null $output converted filename
     * @param string $outputPrefix prefix output file
     * @param bool $forceOverwrite overwrite source file
     * @return array [outputFileName] + [The last line from the result of the command]
     */
    public static function pdf2pdfa($input, $output = null, $outputPrefix = 'archivable_', $forceOverwrite = false)
    {
        setlocale(LC_CTYPE, "fr_FR.UTF-8"); // escapeshellarg strip les lettres accentuees si on n'est pas dans une locale Utf8
        //ghostscript binary
        $ghostscript_bin = GHOSTSCRIPT_PATH . DIRECTORY_SEPARATOR . 'gs';

        // PDF/A definition file
        $pdfa_definition = GHOSTSCRIPT_PATH . DIRECTORY_SEPARATOR . 'PDFA_def.ps';

        if (!is_executable($ghostscript_bin)) {
            throw new RuntimeException('Ghostscript binary: ' . $ghostscript_bin . ' is not executable. Abort the mission.');
        }

        if (!is_readable($pdfa_definition)) {
            throw new RuntimeException('PDFA definition file:  ' . $pdfa_definition . ' is not readable. Abort the mission.');
        }

        if (!is_readable($input)) {
            throw new RuntimeException('Input File: ' . $input . ' is not readable. Abort the mission.');
        }

        if (!is_writable(GHOSTSCRIPT_TEMPDIR)) {
            throw new RuntimeException('Ghostscript temp dir: ' . GHOSTSCRIPT_TEMPDIR . ' is not writable. Abort the mission.');
        }

        $pathInfoInput = pathinfo($input);


        // default : output file in input file directory
        if (empty($output)) {
            $output = $pathInfoInput['dirname'] . DIRECTORY_SEPARATOR . $outputPrefix . $pathInfoInput['basename'];
        }

        // default : no overwrite of input
        if ((!$forceOverwrite) && ($input == $output)) {
            throw new InvalidArgumentException('input file == output file but overwriting not allowed. The force overwrite you must use.');
        }

        $input = escapeshellarg($input);
        $unescapedOutput = $output;
        $output = escapeshellarg($output);


        $setMyEnv = 'TMPDIR=' . GHOSTSCRIPT_TEMPDIR . ' ';
        // PDF/A-1
        $command = $setMyEnv . $ghostscript_bin .
            " -dPDFA=1 -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sProcessColorModel=DeviceRGB -sDEVICE=pdfwrite -sPDFACompatibilityPolicy=2 -sOutputFile="
            . $output
            . ' ' . $pdfa_definition . ' '
            . $input;

        $execOutput = exec($command, $execOutput);

        return ['outputFile' => $unescapedOutput, 'execMessage' => $execOutput];

    }
}
