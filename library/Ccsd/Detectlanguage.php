<?php
/**
 * Deteclanguage
 */

class Ccsd_Detectlanguage
{

	/**
	 * Initialize
	 *
	 */
	public function __construct() {}
	
	/**
	* Detect text language.
	 *
	 * @param string @text The text for language detection
	 * @return array detected languages information
	 */
	public function detect($text) {
        if ( $text && file_put_contents($tmpfname = tempnam("/tmp", "ld"), strtolower($text)) ) {
            @exec('java -jar '.__DIR__.'/Detectlanguage/langdetect.jar --detectlang -d '.__DIR__.'/Detectlanguage/profiles/ '.escapeshellarg($tmpfname), $output);
            unlink($tmpfname);
            if ( count($output) && preg_match('/:\[([a-z]+):([0-9\.]+)(\]|,)/', $output[0], $match) ) {
                // on retourne tjrs qu'une seule langue
                return ['langid'=>$match[1], 'proba'=>$match[2]];
            } else {
                return null;
            }
        }
        return null;
  	}

}