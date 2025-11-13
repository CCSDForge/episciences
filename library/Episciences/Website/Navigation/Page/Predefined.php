<?php

class Episciences_Website_Navigation_Page_Predefined extends Episciences_Website_Navigation_Page
{
    const PERMALIEN = 'permalien';
    protected $_controller = 'page';
    protected $_multiple = false;
    protected string $_page = '';

    private static ?array $_cachedPermaliens = null;


    public function toArray(): array
    {
        $array = parent::toArray();
        $array[self::PERMALIEN] = $this->getPermalien();
        return $array;
    }


    public function getPermalien(): string
    {
        return $this->_permalien;
    }


    public function setPermalien($permalien): void
    {
        $this->_permalien = $permalien;
    }


    public function getAction(): string
    {
        return $this->getPermalien();
    }


    public function getForm($pageidx)
    {
        parent::getForm($pageidx);
        if (!$this->_form->getElement(self::PERMALIEN)) {
            $this->_form->addElement('hidden', self::PERMALIEN, [
                'required' => true,
                'value' => $this->getPermalien(),
                'belongsTo' => 'pages_' . $pageidx,
                'class' => 'permalien',
            ]);
        }
        $this->_form->getElement('labels')->setOptions(['class' => 'inputlangmulti permalien-src']);
        return $this->_form;
    }


    public function setOptions($options = []): void
    {
        foreach ($options as $option => $value) {
            $option = strtolower($option);

            switch ($option) {
                case self::PERMALIEN:
                    $this->setPermalien($this->_permalien);
                    break;
                case 'page':
                    $this->setPage($value);
                    break;
            }
        }

        parent::setOptions($options);
    }


    public function setPage($page): void
    {
        $this->_page = $page;
    }

    public function getSuppParams(): string
    {
        $res = '';
        if ($this->_permalien != '') {
            $res = serialize([self::PERMALIEN => $this->_permalien]);
        }
        return $res;
    }

    /**
     * Récupère tous les permaliens des classes prédéfinies (avec cache)
     *
     * @return array Tableau associatif [className => permalien]
     * @throws ReflectionException
     */
    public static function getAllPermaliens(): array
    {
        if (self::$_cachedPermaliens === null) {
            $permaliens = [];
            $pageDir = APPLICATION_PATH . '/../library/Episciences/Website/Navigation/Page/';

            foreach (glob($pageDir . '*.php') as $file) {
                $className = 'Episciences_Website_Navigation_Page_' . basename($file, '.php');

                if (!class_exists($className)) {
                    require_once $file;
                }

                if (is_subclass_of($className, __CLASS__)) {
                    $reflection = new ReflectionClass($className);
                    $defaults = $reflection->getDefaultProperties();

                    if (!empty($defaults['_permalien'])) {
                        $permaliens[$className] = $defaults['_permalien'];
                    }
                }
            }

            self::$_cachedPermaliens = $permaliens;
        }

        return self::$_cachedPermaliens;
    }

    /**
     * Vérifie si un pageCode (permalien) correspond à une page prédéfinie
     *
     * @param string $pageCode Le permalien à vérifier
     * @return bool True si le pageCode correspond à une page prédéfinie
     * @throws ReflectionException
     */
    public static function isPredefinedPage(string $pageCode): bool
    {
        $permaliens = self::getAllPermaliens();
        return in_array($pageCode, $permaliens, true);
    }

}
