<?php

/**
 * Class Ccsd_Locale
 */
class Ccsd_Locale
{
    static protected $_topListCountries = array('fr', 'us', 'gb', 'de', 'it', 'es');
    // la liste des premières langues de dépôt peut être modifiée par le fichier de configuration toplang.json
    // lu en dehors de la classe
    // (voir en fin de fichier)
    static $_topListLanguages = array('en', 'fr', 'de', 'it', 'es');

    static protected $_accepted3lettersLanguage = [
        'sah' => 'Yakoute languages'
    ];
    protected $_conversionTable = array(
        "aar" => "aa",
        "abk" => "ab",
        "ave" => "ae",
        "afr" => "af",
        "aka" => "ak",
        "amh" => "am",
        "arg" => "an",
        "ara" => "ar",
        "asm" => "as",
        "ava" => "av",
        "aym" => "ay",
        "aze" => "az",
        "bak" => "ba",
        "bel" => "be",
        "bul" => "bg",
        "bih" => "bh",
        "bis" => "bi",
        "bam" => "bm",
        "ben" => "bn",
        "bod" => "bo",
        "tib" => "bo",
        "bre" => "br",
        "bos" => "bs",
        "cat" => "ca",
        'che' => 'ce',
        'cha' => 'ch',
        'cos' => 'co',
        'cre' => 'cr',
        'cze' => 'cs',
        'ces' => 'cs',
        'chu' => 'cu',
        'chv' => 'cv',
        'wel' => 'cy',
        'cym' => 'cy',
        'dan' => 'da',
        'ger' => 'de',
        'deu' => 'de',
        'div' => 'dv',
        'dzo' => 'dz',
        'ewe' => 'ee',
        'gre' => 'el',
        'ell' => 'el',
        'eng' => 'en',
        'epo' => 'eo',
        'spa' => 'es',
        'est' => 'et',
        'baq' => 'eu',
        'eus' => 'eu',
        'fas' => 'fa',
        'per' => 'fa',
        'ful' => 'ff',
        'fin' => 'fi',
        'fij' => 'fj',
        'fao' => 'fo',
        'fre' => 'fr',
        'fra' => 'fr',
        'fry' => 'fy',
        'gle' => 'ga',
        'gla' => 'gd',
        'glg' => 'gl',
        'grn' => 'gn',
        'guj' => 'gu',
        'glv' => 'gv',
        'hau' => 'ha',
        'heb' => 'he',
        'hin' => 'hi',
        'hmo' => 'ho',
        'scr' => 'hr',
        'hrv' => 'hr',
        'hat' => 'ht',
        'hun' => 'hu',
        'arm' => 'hy',
        'hye' => 'hy',
        'her' => 'hz',
        'ina' => 'ia',
        'ind' => 'id',
        'ile' => 'ie',
        'ibo' => 'ig',
        'iii' => 'ii',
        'ipk' => 'ik',
        'ido' => 'io',
        'ice' => 'is',
        'isl' => 'is',
        'ita' => 'it',
        'iku' => 'iu',
        'jpn' => 'ja',
        'jav' => 'jv',
        'geo' => 'ka',
        'kat' => 'ka',
        'kon' => 'kg',
        'kik' => 'ki',
        'kua' => 'kj',
        'kaz' => 'kk',
        'kal' => 'kl',
        'khm' => 'km',
        'kan' => 'kn',
        'kor' => 'ko',
        'kau' => 'kr',
        'kas' => 'ks',
        'kur' => 'ku',
        'kom' => 'kv',
        'cor' => 'kw',
        'kir' => 'ky',
        'lat' => 'la',
        'ltz' => 'lb',
        'lug' => 'lg',
        'lim' => 'li',
        'lin' => 'ln',
        'lao' => 'lo',
        'lit' => 'lt',
        'lub' => 'lu',
        'lav' => 'lv',
        'mlg' => 'mg',
        'mah' => 'mh',
        'mao' => 'mi',
        'mri' => 'mi',
        'mac' => 'mk',
        'mkd' => 'mk',
        'mal' => 'ml',
        'mon' => 'mn',
        'mol' => 'mo',
        'may' => 'ms',
        'msa' => 'ms',
        'mlt' => 'mt',
        'bur' => 'my',
        'mya' => 'my',
        'nau' => 'na',
        'nob' => 'nb',
        'nde' => 'nd',
        'nep' => 'ne',
        'ndo' => 'ng',
        'dut' => 'nl',
        'nld' => 'nl',
        'nno' => 'nn',
        'nor' => 'no',
        'nbl' => 'nr',
        'nya' => 'ny',
        'nav' => 'nv',
        'oci' => 'oc',
        'oji' => 'oj',
        'orm' => 'om',
        'ori' => 'or',
        'oss' => 'os',
        'pan' => 'pa',
        'pli' => 'pi',
        'pol' => 'pl',
        'pus' => 'ps',
        'por' => 'pt',
        'que' => 'qu',
        'rcf' => 'rc',
        'roh' => 'rm',
        'run' => 'rn',
        'rum' => 'ro',
        'ron' => 'ro',
        'rus' => 'ru',
        'kin' => 'rw',
        'san' => 'sa',
        'srd' => 'sc',
        'snd' => 'sd',
        'sme' => 'se',
        'sag' => 'sg',
        'sin' => 'si',
        'slo' => 'sk',
        'slk' => 'sk',
        'slv' => 'sl',
        'smo' => 'sm',
        'sna' => 'sn',
        'som' => 'so',
        'alb' => 'sq',
        'sqi' => 'sq',
        'scc' => 'sr',
        'srp' => 'sr',
        'ssw' => 'ss',
        'sot' => 'st',
        'sun' => 'su',
        'swe' => 'sv',
        'swa' => 'sw',
        'tam' => 'ta',
        'tel' => 'te',
        'tgk' => 'tg',
        'tha' => 'th',
        'tir' => 'ti',
        'tuk' => 'tk',
        'tgl' => 'tl',
        'tsn' => 'tn',
        'ton' => 'to',
        'tur' => 'tr',
        'tso' => 'ts',
        'tat' => 'tt',
        'twi' => 'tw',
        'tah' => 'ty',
        'uig' => 'ug',
        'ukr' => 'uk',
        'urd' => 'ur',
        'uzb' => 'uz',
        'ven' => 've',
        'vie' => 'vi',
        'vol' => 'vo',
        'wln' => 'wa',
        'wol' => 'wo',
        'xho' => 'xh',
        'yid' => 'yi',
        'yor' => 'yo',
        'zha' => 'za',
        'chi' => 'zh',
        'zho' => 'zh',
        'zul' => 'zu'
    );

