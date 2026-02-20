<?php

use Laravolt\Avatar\Avatar;

class Episciences_View_Helper_GetAvatar extends Zend_View_Helper_Abstract
{

    /**
     * Random background colors
     * @var string[]
     */
    protected static array $_defaultBackgroundColors = [
        '#f44336', '#E91E63', '#9C27B0', '#673AB7',
        '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4',
        '#009688', '#4CAF50', '#8BC34A', '#CDDC39',
        '#FFC107', '#FF9800', '#FF5722', '#FF6347',
        '#FF4500', '#FF7F50', '#FE1B00', '#BF3030',
        '#FF5E4D', '#D90115', '#F7230C', '#1E7FCB',
        '#689D71', '#DD985C', '#3A8EBA', '#175732',
        '#CC5500', '#708D23', '#048B9A', '#11aa33',
        '#009527', '#ff17b6', '#f89406', '#ca6d00',
        '#dd2222', '#0677bf', '#007cba', '#004876',
        '#5627a8'];

    /**
     * @param $stringToMakeAvatar
     * @return string
     */
    public static function asSvg($stringToMakeAvatar): string
    {
        $avatar = new Avatar([
            'shape' => 'circle', 'theme' => 'colorful', 'width' => 34, 'height' => 34, 'fontSize' => 16, 'backgrounds' => self::$_defaultBackgroundColors]);

        return $avatar->create($stringToMakeAvatar)->setFontFamily('Helvetica')->toSvg();

    }


    /**
     * @param $stringToMakeAvatar
     * @param $paperStatus
     * @return string
     */
    public static function asPaperStatusSvg($stringToMakeAvatar, $paperStatus): string
    {


        if (!array_key_exists((int)$paperStatus, self::getPaperStatusColors())) {
            return '404.svg';
        }

        $backgroundColor = [self::getPaperStatusColors()[(int)$paperStatus]];

        try {
            $lang = Zend_Registry::get('lang');
        } catch (Zend_Exception $exception) {
            $lang = 'en';
        }

        // Sanitize $lang to prevent path traversal via malformed registry value
        $lang = preg_replace('/[^a-z]/', '', strtolower($lang)) ?: 'en';

        $paperStatusAvatarDir = REVIEW_PUBLIC_PATH . 'paper-status';

        if (!is_dir($paperStatusAvatarDir)) {
            if (!mkdir($paperStatusAvatarDir)) {
                trigger_error(sprintf('Directory "%s" was not created', $paperStatusAvatarDir), E_USER_WARNING);
                return '404.svg';
            }
        }

        // Cast to int to prevent path traversal via a crafted $paperStatus value
        $paperStatusAvatarFileName = (int)$paperStatus . '.' . $lang . '.svg';
        $paperStatusAvatarFileNamePath = $paperStatusAvatarDir . '/' . $paperStatusAvatarFileName;

        if (!is_readable($paperStatusAvatarFileNamePath)) {

            $foregrounds = ["#FFFFFF"];

            if ((int)$paperStatus === 7) {
                $foregrounds = ["#000000"];
            }

            $options = ['shape' => 'square', 'chars' => 3, 'theme' => 'colorful', 'width' => 30, 'height' => 30, 'fontSize' => 14,
                'backgrounds' => $backgroundColor,
                'foregrounds' => $foregrounds
            ];


            $avatar = new Avatar($options);

            $data = $avatar->create($stringToMakeAvatar)->setFontFamily('Helvetica')->toSvg();
            file_put_contents($paperStatusAvatarFileNamePath, $data);
        }

        return sprintf('%spublic/paper-status/%s', PREFIX_URL, $paperStatusAvatarFileName);


    }

    /**
     * @return array
     */
    public static function getPaperStatusColors(): array
    {
        $paperStatus[0] = '#aaa';
        $paperStatus[1] = '#666';
        $paperStatus[2] = '#333';
        $paperStatus[3] = '#004876';
        $paperStatus[4] = '#9d0';
        $paperStatus[5] = '#FE1B00';
        $paperStatus[6] = '#d22';
        $paperStatus[7] = '#f8e806';
        $paperStatus[8] = '#f89406';
        $paperStatus[9] = '#ca6d00';
        $paperStatus[10] = '#FF4500';
        $paperStatus[11] = '#ff7f50';
        $paperStatus[12] = '#D90115';
        $paperStatus[13] = '#F7230C';
        $paperStatus[14] = '#1E7FCB';
        $paperStatus[15] = '#f89406';
        $paperStatus[16] = '#009527';
        $paperStatus[17] = '#ff6347';
        $paperStatus[18] = '#DD985C';
        $paperStatus[19] = '#3A8EBA';
        $paperStatus[20] = '#175732';
        $paperStatus[21] = '#c50';
        $paperStatus[22] = '#708D23';
        $paperStatus[23] = '#689D71';
        $paperStatus[24] = '#048B9A';
        $paperStatus[25] =  $paperStatus[4];
        $paperStatus[26] =  $paperStatus[4];
        $paperStatus[27] =  $paperStatus[4];
        $paperStatus[28] =  $paperStatus[4];
        $paperStatus[29] =  $paperStatus[4];
        $paperStatus[30] =  $paperStatus[4];
        $paperStatus[31] =  $paperStatus[4];
        $paperStatus[32] =  $paperStatus[20];
        $paperStatus[33] =  $paperStatus[23];

        return $paperStatus;
    }


}