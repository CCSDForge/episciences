<?php

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class Ccsd_Tools
{
    public const DEFAULT_CURL_OPT_TIMEOUT = 60;

    private static $_TRANS_CAR_LATEX_GREEK = [
        //lettres grecques
        "Α" => '$A$',
        "α" => '$\\alpha$',
        "Β" => '$B$',
        "β" => '$\\beta$',
        "Γ" => '$\\Gamma$',
        "γ" => '$\\gamma$',
        "Δ" => '$\\Delta$',
        "∆" => '$\\Delta$', // different du precedent...
        "δ" => '$\\delta$',
        "Ε" => '$E$',  // Juste un E!!!
        "ε" => '$\\epsilon$',
        "Ζ" => '$Z$',
        "ζ" => '$\\zeta$',
        "Η" => '$H$',
        "η" => '$\\eta$',
        "ή" => '$\\eta$', // TODO pas encore l'accent sans utiliser un mode Text special...
        "Θ" => '$\\Theta$',
        "θ" => '$\\theta$',
        "Ι" => 'I',
        "ι" => '$\\iota$',
        "ί" => '$\\iota$', // TODO pas encore l'accent sans utiliser un mode Text special...
        "Κ" => '$K$',
        "κ" => '$\\kappa$',
        "Λ" => '$\\Lambda$',
        "λ" => '$\\lambda$',
        "Μ" => '$M$',   // \\Mu ne fonctionne pas dans notre version de latex (2014)
        "μ" => '$\\mu$',
        "µ" => '$\\mu$', # different du precedent...
        "Ν" => '$\\Nu$',
        "ν" => '$\\nu$',
        "Ξ" => '$\\Xi$',
        "ξ" => '$\\xi$',
        "Ο" => '$O$',  // \\Omicron ne fonctionne pas dans notre version de latex (2014)
        "ο" => '$o$',  // \\omicron ne fonctionne pas dans notre version de latex (2014)
        "ό" => "$\\'{o}$",  // TODO pas encore l'accent sans utiliser un mode Text special...ή
        "Π" => '$\\Pi$',
        "π" => '$\\pi$',
        "Ρ" => '$\\Rho$',
        "ρ" => '$\\rho$',
        "Σ" => '$\\Sigma$',
        "σ" => '$\\sigma$',
        "ς" => '$\\varsigma$',
        "Τ" => '$T$',
        "τ" => '$\\tau$',
        "Υ" => '$\\Upsilon$',
        "υ" => '$\\upsilon$',
        "ύ" => '$\\upsilon$',
        "Φ" => '$\\Phi$',
        "ϕ" => '$\\Phi$',
        "φ" => '$\\phi$',
        "Χ" => '$X$',
        "χ" => '$\\chi$',
        "Ψ" => '$\\Psi$',
        "ψ" => '$\\psi$',
        "Ω" => '$\\Omega$',
        "Ω" => '$\\Omega$', // different du precedent...
        "ω" => '$\\omega$',
        "ώ" => '$\\omega$', // TODO pas encore l'accent sans utiliser un mode Text special...
    ];
    /**
     * TODO:
     * Il y a un probleme avec _ < et >
     * Suivant que l'on soit en mode latex math ou pas, les carateres _ < > ne doivent pas etre traduit de la meme facon.
     * La difficulte est que l'on peut recevoir des chaines avec du latex ou en html
     */
    private static $_TRANS_CAR_LATEX = [
        //caracteres spéciaux latex à échaper
        "&" => "\\&",
        "#" => "\\#",
        // modif lfa car certains titres ont des caractères latex "%" => "\\%",
        // modif lfa car certains titres ont des caractères latex "$" => "\\$",
        // modif lfa car certains titres ont des caractères latex "^" => "\\^{}",
        // modif lfa car certains titres ont des caractères latex "~" => "\\~{}",
        // modif lfa car certains titres ont des caractères latex"{" => "\\{",
        // modif lfa car certains titres ont des caractères latex"}" => "\\}",
        // BUG si definie ici, alors HtmlToTex plante car il transforme les < > des tags avant de faire les tags
        // "<" => "\\textless{}",
        //">" => "\\textgreater{}",
        //exposants
        "°" => "{\\textdegree}",
        "²" => '${}^2$',
        "³" => '${}^3$',

        // Autre alphabet
        'ℵ' => '$\\aleph',
        //symboles mathématiques
        "ℜ" => "{\\Re}",
        "Ð" => "{\\DH}",
        "ð" => "{\\dh}",
        "Đ" => "{\\DJ}",
        "đ" => "{\\dj}",
        "Ɖ" => '$\\M{D}$',
        "ɖ" => '$\\M{d}$',
        "Þ" => "{\\TH}",
        "þ" => "{\\th}",
        "∀" => '$\\forall$',
        "∃" => '$\\exists$',
        "∅" => '$\\emptyset$',
        "∇" => '$\\nabla$',
        "∈" => '$\\in$',
        "∉" => '$\\notin$',
        "∋" => '$\\ni$',
        "−" => "--",
        "∞" => '$\\infty$',
        "∧" => '$\\wedge$',
        "∨" => '$\\vee$',
        "⋒" => '$\\doublecap$',
        "⋓" => '$\\doublecup$',
        "∼" => '$\\sim$',
        "≅" => '$\\cong$',
        "≈" => '$\\approx$',
        "≠" => '$\\ne$',
        "≡" => '$\\not\equiv$',
        "≢" => '$\\equiv$',
        "≤" => '$\\le$',
        "≪" => '$\\ll$',
        "≥" => '$\\ge$',
        "⊂" => '$\\subset$',
        "⊃" => '$\\sup$',
        "⊄" => '$\\notin$',
        "⊆" => '$\\subseteq$',
        "⊇" => '$\\supseteq$',
        "⊥" => '$\\perp$',
        "⋅" => '$\\cdot$',
        "·" => '$\\times$',
        "←" => '$\\leftarrow$',
        "↑" => '$\\uparrow$',
        "→" => '$\\rightarrow$',
        "↓" => '$\\downarrow$',
        "↔" => '$\\leftrightarrow$',
        "↵" => '$\\hookleftarrow$',
        "⇐" => '$\\Leftarrow$',
        "⇑" => '$\\Uparrow$',
        "⇒" => '$\\Rightarrow$',
        "⇓" => '$\\Downarrow$',
        '⊳' => '$\\triangleleft',
        "⇔" => '$\\Leftrightarrow$',
        "±" => '$\\pm$',
        "÷" => '$\\div$',
        "•" => '$\\bullet$',
        "∂" => '$\\partial$',
        "∏" => '$\\prod$',
        "∑" => '$\\sum$',
        "∗" => '$\\star$',
        "×" => 'x',
        "√" => '$\\sqrt$',
        "∧" => '$\\land$',
        "∨" => '$\\lor$',
        "∩" => '$\\cap$',
        "∪" => '$\\cup$',
        "∫" => '$\\int$',
        "∬" => '$\\iint$',
        "∭" => '$\\iiint$',
        "∮" => '$\\oint$',
        "⊕" => '$\\oplus$',
        "⊗" => '$\\otimes$',
        "⋆" => '$\\star$',
        '⁺' => '${}^{+}$',
        // caracteres spéciaux
        '"' => "''",
        "“" => "``",
        "”" => "''",
        "_" => "\\_",
        "—" => "-",
        "‐" => "-",
        "‒" => "-",
        "–" => "--",
        "―" => "--",
        "ʼ" => "'",
        "’" => "'",
        "′" => "'",
        "©" => "{\\copyright}",
        "§" => '\\S',
        "〈" => "\\textless{}",
        "〉" => "\\textgreater{}",
        //pour supprimer l accent special independant de la lettre
        "́" => "",
        "«" => "``",
        "»" => "''",
        "∘" => '$\\circ$',
        "é" => "{\\'e}",
        "É" => "{\\'E}",
        "è" => "{\\`e}",
        "È" => "{\\`E}",
        "ê" => "{\\^e}",
        "Ê" => "{\\^E}",
        "ë" => "{\\\"e}",
        "Ë" => "{\\\"E}",
        "î" => "{\\^i}",
        "Î" => "{\\^I}",
        "í" => "{\\'i}",
        "Í" => "{\\'I}",
        "ì" => "{\\`i}",
        "Ì" => "{\\`I}",
        "ï" => "{\\\"i}",
        "Ï" => "{\\\"I}",
        "à" => "{\\`a}",
        "ã" => "{\\~a}",
        "À" => "{\\`A}",
        "á" => "{\\'a}",
        "á" => "{\\'a}",
        "Á" => "{\\'A}",
        "â" => "{\\^a}",
        "Â" => "{\\^A}",
        "å" => "{\\aa}",
        "Å" => "{\\AA}",
        "ä" => "{\\\"a}",
        "Ä" => "{\\\"A}",
        "û" => "{\\^u}",
        "Û" => "{\\^U}",
        "ù" => "{\\`u}",
        "Ù" => "{\\`U}",
        "ú" => "{\\'u}",
        "Ú" => "{\\'U}",
        "ü" => "{\\\"u}",
        "Ü" => "{\\\"U}",
        "ô" => "{\\^o}",
        "Ô" => "{\\^O}",
        "ó" => "{\\'o}",
        "Ó" => "{\\'O}",
        "ò" => "{\\`o}",
        "Ò" => "{\\`O}",
        "ő" => "{\\\"o}",
        "ö" => "{\\\"o}",
        "Ö" => "{\\\"O}",
        "ø" => "{{\\o}}",
        "Ø" => "{{\\O}}",
        "ý" => "{\\'y}",
        "Ý" => "{\\'Y}",
        "ć" => "{\\'c}",
        "Ć" => "{\\'C}",
        "ǵ" => "{\\'g}",
        "Ǵ" => "{\\'G}",
        "ḱ" => "{\\'k}",
        "Ḱ" => "{\\'K}",
        "ĺ" => "{\\'l}",
        "Ĺ" => "{\\'L}",
        "ḿ" => "{\\'M}",
        "Ḿ" => "{\\'M}",
        "ń" => "{\\'n}",
        "ñ" => "{\\~n}",
        "Ń" => "{\\'N}",
        "ṕ" => "{\\'p}",
        "Ṕ" => "{\\'P}",
        "ŕ" => "{\\'r}",
        "Ŕ" => "{\\'R}",
        "ś" => "{\\'s}",
        "Ś" => "{\\'S}",
        "ẃ" => "{\\'w}",
        "Ẃ" => "{\\'W}",
        "ź" => "{\\'z}",
        "Ź" => "{\\'Z}",
        "ğ" => "{\\u g}",
        "Ğ" => "{\\u G}",
        // ajout des accents tchèque
        "č" => "{\\v c}",
        "Č" => "{\\v C}",
        "š" => "{\\v s}",
        "Š" => "{\\v S}",
        "ř" => "{\\v r}",
        "Ř" => "{\\v R}",
        "ž" => "{\\v z}",
        "Ž" => "{\\v Z}",
        "ě" => "{\\v e}",
        "Ě" => "{\\v E}",
        "ǎ" => "{\\v a}",
        "Ǎ" => "{\\v A}",
        "ǔ" => "{\\v u}",
        "Ǔ" => "{\\v U}",
        "ǒ" => "{\\v o}",
        "Ǒ" => "{\\v O}",
        #macron : lettre avec un trait dessus
        "ā" => "{\\=a}",
        "Ā" => "{\\=A}",
        "ē" => "{\\=e}",
        "Ē" => "{\\=E}",
        "ḡ" => "{\\=g}",
        "Ḡ" => "{\\=G}",
        "ī" => "{\\=i}",
        "Ī" => "{\\=I}",
        "ō" => "{\\=o}",
        "Ō" => "{\\=O}",
        "ū" => "{\\=u}",
        "Ū" => "{\\=U}",
        "ȳ" => "{\\=y}",
        "Ȳ" => "{\\=Y}",
        // Autres accents mal convertit
        "ă" => "{\\u a}",
        "Ă" => "{\\u a}",
        // accent windows
        "è" => "{\\`e}",
        "é" => "{\\'e}",
        "à" => "{\\`a}",
        "ů" => "{\\r u}",
        "Ů" => "{\\r U}",
        "ẙ" => "{\\r y}",
        "ẘ" => "{\\r w}",
        "ç" => "{\\c c}",
        "ç" => "{\\c c}",
        "ç̧" => "{\\c c}",
        "Ş" => "{\\c S}",
        "ş" => "{\\c s}",
        "Ç" => "{\\c C}",
        "ö̈l" => "{\\\"o}l",
        // double s allemand
        "ß" => "{\\ss}",
        //ligatures
        "ℓ" => "{\\ell}",
        "æ" => "{\\ae}",
        "Æ" => "{\\AE}",
        "œ" => "{\\oe}",
        "Œ" => "{\\OE}",
        "ﬁ" => "fi",
        "ﬀ" => "ff",
        "ﬂ" => "fl",
        "ﬃ" => "ffi",
        "ﬄ" => "ffl",
        "ł" => "l",
        "ı" => "{\\i}",
    ];
    private static $_PatternCarLatex = null;
    private static $_ReplaceCarLatex = null;
    private static $_PatternCarLatexGreek = null;
    private static $_ReplaceCarLatexGreek = null;
    private static $_PatternCarLatexGlobal = null;
    private static $_ReplaceCarLatexGlobal = null;

    /**
     * Formatage d'un utilisateur
     *
     * @param string $lastname
     * @param string $firstname
     * @return string
     */
    public static function formatUser($lastname = "", $firstname = ""): string
    {
        return self::formatAuthor($lastname, $firstname);
    }

    /**
     * Formatage d'un auteur
     *
     * @param string $lastname
     * @param string $firstname
     * @param string $civ
     * @return string
     */
    public static function formatAuthor($lastname = "", $firstname = "", $civ = ""): string
    {
        return trim((($civ && is_string($civ)) ? $civ . " " : "")
            . (($lastname && is_string($lastname)) ? self::upperWord($lastname) . " " : "")
            . (($firstname && is_string($firstname)) ? self::upperWord($firstname) : ""));
    }

    /**
     * Formatage d'une chaine texte : maj 1ère lettre
     *
     * @param string
     * @return string
     */
    public static function upperWord($string = ""): string
    {
        if ($string === '') {
            return '';
        }
        $string = mb_strtolower(Episciences_Tools::spaceCleaner($string));
        $res = mb_strtoupper(mb_substr($string, 0, 1));
        for ($i = 1, $iMax = mb_strlen($string); $i < $iMax; $i++) {
            $theIchar = mb_substr($string, ($i - 1), 1);
            if ($theIchar === "-" || $theIchar === "'" || $theIchar === " " || $theIchar === ".") {
                $res .= mb_strtoupper(mb_substr($string, $i, 1));
            } else {
                $res .= mb_substr($string, $i, 1);
            }
        }
        // Particules patronymiques
        /* française : de ou le d’ sont toujours en minuscules */
        //$res = str_replace('De ', 'de ', $res);
        $res = str_replace("D'", "d'", $res);
        /* allemandes : an, auf, von (der), zu */
        $res = str_replace('An ', 'an ', $res);
        $res = str_replace('Auf ', 'auf ', $res);
        $res = str_replace('Von Der ', 'von der ', $res);
        $res = str_replace('Von ', 'von ', $res);
        $res = str_replace('Zu ', 'zu ', $res);
        $res = str_replace("L'", "l'", $res);
        /* anglaise : of */
        $res = str_replace('Of ', 'of ', $res);
        /* espagnoles : de, del, de la, de los, de las, y */
        // $res = str_replace('De ', 'de ', $res);
        $res = str_replace('Del ', 'del ', $res);
        $res = str_replace('De La ', 'de la ', $res);
        $res = str_replace('De Los ', 'de los ', $res);
        $res = str_replace('De Las ', 'de las ', $res);
        $res = str_replace('Y ', 'y ', $res);
        /* néerlandaises aux Pays-Bas : de, den, t’, ten, ter, van (der/den) */
        //$res = str_replace('De ', 'de ', $res);
        $res = str_replace('Den ', 'den ', $res);
        $res = str_replace("T'", "t'", $res);
        $res = str_replace('Ten ', 'ten ', $res);
        $res = str_replace('Ter ', 'ter ', $res);
        $res = str_replace('Van Der ', 'van der ', $res);
        $res = str_replace('Van Den ', 'van den ', $res);
        $res = str_replace('Van ', 'van ', $res);
        /* portugaises : a, da, das, de, dos */
        $res = str_replace('A ', 'a ', $res);
        $res = str_replace('Da ', 'da ', $res);
        $res = str_replace('Das ', 'das ', $res);
        //$res = str_replace('De ', 'de ', $res);
        $res = str_replace('Dos ', 'dos ', $res);
        /* scandinaves : af, av, von */
        $res = str_replace('Af ', 'af ', $res);
        $res = str_replace('Av ', 'av ', $res);
        //$res = str_replace('Von ', 'von ', $res);
        // Les particules non composees
        $res = str_replace('De ', 'de ', $res);
        return $res;
    }

    /**
     * Clean whitespace and control characters from strings or arrays
     *
     * @deprecated Use Episciences_Tools::spaceCleaner() instead.
     *             This method will be removed in a future version.
     *             The new method provides better PHP 8.1+ compatibility and null handling.
     *
     * @param string|string[]|null $mixed The input to clean
     * @param bool $strip_br Whether to convert BR tags to spaces (default: true)
     * @param bool $allUtf8 Whether to remove all UTF-8 special whitespace characters (default: false)
     * @return string|string[]|null Cleaned string/array, or null if input was null
     *
     * @see Episciences_Tools::spaceCleaner() For the modern replacement method
     */
    public static function space_clean($mixed, $strip_br = true, $allUtf8=false) {
        // Trigger deprecation warning
        trigger_error(
            'Ccsd_Tools::space_clean() is deprecated. Use Episciences_Tools::spaceCleaner() instead.',
            E_USER_DEPRECATED
        );

        // Delegate to the new method
        return Episciences_Tools::spaceCleaner($mixed, $strip_br, $allUtf8);
    }

    /**
     * Convert BR tags to spaces in strings or arrays
     *
     * @param string|string[]|null $string The input string or array
     * @return string|string[]|null The string with BR tags replaced by spaces, or null if input was null
     */
    public static function br2space($string)
    {
        // Handle null explicitly to avoid PHP 8.1 deprecation warnings
        if ($string === null) {
            return null;
        }

        // Handle empty strings
        if ($string === '') {
            return '';
        }

        // Process the string
        return preg_replace("/<br[[:space:]]*\/?[[:space:]]*>/i", " ", $string);
    }


    /**
     * In an array of array, return the index of subarray that contain the $key => $needle item
     * If $all parameter is true, then return all indexes of subarrays that contain an item $key => $needle
     *
     * @param string $needle
     * @param array $array
     * @param string $key
     * @param bool $all
     * @return array|int|null|string
     */
    public static function in_next_array($needle, $array, $key, $all = false)
    {
        $out = [];
        if (is_array($array)) {
            foreach ($array as $k => $next) {
                if (is_array($next) && array_key_exists($key, $next) && $next [$key] === $needle) {
                    if ($all) {
                        $out [] = $k;
                    } else {
                        return $k;
                    }
                }
            }
        }
        return ($all) ? $out : null;
    }

    /**
     * This is a compatibility with previous writing function
     * Search for a regexp $needle in array and return the first index that matche
     * @param $string
     * @param $array
     * @return int|string
     */
    public static function preg_get_key($string, $array)
    {
        $res = self::preg_in_array_get_key($string, $array);
        return ($res == null ? -1 : $res);
    }

    /**
     * Search for a regexp $needle in array and return the first index that matche
     * You can specify a $begin prefix and $end suffix for the regexp
     * @param string $needle
     * @param array $array
     * @param string $begin
     * @param string $end
     * @return int|string|null
     */
    public static function preg_in_array_get_key($needle = "", $array = [], $begin = "", $end = "")
    {
        // T4 fix: use preg_quote for $needle to escape all regex metacharacters; $begin/$end remain literal regex context
        $pattern = '/' . str_replace('/', '\/', $begin) . preg_quote($needle, '/') . str_replace('/', '\/', $end) . '/';
        if (is_array($array)) {
            foreach ($array as $k => $val) {
                if (preg_match($pattern, $val)) {
                    return $k;
                }
            }
        }
        return null;
    }

    /**
     * This is a compatibility with previous writing function
     * Search for a regexp $needle in array and return true if found, false if not
     * You can specify a $begin prefix and $end suffix for the regexp
     * @param string $needle
     * @param array $array
     * @param string $begin
     * @param string $end
     * @return bool
     */
    public static function preg_in_array($needle = "", $array = [], $begin = "", $end = ""): bool
    {
        return self::preg_in_array_get_key($needle, $array, $begin , $end) !== null;
    }

    /**
     * Vérifier l'existence de $var
     *
     * @param mixed $var
     *            variable à tester
     * @param mixed $set
     *            valeur par défaut
     * @return mixed $var s'il existe $set sinon
     */
    public static function ifsetor(&$var, $set = '')
    {
        return $var ?? $set;
    }


    /**
     * Apply an XSLT transformation to an XML string.
     *
     * @param string $xmlStr The XML string to transform.
     * @param string $xslFile The path to the XSLT file.
     * @param array $params An array of parameters to pass to the XSLT processor.
     * @throws Exception If the XML data is empty or the XSLT file is not found or not readable.
     * @return string The result of the XSLT transformation.
     */
    public static function xslt($xmlStr, $xslFile, array $params = [])
    {
        $xml = new DOMDocument ();
        if (!$xmlStr) {
            throw new Exception('XML Data is empty');
        }
        if (!is_file($xslFile)) {
            throw new Exception(sprintf("File %s is not found or not readable", $xslFile));
        }

        set_error_handler('\Ccsd\Xml\Exception::HandleXmlError');
        $xml->loadXML($xmlStr);
        restore_error_handler();

        $xsl = new DOMDocument ();
        $xsl->load($xslFile);
        $proc = new XSLTProcessor ();
        $proc->registerPHPFunctions();
        foreach ($params as $key => $value) {
            $proc->setParameter('', $key, $value);
        }
        $proc->importStyleSheet($xsl);
        return $proc->transformToXML($xml);
    }


    /**
     * @param string $str
     * @param string $lang
     * @return string
     */
    public static function translate($str, $lang = null)
    {
        try {
            /** @var Zend_Translate_Adapter $translator */
            $translator = Zend_Registry::get('Zend_Translate');
            if ($lang === null) {
                $lang = Zend_Registry::get('lang');
            }

            if ($lang !== 'fr' && !$translator->isTranslated($str)) { // log missing translations unless and only if the language is not French
                error_log('Missing translation: ' . $str);
                return $str;
            }

            return $translator->translate($str, $lang);
        } catch (Zend_Exception $e) {
            Ccsd_Tools::panicMsg(__FILE__, __LINE__, "Zend registry: Zend_Translate not defined\n" . $e->getMessage());
            return $str;
        }
    }

    /**
     * Log function for case which can't arise but which arise...
     * @param $file
     * @param $line
     * @param $message
     */
    public static function panicMsg($file, $line, $message)
    {
        error_log("Panic: $file:$line - $message");
    }


    /**
     * Effectue une recherche xpath dans une chaine XML
     *
     * @param $xmlStr string
     *            XML
     * @param $tag string
     *            chemin à chercher
     * @return string[]|bool
     */
    public static function xpath($xmlStr, $tag)
    {
        try {
            $out = [];
            $xml = new DOMDocument();
            if (!$xmlStr || !$tag) {
                throw new Exception('pas de données');
            }
            set_error_handler('\Ccsd\Xml\Exception::HandleXmlError');
            $xml->loadXML($xmlStr);
            restore_error_handler();
            $xpath = new DOMXPath($xml);
            foreach (self::getNamespaces($xml->documentElement) as $id => $ns) {
                $xpath->registerNamespace($id, $ns);
            }
            foreach ($xpath->query($tag) as $entry) {
                $out[] = $entry->nodeValue;
            }
            if (count($out) === 1) {
                return $out[0];
            }

            if (count($out) === 0) {
                return false;
            }

            return $out;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retourne l'ensemble des espaces de nom d'un objet DomNode
     *
     * @param DomNode
     * @return array
     */
    public static function getNamespaces(DomNode $node)
    {
        $namespaces = [];
        static $cpt = [];
        if ($node->namespaceURI) {
            $namespace = $node->namespaceURI;
            $prefix = $node->lookupPrefix($namespace);
            $namespaces [($prefix) ?: 'xmlns'] = $namespace;
        }
        if ($node instanceof DomElement) {
            foreach ($node->childNodes as $child) {
                $dummy = [];
                foreach (self::getNamespaces($child) as $code => $uri) {
                    if (!in_array($uri, $namespaces)) {
                        if (array_key_exists($code, $namespaces)) {
                            if (array_key_exists($code, $cpt)) {
                                $cpt[$code]++;
                            } else {
                                $cpt[$code] = 1;
                            }
                            $dummy[$code . $cpt [$code]] = $uri;
                        } else {
                            $dummy[$code] = $uri;
                        }
                    }
                }
                $namespaces = array_merge($namespaces, $dummy);
            }
        }
        return $namespaces;
    }

    /**
     * Transforme un objet DOMDocument|DOMElement en tableau php
     * Le DOM corresponds a un fichier XML/JSON: un contenu est soit textuel, soit avec sous noeud
     * (Pas de sous noeud de type texte (ils sont ignores))
     * Un noeud ayant un contenu textuel mais ayant des attributs aura sa valeur textuelle dans la clef _valeur
     * Si pas d'attributs, alors la valeur sera directement dans la valeur du noeud
     * @param $root DOMDocument|DOMElement
     *            à transformer
     * @return array|string  Si le noeud est un noeud texte sans attribut: le retour est une chaine
     *
     * Note BM: on ne sais pas si un noeud est multivalue...  a l'appelant de faire gaffe !!!
     */
    public static function dom2array($root)
    {
        $result = [];
        if ($root->hasAttributes()) {
            foreach ($root->attributes as $i => $attr) {
                $result [$attr->name] = $attr->value;
            }
        }
        $children = $root->childNodes;
        if ($children) {
            if ($children->length == 1) {
                $child = $children->item(0);
                if ($child->nodeType == XML_TEXT_NODE) {
                    $result ['_value'] = trim($child->nodeValue);
                    if (count($result) == 1) {
                        // Avec attributs
                        return $result['_value'];
                    }
                    return $result;
                }
            }
            $group = [];
            for ($i = 0; $i < $children->length; $i++) {
                $child = $children->item($i);
                if ($child->nodeType == XML_ELEMENT_NODE) {
                    /** @var DOMElement $child */
                    if (!isset($result [$child->nodeName])) {
                        // Noeuds pour l';instant monovalue
                        $result[$child->nodeName] = self::dom2array($child);
                    } else {
                        // Un meme noeud repete:
                        if (!isset($group [$child->nodeName])) {
                            // Si premiere repetition, on transforme la valeur en tableau
                            $tmp = $result [$child->nodeName];
                            $result[$child->nodeName] = [$tmp];
                            $group [$child->nodeName] = 1;
                        }
                        // Puis on ajoute le noeud a la liste des noeuds
                        $result[$child->nodeName][] = self::dom2array($child);
                    }
                }
                // Else: les noeuds textes sont ignores
            }
        }
        return $result;
    }

    /**
     * Return a unique filename
     * @param string $filename
     * @param string $dir
     * @return string
     */
    public static function getNewFileName(string $filename, string $dir): string
    {

        $filename = self::cleanFileName($filename);
        $filesystem = new Filesystem();

        try {
            $filenameUniq = $filesystem->tempnam($dir, '', '-' . $filename);
        } catch (IOException $IOException) {
            return '';
        }

        return basename($filenameUniq);
    }

    /**
     * Nettoie le nom d'un fichier
     *
     * @param string
     * @return string
     */
    public static function cleanFileName($filename = ''): string
    {
        $filename = preg_replace('/[^a-z0-9_\.-\/\\\\]/i', '_', self::spaces2Space(self::stripAccents(($filename))));
        $filename = preg_replace("~\.\.*~", ".", $filename);
        return preg_replace("/__*/", "_", $filename);
    }

    /**
     * Retire les espaces mutiples
     *
     * @param string
     * @return string
     */
    public static function spaces2Space($string): string
    {
        return preg_replace("/  +/", " ", $string);
    }

    /**
     * Retire les accents de la chaine passée en parametre
     *
     * @param $text string
     * @return string
     */
    public static function stripAccents($text): string
    {
        $text = str_replace(
            ['æ', 'Æ', 'œ', 'Œ', 'ý', 'ÿ', 'Ý', 'ç', 'Ç', 'ñ', 'Ñ'], ['ae', 'AE', 'oe', 'OE', 'y', 'y', 'Y', 'c', 'C', 'n', 'N'], $text);
        $text = preg_replace("/[éèëêě]/u", "e", $text);
        $text = preg_replace("/[ÈÉÊË]/u", "E", $text);
        $text = preg_replace("/[àâäáãå]/u", "a", $text);
        $text = preg_replace("/[ÀÁÂÃÄÅ]/u", "A", $text);
        $text = preg_replace("/[ïîíì]/u", "i", $text);
        $text = preg_replace("/[ÌÍÎÏ]/u", "I", $text);
        $text = preg_replace("/[üûùú]/u", "u", $text);
        $text = preg_replace("/[ÙÚÛÜ]/u", "U", $text);
        $text = preg_replace("/[ôöóòõø]/u", "o", $text);
        $text = preg_replace("/[ÒÓÔÕÖØ]/u", "O", $text);
        $text = str_replace(
            ['č', 'ć', 'ň', 'ř', 'ť', 'ž', 'š', 'Č', 'Ć', 'Ň', 'Ř', 'Ť', 'Ž', 'Š',],
            ['c', 'c', 'n', 'r', 't', 'z', 's', 'C', 'C', 'N', 'R', 'T', 'Z', 'S',], $text);
        return $text;
    }


    /**
     * @param string $text
     * @return string
     */
    public static function clear_nl($text): string
    {
        $text = str_replace(["\n", "\r"], "", $text);
        return ($text);
    }

    /**
     * @param $string
     * @param int $length
     * @param string $replacement
     * @return mixed
     */
    public static function truncate($string, int $length = 100, string $replacement = "...")
    {
        if (mb_strlen($string, 'UTF-8') <= $length) {
            return $string;
        }

        // problème d'encodage avec "substr_replace" : on se retrouve avec des articles sans titre voir ici : https://slovo.episciences.org/volume/edit?id=652 (#9686)

        return mb_substr($string, 0, $length,'UTF-8') . $replacement;

    }

    /**
     * Un in_array récursif permettant de parcourir un tableau multidimentionnel
     *
     * @param mixed $mixed
     * @param array $array
     * @param bool $strict
     * @return bool
     */
    public static function in_array_r($mixed, $array, $strict = false): bool
    {
        foreach ($array as $item) {
            if (($strict ? $item === $mixed : $item == $mixed) || (is_array($item) && self::in_array_r($mixed, $item, $strict))) {
                return true;
            }
        }

        return false;
    }
    /**
     *
     * Curl sur le serveur de requêtes de solr
     * Exemple : Ccsd_Tools::solrCurl('q=*:*&wt=json','ref_journal');
     *
     * @param string $queryString solr query
     * @param string $core solr core
     * @param string $handler solr handler
     * @param int $timeout curl request timeout in seconds
     * @return bool|string
     * @throws Exception
     */
    public static function solrCurl(string $queryString, string $core = 'episciences', string $handler = 'select', int $timeout = 40)
    {
        $options = [];
        // Doit être définit dans l'application cliente
        if (defined('APPLICATION_ENV')) {
            $options ['env'] = APPLICATION_ENV;
        }

        $options ['core'] = $core;
        $options ['handler'] = $handler;

        $s = new Ccsd_Search_Solr($options);

        $endPointUrl = $s->getEndPointUrl();
        $endPointUrl .= '?';
        $endPointUrl .= $queryString;


        $curlHandler = curl_init();
        $curlUserAgent = DOMAIN;
        curl_setopt($curlHandler, CURLOPT_USERAGENT, $curlUserAgent);
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandler, CURLOPT_CONNECTTIMEOUT, 15); // timeout in seconds

        curl_setopt($curlHandler, CURLOPT_URL, $endPointUrl);
        curl_setopt($curlHandler, CURLOPT_TIMEOUT, $timeout); // timeout in seconds

        $info = curl_exec($curlHandler);

        if (curl_errno($curlHandler) === CURLE_OK) {
            return $info;
        }

        $errno = curl_errno($curlHandler);
        $error_message = curl_strerror($errno) . '. Query: ' . $queryString;
        curl_close($curlHandler);
        throw new Exception("cURL error ({$errno}): {$error_message}", $errno);
    }



    /**
     * @param string $text
     * @return string
     */
    public static function protectLatex($text): string
    {
        /* lfarhi : on ne met pas $,^,~,_ { et } car il y a du latex dans certains champs avec ces caractères
         * */
        return str_replace(["&", "#", "%"], ["\\&", "\\#", "\\%"], $text);
    }



    /**
     * @param string $text
     * @param bool $doGreek
     * @return string
     */
    public static function htmlToTex(string $text, bool $doGreek = true): string
    {
        // Pour vrais < il faut absoluement un espace apres afin de ne pas etre retire par le strip_tags
        // formule math avec <
        $text = preg_replace("/<([^[:alpha:]\/])/", '&lt;\\1', $text);
        // strip_tags
        $text = trim(strip_tags($text, '<br><i><b><sup><s><sub><span><u><p><ul><li><NNT>'));
        // balise html
        $text = preg_replace('~<span style="font-weight: *bold;">([^<]+)</span>~i', '\textbf{\\1}', $text);
        $text = preg_replace('~<span style="font-style: *italic;">([^<]+)</span>~i', '\textit{\\1}', $text);
        $text = preg_replace('~<span style="text-decoration: *underline;">([^<]+)</span>~i', '\underline{\\1}', $text);
        $text = preg_replace('~<span style="text-decoration: *line-through;">([^<]+)</span>~i', '\sout{\\1}', $text);
        $text = preg_replace('~<br\s*/?>~i', '\\\\\\', $text);
        $text = preg_replace('~<ul>~i', '\begin{itemize}', $text);
        $text = preg_replace('~</ul>~i', '\end{itemize}', $text);
        $text = preg_replace('~<li( [^>]*)*>([^<]+)</li>~i', '\item[$\bullet$] \\2', $text);
        $text = preg_replace('~<i( [^>]*)*>([^<]+)</i>~i', '\textit{\\2}', $text);
        $text = preg_replace('~<b( [^>]*)*>([^<]+)</b>~i', '\textbf{\\2}', $text);
        $text = preg_replace('~<u( [^>]*)*>([^<]+)</u>~i', '\underline{\\2}', $text);
        $text = preg_replace('~<s( [^>]*)*>([^<]+)</s>~i', '\sout{\\2}', $text);
        $text = preg_replace('~<sup>([^<]+)</sup>~i', '\textsuperscript{\\1}', $text);
        $text = preg_replace('~<sub>([^<]+)</sub>~i', '\textsubscr{\\1}', $text);

        $text = strip_tags($text, '<p><NNT>');
        $text = preg_replace('~\s+~i', ' ', $text);
        $text = preg_replace('~^<p( [^>]*)*>(.*)</p>$~i', '\2', $text);
        $text = preg_replace('~<p( [^>]*)*>\s*</p>~i', '', $text);
        $text = preg_replace('~<p( [^>]*)*>([^<]+)</p>~i', '\\\\\\\ \2 \\\\\\', $text);
        // Traitement des espaces insecables avant les ponctuations
        $text = preg_replace('~ ([:;!?])~', '\,\\1', $text);
        $text = str_replace('&lt;', '<', $text);
        // protection
        $text = self::decodeLatex(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), $doGreek);

        return $text;
    }

    /**
     * @param string $text
     * @return string
     */
    public static function decodeLatex(string $text, $greekRecode = true): string
    {

        if (!empty($text)) {
            //si le tableau des patern n'a jamais été généré, on le fait
            if (self::$_PatternCarLatex == null) {//n'a pas encore ete genere
                foreach (self::$_TRANS_CAR_LATEX as $car_utf8 => $latex) {
                    self::$_PatternCarLatex[] = $car_utf8;
                    self::$_ReplaceCarLatex[] = $latex;
                }
            }
            if (self::$_PatternCarLatexGreek == null) {//n'a pas encore ete genere
                foreach (self::$_TRANS_CAR_LATEX_GREEK as $car_utf8 => $latex) {
                    self::$_PatternCarLatexGreek[] = $car_utf8;
                    self::$_ReplaceCarLatexGreek[] = $latex;
                }
                self::$_PatternCarLatexGlobal = array_merge(self::$_PatternCarLatexGreek, self::$_PatternCarLatex);
                self::$_ReplaceCarLatexGlobal = array_merge(self::$_ReplaceCarLatexGreek, self::$_ReplaceCarLatex);
            }
            if ($greekRecode) {
                return str_replace(self::$_PatternCarLatexGlobal, self::$_ReplaceCarLatexGlobal, $text);
            }

            return str_replace(self::$_PatternCarLatex, self::$_ReplaceCarLatex, $text);
        }

        return $text;
    }

    /**
     * Supprime toute les valeurs correspondant a la valeur de filter
     * Et supprime toute les clefs dont la valeur est un tableau vide
     *
     * @param $input array
     * @param mixed $filter
     * @return mixed
     */
    public static function filter_multiarray(&$input, $filter = '')
    {
        if (is_array($input)) {
            foreach ($input as $key => &$value) {

                if (!is_array($value) && $value === $filter) {
                    unset($input [$key]);
                }

                if (is_array($value) && count($value)) {
                    self::filter_multiarray($value, $filter);
                }

                if (is_array($value) && !count($value)) {
                    unset($input [$key]);
                }
            }
        }

        return $input;
    }

    /**
     * Détecte si le script est executé depuis la CLI
     *
     * @return boolean true si CLI false si HTTP
     */
    public static function isFromCli(): bool
    {
        return !isset($_SERVER ['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER ['argc']) && $_SERVER ['argc'] > 0));
    }


    /**
     * @param string $url
     * @param array $options
     * @return bool|string
     * @throws Ccsd_Error
     */
    public static function curlSourceGetContents(string $url, array $options = [])
    {

        // S2 fix: block non-HTTP(S) schemes to prevent SSRF via file://, gopher://, etc.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new Ccsd_Error('Only http and https URLs are allowed', 0, 'CURL_ERROR');
        }

        $curlUserAgent = 'CcsdToolsCurl';

        $defaultOptions = [
            CURLOPT_URL => $url, // L'URL à récupérer
            CURLOPT_HEADER => 0, // TRUE pour inclure l'en-tête dans la valeur de retour.
            CURLOPT_RETURNTRANSFER => 1, // TRUE pour retourner le transfert en tant que chaîne de caractères de la valeur retournée par curl_exec() au lieu de l'afficher directement.
            CURLOPT_CONNECTTIMEOUT => 15, // Le nombre de secondes à attendre durant la tentative de connexion
            CURLOPT_TIMEOUT => self::DEFAULT_CURL_OPT_TIMEOUT, // Le temps maximum d'exécution de la fonction cURL exprimé en secondes
            CURLOPT_USERAGENT => $curlUserAgent, // Le contenu de l'en-tête "User-Agent: " à utiliser dans une requête HTTP.
            CURLOPT_FOLLOWLOCATION => true // TRUE pour suivre tous les en-têtes "Location: " que le serveur envoie dans les en-têtes HTTP (à noter que ceci est récursif, PHP suivra tous les en-têtes "Location: " qui lui sont envoyés à moins que CURLOPT_MAXREDIRS ne soit définie).
        ];

        $options = !empty($options) ? $options + $defaultOptions : $defaultOptions;

        $curlResource = curl_init();

        curl_setopt_array($curlResource, $options);
        $response = curl_exec($curlResource);
        $errorCode = curl_errno($curlResource);
        if ($errorCode === CURLE_OK) {
            curl_close($curlResource);
            return $response;
        }

        $error_message = curl_strerror($errorCode);
        curl_close($curlResource);
        throw new Ccsd_Error($error_message, $errorCode, 'CURL_ERROR');
    }

    /**
     * Génération du mot de passe d'un compte
     *
     * @param int $min
     *            nombre min de caractères
     * @param int $max
     *            nombre max de caractères
     * @return string
     * @throws Exception
     */
    public static function generatePw($min = 10, $max = 15)
    {
        $pass = "";
        $nbchar = random_int($min, $max);
        $chars = ['#', '&', '@', '?', '!', '%',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        for ($i = 0; $i < $nbchar; $i++) {
            $pass .= $chars[random_int(0, count($chars) - 1)];
        }
        return $pass;
    }

}
