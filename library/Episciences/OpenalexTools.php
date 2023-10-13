<?php

class Episciences_OpenalexTools {
    /**
     * @param array $authorList
     * @return string
     */
    public static function getAuthors(array $authorList): string {
        $strAuthor ='';
        $kLast = array_key_last($authorList);
        foreach ($authorList as $key => $authorInfo){
            $strAuthor .= $authorInfo['raw_author_name'];
            if (isset($authorInfo['author']['orcid'])){
                $strAuthor .= ", ".str_replace("https://orcid.org/",'',$authorInfo['author']['orcid']);
            }
            if ($kLast !== $key){
                $strAuthor.="; ";
            }
        }
        return $strAuthor;
    }

    /**
     * @param string|null $fp
     * @param string|null $lp
     * @return string
     */
    public static function getPages(?string $fp, ?string $lp): string {
        if (is_null($fp)) {
            return "";
        }
        return ($fp === $lp) ? $fp : $fp."-".$lp ;
    }

    /**
     * @param array $openAccess
     * @return mixed|string
     */
    public static function getOaLink(array $openAccess) {
        if ($openAccess['is_oa'] === true){
           return  $openAccess['oa_url'];
        }
        return "";
    }

    /**
     * @param array $locations
     * @return mixed|void
     */
    public static function getFirstAlternativeLocations(array $locations) {
        foreach ($locations as $location){
            if (!is_null($location['source'])){
                $arrayOa = ['source_title' => $location['source']['display_name'], 'oa_link' => ""];
                if ($location['is_oa']=== true){
                    $arrayOa['oa_link'] = $location['source']['landing_page_url'];
                }
                return $arrayOa;
            }
        }
        return "";
    }

    public static function getBestOaInfo($primaryLocation,$locations,$bestOaLocation){
        if ($bestOaLocation !== null){
          return ['source_title' => $bestOaLocation['source']['display_name'],'oa_link' => $bestOaLocation['landing_page_url']];
        }
        if ($primaryLocation['is_oa'] === true && !is_null($primaryLocation['source'])) {
            return ['source_title' => $primaryLocation['source']['display_name'],'oa_link' => $primaryLocation['landing_page_url']];
        }
        foreach ($locations as $location){
            if ($location['is_oa'] === true && !is_null($location['source'])){
                return ['source_title' => $location['source']['display_name'],'oa_link' => $location['landing_page_url']];
            }
        }
        return self::getFirstAlternativeLocations($locations);
    }

//{"0":{"author":"Achter, Jeffrey D., 0000-0001-8492-8532; Casalaina-Martin, Sebastian, 0000-0003-0887-846X; Vial, Charles, 0000-0001-7752-5612","year":"2021","title":"The Walker Abel–Jacobi Map Descends","source_title":"Mathematische Zeitschrift","volume":"300","issue":"2","page":"1799-1817","doi":"10.1007/s00209-021-02833-4","oa_link":"10.1007/s00209-021-02833-4"}}

}