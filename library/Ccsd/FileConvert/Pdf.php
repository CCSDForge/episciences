<?php
/**
 * Created by PhpStorm.
 * User: tournoy
 * Date: 13/07/18
 * Time: 13:57
 */

class Ccsd_FileConvert_Pdf
{
    /** @const PDF max file size to process  1G */
    const PDF_MAX_FILE_SIZE = 1073741824;

    /**
     * pdftotext from poppler-utils
     * @see https://poppler.freedesktop.org/
     */
    const PDFTOTEXT_BIN = '/usr/bin/pdftotext';

    /**
     * @param string $filename
     * @param string $method
     * @param bool $useCache
     * @param string $cacheFilename
     * @return bool|string
     * @throws Ccsd_FileConvert_Exception
     */
    public static function convertPDFtoText($filename, $method = '', $useCache = false, $cacheFilename = '')
    {

        if (($useCache) && ($cacheFilename != '')) {
            $cachedContent = self::getCache($cacheFilename);
        }

        if ($cachedContent != '') {
            return $cachedContent;
        }


        if (!is_readable($filename)) {
            throw new Ccsd_FileConvert_Exception(Ccsd_FileConvert_Exception::FILE_NOT_READABLE);
        }

        $fileSize = @filesize($filename);

        if ($fileSize == false) {
            throw new Ccsd_FileConvert_Exception(Ccsd_FileConvert_Exception::FILE_EMPTY);
        }

        // 1 073 741 824
        if ($fileSize > static::PDF_MAX_FILE_SIZE) {
            throw new Ccsd_FileConvert_Exception(Ccsd_FileConvert_Exception::FILE_TOO_BIG);
        }


        switch ($method) {
            case 'poppler':
                $textFromPdf = self::convertPdfToTextWithPoppler($filename, $cacheFilename);
                break;
            case 'grobid':
                $textFromPdf = self::convertPdfToTextWithGrobid($filename);
                break;
            default:
                throw new Ccsd_FileConvert_Exception(Ccsd_FileConvert_Exception::UNKNOWN_CONVERT_METHOD);
                break;
        }

        if (($useCache) && ($cacheFilename != '') && ($textFromPdf != '') && ($method != 'poppler')) {
            self::writeCache($textFromPdf, $cacheFilename);
        }

        return $textFromPdf;
    }

    /**
     * Get fulltext cache
     * @param $cacheFilename
     * @return string
     */
    private static function getCache($cacheFilename)
    {
        if (is_readable($cacheFilename)) {
            $content = file_get_contents($cacheFilename);
            if (!$content) {
                return '';
            }
        }

        return $content;
    }

    /**
     * Convert pdf to text with poppler-utils pdftotext
     * @param $pdfInputFile
     * @param string $fullTextCacheFile
     * @return bool|string
     * @throws Ccsd_FileConvert_Exception
     */
    public static function convertPdfToTextWithPoppler($pdfInputFile, $fullTextCacheFile = '')
    {
        if ($fullTextCacheFile == '') {
            throw new Ccsd_FileConvert_Exception(Ccsd_FileConvert_Exception::POPPLER_CACHE_MANDATORY, 'Invalid cache file: ' . $fullTextCacheFile);
        }

        $pdftotextOptions = ' -enc UTF-8 -q ';

        setlocale(LC_CTYPE, "fr_FR.UTF-8"); // escapeshellarg strip les lettres accentuees si on n'est pas dans une locale Utf8
        $escapedCommandToExec = self::PDFTOTEXT_BIN . $pdftotextOptions . escapeshellarg($pdfInputFile) . ' ' . $fullTextCacheFile;

        shell_exec($escapedCommandToExec);

        $fulltext = file_get_contents($fullTextCacheFile);

        if (!$fulltext) {
            return '';
        }

        return $fulltext;

    }




    /**
     * Convert pdf to text with grobid
     * @param string $pdfPath
     * @return string
     */
    public static function convertPdfToTextWithGrobid($pdfPath)
    {

        // Crée un gestionnaire cURL
        $ch = curl_init(GROBID_HOST . ':' . GROBID_PORT . '/api/processFulltextDocument');

        // Crée un objet CURLFile
        $cfileToPost = new CURLFile($pdfPath, 'text/pdf', 'iCanHazTextPlz.pdf');


        // Assigne les données POST
        $dataToPost = ['input' => $cfileToPost];
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToPost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, DOMAIN);

        $rawXmlOutput = curl_exec($ch);

        curl_close($ch);

        $xmlDocument = new SimpleXMLElement($rawXmlOutput);
        $bodyText = $xmlDocument->xpath("/*[name()='TEI']/*[name()='text']/*[name()='body']");

        $bodyString = '';

        foreach ($bodyText[0] as $bodyElement) {
            $bodyString .= $bodyElement->asXML();
        }

        $bodyString = strip_tags($bodyString);

        return $bodyString;
    }

    /**
     * write fulltext cache content
     * @param string $content
     * @param $cacheFilename
     * @return bool|int
     */
    static private function writeCache($content, $cacheFilename)
    {
        return file_put_contents($cacheFilename, $content);
    }

}