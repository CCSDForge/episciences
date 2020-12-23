<?php

/**
 *
 * @author yannick
 *
 */
class Episciences_Website_Navigation_Page extends Ccsd_Website_Navigation_Page
{
    /**
     * @var Episciences_Website_Navigation
     */
    protected $nav;


    /**
     * Tableau des acl de la page
     * @var array
     */
    protected $_acl = [];

    public function setOptions($options = [])
    {
        parent::setOptions($options);

        // Gestion des droits d'accès de la page
        if (array_key_exists('visibility', $options)) {
            $visibility = $options['visibility'];
            switch ($visibility) {
                case 0:
                    // accès libre
                    $this->setAcl([]);
                    break;
                case 1:
                    // accès réservé aux membres
                    $this->setAcl([Episciences_Acl::ROLE_MEMBER]);
                    break;
                case 2:
                    // droits d'accès personnalisés
                    $this->setAcl($options['acl']);
                    break;
            }
        }
    }

    public function toArray()
    {
        $array = parent::toArray();
        if ($this->getAcl()) {
            $array['privilege'] = implode(',', $this->getAcl());
        }
        return $array;
    }

    public function getAcl()
    {
        return $this->_acl;
    }

    public function setAcl($privileges)
    {
        $this->_acl = $privileges;
    }

    public function getForm($pageidx)
    {
        parent::getForm($pageidx);

        // Récupération des différents rôles
        $acl = new Episciences_Acl();
        $roles = $acl->getRolesCodes();
        unset($roles[$acl::ROLE_ROOT]);
        $selectedRoles = $this->getAcl();

        if (empty($selectedRoles)) {
            $visibility = 0;
        } elseif (count($selectedRoles) == 1 && in_array(Episciences_Acl::ROLE_MEMBER, $selectedRoles)) {
            $visibility = 1;
        } else {
            $visibility = 2;
        }

        // Menu déroulant pour choisir la visibilité de la page (publique, privée ou personnalisée)
        $this->_form->addElement('select', 'visibility',
            ['label' => 'Visibilité de la page',
                'belongsTo' => 'pages_' . $pageidx,
                'onchange' => "setVisibility($pageidx, this)",
                'multioptions' => ['Publique', 'Réservée aux membres', 'Personnalisée'],
                'value' => $visibility
            ]);

        $rolesToolTip = Zend_Registry::get('Zend_Translate')->translate("Si aucun rôle n'est sélectionné, la page sera publique");
        // Multicheckbox pour personnaliser la visibilité de la page (accès limité par rôle)
        $display = (true) ? 'none' : '';
        $this->_form->addElement('multiCheckbox', 'acl',
            ['label' => 'Visible par : ',
                'decorators' => [
                    'Label' => ['decorator' => 'Label', 'options' => (['style' => 'display: inline', 'data-toggle' => 'tooltip', 'title' => $rolesToolTip])],
                    'ViewHelper',
                    'HtmlTag' => ['decorator' => 'HtmlTag', 'options' => (['tag' => 'div', 'class' => 'multicheckbox', 'style' => 'display:' . $display])],
                ],
                'separator' => '',
                'belongsTo' => 'pages_' . $pageidx,
                'multioptions' => $roles,
                'value' => $selectedRoles]);

        return $this->_form;
    }

    /**
     * Retourne le label de la page
     * @see Ccsd_Website_Navigation_Page::getLabel($lang)
     */
    public function getLabel($lang)
    {
        $label = parent::getLabel($lang);
        if ($label === '') {
            $label = $this->getPageClassLabel($lang);
        }
        return $label;
    }

    /**
     * Retourne la traduction du nom de la classe
     */
    public function getPageClassLabel($lang = '')
    {
        if ($lang === '') {
            $lang = Zend_Registry::get('Zend_Translate')->getLocale();
        }
        return Zend_Registry::get('Zend_Translate')->translate(get_class($this), $lang);
    }

    /**
     * Indique si la page est un répertoire
     * @return boolean
     */
    public function isFolder()
    {
        return $this->getPageClass() === 'Episciences_Website_Navigation_Page_Folder';
    }

    public function isCustom()
    {
        return $this->getPageClass() === 'Episciences_Website_Navigation_Page_Custom';
    }

    public function isFile()
    {
        return $this->getPageClass() === 'Episciences_Website_Navigation_Page_File';
    }


}
