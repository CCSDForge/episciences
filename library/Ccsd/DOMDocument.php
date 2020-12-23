<?php
class Ccsd_DOMDocument extends DOMDocument
{
	public function createElement($name, $value = null) {
		return parent::createElement($name, Ccsd_Tools_String::stripCtrlChars(Ccsd_Tools_String::xmlSafe($value)));
	}
	public function setAttribute($name, $value) {
		return parent::setAttribute($name, Ccsd_Tools_String::stripCtrlChars(Ccsd_Tools_String::xmlSafe($value)));
	}
	
	public function setDOMDocument(DOMDocument $domDocument)
	{
		$sourceReflection = new ReflectionObject($domDocument);
		$destinationReflection = new ReflectionObject(self);
		foreach ($sourceReflection->getProperties() as $sourceProperty) {
			$sourceProperty->setAccessible(true);
			$name = $sourceProperty->getName();
			$value = $sourceProperty->getValue($domDocument);
			if ($destinationReflection->hasProperty($name)) {
				$propDest = $destinationReflection->getProperty($name);
				$propDest->setAccessible(true);
				$propDest->setValue($this,$value);
			} else {
				$this->$name = $value;
			}
		}
	}

    /**
     * Convert xml dom document => php array
     * The format of this php array is [name, parent, children[name, parent, children [...]] ]
     * @param $xml : xml dom document
     * @return array : php array
     */
    public function XmlToArray ($root)
    {
        $array = [];
        // Handle Attributes
        if($root->hasAttributes()) {
            foreach($root->attributes as $attribute) {
                $array['attributes'][$attribute->name] = $attribute->value;
            }
        }
        // Handle Element Node
        if($root->nodeType == XML_ELEMENT_NODE) {
            $array['name'] = $root->localName;
            $array['parent'] = $root->parentNode->localName;
            if ($root->hasChildNodes()) {
                $children = $root->childNodes;
                for ($i = 0; $i < $children->length; $i++) {
                    $child = $this->XmlToArray($children->item($i), $children);
                    if (!empty($child)) {
                        $array['children'][] = $child;
                    }
                }
            }
        }
        // Handle Text Node
        else if ($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
            $value = $root->nodeValue;
            $value = trim ($value);
            if(!empty($value)) {
                $array['name']    = 'text';
                $array['content'] = $value;
                $array['parent']  = $root->parentNode->localName;
            }
        }
        return $array;
    }

    /**
     * Convert xml dom document => grouped php array
     * The format of this php array is [name, parent, children[name, parent, children [...]] ]
     * TIP : if there are nodes with a same name, we generate one global node that warp all the nodes as children
     * @param $xml : xml dom document
     * @return array : php array
     */
    public function XmlToGroupedArray ($root)
    {
        $array = [];
        // Handle Attributes
        if($root->hasAttributes()) {
            foreach($root->attributes as $attribute) {
                $array['attributes'][$attribute->name] = $attribute->value;
            }
        }
        // Handle Element Node
        if($root->nodeType == XML_ELEMENT_NODE) {
            $array['name'] = $root->localName;
            $array['parent'] = $root->parentNode->localName;
            if ($root->hasChildNodes()) {
                $children = $root->childNodes;
                for ($i = 0; $i < $children->length; $i++) {
                    $child = $this->XmlToGroupedArray($children->item($i));
                    if (!empty($child)) {
                        $array['children'][] = $child;
                    }
                }
                // Group elements that have same name
                $tmp = [];
                foreach ($array['children'] as $key => $value) {
                    if (is_array($value) && isset($value['name'])) {
                        if (!isset($tmp[$value['name']])) {
                            $tmp[$value['name']] = $value;
                        } else {
                            if (isset($tmp[$value['name']]['children'])) {
                                $tmp[$value['name']]['children'] = array_merge($tmp[$value['name']]['children'], $value['children']);
                            }
                            if (isset($value['attributes'])) {
                                $tmp[$value['name']]['attributes'][] = $value['attributes'];
                            }
                        }
                    }
                    $array['children'] = $tmp;
                    $array['children'] = array_values($array['children']); // Remove indexes
                }
            }
        }
        // Handle Text Node
        else if ($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
            $value = $root->nodeValue;
            $value = trim($value);
            if(!empty($value)) {
                $array['name']    = 'text';
                $array['content'] = str_replace("'", '', $value);
                $array['parent']  = $root->parentNode->localName;
            }
        }
        return $array;
    }
}