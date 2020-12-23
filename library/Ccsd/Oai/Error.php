<?php

class Ccsd_Oai_Error extends ErrorException {

	public function __construct($code, $argument='', $value='') {
		$this->code = $code;
		switch ($code) {
			case 'badRequestMethod' :
				$this->message = "The request method '".$argument."' is unknown.";
				$this->code = "badVerb";
				break;
			case 'badVerb' :
				$this->message = "The verb '".$argument."' provided in the request is illegal.";
				break;
			case 'noVerb' :
				$this->message = "The request does not provide any verb.";
				$this->code = "badVerb";
				break;
			case 'badArgument' :
				$this->message = "The argument '".$argument."' (value='".$value."') included in the request is not valid.";
				break;
			case 'badGranularity' :
				$this->message = "The value '".$value."' of the argument '".$argument."' is not valid.";
				$this->code = 'badArgument';
				break;
			case 'badResumptionToken' :
				$this->message = "The resumptionToken '".$value."' does not exist or has already expired.";
				break;
			case 'cannotDisseminateFormat' :
				$this->message = "The metadata format '".$value."' given by '".$argument."' is not supported by this repository.";
				break;
			case 'exclusiveArgument' :
				$this->message = 'The usage of resumptionToken as an argument allows no other arguments.';
				$this->code = 'badArgument';
				break;
			case 'idDoesNotExist' :
				$this->message = "The value '".$value."' of the identifier is illegal for this repository.";
				break;
			case 'missingArgument' :
				$this->message = "The required argument '".$argument."' is missing in the request.";
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
				$this->message = "Unknown error: code: '".$code."', argument: '".$argument."', value: '".$value."'";
				$this->code = 'badArgument';
				break;
		}
	}
}