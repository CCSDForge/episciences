<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Translate
 * @subpackage Ressource
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id:$
 */

/**
 * EN-Revision: 22668
 */
return [
    // Zend_Validate_Alnum
    "Invalid type given. String, integer or float expected" => "Invalid type given. String, integer or float expected",
    "'%value%' contains characters which are non alphabetic and no digits" => "'%value%' contains characters which are non alphabetic and no digits",

    // Zend_Validate_Alpha
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' contains non alphabetic characters" => "'%value%' contains non alphabetic characters",

    // Zend_Validate_Barcode
    "'%value%' failed checksum validation" => "'%value%' failed checksum validation",
    "'%value%' contains invalid characters" => "'%value%' contains invalid characters",
    "'%value%' should have a length of %length% characters" => "'%value%' should have a length of %length% characters",

    // Zend_Validate_Between
    "'%value%' is not between '%min%' and '%max%', inclusively" => "'%value%' is not between '%min%' and '%max%', inclusively",
    "'%value%' is not strictly between '%min%' and '%max%'" => "'%value%' is not strictly between '%min%' and '%max%'",

    // Zend_Validate_Callback
    "'%value%' is not valid" => "'%value%' is not valid",
    "An exception has been raised within the callback" => "An exception has been raised within the callback",

    // Zend_Validate_Ccnum
    "'%value%' must contain between 13 and 19 digits" => "'%value%' must contain between 13 and 19 digits",
    "Luhn algorithm (mod-10 checksum) failed on '%value%'" => "Luhn algorithm (mod-10 checksum) failed on '%value%'",

    // Zend_Validate_CreditCard
    "'%value%' seems to contain an invalid checksum" => "'%value%' seems to contain an invalid checksum",
    "'%value%' contains an invalid amount of digits" => "'%value%' contains an invalid amount of digits",
    "'%value%' is not from an allowed institute" => "'%value%' is not from an allowed institute",
    "'%value%' seems to be an invalid creditcard number" => "'%value%' seems to be an invalid creditcard number",
    "An exception has been raised while validating '%value%'" => "An exception has been raised while validating '%value%'",

    // Zend_Validate_Date
    "Invalid type given. String, integer, array or Zend_Date expected" => "Invalid type given. String, integer, array or Zend_Date expected",
    "'%value%' does not appear to be a valid date" => "'%value%' does not appear to be a valid date",
    "'%value%' does not fit the date format '%format%'" => "'%value%' does not fit the date format '%format%'",

    // Zend_Validate_Db_Abstract
    "No record matching '%value%' was found" => "No record matching '%value%' was found",
    "A record matching '%value%' was found" => "A record matching '%value%' was found",

    // Zend_Validate_Digits
    "Invalid type given. String, integer or float expected" => "Invalid type given. String, integer or float expected",
    "'%value%' must contain only digits" => "'%value%' must contain only digits",
    "'%value%' is an empty string" => "'%value%' is an empty string",

    // Zend_Validate_EmailAddress
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' is not a valid email address in the basic format local-part@hostname" => "'%value%' is not a valid email address in the basic format local-part@hostname",
    "'%hostname%' is not a valid hostname for email address '%value%'" => "'%hostname%' is not a valid hostname for email address '%value%'",
    "'%hostname%' does not appear to have a valid MX record for the email address '%value%'" => "'%hostname%' does not appear to have a valid MX record for the email address '%value%'",
    "'%hostname%' is not in a routable network segment. The email address '%value%' should not be resolved from public network" => "'%hostname%' is not in a routable network segment. The email address '%value%' should not be resolved from public network",
    "'%localPart%' can not be matched against dot-atom format" => "'%localPart%' can not be matched against dot-atom format",
    "'%localPart%' can not be matched against quoted-string format" => "'%localPart%' can not be matched against quoted-string format",
    "'%localPart%' is not a valid local part for email address '%value%'" => "'%localPart%' is not a valid local part for email address '%value%'",
    "'%value%' exceeds the allowed length" => "'%value%' exceeds the allowed length",

    // Zend_Validate_File_Count
    "Too many files, maximum '%max%' are allowed but '%count%' are given" => "Too many files, maximum '%max%' are allowed but '%count%' are given",
    "Too few files, minimum '%min%' are expected but '%count%' are given" => "Too few files, minimum '%min%' are expected but '%count%' are given",

    // Zend_Validate_File_Crc32
    "File '%value%' does not match the given crc32 hashes" => "File '%value%' does not match the given crc32 hashes",
    "A crc32 hash could not be evaluated for the given file" => "A crc32 hash could not be evaluated for the given file",

    // Zend_Validate_File_ExcludeExtension
    "File '%value%' has a false extension" => "File '%value%' has a false extension",

    // Zend_Validate_File_ExcludeMimeType
    "File '%value%' has a false mimetype of '%type%'" => "File '%value%' has a false mimetype of '%type%'",

    // Zend_Validate_File_Exists
    "File '%value%' does not exist" => "File '%value%' does not exist",

    // Zend_Validate_File_Extension
    "File '%value%' has a false extension" => "File '%value%' has a false extension",

    // Zend_Validate_File_FilesSize
    "All files in sum should have a maximum size of '%max%' but '%size%' were detected" => "All files in sum should have a maximum size of '%max%' but '%size%' were detected",
    "All files in sum should have a minimum size of '%min%' but '%size%' were detected" => "All files in sum should have a minimum size of '%min%' but '%size%' were detected",
    "One or more files can not be read" => "One or more files can not be read",

    // Zend_Validate_File_Hash
    "File '%value%' does not match the given hashes" => "File '%value%' does not match the given hashes",
    "A hash could not be evaluated for the given file" => "A hash could not be evaluated for the given file",

    // Zend_Validate_File_ImageSize
    "Maximum allowed width for image '%value%' should be '%maxwidth%' but '%width%' detected" => "Maximum allowed width for image '%value%' should be '%maxwidth%' but '%width%' detected",
    "Minimum expected width for image '%value%' should be '%minwidth%' but '%width%' detected" => "Minimum expected width for image '%value%' should be '%minwidth%' but '%width%' detected",
    "Maximum allowed height for image '%value%' should be '%maxheight%' but '%height%' detected" => "Maximum allowed height for image '%value%' should be '%maxheight%' but '%height%' detected",
    "Minimum expected height for image '%value%' should be '%minheight%' but '%height%' detected" => "Minimum expected height for image '%value%' should be '%minheight%' but '%height%' detected",
    "The size of image '%value%' could not be detected" => "The size of image '%value%' could not be detected",

    // Zend_Validate_File_IsCompressed
    "File '%value%' is not compressed, '%type%' detected" => "File '%value%' is not compressed, '%type%' detected",

    // Zend_Validate_File_IsImage
    "File '%value%' is no image, '%type%' detected" => "File '%value%' is no image, '%type%' detected",

    // Zend_Validate_File_Md5
    "File '%value%' does not match the given md5 hashes" => "File '%value%' does not match the given md5 hashes",
    "A md5 hash could not be evaluated for the given file" => "A md5 hash could not be evaluated for the given file",

    // Zend_Validate_File_MimeType
    "File '%value%' has a false mimetype of '%type%'" => "File '%value%' has a false mimetype of '%type%'",
    "The mimetype of file '%value%' could not be detected" => "The mimetype of file '%value%' could not be detected",

    // Zend_Validate_File_NotExists
    "File '%value%' exists" => "File '%value%' exists",

    // Zend_Validate_File_Sha1
    "File '%value%' does not match the given sha1 hashes" => "File '%value%' does not match the given sha1 hashes",
    "A sha1 hash could not be evaluated for the given file" => "A sha1 hash could not be evaluated for the given file",

    // Zend_Validate_File_Size
    "Maximum allowed size for file '%value%' is '%max%' but '%size%' detected" => "Maximum allowed size for file '%value%' is '%max%' but '%size%' detected",
    "Minimum expected size for file '%value%' is '%min%' but '%size%' detected" => "Minimum expected size for file '%value%' is '%min%' but '%size%' detected",

    // Zend_Validate_File_Upload
    "File '%value%' exceeds the defined ini size" => "File '%value%' exceeds the defined ini size",
    "File '%value%' exceeds the defined form size" => "File '%value%' exceeds the defined form size",
    "File '%value%' was only partially uploaded" => "File '%value%' was only partially uploaded",
    "File '%value%' was not uploaded" => "File '%value%' was not uploaded",
    "No temporary directory was found for file '%value%'" => "No temporary directory was found for file '%value%'",
    "File '%value%' can't be written" => "File '%value%' can't be written",
    "A PHP extension returned an error while uploading the file '%value%'" => "A PHP extension returned an error while uploading the file '%value%'",
    "File '%value%' was illegally uploaded. This could be a possible attack" => "File '%value%' was illegally uploaded. This could be a possible attack",
    "File '%value%' was not found" => "File '%value%' was not found",
    "Unknown error while uploading file '%value%'" => "Unknown error while uploading file '%value%'",

    // Zend_Validate_File_WordCount
    "Too much words, maximum '%max%' are allowed but '%count%' were counted" => "Too much words, maximum '%max%' are allowed but '%count%' were counted",
    "Too less words, minimum '%min%' are expected but '%count%' were counted" => "Too less words, minimum '%min%' are expected but '%count%' were counted",
    "File '%value%' is not readable or does not exist" => "File '%value%' is not readable or does not exist",

    // Zend_Validate_Float
    "Invalid type given. String, integer or float expected" => "Invalid type given. String, integer or float expected",
    "'%value%' does not appear to be a float" => "'%value%' does not appear to be a float",

    // Zend_Validate_GreaterThan
    "'%value%' is not greater than '%min%'" => "'%value%' is not greater than '%min%'",

    // Zend_Validate_Hex
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' has not only hexadecimal digit characters" => "'%value%' has not only hexadecimal digit characters",

    // Zend_Validate_Hostname
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' appears to be an IP address, but IP addresses are not allowed" => "'%value%' appears to be an IP address, but IP addresses are not allowed",
    "'%value%' appears to be a DNS hostname but cannot match TLD against known list" => "'%value%' appears to be a DNS hostname but cannot match TLD against known list",
    "'%value%' appears to be a DNS hostname but contains a dash in an invalid position" => "'%value%' appears to be a DNS hostname but contains a dash in an invalid position",
    "'%value%' appears to be a DNS hostname but cannot match against hostname schema for TLD '%tld%'" => "'%value%' appears to be a DNS hostname but cannot match against hostname schema for TLD '%tld%'",
    "'%value%' appears to be a DNS hostname but cannot extract TLD part" => "'%value%' appears to be a DNS hostname but cannot extract TLD part",
    "'%value%' does not match the expected structure for a DNS hostname" => "'%value%' does not match the expected structure for a DNS hostname",
    "'%value%' does not appear to be a valid local network name" => "'%value%' does not appear to be a valid local network name",
    "'%value%' appears to be a local network name but local network names are not allowed" => "'%value%' appears to be a local network name but local network names are not allowed",
    "'%value%' appears to be a DNS hostname but the given punycode notation cannot be decoded" => "'%value%' appears to be a DNS hostname but the given punycode notation cannot be decoded",

    // Zend_Validate_Iban
    "Unknown country within the IBAN '%value%'" => "Unknown country within the IBAN '%value%'",
    "'%value%' has a false IBAN format" => "'%value%' has a false IBAN format",
    "'%value%' has failed the IBAN check" => "'%value%' has failed the IBAN check",

    // Zend_Validate_Identical
    "The two given tokens do not match" => "The two given tokens do not match",
    "No token was provided to match against" => "No token was provided to match against",

    // Zend_Validate_InArray
    "'%value%' was not found in the haystack" => "'%value%' was not found in the haystack",

    // Zend_Validate_Int
    "Invalid type given. String or integer expected" => "Invalid type given. String or integer expected",
    "'%value%' does not appear to be an integer" => "'%value%' does not appear to be an integer",

    // Zend_Validate_Ip
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' does not appear to be a valid IP address" => "'%value%' does not appear to be a valid IP address",

    // Zend_Validate_Isbn
    "Invalid type given. String or integer expected" => "Invalid type given. String or integer expected",
    "'%value%' is not a valid ISBN number" => "'%value%' is not a valid ISBN number",

    // Zend_Validate_LessThan
    "'%value%' is not less than '%max%'" => "'%value%' is not less than '%max%'",

    // Zend_Validate_NotEmpty
    "Invalid type given. String, integer, float, boolean or array expected" => "Invalid type given. String, integer, float, boolean or array expected",
    "Value is required and can't be empty" => "Value is required and can't be empty",

    // Zend_Validate_PostCode
    "Invalid type given. String or integer expected" => "Invalid type given. String or integer expected",
    "'%value%' does not appear to be a postal code" => "'%value%' does not appear to be a postal code",

    // Zend_Validate_Regex
    "Invalid type given. String, integer or float expected" => "Invalid type given. String, integer or float expected",
    "'%value%' does not match against pattern '%pattern%'" => "'%value%' does not match against pattern '%pattern%'",
    "There was an internal error while using the pattern '%pattern%'" => "There was an internal error while using the pattern '%pattern%'",

    // Zend_Validate_Sitemap_Changefreq
    "'%value%' is not a valid sitemap changefreq" => "'%value%' is not a valid sitemap changefreq",

    // Zend_Validate_Sitemap_Lastmod
    "'%value%' is not a valid sitemap lastmod" => "'%value%' is not a valid sitemap lastmod",

    // Zend_Validate_Sitemap_Loc
    "'%value%' is not a valid sitemap location" => "'%value%' is not a valid sitemap location",
    "Invalid type given. String expected" => "Invalid type given. String expected",

    // Zend_Validate_Sitemap_Priority
    "'%value%' is not a valid sitemap priority" => "'%value%' is not a valid sitemap priority",
    "Invalid type given. Numeric string, integer or float expected" => "Invalid type given. Numeric string, integer or float expected",

    // Zend_Validate_StringLength
    "Invalid type given. String expected" => "Invalid type given. String expected",
    "'%value%' is less than %min% characters long" => "'%value%' is less than %min% characters long",
    "'%value%' is more than %max% characters long" => "'%value%' is more than %max% characters long",

    /**
     * Zend languages with prefix
     */

    "lang_aa" => "Afar",
    "lang_ab" => "Abkhazian",
    "lang_ace" => "Achinese",
    "lang_ach" => "Acoli",
    "lang_ada" => "Adangme",
    "lang_ady" => "Adyghe",
    "lang_ae" => "Avestan",
    "lang_af" => "Afrikaans",
    "lang_afa" => "Afro-Asiatic Language",
    "lang_afh" => "Afrihili",
    "lang_ain" => "Ainu",
    "lang_ak" => "Akan",
    "lang_akk" => "Akkadian",
    "lang_ale" => "Aleut",
    "lang_alg" => "Algonquian Language",
    "lang_alt" => "Southern Altai",
    "lang_am" => "Amharic",
    "lang_an" => "Aragonese",
    "lang_ang" => "Old English",
    "lang_anp" => "Angika",
    "lang_apa" => "Apache Language",
    "lang_ar" => "Arabic",
    "lang_arc" => "Aramaic",
    "lang_arn" => "Araucanian",
    "lang_arp" => "Arapaho",
    "lang_art" => "Artificial Language",
    "lang_arw" => "Arawak",
    "lang_as" => "Assamese",
    "lang_ast" => "Asturian",
    "lang_ath" => "Athapascan Language",
    "lang_aus" => "Australian Language",
    "lang_av" => "Avaric",
    "lang_awa" => "Awadhi",
    "lang_ay" => "Aymara",
    "lang_az" => "Azerbaijani",
    "lang_ba" => "Bashkir",
    "lang_bad" => "Banda",
    "lang_bai" => "Bamileke Language",
    "lang_bal" => "Baluchi",
    "lang_ban" => "Balinese",
    "lang_bas" => "Basa",
    "lang_bat" => "Baltic Language",
    "lang_be" => "Belarusian",
    "lang_bej" => "Beja",
    "lang_bem" => "Bemba",
    "lang_ber" => "Berber",
    "lang_bg" => "Bulgarian",
    "lang_bh" => "Bihari",
    "lang_bho" => "Bhojpuri",
    "lang_bi" => "Bislama",
    "lang_bik" => "Bikol",
    "lang_bin" => "Bini",
    "lang_bla" => "Siksika",
    "lang_bm" => "Bambara",
    "lang_bn" => "Bengali",
    "lang_bnt" => "Bantu",
    "lang_bo" => "Tibetan",
    "lang_br" => "Breton",
    "lang_bra" => "Braj",
    "lang_bs" => "Bosnian",
    "lang_btk" => "Batak",
    "lang_bua" => "Buriat",
    "lang_bug" => "Buginese",
    "lang_byn" => "Blin",
    "lang_ca" => "Catalan",
    "lang_cad" => "Caddo",
    "lang_cai" => "Central American Indian Language",
    "lang_car" => "Carib",
    "lang_cau" => "Caucasian Language",
    "lang_cch" => "Atsam",
    "lang_ce" => "Chechen",
    "lang_ceb" => "Cebuano",
    "lang_cel" => "Celtic Language",
    "lang_ch" => "Chamorro",
    "lang_chb" => "Chibcha",
    "lang_chg" => "Chagatai",
    "lang_chk" => "Chuukese",
    "lang_chm" => "Mari",
    "lang_chn" => "Chinook Jargon",
    "lang_cho" => "Choctaw",
    "lang_chp" => "Chipewyan",
    "lang_chr" => "Cherokee",
    "lang_chy" => "Cheyenne",
    "lang_cmc" => "Chamic Language",
    "lang_co" => "Corsican",
    "lang_cop" => "Coptic",
    "lang_cpe" => "English-based Creole or Pidgin",
    "lang_cpf" => "French-based Creole or Pidgin",
    "lang_cpp" => "Portuguese-based Creole or Pidgin",
    "lang_cr" => "Cree",
    "lang_crh" => "Crimean Turkish",
    "lang_crp" => "Creole or Pidgin",
    "lang_cs" => "Czech",
    "lang_csb" => "Kashubian",
    "lang_cu" => "Church Slavic",
    "lang_cus" => "Cushitic Language",
    "lang_cv" => "Chuvash",
    "lang_cy" => "Welsh",
    "lang_da" => "Danish",
    "lang_dak" => "Dakota",
    "lang_dar" => "Dargwa",
    "lang_day" => "Dayak",
    "lang_de" => "German",
    "lang_de_AT" => "Austrian German",
    "lang_de_CH" => "Swiss High German",
    "lang_del" => "Delaware",
    "lang_den" => "Slave",
    "lang_dgr" => "Dogrib",
    "lang_din" => "Dinka",
    "lang_doi" => "Dogri",
    "lang_dra" => "Dravidian Language",
    "lang_dsb" => "Lower Sorbian",
    "lang_dua" => "Duala",
    "lang_dum" => "Middle Dutch",
    "lang_dv" => "Divehi",
    "lang_dyu" => "Dyula",
    "lang_dz" => "Dzongkha",
    "lang_ee" => "Ewe",
    "lang_efi" => "Efik",
    "lang_egy" => "Ancient Egyptian",
    "lang_eka" => "Ekajuk",
    "lang_el" => "Greek",
    "lang_elx" => "Elamite",
    "lang_en" => "English",
    "lang_en_AU" => "Australian English",
    "lang_en_CA" => "Canadian English",
    "lang_en_GB" => "British English",
    "lang_en_US" => "U.S. English",
    "lang_enm" => "Middle English",
    "lang_eo" => "Esperanto",
    "lang_es" => "Spanish",
    "lang_es_419" => "Latin American Spanish",
    "lang_es_ES" => "Iberian Spanish",
    "lang_et" => "Estonian",
    "lang_eu" => "Basque",
    "lang_ewo" => "Ewondo",
    "lang_fa" => "Persian",
    "lang_fan" => "Fang",
    "lang_fat" => "Fanti",
    "lang_ff" => "Fulah",
    "lang_fi" => "Finnish",
    "lang_fil" => "Filipino",
    "lang_fiu" => "Finno-Ugrian Language",
    "lang_fj" => "Fijian",
    "lang_fo" => "Faroese",
    "lang_fon" => "Fon",
    "lang_fr" => "French",
    "lang_fr_CA" => "Canadian French",
    "lang_fr_CH" => "Swiss French",
    "lang_frm" => "Middle French",
    "lang_fro" => "Old French",
    "lang_frr" => "Northern Frisian",
    "lang_frs" => "Eastern Frisian",
    "lang_fur" => "Friulian",
    "lang_fy" => "Western Frisian",
    "lang_ga" => "Irish",
    "lang_gaa" => "Ga",
    "lang_gay" => "Gayo",
    "lang_gba" => "Gbaya",
    "lang_gd" => "Scottish Gaelic",
    "lang_gem" => "Germanic Language",
    "lang_gez" => "Geez",
    "lang_gil" => "Gilbertese",
    "lang_gl" => "Galician",
    "lang_gmh" => "Middle High German",
    "lang_gn" => "Guarani",
    "lang_goh" => "Old High German",
    "lang_gon" => "Gondi",
    "lang_gor" => "Gorontalo",
    "lang_got" => "Gothic",
    "lang_grb" => "Grebo",
    "lang_grc" => "Ancient Greek",
    "lang_gsw" => "Swiss German",
    "lang_gu" => "Gujarati",
    "lang_gv" => "Manx",
    "lang_gwi" => "Gwichʼin",
    "lang_ha" => "Hausa",
    "lang_hai" => "Haida",
    "lang_haw" => "Hawaiian",
    "lang_he" => "Hebrew",
    "lang_hi" => "Hindi",
    "lang_hil" => "Hiligaynon",
    "lang_him" => "Himachali",
    "lang_hit" => "Hittite",
    "lang_hmn" => "Hmong",
    "lang_ho" => "Hiri Motu",
    "lang_hr" => "Croatian",
    "lang_hsb" => "Upper Sorbian",
    "lang_ht" => "Haitian",
    "lang_hu" => "Hungarian",
    "lang_hup" => "Hupa",
    "lang_hy" => "Armenian",
    "lang_hz" => "Herero",
    "lang_ia" => "Interlingua",
    "lang_iba" => "Iban",
    "lang_id" => "Indonesian",
    "lang_ie" => "Interlingue",
    "lang_ig" => "Igbo",
    "lang_ii" => "Sichuan Yi",
    "lang_ijo" => "Ijo",
    "lang_ik" => "Inupiaq",
    "lang_ilo" => "Iloko",
    "lang_inc" => "Indic Language",
    "lang_ine" => "Indo-European Language",
    "lang_inh" => "Ingush",
    "lang_io" => "Ido",
    "lang_ira" => "Iranian Language",
    "lang_iro" => "Iroquoian Language",
    "lang_is" => "Icelandic",
    "lang_it" => "Italian",
    "lang_iu" => "Inuktitut",
    "lang_ja" => "Japanese",
    "lang_jbo" => "Lojban",
    "lang_jpr" => "Judeo-Persian",
    "lang_jrb" => "Judeo-Arabic",
    "lang_jv" => "Javanese",
    "lang_ka" => "Georgian",
    "lang_kaa" => "Kara-Kalpak",
    "lang_kab" => "Kabyle",
    "lang_kac" => "Kachin",
    "lang_kaj" => "Jju",
    "lang_kam" => "Kamba",
    "lang_kar" => "Karen",
    "lang_kaw" => "Kawi",
    "lang_kbd" => "Kabardian",
    "lang_kcg" => "Tyap",
    "lang_kfo" => "Koro",
    "lang_kg" => "Kongo",
    "lang_kha" => "Khasi",
    "lang_khi" => "Khoisan Language",
    "lang_kho" => "Khotanese",
    "lang_ki" => "Kikuyu",
    "lang_kj" => "Kuanyama",
    "lang_kk" => "Kazakh",
    "lang_kl" => "Kalaallisut",
    "lang_km" => "Khmer",
    "lang_kmb" => "Kimbundu",
    "lang_kn" => "Kannada",
    "lang_ko" => "Korean",
    "lang_kok" => "Konkani",
    "lang_kos" => "Kosraean",
    "lang_kpe" => "Kpelle",
    "lang_kr" => "Kanuri",
    "lang_krc" => "Karachay-Balkar",
    "lang_krl" => "Karelian",
    "lang_kro" => "Kru",
    "lang_kru" => "Kurukh",
    "lang_ks" => "Kashmiri",
    "lang_ku" => "Kurdish",
    "lang_kum" => "Kumyk",
    "lang_kut" => "Kutenai",
    "lang_kv" => "Komi",
    "lang_kw" => "Cornish",
    "lang_ky" => "Kirghiz",
    "lang_la" => "Latin",
    "lang_lad" => "Ladino",
    "lang_lah" => "Lahnda",
    "lang_lam" => "Lamba",
    "lang_lb" => "Luxembourgish",
    "lang_lez" => "Lezghian",
    "lang_lg" => "Ganda",
    "lang_li" => "Limburgish",
    "lang_ln" => "Lingala",
    "lang_lo" => "Lao",
    "lang_lol" => "Mongo",
    "lang_loz" => "Lozi",
    "lang_lt" => "Lithuanian",
    "lang_lu" => "Luba-Katanga",
    "lang_lua" => "Luba-Lulua",
    "lang_lui" => "Luiseno",
    "lang_lun" => "Lunda",
    "lang_luo" => "Luo",
    "lang_lus" => "Lushai",
    "lang_lv" => "Latvian",
    "lang_mad" => "Madurese",
    "lang_mag" => "Magahi",
    "lang_mai" => "Maithili",
    "lang_mak" => "Makasar",
    "lang_man" => "Mandingo",
    "lang_map" => "Austronesian Language",
    "lang_mas" => "Masai",
    "lang_mdf" => "Moksha",
    "lang_mdr" => "Mandar",
    "lang_men" => "Mende",
    "lang_mfe" => "Morisyen",
    "lang_mg" => "Malagasy",
    "lang_mga" => "Middle Irish",
    "lang_mh" => "Marshallese",
    "lang_mi" => "Maori",
    "lang_mic" => "Micmac",
    "lang_min" => "Minangkabau",
    "lang_mis" => "Miscellaneous Language",
    "lang_mk" => "Macedonian",
    "lang_mkh" => "Mon-Khmer Language",
    "lang_ml" => "Malayalam",
    "lang_mn" => "Mongolian",
    "lang_mnc" => "Manchu",
    "lang_mni" => "Manipuri",
    "lang_mno" => "Manobo Language",
    "lang_mo" => "Moldavian",
    "lang_moh" => "Mohawk",
    "lang_mos" => "Mossi",
    "lang_mr" => "Marathi",
    "lang_ms" => "Malay",
    "lang_mt" => "Maltese",
    "lang_mul" => "Multiple Languages",
    "lang_mun" => "Munda Language",
    "lang_mus" => "Creek",
    "lang_mwl" => "Mirandese",
    "lang_mwr" => "Marwari",
    "lang_my" => "Burmese",
    "lang_myn" => "Mayan Language",
    "lang_myv" => "Erzya",
    "lang_na" => "Nauru",
    "lang_nah" => "Nahuatl",
    "lang_nai" => "North American Indian Language",
    "lang_nap" => "Neapolitan",
    "lang_nb" => "Norwegian Bokmål",
    "lang_nd" => "North Ndebele",
    "lang_nds" => "Low German",
    "lang_ne" => "Nepali",
    "lang_new" => "Newari",
    "lang_ng" => "Ndonga",
    "lang_nia" => "Nias",
    "lang_nic" => "Niger-Kordofanian Language",
    "lang_niu" => "Niuean",
    "lang_nl" => "Dutch",
    "lang_nl_BE" => "Flemish",
    "lang_nn" => "Norwegian Nynorsk",
    "lang_no" => "Norwegian",
    "lang_nog" => "Nogai",
    "lang_non" => "Old Norse",
    "lang_nqo" => "N’Ko",
    "lang_nr" => "South Ndebele",
    "lang_nso" => "Northern Sotho",
    "lang_nub" => "Nubian Language",
    "lang_nv" => "Navajo",
    "lang_nwc" => "Classical Newari",
    "lang_ny" => "Nyanja",
    "lang_nym" => "Nyamwezi",
    "lang_nyn" => "Nyankole",
    "lang_nyo" => "Nyoro",
    "lang_nzi" => "Nzima",
    "lang_oc" => "Occitan",
    "lang_oj" => "Ojibwa",
    "lang_om" => "Oromo",
    "lang_or" => "Oriya",
    "lang_os" => "Ossetic",
    "lang_osa" => "Osage",
    "lang_ota" => "Ottoman Turkish",
    "lang_oto" => "Otomian Language",
    "lang_pa" => "Punjabi",
    "lang_paa" => "Papuan Language",
    "lang_pag" => "Pangasinan",
    "lang_pal" => "Pahlavi",
    "lang_pam" => "Pampanga",
    "lang_pap" => "Papiamento",
    "lang_pau" => "Palauan",
    "lang_peo" => "Old Persian",
    "lang_phi" => "Philippine Language",
    "lang_phn" => "Phoenician",
    "lang_pi" => "Pali",
    "lang_pl" => "Polish",
    "lang_pon" => "Pohnpeian",
    "lang_pra" => "Prakrit Language",
    "lang_pro" => "Old Provençal",
    "lang_ps" => "Pashto",
    "lang_pt" => "Portuguese",
    "lang_pt_BR" => "Brazilian Portuguese",
    "lang_pt_PT" => "Iberian Portuguese",
    "lang_qu" => "Quechua",
    "lang_raj" => "Rajasthani",
    "lang_rap" => "Rapanui",
    "lang_rar" => "Rarotongan",
    "lang_rm" => "Rhaeto-Romance",
    "lang_rn" => "Rundi",
    "lang_ro" => "Romanian",
    "lang_roa" => "Romance Language",
    "lang_rom" => "Romany",
    "lang_root" => "Root",
    "lang_ru" => "Russian",
    "lang_rup" => "Aromanian",
    "lang_rw" => "Kinyarwanda",
    "lang_sa" => "Sanskrit",
    "lang_sad" => "Sandawe",
    "lang_sah" => "Yakut",
    "lang_sai" => "South American Indian Language",
    "lang_sal" => "Salishan Language",
    "lang_sam" => "Samaritan Aramaic",
    "lang_sas" => "Sasak",
    "lang_sat" => "Santali",
    "lang_sc" => "Sardinian",
    "lang_scn" => "Sicilian",
    "lang_sco" => "Scots",
    "lang_sd" => "Sindhi",
    "lang_se" => "Northern Sami",
    "lang_sel" => "Selkup",
    "lang_sem" => "Semitic Language",
    "lang_sg" => "Sango",
    "lang_sga" => "Old Irish",
    "lang_sgn" => "Sign Language",
    "lang_sh" => "Serbo-Croatian",
    "lang_shn" => "Shan",
    "lang_si" => "Sinhala",
    "lang_sid" => "Sidamo",
    "lang_sio" => "Siouan Language",
    "lang_sit" => "Sino-Tibetan Language",
    "lang_sk" => "Slovak",
    "lang_sl" => "Slovenian",
    "lang_sla" => "Slavic Language",
    "lang_sm" => "Samoan",
    "lang_sma" => "Southern Sami",
    "lang_smi" => "Sami Language",
    "lang_smj" => "Lule Sami",
    "lang_smn" => "Inari Sami",
    "lang_sms" => "Skolt Sami",
    "lang_sn" => "Shona",
    "lang_snk" => "Soninke",
    "lang_so" => "Somali",
    "lang_sog" => "Sogdien",
    "lang_son" => "Songhai",
    "lang_sq" => "Albanian",
    "lang_sr" => "Serbian",
    "lang_srn" => "Sranan Tongo",
    "lang_srr" => "Serer",
    "lang_ss" => "Swati",
    "lang_ssa" => "Nilo-Saharan Language",
    "lang_st" => "Southern Sotho",
    "lang_su" => "Sundanese",
    "lang_suk" => "Sukuma",
    "lang_sus" => "Susu",
    "lang_sux" => "Sumerian",
    "lang_sv" => "Swedish",
    "lang_sw" => "Swahili",
    "lang_syc" => "Classical Syriac",
    "lang_syr" => "Syriac",
    "lang_ta" => "Tamil",
    "lang_tai" => "Tai Language",
    "lang_te" => "Telugu",
    "lang_tem" => "Timne",
    "lang_ter" => "Tereno",
    "lang_tet" => "Tetum",
    "lang_tg" => "Tajik",
    "lang_th" => "Thai",
    "lang_ti" => "Tigrinya",
    "lang_tig" => "Tigre",
    "lang_tiv" => "Tiv",
    "lang_tk" => "Turkmen",
    "lang_tkl" => "Tokelau",
    "lang_tl" => "Tagalog",
    "lang_tlh" => "Klingon",
    "lang_tli" => "Tlingit",
    "lang_tmh" => "Tamashek",
    "lang_tn" => "Tswana",
    "lang_to" => "Tonga",
    "lang_tog" => "Nyasa Tonga",
    "lang_tpi" => "Tok Pisin",
    "lang_tr" => "Turkish",
    "lang_trv" => "Taroko",
    "lang_ts" => "Tsonga",
    "lang_tsi" => "Tsimshian",
    "lang_tt" => "Tatar",
    "lang_tum" => "Tumbuka",
    "lang_tup" => "Tupi Language",
    "lang_tut" => "Altaic Language",
    "lang_tvl" => "Tuvalu",
    "lang_tw" => "Twi",
    "lang_ty" => "Tahitian",
    "lang_tyv" => "Tuvinian",
    "lang_udm" => "Udmurt",
    "lang_ug" => "Uighur",
    "lang_uga" => "Ugaritic",
    "lang_uk" => "Ukrainian",
    "lang_umb" => "Umbundu",
    "lang_und" => "Unknown or Invalid Language",
    "lang_ur" => "Urdu",
    "lang_uz" => "Uzbek",
    "lang_vai" => "Vai",
    "lang_ve" => "Venda",
    "lang_vi" => "Vietnamese",
    "lang_vo" => "Volapük",
    "lang_vot" => "Votic",
    "lang_wa" => "Walloon",
    "lang_wak" => "Wakashan Language",
    "lang_wal" => "Walamo",
    "lang_war" => "Waray",
    "lang_was" => "Washo",
    "lang_wen" => "Sorbian Language",
    "lang_wo" => "Wolof",
    "lang_xal" => "Kalmyk",
    "lang_xh" => "Xhosa",
    "lang_yao" => "Yao",
    "lang_yap" => "Yapese",
    "lang_yi" => "Yiddish",
    "lang_yo" => "Yoruba",
    "lang_ypk" => "Yupik Language",
    "lang_za" => "Zhuang",
    "lang_zap" => "Zapotec",
    "lang_zbl" => "Blissymbols",
    "lang_zen" => "Zenaga",
    "lang_zh" => "Chinese",
    "lang_zh_Hans" => "Simplified Chinese",
    "lang_zh_Hant" => "Traditional Chinese",
    "lang_znd" => "Zande",
    "lang_zu" => "Zulu",
    "lang_zun" => "Zuni",
    "lang_zxx" => "No linguistic content",
    "lang_zza" => "Zaza"

];
