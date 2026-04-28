<?php

class Ccsd_Website_Navigation_Page_Gallery extends Ccsd_Website_Navigation_Page
{      
    
	/**
     * Lien de l'image
     * @var string
     */
    protected $_path_src = '';
    
    /**
     * Répertoire de la vignette
     * @var string
     */
    protected $_path_thumb = '';
    
    /**
     * Répertoire de l'image
     * @var string
     */
    protected $_path_img = '';

    /**
     * Fonction qui va redimensionner l'image passée en parametre dans des dimensions données
     * @param string $imgName
     */
    public function convertImg($imgName) {}
    
    /**
     * Liste les image de la gallerie
     */
    public function listImg() {}
} 