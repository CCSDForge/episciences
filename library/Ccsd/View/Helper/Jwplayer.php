<?php

/**
 * Class Ccsd_View_Helper_Jwplayer
 */
class Ccsd_View_Helper_Jwplayer // extends Zend_View_Helper_Abstract
{

    const LICENCE = 'hOkBsHFzo2UzoKBm+u2dVsDnxVSC41llsOuG5g==';

    public $view;

    protected $_id = 'videoPreview';

    protected $_src = '';
    /** Todo: Mettre l'URL en config plutot qu'en dur!  */
    protected $_rtmp = 'rtmp://streaming.ccsd.cnrs.fr/hal/_definst_/mp4:%SRC%';

    protected $_hls = '//streaming.ccsd.cnrs.fr:/hal/_definst_/mp4:%SRC%/playlist.m3u8';

    protected $_image = '';

    protected $_skin = 'five';

    protected $_logo = '/img/videhal.png';

    protected $_width = 600;

    protected $_height = 360;

    protected $_title = '';

    protected $_link = '';

    protected $_code = '';

    protected $_play = false;

    public function __construct() {
        // Hack pour gerer les instances.
        $instance = getenv('INSTANCE');
        if ($instance) {
            $this -> _rtmp = str_replace('/hal/', '/' . $instance . '/', $this -> _rtmp);
            $this -> _hls  = str_replace('/hal/', '/' . $instance . '/', $this -> _hls);
        }
    }
    /**
     * @param array $options
     * @return Ccsd_View_Helper_Jwplayer
     */
    public function jwplayer($options = [])
    {
        foreach ($options as $key => $value) {
            $this->{'_' . $key} = $value;
        }

        $this->_rtmp = str_replace('%SRC%', $this->_src, $this->_rtmp);
        $this->_hls  = str_replace('%SRC%', $this->_src, $this->_hls);


        return $this;
    }

    /**
     * @param $id
     * @return Ccsd_View_Helper_Jwplayer
     */
    public function setId($id)
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function render()
    {
        /** @var Ccsd_View $view */
        $view = $this->view;
        $view->jQuery()->addJavascriptFile('https://cdn.jwplayer.com/libraries/jI7CRvpx.js');

        $render = '<div id="' . $this->_id . '">' . $view->translate('Chargement de la vidéo') . '...</div>';
        $render .= '<script type="text/javascript">' . "\n";
        $render .= 'jwplayer.key="' . self::LICENCE . '";' . "\n";
        $render .= '$(document).ready( function() {' . "\n";
        $render .= ' if (navigator.userAgent.match(/android/i) != null){' . "\n";
        $render .= '  jwplayer("' . $this->_id . '").setup({' . "\n";
        $render .= '    file: "' . $this->_hls . '",' . "\n";
        $render .= '    width: ' . $this->_width . ',' . "\n";
        $render .= '    height: ' . $this->_height . ',' . "\n";
        $render .= '    type: "mp4",' . "\n";
        $render .= '    primary: "html5",' . "\n";
        $render .= '    stretching: "exactfit"' . "\n";
        $render .= '  });' . "\n";
        $render .= ' } else {' . "\n";
        $render .= '  jwplayer("' . $this->_id . '").setup({' . "\n";
        $render .= '    playlist: [{' . "\n";
        $render .= '      sources: [' . "\n";
        $render .= '        {file: "' . $this->_rtmp . '", type: "rtmp"},' . "\n";
        $render .= '        {file: "' . $this->_hls . '"}' . "\n";
        $render .= '      ],' . "\n";
        $render .= '      title: "' . trim(addslashes($this->_title)) . '",' . "\n";
        $render .= '      image: "' . $this->_image . '",' . "\n";
        $render .= '    }],' . "\n";
        $render .= '    primary: "flash",' . "\n";
        if ($this->_skin != '') {
            $render .= '      skin: "' . $this->_skin . '",' . "\n";
        }
        $render .= '    logo: { file: "' . $this->_logo . '", position: "bottom-right"},' . "\n";
        $render .= '    width: ' . $this->_width . ',' . "\n";
        $render .= '    height: ' . $this->_height . ',' . "\n";
        if ($this->_play) {
            $render .= '    autostart: "' . $this->_play . '",' . "\n";
        }
        if ($this->_link != '' || $this->_code != '') {
            $render .= '    sharing: {' . "\n";
            if ($this->_link != '') {
                $render .= '    link: "' . $this->_link . '",' . "\n";
            }
            if ($this->_code != '') {
                $render .= '    code: "' . $this->_code . '"' . "\n";
            }
            $render .= '    },' . "\n";
        }
        $render .= '  });' . "\n";
        $render .= ' }' . "\n";
        $render .= '})' . "\n";
        $render .= '</script>';

        return $render;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * @param Zend_View_Interface $view
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

    /**
     * @return $this
     */
    public function play()
    {
        $this->_play = true;
        return $this;
    }
}