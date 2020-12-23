<?php

/**
 * User: marmol
 * Date: 08/08/17
 * Time: 13:36
 */

/**
 * To add a new languages
 * 1) Verify that the language is not natively in xelatex -> nothing to do
 * 2) Get the Unicode script name in PHP:
 *      @see http://php.net/manual/fr/regexp.reference.unicode.php
 * 3) Look in polyglossia (xelatex) to see how to handle the language
 *      Some need to add headers like \usepackage{...}, \setotherlanguages{...} ...
 *      Some need to enclose text into \begin{mylang} ... \end{mylang}
 *      Some need to encloe text into \mylang{...text...}
 *    A very usefull document:
 *      @see https://www.overleaf.com/latex/examples/how-to-write-multilingual-text-with-different-scripts-in-latex/wfdxqhcyyjxz
 * 4) Create a new Tex_Language_Mylanguage
 * 5) In that new class you need to override definition for
 *      - $locale    @see https://fr.wikipedia.org/wiki/Liste_des_codes_ISO_639-1
 *      - $header
 *      - $texlangName @see http://ctan.mines-albi.fr/macros/latex/contrib/polyglossia/polyglossia.pdf
 *      - $unicodeScript @see http://php.net/manual/fr/regexp.reference.unicode.php
 *      - $replaceRegexp
 *      - $replaceBy
 *
 *  6) If nothing is working, you can overide function... but it's more harder!
 *        hasLang try to look at chars in string and use the Php unicode script property
 *
 *        addLanguageTags use $replaceRegexp and $replaceBy to enclose text with \begin{} or \lang{}
 *
 *        addHeader will add the header of this languages to those allready here
 *
 *  7) Now, the Tex file can be Ok but fonts may be not present
 *     Take a try, if it don't work, maybe you need to install new font on your xelatex installation
 */


/**
 * Property setters and getters
 * @property string unicodeScript
 * @property string name
 * @property string header
 * @property string texlangName
 * @property bool   hasRegexp
 * @property bool   forceCantUseRegexp
 * @property string replaceRegexp
 * @property string replaceBy(
 */

class Ccsd_Tex_Language
{

    protected $options = ['header','locale', 'unicodeScript','texlangName','hasRegexp','forceCantUseRegexp','replaceRegexp','replaceBy'];
    /**
     * @var string ISO 639-1 or 639-3 or 639-3 language code
     */
    protected $locale = '';
    /**
     * Array has key are constructed with language code for example
     * If some langage need the same header but the header must not be repeated
     * Then it is suffisant to declare the same code in both language
     * eg: 'zh+jp+cr+fr' You can trace where this header exists
     * The keyis meaningless and MUST BE kept meaningless
     * @var string[]
     */
    protected $header = [];
    /** @var string  */
    protected $unicodeScript = '';
    /** @var string  used for \setotherlanguages */
    protected $texlangName = '';
    /** @var string  */
    protected $hasRegexp = '';
    protected $forceCantUseRegexp = false;
    /** @var string  */
    protected $replaceRegexp = '';
    /** @var string  */
    protected $replaceBy = '';

    /**
     * @param string $toLang
     * @return string
     */
    public function getName($toLang = null) {
        if ($toLang) {
            return Locale::getDisplayLanguage($this->locale, $toLang);
        } else {
            return Locale::getDisplayLanguage($this->locale);
        }
    }

    /** Constructor
     * @param array $options */
    public function __construct($options=[]) {
        $this -> setOptions($options);
    }
    /** Constructor
     * @param string $classname : Language name corresponding to the ClassName
     * @uses Ccsd_Tex_Language_Russian
     * @uses Ccsd_Tex_Language_Japanese
     * @uses Ccsd_Tex_Language_Chinese
     * @uses Ccsd_Tex_Language_Corean
     * @uses Ccsd_Tex_Language_Greek
     * @uses ...
     * @return false|Ccsd_Tex_Language
     */
    public static function getLanguage($classname) {
        try {
            $fullclass = "Ccsd_Tex_Language_$classname" ;
            return  new $fullclass();
        } catch (Exception $e) {
            return false;
        }
    }

    /** Initialize objects property
     * @param array $options
     */
    public function setOptions($options) {
        foreach ($this -> options as $optname) {
            if (array_key_exists($optname, $options)) {
                $this -> $optname = $options[$optname];
                unset($options[$optname]);
            }
        }
        if ($options != []) {
            foreach ($options as $k => $v) {
                error_log("Ccsd_Tex_Language::setOptions called with bad option ($k)");
            }
        }
    }
    /**
     * return a regexp for language determination
     * If regexp is not possible for the language, then return false
     * @return string|false
     */
    public function getLangRegexp() {
        if ($this -> forceCantUseRegexp) {
            return false;
        }
        $script = $this->unicodeScript;
        if ($script != '') {
            return '/\\b\\p{' . $script . '}+\\b/u';
        }
        $myRegexp = $this -> hasRegexp;
        if ($myRegexp != '') {
            return $myRegexp;
        }
        return false;
    }
    /**
     * Getters
     * @param string $arg
     * @return mixed
     */
    public function __get($arg)
    {
        if (in_array($arg, $this->options)) {
            return $this->$arg;
        }
        // Todo: An exception maybe ??
        return false;
    }
    /**
     * Setters
     * @param string $arg
     * @param mixed $val
     * @return mixed
     */
    public function __set($arg, $val)
    {
        if (in_array($arg, $this->options)) {
            $this -> $arg = $val;
        }
        return $val;
    }
    /**
     * Return true if Language is used in string
     * @param $string
     * @param $matches (Can be used after to replace)
     * @return bool
     */
    public function hasLang($string, &$matches = []) {
        $regexp = $this -> getLangRegexp();
        if ($regexp) {
            return preg_match($regexp, $string, $matches);
        }
        return false;
    }
    /**
     * xelatex need some language particular tags, like \begin{Arabic}, {\japanesefont ...
     * Thos replacement are done by the replaceRegexp and replaceBy field
     * @param $string
     * @return mixed
     */
    public function addLanguageTags($string) {
        if ($this->replaceRegexp != '') {
            return preg_replace($this->replaceRegexp, $this ->replaceBy, $string);
        }
        return $string;
    }
    /**
     * Add necessary xelatex command in document header
     *    Load font,...
     * @param $header
     */
    public function addHeaders($header) {
        return array_merge($header, $this -> header);
    }
}