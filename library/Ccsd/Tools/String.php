<?php

class Ccsd_Tools_String {
    const CLASS_CTRL = '[:cntrl:]';
    /**
     * Supprime les caractères de contrôle et les remplace éventuellement par
     * une chaine
     *
     * @param string $inputString
     * @param string $replaceString
     * @param boolean $allCtrl
     * @return string
     */
    static function stripCtrlChars($inputString, $replaceString = '', $allCtrl = true, $preserveNewLines = false) {
        if ($preserveNewLines == true) {
            $inputString = nl2br($inputString, false);
        }

        if ($allCtrl == false) {
            // regex Cntrl sauf 10=\n et 13=\r
            $outputString = preg_replace('/[\x00-\x09\x14-\x1F\x11\x12\x7F]/u', $replaceString, $inputString);
        } else {
            $outputString = preg_replace('/[[:cntrl:]]/u', $replaceString, $inputString);
        }

        if ($preserveNewLines == true) {
            $outputString = str_replace('<br>', '', $outputString);
        }

        return $outputString;
    }

    /**
     * Tronque une chaine de caractères
     * La chaine retournee fait au maximum $stringMaxLength + length($postTruncateString)
     *
     * @see Ccsd_View_Helper_Truncate helper de vue
     * @param string   $inputString
     * @param int      $stringMaxLength
     * @param string   $postTruncateString
     * @param boolean  $cutAtSpace
     * @return string
     */
    static function truncate($inputString, $stringMaxLength, $postTruncateString = '', $cutAtSpace = true) {
        // Renvoie une chaîne vide si max length < 1
        if ($stringMaxLength < 1) {
            return '';
        }
        $l = strlen($inputString);
        // Renvoie la chaîne entière si max_length plus long que la chaîne
        if ($stringMaxLength >= $l) {
            return $inputString;
        }
        $cutPos = $stringMaxLength;
        // Renvoie la chaîne tronquée
        if ($cutAtSpace) {
            // On raccourci la chaine avant de trouver les mots.
            // Cela ecite un parcours de l'ensemble des mots de la fin a $stringMaxLength
            $fromEnd = $stringMaxLength - $l;
            $cutPos = strrpos($inputString, ' ', $fromEnd);
            if ($cutPos === false) {
                // Si il ne reste pas d'espaces, la chaîne entière est tronquée brutalement independamment des espaces
                $cutPos = $stringMaxLength;
            }
        }
        $inputString = trim(substr($inputString, 0, $cutPos));
        return $inputString . $postTruncateString;
    }

    /**
     * ucfirst UTF-8public function testsave($docid, $result)
     *
     * @param string $str
     * @return string
     */
    static function utf8_ucfirst($str) {

        $stringLen = mb_strlen($str, "UTF-8");

        if ($stringLen == 0) {
            $value = '';
        } elseif ($stringLen == 1) {
            $value = mb_convert_case($str, MB_CASE_UPPER, "UTF-8");
        } else {
            $matches = [];
            preg_match('/^(.{1})(.*)$/us', $str, $matches);
            $value = mb_convert_case($matches [1], MB_CASE_UPPER, "UTF-8") . $matches [2];
        }

        return $value;
    }

    /**
     * Encode les entités XML pour générer du XML
     *
     * @param string $str
     * @return string
     */
    static function xmlSafe($str) {
        return htmlspecialchars($str, ENT_COMPAT | ENT_XML1, 'UTF-8');
    }

    /** Valide une date par creation d'un object  */
    static function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
    /**
     * Transforme une chaine en date ISO8601
     * @see https://lucene.apache.org/solr/guide/6_6/working-with-dates.html#WorkingwithDates-DateFormatting
     * @param string $input
     * @return string 1995-12-31T23:59:59Z ou une chaine vide si la date en sortie n'est pas valide
     */

    static function stringToIso8601($input = '') {

        $iso8601Format = 'Y-m-d\TH:i:s\Z';

        if (($input == '0000-00-00') || ($input == '0') || ( $input == '')) {
            return '';
        }

        // exemple de "dates"
        // 00-00-2000
        // 2000-00-00
        // 2010-01

        // remplace 00 dans les dates sinon les arrondis de php sont trop eloignés de la réalité
        // $d = new DateTime('2015-00-00');
        // echo $d->format('Y-m-d\TH:i:s\Z');  ==> 2014-11-30T00:00:00Z
        $badDates = ['-00', '-00-'];
        $roundedDates = ['-01', '-01-'];

        $output = str_pad($input, 10, "-01", STR_PAD_RIGHT);


        $output = str_replace($badDates, $roundedDates, $output);

        try {
            $date = new DateTime($output);
            return $date->format($iso8601Format);
        } catch (Exception $e) {
            return '';
        }

    }

    /** Transforme une date partielle en date pour Mysql
     *  Retourne $default en cas de date invalide
     * @param string $date
     * @param string $default
     * @return string|null
     */
    static function stringToMysqlDate($date, $default=null) {
        if (($date == '') || ($date ==null) || ($date == '0000-00-00')) {
            return $default;
        }
        $newdate = preg_replace('/-00/', '-01', $date) ;
        if (self::validateDate($newdate)) {
            return $newdate;
        } else {
            return $default;
        }
    }

