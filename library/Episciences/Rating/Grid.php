<?php

/**
 * Class Episciences_Rating_Grid
 */
class Episciences_Rating_Grid
{

    private $_id; // associated volume id
    private $_xml;
    private $_filename;
    private $_criteria;

    public function __construct($values = [])
    {
        if (is_array($values)) {
            $this->populate($values);
        }
    }

    public function populate($values)
    {
        foreach ($values as $name => $value) {
            $method = 'set' . ucfirst(strtolower($name));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // load grid structure from XML file
    public function loadXML($filepath)
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $xml = new Ccsd_DOMDocument();
        $xml->preserveWhiteSpace = false;
        if (!$xml->load($filepath)) {
            return false;
        }

        if (!$this->getFilename()) {
            $this->setFilename(basename($filepath));
        }

        if (!$this->getId()) {
            $this->setId(filter_var($this->getFilename(), FILTER_SANITIZE_NUMBER_INT));
        }

        $xml->getElementsByTagName('text');


        $items = $xml->getElementsByTagName('div');
        $criteria = [];
        foreach ($items as $i => $item) {

            $criterion_values = [];
            $criterion_values['position'] = $i + 1;

            // id
            $criterion_values['id'] = $item->getAttribute('xml:id');
            //type
            $criterion_values['type'] = $item->getAttribute('type');

            // labels
            $labels = [];
            foreach ($item->getElementsByTagName('head')->item(0)->getElementsByTagName('label') as $node) {
                $labels[$node->getAttribute('xml:lang')] = $node->nodeValue;
            }
            $criterion_values['labels'] = $labels;

            // descriptions
            $descriptions = [];
            foreach ($item->getElementsByTagName('desc') as $node) {
                $descriptions[$node->getAttribute('xml:lang')] = $node->nodeValue;
            }
            $criterion_values['descriptions'] = $descriptions;

            //comment
            $ab = $item->getElementsByTagName('ab');
            if ($ab->length && $ab->item(0)->getAttribute('type') == 'comment') {
                $criterion_values['comment_setting'] = true;
            }

            //attachment
            $ref = $item->getElementsByTagName('ref');
            if ($ref->length && $ref->item(0)->getAttribute('type') == 'attachment') {
                $criterion_values['attachment_setting'] = true;
            }

            //options
            $options = [];
            $list = $item->getElementsByTagName('list')->item(0);
            if ($list) {
                foreach ($list->getElementsByTagName('item') as $iList => $node) {
                    $option = ['value' => $iList, 'label' => null];
                    foreach ($node->getElementsByTagName('label') as $label) {
                        $option['label'][$label->getAttribute('xml:lang')] = $label->nodeValue;
                    }
                    $options[] = $option;
                }
            }

            $criterion_values['options'] = $options;

            //coefficient & visibility
            foreach ($item->getElementsByTagName('f') as $node) {
                $criterion_values[$node->getAttribute('name')] = $node->firstChild->getAttribute('value');
            }

            $criteria[] = new Episciences_Rating_Criterion($criterion_values);
        }

        $this->setCriteria($criteria);

        $this->setXml($xml->saveXML());

        return true;
    }

    public function getFilename()
    {
        return $this->_filename;
    }

    public function setFilename($filename)
    {
        $this->_filename = $filename;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function addCriterion(Episciences_Rating_Criterion $criterion)
    {
        $this->_criteria[] = $criterion;
    }

    public function setCriterion($id, Episciences_Rating_Criterion $criterion)
    {
        $this->_criteria[$id] = $criterion;
    }

    public function getCriterion($id)
    {
        return (array_key_exists($id, $this->_criteria)) ? $this->_criteria[$id] : null;
    }

    public function removeCriterion($item_id)
    {
        $criteria = [];

        foreach ($this->getCriteria() as $criterion) {
            if ($criterion->getId() == $item_id) {
                // skip this one
                continue;
            }
            if ($criterion->getId() > $item_id) {
                // update item id
                $i = filter_var($criterion->getId(), FILTER_SANITIZE_NUMBER_INT) - 1;
                $criterion->setId('item_' . $i);
            }
            $criteria[] = $criterion;
        }
        $this->setCriteria($criteria);
    }

    /**
     * @return Episciences_Rating_Criterion[]
     */
    public function getCriteria()
    {
        return $this->_criteria;
    }

    public function setCriteria($criteria)
    {
        $tmp = [];
        foreach ($criteria as $criterion) {
            if (is_a($criterion, 'Episciences_Rating_Criterion')) {
                $tmp[] = $criterion;
            } else {
                $tmp[] = new Episciences_Rating_Criterion($criterion);
            }
        }
        $this->_criteria = $tmp;
    }


    /**
     * Save Grid as a file
     * @param string|null $path
     * @return bool
     */
    public function save($path = null)
    {
        if (!$this->toXML()) {
            return false;
        }

        if (!$path) {
            $path = REVIEW_GRIDS_PATH;
        }

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if (file_put_contents($path . $this->getFilename(), $this->getXml(), LOCK_EX) === false) {
            return false;
        }
        return true;

    }

    // export rating grid structure to a XML string
    private function toXML()
    {
        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');
        $xml->formatOutput = true;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;

        $model = $xml->createProcessingInstruction('xml-model', 'href="http://www.tei-c.org/release/xml/tei/custom/schema/relaxng/tei_all.rng" type="application/xml" schematypens="http://relaxng.org/ns/structure/1.0"');
        $xml->appendChild($model);
        $model = $xml->createProcessingInstruction('xml-model', 'href="http://www.tei-c.org/release/xml/tei/custom/schema/relaxng/tei_all.rng" type="application/xml" schematypens="http://purl.oclc.org/dsdl/schematron"');
        $xml->appendChild($model);

        $root = $xml->createElement('TEI');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.tei-c.org/ns/1.0');

        // teiHeader
        $header = $xml->createElement('teiHeader');
        $fileDesc = $xml->createElement('fileDesc');
        $titleStmt = $xml->createElement('titleStmt');
        $titles = ['fr' => 'Grille de relecture', 'en' => 'Rating grid'];
        foreach ($titles as $lang => $title) {
            $t = $xml->createElement('title', $title);
            $t->setAttribute('xml:lang', $lang);
            $titleStmt->appendChild($t);
        }

        $publicationStmt = $xml->createElement('publicationStmt');
        $p = $xml->createElement('p', 'Publication Information');
        $publicationStmt->appendChild($p);

        $sourceDesc = $xml->createElement('sourceDesc');
        $p = $xml->createElement('p', 'Information about the source');
        $sourceDesc->appendChild($p);

        $fileDesc->appendChild($titleStmt);
        $fileDesc->appendChild($publicationStmt);
        $fileDesc->appendChild($sourceDesc);
        $header->appendChild($fileDesc);
        $root->appendChild($header);

        // rating grid criteria
        $criteria = $this->getCriteria();

        // text
        $text = $xml->createElement('text');
        $body = $xml->createElement('body');

        // one div per criterion
        foreach ($criteria as $id => $oCriterion) {

            $div = $xml->createElement('div');
            $div->setAttribute('xml:id', 'item_' . $id);
            $div->setAttribute('type', $oCriterion->getType());

            if ($oCriterion->getSubType() !='') {
                $div->setAttribute('subtype', $oCriterion->getSubType());
            }

            // criterion name
            $head = $xml->createElement('head');
            foreach ($oCriterion->getLabels() as $lang => $label) {
                $label = $xml->createElement('label', $label);
                $label->setAttribute('xml:lang', $lang);
                $head->appendChild($label);
            }
            $div->appendChild($head);

            // criterion description
            foreach ($oCriterion->getDescriptions() as $lang => $label) {
                $desc = $xml->createElement('desc', $label);
                $desc->setAttribute('xml:lang', $lang);
                $div->appendChild($desc);
            }

            if ($oCriterion->isCriterion()) {

                // comment
                //<ab type="comment"><!-- commentaire saisi par le relecteur --></ab>
                if ($oCriterion->getComment_setting() == true) {
                    $comment = $xml->createElement('ab', '');
                    $comment->setAttribute('type', 'comment');
                    $div->appendChild($comment);
                }

                // attachment
                //<ref type="attachment" target="<!-- chemin vers le fichier uploadé par le relecteur -->"></ref>
                if ($oCriterion->getAttachment_setting() == true) {
                    $attachment = $xml->createElement('ref');
                    $attachment->setAttribute('type', 'attachment');
                    $div->appendChild($attachment);
                }

                // options

                if ($oCriterion->hasOptions()) {
                    $options = $xml->createElement('list');
                    $options->setAttribute('style', 'options');
                    foreach ($oCriterion->getOptions() as $option) {
                        $item = $xml->createElement('item');
                        $item->setAttribute('xml:id', 'item_' . $id . '_option_' . $option['value']);
                        $item->setAttribute('n', $option['value']);
                        if (array_key_exists('label', $option) && is_array($option['label'])) {
                            foreach ($option['label'] as $lang => $label) {
                                $label = $xml->createElement('label', $label);
                                $label->setAttribute('xml:lang', $lang);
                                $item->appendChild($label);
                            }
                        }
                        $options->appendChild($item);
                    }
                    $div->appendChild($options);
                }
            }

            $settings = $xml->createElement('fs');

            // coefficient
            if ($oCriterion->hasCoefficient()) {
                $coef = $xml->createElement('f');
                $coef->setAttribute('name', 'coefficient');
                $val = $xml->createElement('numeric');
                $val->setAttribute('value', $oCriterion->getCoefficient());
                $coef->appendChild($val);
                $settings->appendChild($coef);
            }

            // visibility
            $visibility = $xml->createElement('f');
            $visibility->setAttribute('name', 'visibility');
            $val = $xml->createElement('symbol');
            $val->setAttribute('value', $oCriterion->getVisibility()); // author, public, editor
            $visibility->appendChild($val);
            $settings->appendChild($visibility);

            $div->appendChild($settings);

            $body->appendChild($div);
        }

        $text->appendChild($body);
        $root->appendChild($text);

        $xml->appendChild($root);

        $this->setXml($xml->saveXML());
        return $this->getXml();
    }

    public function getXml()
    {
        return $this->_xml;
    }

    // set rating grid xml string

    public function setXml($xml)
    {
        $this->_xml = $xml;
    }

    // return rating grid xml string

    public function toArray()
    {
        $criteria = [];
        if (is_array($this->getCriteria())) {
            foreach ($this->getCriteria() as $oCriterion) {
                $criteria[] = $oCriterion->toArray();
            }
        }

        return [
            'criteria' => $criteria
        ];
    }
}
