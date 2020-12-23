<?php

class Ccsd_Externdoc_Image_Exif
{

    static public function get( $image_file_name ) {
        if ( $image_file_name && is_file($image_file_name) ) {

            // si pas de support pour ce type d'image
            if (!exif_imagetype($image_file_name)) {
                return [];
            }


            $meta = array();
            if ( $exif_data = exif_read_data($image_file_name, 'EXIF') ) {
                foreach ( array('DateTimeOriginal', 'ExifImageWidth', 'ExifImageLength') as $data ) {
                    if ( isset($exif_data[$data]) ) {
                        $meta[$data] = $exif_data[$data];
                    }
                }
            }
            if ( $exif_gps = exif_read_data($image_file_name, 'GPS') ) {
                foreach ( array('GPSLatitude', 'GPSLongitude') as $data ) {
                    if ( array_key_exists($data, $exif_gps) ) {
                        list($deg, $min, $sec) = $exif_gps[$data];
                        if ( isset($deg) ) {
                            eval("\$deg = $deg;");
                        } else {
                            $deg = null;
                        }
                        if ( isset($min) ) {
                            eval("\$min = $min;");
                        } else {
                            $min = null;
                        }
                        if ( isset($sec) ) {
                            eval("\$sec = $sec;");
                        } else {
                            $sec = null;
                        }
                        if ( $deg != null && $min != null && $sec != null ) {
                            $meta[$data] = self::DMS2Dec($deg, $min, $sec, $exif_gps[$data.'Ref']);
                        }
                    }
                }
            }
            return array_filter($meta);
        }
        return array();
    }

    static private function DMS2Dec( $deg, $min, $sec, $ref ) {
        $res = $deg+((($min*60)+($sec))/3600);
        if ( in_array($ref, array('S','W'))) {
            $res = -$res;
        }
        return $res ;
    }

}