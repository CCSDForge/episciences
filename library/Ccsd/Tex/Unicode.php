<?php

/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 08/08/17
 * Time: 13:34
 */
/** Can be viewed as an array of @see Ccsd_Tex_Language  with some method to iterate properly */
class Ccsd_Tex_Unicode
{
    /** @var Ccsd_Tex_Language[]  */
    static private $allLanguages = null;
    /** @var Ccsd_Tex_Language[]   */
    private $myLangages = [];
    /** @var string[] */
    private $headers = [];
    /** @var string[] */
    private $setOtherlanguagesValue=[];
    /**
     * New Language must be inserted her
     * @Todo: Need to change that to a dynamicaly constructed list
     * @return Ccsd_Tex_Language[]
     */
    private function getAllLanguagesArray() {
        if (self::$allLanguages == null) {

            self::$allLanguages = [
                new Ccsd_Tex_Language_Chinese(),
                new Ccsd_Tex_Language_Japanese(),
                new Ccsd_Tex_Language_Arabic(),
                new Ccsd_Tex_Language_Greek(),
                new Ccsd_Tex_Language_Sanskrit(),
                new Ccsd_Tex_Language_Corean(),
                new Ccsd_Tex_Language_Hindi(),
                new Ccsd_Tex_Language_Russian(),
                //new Ccsd_Tex_Language_Thai(),
            ];
        }
        return self::$allLanguages;
    }
    /**
     * @param string $locale : Language code
     * @return false|Ccsd_Tex_Language
     */
    private function getLanguageByLocale($locale) {
        foreach ($this->getAllLanguagesArray() as $lang) {
            if ($lang -> locale == $locale) {
                return $lang;
            }
        }
        return false;
    }
    /**
     * Ccsd_Tex_Unicode constructor.
     * @param string[] $langs : array of locale needed, null for all languages
     */
    public function __construct($langs = null) {
        if ($langs == null) {
            $this -> myLangages = $this -> getAllLanguagesArray();
            return;
        }
        if (!is_array($langs)) {
            $langs = [$langs];
        }
        foreach ($langs as $lang) {
            $obj = $this->getLanguageByLocale($lang);
            if ($obj !== false) {
                $this->myLangages[] = $obj;
            }
        }
    }
    /**
     * Return necessary language headers and modified text with languages tags
     * For test with multiple languages, this can give a wrong result in case of common set of characters like chinese and japanese
     * which have \p{Han} in common so we can't know what is zh and what is ja...
     * Don't know if it is a real difficulty: maybe characters are display the same ???
     *
     * Multiple calls to this function add to header and to setOtherlanguagesValue
     *
     * header and setOtherlanguagesValue are resetted after a call to get header()
     * @param string
     * @param string
     * @return string
     */
    public function parseXelatexLingualsCommand($string) {

        foreach ($this->myLangages as $lang ) {
            $matches = [];
            if ($lang -> hasLang($string, $matches)) {
                if ($lang->texlangName != '') {
                    $this->setOtherlanguagesValue[] = $lang->texlangName;
                }
                $string  = $lang -> addLanguageTags($string);
                $this->headers = $lang -> addHeaders($this->headers);
            }
        }

        return $string;
    }
    /**
     * @return string[]
     */
    public function headers() {
        if ($this->setOtherlanguagesValue != []) {
            $this->headers['setOtherlanguagesValue'] = '\setotherlanguages{' . implode(',', $this->setOtherlanguagesValue) . '}';
        }
        $ret = $this->headers;
        $this -> reset();
        return $ret;
    }
    /**
     * Reset the Unicode Tex transformation by cleaning needed headers and setOtherlanguagesValue
     * @return void
     */
    private function reset() {
        $this->headers = [];
        $this->setOtherlanguagesValue = [];
    }

}