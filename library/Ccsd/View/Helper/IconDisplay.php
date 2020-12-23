<?php

/**
 * Pratique pour l'accessibilité
 * Si l'icone est informative, il créé un span avec la classe sr-only avec le texte à lire pour le lecteur d'écran
 * Sinon, il cache l'icone pour le lecteur d'écran
 */

class Ccsd_View_Helper_IconDisplay extends Zend_View_Helper_Abstract
{
    /**
     * Description de l'icone
     *
     * @var string
     */
    protected $_output = "";
    /**
     * Id du span icone
     *
     * @var string
     */
    protected $_id = "";

    /**
     * @param string $icon
     * @param string $desc
     * @param string $id
     */
    public function iconDisplay ($icon, $desc=null, $id=null)
    {
        if($desc){
            $this->_output .= "<span class='sr-only'> " . $desc . "</span>";
        }
        if($id){
            $this->_id .= "id='" . $id . "'";
        }
        ?>

        <span class="<?php echo $icon ?>" aria-hidden="true" <?php echo $this->_id ?>></span>
    <?php
        echo $this->_output;
    }
}