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
    public function __construct(mixed ...$args)
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        if (get_parent_class($this) !== false && method_exists(get_parent_class($this), '__construct')) {
            parent::__construct(...$args);
        }
    }

    public function convertImg($imgName) {}
    
    /**
     * Liste les image de la gallerie
     */
    public function listImg() {}
} 