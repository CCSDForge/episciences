<?php

class Episciences_Repositories_Common
{
    public static function isOpenAccessRight(array $hookParams): array
    {

        $isOpenAccessRight = false;
        $pattern = '<dc:rights>info:eu-repo\/semantics\/openAccess<\/dc:rights>';

        if (array_key_exists('record', $hookParams)) {
            $found = Episciences_Tools::extractPattern('/' . $pattern . '/', $hookParams['record']);
            $isOpenAccessRight = !empty($found);
        }


        return ['isOpenAccessRight' => $isOpenAccessRight];

    }

}