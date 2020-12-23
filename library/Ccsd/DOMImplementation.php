<?php
class Ccsd_DOMImplementation extends DOMImplementation
{
	public function createDocument($namespaceURI = null, $qualifiedName = null, DOMDocumentType $doctype = null){
		$ccsd_DomDocument = new Ccsd_DOMDocument();
		$ccsd_DomDocument->setDOMDocument(parent::createDocument($namespaceURI, $qualifiedName, $doctype));
		return $ccsd_DomDocument;
	}

}