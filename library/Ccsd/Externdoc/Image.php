<?php

class Ccsd_Externdoc_Image extends Ccsd_Externdoc
{
    static public function createFromXML($id, $xmlDom) {
        return new self($id);
    }


    /**
     * Retourne les métadonnées sous la forme de tableau attendue par HAL
     * @return $metas : array
     */
    public function getMetadatas()
    {
        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = array('metas'=>array());
        $dl = new Ccsd_Detectlanguage();

        $meta_image = Ccsd_Externdoc_Image_Iptc::get($this->getID()) + Ccsd_Externdoc_Image_Xmp::get($this->getID()) + Ccsd_Externdoc_Image_Exif::get($this->getID());
        foreach ( $meta_image as $key=>$value ) {
            if ( is_array($value) && in_array($key, array('ObjectName', 'DateCreated', 'Headline', 'Byline', 'Caption', 'Source', 'City', 'CountryCode', 'Country', 'Credits')) ) {
                $value = $value[0];
            }
            switch ( $key ) {
                case 'DateTimeOriginal' :
                    $value = str_replace(':', '-', substr($value, 0, 10));
                    if ( preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$value) ) {
                        $this->_metas['metas']['date'] = $value;
                    }
                    break;
                case 'ExifImageWidth' :
                    $this->_metas['metas']['width'] = (int)$value;
                    break;
                case 'ExifImageLength' :
                    $this->_metas['metas']['length'] = (int)$value;
                    break;
                case 'GPSLatitude' :
                    $this->_metas['metas']['latitude'] = $value;
                    break;
                case 'GPSLongitude' :
                    $this->_metas['metas']['longitude'] = $value;
                    break;
                case 'ObjectName' :
                case 'Headline' :
                    $langueid = $dl->detect($value);
                    if ( count($langueid) && isset($langueid['langid']) ) {
                        if ( isset($this->_metas['metas']['title'][strtolower($langueid['langid'])]) ) {
                            if ( $value != $this->_metas['metas']['title'][strtolower($langueid['langid'])] ) {
                                $this->_metas['metas']['title'][strtolower($langueid['langid'])] .= ' '.$value;
                            }
                        } else {
                            $this->_metas['metas']['title'][strtolower($langueid['langid'])] = $value;
                        }
                    } else {
                        if ( isset($this->_metas['metas']['title']['fr']) ) {
                            if ( $value != $this->_metas['metas']['title']['fr'] ) {
                                $this->_metas['metas']['title']['fr'] .= ' '.$value;
                            }
                        } else {
                            $this->_metas['metas']['title']['fr'] = $value;
                        }
                    }
                    break;
                case 'DateCreated' :
                    if ( preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$value) ) {
                        $this->_metas['metas']['date'] = $value;
                    }
                    break;
                case 'Byline' :
                    $auteur = explode(' ', Ccsd_Tools::nl2space(trim($value)));
                    if ( count($auteur) > 0 && isset($auteur[0]) && $auteur[0] != '' ) {
                        $author = array();
                        $author['firstname'] = Ccsd_Tools::upperWord(trim($auteur[0]));
                        unset($auteur[0]);
                        if ( count($auteur) ) {
                            $author['lastname'] = Ccsd_Tools::upperWord(trim(implode(' ', $auteur)));
                        }
                        $this->_metas['authors'][] = $author;
                    }
                    break;
                case 'Keywords' :
                    if ( !is_array($value) ) {
                        $value = array($value);
                    }
                    $langueid = $dl->detect(implode(' ', $value));
                    foreach ( $value as $v ) {
                        if ( count($langueid) && isset($langueid['langid']) ) {
                            $this->_metas['metas']['keyword'][strtolower($langueid['langid'])][] = $v;
                        } else {
                            $this->_metas['metas']['keyword']['fr'][] = $v;
                        }
                    }
                    break;
                case 'Caption' :
                    $langueid = $dl->detect($value);
                    if ( count($langueid) && isset($langueid['langid']) ) {
                        $this->_metas['metas']['abstract'][strtolower($langueid['langid'])] = $value;
                    } else {
                        $this->_metas['metas']['abstract']['fr'] = $value;
                    }
                    break;
                case 'Source' :
                    $this->_metas['metas']['source'] = $value;
                    break;
                case 'City' :
                    $this->_metas['metas']['city'] = $value;
                    break;
                case 'CountryCode' :
                    $this->_metas['metas']['country'] = strtolower(substr($value,0,2));
                    break;
                case 'Credits' :
                    $this->_metas['metas']['credit'] = $value;
                    break;
            }
        }
        if ( isset($this->_metas['metas']['latitude']) && isset($this->_metas['metas']['longitude']) ) {
            if ( $geoname = Ccsd_Tools::geoname($this->_metas['metas']['longitude'], $this->_metas['metas']['latitude']) ) {
                $this->_metas['metas']['city'] = $geoname['city'];
                $this->_metas['metas']['country'] = $geoname['country'];
            }
        }
        return $this->_metas;
    }
}