    /**
     * If the locale is a 2 letters code, return a complete code: xx_XX_UTF8
     * Else use $locale as is
     * Then setlocale with the value
     *
     * TODO: cette fonction devrait s'appeller setFullLocale voir setFullLocaleMaybe, pas GET
     *
     * The return value is the return value of setLocale
     * @param $locale string
     * @return string|null (@see man setlocale)
     */
    public static function getFullLocale($locale) {
        if (strlen($locale) == 2) {
            // On essaye de completer par xx_XX.utf8
            $localeFormatted= strtolower($locale) . '_' . strtoupper($locale);
            $localeFormattedUtf8 = $localeFormatted . '.UTF-8';
            $resLocale = setlocale (LC_COLLATE, $localeFormattedUtf8, $localeFormatted, $locale);
            if ($resLocale) {
                return $resLocale;
            }
        }
        // Can't have a xx_XX, so try just xx
        $resLocale = setlocale (LC_COLLATE, $locale);
        // Can return null if locale not set
        return $resLocale;
    }
    /**
     * Retourne une liste des pays
     * @param Zend_Locale $locale langue par défaut de l'application
     * @param bool $orderList  affichage des pays les plus utilisés en tête de liste
     * @param bool $separator  affichage d'un séparateur entre les pays de la tête de liste et les autres
     * @throws Zend_Exception
     * @return array
     * NOTE: This function CHANGE the locale setting  (call to getFullLocale) !!!
     */
    public static function getCountry($locale = null, $orderList = false, $separator = false)
    {
        if ($locale == null) {
            $locale = Zend_Registry::get('Zend_Locale');
        }

        $countries = array_change_key_case(Zend_Locale::getTranslationList('territory', $locale, 2), CASE_LOWER);

        if (self::getFullLocale($locale)) {
            uasort($countries, 'strcoll');
        } else {
            asort($countries);
        }

        if ($orderList) {
            $firstCountries = array();
            foreach (self::$_topListCountries as $c) {
                if (isset($countries[$c])) {
                    $firstCountries[$c] = $countries[$c];
                }
            }
            if ($separator) {
                $firstCountries['--'] = '--------';
            }
            $countries = array_merge($firstCountries, $countries);
        }
        return $countries;
    }

