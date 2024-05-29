<?php

class Ccsd_Form_Decorator_Picture extends Zend_Form_Decorator_Abstract
{
    protected $_uid;
    protected $_uuid;

    public function setUID ($uid)
    {
        $this->_uid = $uid;
        return $this;
    }
    
    public function getUID ()
    {
        $uid = $this->getOption('uid');
        
        if (isset($uid)) {
            $this->setUID($uid);
        }

        return $this->_uid ?? Ccsd_Auth::getUid();
    }
    
    /**
     * Render a form image
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $element = $this->getElement();

        if (null === $element->getView()) {
            return $content;
        }

        if (!Ccsd_User_Models_User::hasPhoto($this->getUID())) {
            return $content;
        }
        
        $placement     = $this->getPlacement();

        $image = "<div class='col-md-3' style='padding-left: 0px;'>";
        $image .= '<img src="/user/photo/size/large?v=' . Episciences_Auth::getPhotoVersion() .'" class="user-photo-normal img-responsive"/>';
        $image .= "<br>";
        $image .= "<a id='delete-photo' role='button' href='#' class='btn btn-default btn-xs' attr-uid='" . $this->getUID() . "'>";
        $image .= "<span class='glyphicon glyphicon-trash'></span>&nbsp;" . Ccsd_Form::getDefaultTranslator()->translate('Supprimer');
        $image .= "</a>";
        $image .= "</div>";

        switch ($placement) {
            case self::PREPEND:
                return $image . $content;
            case self::APPEND:
            default:
                return $content . $image;
        }
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        $uuid = $this->getOption('uuid');

        if (isset($uuid)) {
            $this->setUuid($uuid);
        }

        return $this->_uid ?? Ccsd_Auth::getUid();
    }

    /**
     * @param string $uuid
     * @return Ccsd_Form_Decorator_Picture
     */
    public function setUuid(string $uuid = ''): self
    {
        $this->_uuid = $uuid;
        return $this;
    }
}