    /**
     * Transforme un code de domaine en tableau qui contient toute la hierarchie
     * des domaines
     *
     * In :
     * spi.meca.ther
     *
     *
     *
     * Out :
     * array(3) {
     * [0] => string(3) "spi"
     * [1] => string(8) "spi.meca"
     * [2] => string(13) "spi.meca.ther"
     * }
     *
     * @param string $domainCode
     * @return array
     */
    static function getHalDomainPaths($domainCode) {
        //
         $domains = explode('.', $domainCode);
         $concat='';
         $sep = '';
         $domainesPaths = [];
         foreach ($domains as $code) {
             $concat = $concat . $sep . $code;
             $domainesPaths [] = $concat;
             $sep = '.';
         }

        return $domainesPaths;
    }

    /**
     * @param string $domainCode
     * @param string $lang
     * @param string $sep
     * @param bool   $keepNotTranslated
     * @return string
     */
    static function getHalDomainTranslated($domainCode, $lang = null, $sep = '/', $keepNotTranslated = true) {
        return self::getHalMetaTranslated($domainCode, $lang, $sep, 'domain', $keepNotTranslated);
    }

    /**
     * @param string $codeStr
     * @param string $lang
     * @param string $sep
     * @param string $prefix
     * @param bool   $keepNotTranslated
     * @return string
     */
    static function getHalMetaTranslated($codeStr, $lang = null, $sep = '/', $prefix = 'domain', $keepNotTranslated = false) {
        $res = [];
        $translator = Zend_Registry::get('Zend_Translate');

        foreach (self::getHalDomainPaths($codeStr) as $code) {
            $libelle = $translator->translate($prefix . '_' . $code, $lang);
            if (!$keepNotTranslated) {
                if ($libelle != ($prefix . '_' . $code)) {
                    $res [] = $libelle;
                }
            } else {
                $res [] = $libelle;
            }
        }
        return implode($sep, $res);
    }


    /**
     * Retourne la première lettre de l'alphabet pour une chaine ou $returnIfMissing ='other' si
     * la premiere lettre n'est pas dans l'alphabet
     *
     * @param string $string
     * @param string $returnIfMissing
     * @return string
     */
    static function getAlphaLetter($string, $returnIfMissing = 'other')
    {
        $string = ltrim($string);
        $string = ltrim($string, '“"()[]*,-');
        $string = ltrim($string, "'");
        $string = ltrim($string);

        $string = Ccsd_Tools::stripAccents($string);

        $string = mb_substr($string, 0, 1);

        $string = strtoupper($string);

        if (ctype_alpha($string) != 1) {
            return $returnIfMissing;
        }

        return $string;
    }

    const CLEAN_BEG_SPACE=0x01;
    const CLEAN_END_SPACE=0x02;
    const CLEAN_INT_SPACE=0x04;
    const CLEAN_CTRL     =0x08;
    const CLEAN_EXCEPT_AZ=0x10;

    const CLEAN_SPACES    =0x03;
    const CLEAN_ALL_SPACES=0x07;

    /**
     * @param string $string   (unicode string)
     * @param int $mode  (flag to indicate cleaning mode:
     *             CLEAN_BEG_SPACE : clean all blank chars at begining of string
     *             CLEAN_END_SPACE : clean all blank chars at end of string
     *             CLEAN_INT_SPACE : clean all blank chars in the string
     *             CLEAN_CTRL      : clean all control chars in the string
     *             CLEAN_EXCEPT_AZ : keep only word content (unicode letter/digit)
     *
     *  Some shortcuts you can use
     *      CLEAN_SPACES     : clean space at beginning and end
     *      CLEAN_ALL_SPACES : suppress all space everywhere in string
     *
     *     You can select more than a flag by doing binary or f1 | f2
     * @return string
     */
    static function cleanString($string, $mode = self::CLEAN_SPACES) {
        $begreg = '';
        $endreg = '';
        $midreg = '';

        if ($mode & self::CLEAN_BEG_SPACE) {
            $begreg .= "\s";
        }
        if ($mode & self::CLEAN_INT_SPACE) {
            $midreg .= "\s";
        }
        if ($mode & self::CLEAN_END_SPACE) {
            $endreg .= "\s";
        }
        if ($mode & self::CLEAN_CTRL) {
            $begreg .= self::CLASS_CTRL;
            $endreg .= self::CLASS_CTRL;
            $midreg .= self::CLASS_CTRL;
        }
        if ($mode & self::CLEAN_EXCEPT_AZ) {
            $midreg .= "\W";
        }
        if ($begreg != '') {
            $reg = "/\A[$begreg]+/mu";
            $string =  preg_replace($reg, '', $string);
        }
        if ($endreg != '') {
            $reg = "/[$begreg]+\z/mu";
            $string =  preg_replace($reg, '', $string);
        }
        if ($midreg != '') {
            $reg = "/[$midreg]+/mu";
            $string =  preg_replace($reg, '', $string);
        }
        return $string;
    }
}