    /**
     * @param string $code : 2 or 3 letters language code
     * @return bool
     *
     * Return an array of all languages accepted
     * accept only 2 letters codes plus some 3 letters codes listed in a config array
     */
    public static function isAcceptedLanguage($code) {

        // Peut-on accepter qq langue a 3 caracteres?
        // Demande: sah (Yakoute)
        if (array_key_exists($code, self::$_accepted3lettersLanguage)) {
            return true;
        }
        if (strlen($code) > 2) {
            return true;
        }
        return false;
    }
    /**
     * Retourne une liste des langues (code => libelle)
     *    Le libelle est dans la langue spécifiée ou par défaut dans la langue de l'application
     *    La liste est triée dans l'ordre de la langue par défaut
     *    Certaines langues  $_topListLanguages sont mise en haut de la liste
     * @param Zend_Locale $locale langue par défaut de l'application
     * @throws Zend_Exception
     * @return array
     */
    public static function getLanguage($locale = null)
    {
        if ($locale == null) {
            $locale = Zend_Registry::get('Zend_Locale');
        }

        $languages = Zend_Locale::getTranslationList('language', $locale, 2);
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($languages as $code => &$label) {
            if (self::isAcceptedLanguage($code)) {
                unset($languages[$code]);
            }
        }

        if (self::getFullLocale($locale)) {
            uasort ( $languages, 'strcoll');
        } else {
            asort($languages);
        }

        $topItem = array();
        foreach (self::$_topListLanguages as $c) {
            if (isset($languages[$c])) {
                $topItem[$c] = $languages[$c];
            }
        }
        return array_merge($topItem, $languages);
    }


    /**
     * Conversion d'une langue au format Iso2 à son équivalent Iso1 s'il existe
     * @param string $iso1Lang
     * @return string
     */
    public function convertIso2ToIso1($iso1Lang) {
        $iso1Lang = strtolower($iso1Lang);
        if (isset($this->_conversionTable[$iso1Lang]))
            return $this->_conversionTable[$iso1Lang];
        else
            return $iso1Lang;
    }

    /**
     * Vérification de l'existence d'une langue
     * @param $lang
     * @return bool
     */
    public function langExists($lang) {
        return in_array(strtolower($lang), array_values($this->_conversionTable));
    }
}

// modification de la liste des premières langues de dépôt par lecture du fichier de configuration toplang.json
if (defined('SPACE') && defined('CONFIG')) {
    $filename = SPACE . CONFIG . 'toplang.json';

    if (file_exists($filename)) {
        $liste = json_decode(file_get_contents($filename), true);
        if (is_array($liste)) {
            Ccsd_Locale::$_topListLanguages = $liste;
        }
    }
}
