<?php

class Ccsd_Oai_Error extends ErrorException {

    /**
     * @param $code
     * @param string $argument
     * @param string $value
     */
    public function __construct($code, $argument='', $value='') {
		$this->code = $code;
		switch ($code) {
			case 'badRequestMethod' :
				$this->message = sprintf("The request method '%s' is unknown.", $argument);
				$this->code = "badVerb";
				break;
			case 'badVerb' :
				$this->message = sprintf("The verb '%s' provided in the request is illegal.", $argument);
				break;
			case 'noVerb' :
				$this->message = "The request does not provide any verb.";
				$this->code = "badVerb";
				break;
			case 'badArgument' :
				$this->message = sprintf("The argument '%s' (value='%s') included in the request is not valid.", $argument, $value);
				break;
			case 'badGranularity' :
				$this->message = sprintf("The value '%s' of the argument '%s' is not valid.", $value, $argument);
				$this->code = 'badArgument';
				break;
			case 'badResumptionToken' :
				$this->message = sprintf("The resumptionToken '%s' does not exist or has already expired.", $argument);
				break;
			case 'cannotDisseminateFormat' :
				$this->message = sprintf("The metadata format '%s' given by '%s' is not supported by this repository.", $value, $argument);
				break;
			case 'exclusiveArgument' :
				$this->message = 'The usage of resumptionToken as an argument allows no other arguments.';
				$this->code = 'badArgument';
				break;
			case 'idDoesNotExist' :
				$this->message = sprintf("The value '%s' of the identifier is illegal for this repository.", $value);
				break;
			case 'missingArgument' :
				$this->message = sprintf("The required argument '%s' is missing in the request.", $argument);
				$this->code = 'badArgument';
				break;
			case 'noRecordsMatch' :
				$this->message = 'The combination of the given values results in an empty list.';
				break;
			case 'noMetadataFormats' :
				$this->message = 'There are no metadata formats available for the specified item.';
				break;
			case 'noSetHierarchy' :
				$this->message = 'This repository does not support sets.';
				break;
			case 'sameArgument' :
				$this->message = 'Do not use same argument more than once.';
				$this->code = 'badArgument';
				break;
			case 'sameVerb' :
				$this->message = 'Do not use verb more than once.';
				$this->code = 'badVerb';
				break;
			default:
				$this->message = sprintf("Unknown error: code: '%s', argument: '%s', value: '%s'", $code, $argument, $value);
				$this->code = 'badArgument';
				break;
		}
	}
}