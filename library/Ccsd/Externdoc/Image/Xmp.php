<?php

class Ccsd_Externdoc_Image_Xmp
{

    /**
     * Récupération des XMP d'une image JPEG
     *
     * @param $image_file_name
     * @return array|bool
     */
    static public function get( $image_file_name ) {
        $metas = array();
        if ( function_exists('xmp_read') ) {
            try {
                $xml = new SimpleXMLElement(xmp_read($image_file_name));
                $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
                $xml->registerXPathNamespace('iX', 'http://ns.adobe.com/iX/1.0/');
                $xml->registerXPathNamespace('photoshop', 'http://ns.adobe.com/photoshop/1.0/');
                $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
                //title
                if ( !isset($metas['ObjectName']) || $metas['ObjectName'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/dc:title/rdf:Alt/rdf:li");
                    $metas['ObjectName'] = ( $value ) ? (string)$value[0] : '';
                }
                //title
                if ( !isset($metas['Headline']) || $metas['Headline'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:Headline");
                    $metas['Headline'] = ( $value ) ? (string)$value[0] : '';
                }
                //date
                if ( !isset($metas['DateCreated']) || $metas['DateCreated'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:DateCreated");
                    $metas['DateCreated'] = ( $value ) ? (string)$value[0] : '';
                    if ( preg_match('~([0-9]{2})/([0-9]{2})/([0-9]{4})~', $metas['DateCreated'], $match) ) { #format -> jj/mm/aaaa
                        $metas['DateCreated'] = $match[3].'-'.$match[2].'-'.$match[1];
                    }
                    if ( preg_match('~([0-9]{4})([0-9]{2})([0-9]{2})~', $metas['DateCreated'], $match) ) { #format -> aaaammjj
                        $metas['DateCreated'] = $match[1].'-'.$match[2].'-'.$match[3];
                    }
                }
                //keyword
                if ( !isset($metas['Keywords']) || $metas['Keywords'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/dc:subject/rdf:Bag/rdf:li");
                    $metas['Keywords'] = ( $value ) ? implode(';', array_unique($value)) : '';
                }
                //author
                if ( !isset($metas['Byline']) || $metas['Byline'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/dc:creator/rdf:Seq/rdf:li");
                    $metas['Byline'] = ( $value ) ? (string)$value[0] : ''; # on ne garde que le premier auteur
                }
                //resume
                if ( !isset($metas['Caption']) || $metas['Caption'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/dc:description/rdf:Alt/rdf:li");
                    $metas['Caption'] = ( $value ) ? (string)$value[0] : '';
                }
                //source
                if ( !isset($metas['Source']) || $metas['Source'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:Source");
                    $metas['Source'] = ( $value ) ? (string)$value[0] : '';
                }
                //city
                if ( !isset($metas['City']) || $metas['City'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:City");
                    $metas['City'] = ( $value ) ? (string)$value[0] : '';
                }
                //country
                if ( !isset($metas['Country']) || $metas['Country'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:Country");
                    $metas['Country'] = ( $value ) ? (string)$value[0] : '';
                }
                //credit
                if ( !isset($metas['Credits']) || $metas['Credits'] == '' ) {
                    $value = $xml->xpath("//rdf:RDF/rdf:Description/photoshop:Credit");
                    $metas['Credits'] = ( $value ) ? (string)$value[0] : '';
                }
            } catch (Exception $e) {}
        }
        return array_filter($metas);
    }

    /**
     *
     * Écriture des données XMP dans une image JPEG
     *
     * @param string $image_file_name image filename
     * @param array $data metadatas
     * @return bool
     *
     */
    static public function add( $image_file_name, $data=array() ) {
        if ( empty($data) || !is_array($data) ) {
            return false;
        }
        if ( function_exists('xmp_can_write') && function_exists('xmp_write') ) {
            $xmlData = '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="XMP Core 4.4.0"> <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"> <rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/"> <xmp:MetadataDate>'.date('c').'</xmp:MetadataDate> <xmp:CreateDate>'.date('c').'</xmp:CreateDate> <xmp:ModifyDate>'.date('c').'</xmp:ModifyDate> <xmp:CreatorTool>HAL | archives-ouvertes.fr</xmp:CreatorTool> </rdf:Description> <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"> %%TITLE%% %%KEY%% %%RIGHT%% %%DESCRIPTION%% %%CREATOR%% </rdf:Description> <rdf:Description rdf:about="" xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"> <photoshop:Source>%%SOURCE%%</photoshop:Source> <photoshop:Credit>%%CREDIT%%</photoshop:Credit> <photoshop:Country>%%COUNTRY%%</photoshop:Country> <photoshop:City>%%CITY%%</photoshop:City> <photoshop:DateCreated>%%DATE%%</photoshop:DateCreated> </rdf:Description> </rdf:RDF> </x:xmpmeta>';
            // mots-clés
            if ( is_array($data['keyword']) ) {
                $keyword = '<dc:subject> <rdf:Bag> ';
                foreach ( $data['keyword'] as $l=>$k ) {
                    if ( !is_array($k) ) {
                        $k = array($k);
                    }
                    $keyword .= '<rdf:li xml:lang="'.$l.'">'.implode('</rdf:li> <rdf:li xml:lang="'.$l.'">', array_map('Ccsd_Tools_String::xmlSafe', $k)).'</rdf:li>';
                }
                $keyword .= ' </rdf:Bag> </dc:subject>';
                $xmlData = str_replace('%%KEY%%', $keyword, $xmlData);
            } else {
                $xmlData = str_replace('%%KEY%%', '<dc:subject> <rdf:Bag> <rdf:li>'.implode('</rdf:li> <rdf:li>', $data['keyword']).'</rdf:li> </rdf:Bag> </dc:subject>', $xmlData);
            }
            // titre
            if ( is_array($data['title']) ) {
                $title = '<dc:title> <rdf:Alt> ';
                foreach ( $data['title'] as $l=>$t ) {
                    if ( !is_array($t) ) {
                        $t = array($t);
                    }
                    $title .= '<rdf:li xml:lang="'.$l.'">'.implode('</rdf:li> <rdf:li xml:lang="'.$l.'">', array_map('Ccsd_Tools_String::xmlSafe', $t)).'</rdf:li>';
                }
                $title .= ' </rdf:Alt> </dc:title>';
                $xmlData = str_replace('%%TITLE%%', $title, $xmlData);
            } else {
                $xmlData = str_replace('%%TITLE%%', '<dc:title> <rdf:Alt> <rdf:li xml:lang="x-default">'.Ccsd_Tools_String::xmlSafe($data['title']).'</rdf:li> </rdf:Alt> </dc:title>', $xmlData);
            }
            // description
            if ( is_array($data['description']) ) {
                $description = '<dc:description> <rdf:Alt> ';
                foreach ( $data['description'] as $l=>$d ) {
                    if ( !is_array($d) ) {
                        $d = array($d);
                    }
                    $description .= '<rdf:li xml:lang="'.$l.'">'.implode('</rdf:li> <rdf:li xml:lang="'.$l.'">', array_map('Ccsd_Tools_String::xmlSafe', $d)).'</rdf:li>';
                }
                $description .= ' </rdf:Alt> </dc:description>';
                $xmlData = str_replace('%%DESCRIPTION%%', $description, $xmlData);
            } else {
                $xmlData = str_replace('%%DESCRIPTION%%', '<dc:description> <rdf:Alt> <rdf:li xml:lang="x-default">'.Ccsd_Tools_String::xmlSafe($data['description']).'</rdf:li> </rdf:Alt> </dc:description>', $xmlData);
            }
            // auteur
            if ( is_array($data['creator']) ) {
                $xmlData = str_replace('%%CREATOR%%', '<dc:creator> <rdf:Seq> <rdf:li>'.implode('</rdf:li> <rdf:li>', array_map('Ccsd_Tools_String::xmlSafe', $data['creator'])).'</rdf:li> </rdf:Seq> </dc:creator>', $xmlData);
            } else {
                $xmlData = str_replace('%%CREATOR%%', '<dc:creator> <rdf:Seq> <rdf:li>'.Ccsd_Tools_String::xmlSafe($data['creator']).'</rdf:li> </rdf:Seq> </dc:creator>', $xmlData);
            }
            // autres métas
            $xmlData = str_replace('%%RIGHT%%', '<dc:rights> <rdf:Alt> <rdf:li xml:lang="x-default">'.Ccsd_Tools_String::xmlSafe($data['copyright']).'</rdf:li> </rdf:Alt> </dc:rights>', $xmlData);
            $xmlData = str_replace('%%COUNTRY%%', Ccsd_Tools_String::xmlSafe($data['country']), $xmlData);
            $xmlData = str_replace('%%CITY%%', Ccsd_Tools_String::xmlSafe($data['city']), $xmlData);
            $xmlData = str_replace('%%DATE%%', Ccsd_Tools_String::xmlSafe($data['date']), $xmlData);
            $xmlData = str_replace('%%SOURCE%%', Ccsd_Tools_String::xmlSafe($data['source']), $xmlData);
            $xmlData = str_replace('%%CREDIT%%', Ccsd_Tools_String::xmlSafe($data['right']), $xmlData);
            if ( xmp_can_write($image_file_name, $xmlData) ) {
                return xmp_write($image_file_name, $xmlData);
            }
        }
        return false;
    }

}