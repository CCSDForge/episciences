<?php

class Episciences_Website_Navigation_Page_Predefined extends Episciences_Website_Navigation_Page
{
    const PERMALIEN = 'permalien';
    protected $_controller = 'page';
    protected $_multiple = false;
    protected string $_page = '';


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

        $this->_form->addElement('select', 'visibility',
            ['label' => 'Visibilité de la page',
                'belongsTo' => 'pages_' . $pageidx,
                'onchange' => "setVisibility($pageidx, this)",
                'multioptions' => ['Publique'], // Predefined pages are always public
                'value' => 0 // 0 means public
            ]);

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

}
