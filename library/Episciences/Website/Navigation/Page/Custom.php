<?php

/**
 * Page personnalisable
 *
 */
class Episciences_Website_Navigation_Page_Custom extends Episciences_Website_Navigation_Page
{
    const PERMALIEN = 'permalien';
    /**
     * Max old versions of pages
     */
    const MAX_OLD_VERSIONS = 3;
    /**
     * Page éditable
     * @var boolean
     */
    protected $_editable = true;

    /**
     * Controller
     * @var string
     */
    protected $_controller = 'page';

    /**
     * Page multiple
     * @var boolean
     */
    protected $_multiple = true;

    /**
     * Lien permanent
     * @var string
     */
    protected $_permalien = '';

    /**
     * Nom de la page
     * @var string
     */
    protected $_page = '';

    /**
     * intialisation des options de la page
     * @param array $options
     * @see Ccsd_Website_Navigation_Page::setOptions($options)
     */
    public function setOptions($options = [])
    {
        foreach ($options as $option => $value) {
            $option = strtolower($option);
            if ($option === self::PERMALIEN) {
                $this->setPermalien($value);
            } elseif ($option === 'page') {
                $this->setPage($value);
            }
        }
        parent::setOptions($options);
    }

    /**
     * Initialisation du nom de la page
     */
    public function setPage($page)
    {
        $this->_page = $page;
    }

    /**
     * Conversion de la page en tableau associatif
     * @see Ccsd_Website_Navigation_Page::toArray()
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array[self::PERMALIEN] = $this->getPermalien();
        return $array;
    }

    /**
     * Retourne le lien permanent
     * @return string
     */
    public function getPermalien()
    {
        return $this->_permalien;
    }

    /**
     * Initialisation du permalien
     */
    public function setPermalien($permalien)
    {
        if ($this->_permalien != '' && $this->_permalien != $permalien) {
            //L'utilisateur a changé le nom du permalien, on déplace les fichiers s'il y en a
            $this->renamePage($this->_permalien, $permalien);
        }
        $this->_permalien = $permalien;
    }

    /**
     * Retourne l'action de la page (permaliend ans notre cas)
     * @see Ccsd_Website_Navigation_Page::getAction()
     */
    public function getAction()
    {
        return $this->getPermalien();
    }

    /**
     * Retour du formulaire e création de la page
     * @see Ccsd_Website_Navigation_Page::getForm()
     */
    public function getForm($pageidx)
    {
        parent::getForm($pageidx);
        if (!$this->_form->getElement(self::PERMALIEN)) {
            $this->_form->addElement('text', self::PERMALIEN, [
                'required' => true,
                'label' => 'Lien permanent',
                'value' => $this->getPermalien(),
                'belongsTo' => 'pages_' . $pageidx,
                'class' => 'permalien',
            ]);
        }
        $this->_form->getElement('labels')->setOptions(['class' => 'inputlangmulti permalien-src']);
        return $this->_form;
    }

    /**
     * Retourne les informations complémentaires spécifiques à la page
     * @see Ccsd_Website_Navigation_Page::getSuppParams()
     */
    public function getSuppParams()
    {
        $res = '';
        if ($this->_permalien != '') {
            $res = serialize([self::PERMALIEN => $this->_permalien]);
        }
        return $res;
    }

    /**
     * Enregistrement du contenu d'une page
     * @param array $data
     */
    public function setContent($data, $locales = [])
    {
        if (!is_dir(REVIEW_PAGE_PATH)) {
            if (!mkdir($concurrentDirectory = REVIEW_PAGE_PATH, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        foreach ($locales as $code => $lang) {
            if (($filepath = realpath($this->getPagePath($code))) !== false) {
                file_put_contents($filepath, "");
            }
        }

        $maxVersionsNb = self::MAX_OLD_VERSIONS;

        foreach ($data as $lang => $content) {

            // Si une ancienne version de cette page existe déjà
            if ($this->getContent($lang)) {
                similar_text($content, $this->getContent($lang), $similarity);

                // Et qu'elle est différente de ce qu'on souhaite enregistrer
                if ($similarity < 100) {

                    // On renomme l'ancienne page
                    $oldname = $this->getPagePath($lang);
                    $extension = strrchr($oldname, '.');
                    $newname = str_replace($extension, '', $oldname) . '.' . time() . $extension;
                    rename($oldname, $newname);

                    $versions = $this->getPreviousVersions($lang);

                    // Si on a dépassé le nombre max de versions de la page, on supprime les plus anciennes
                    if (count($versions) > $maxVersionsNb) {
                        krsort($versions);
                        $remove = array_slice($versions, $maxVersionsNb);
                        foreach ($remove as $key => $file) {
                            unlink(REVIEW_PAGE_PATH . $file);
                            unset($versions[$key]);
                        }
                    }
                }
            }

            file_put_contents($this->getPagePath($lang), $content);
        }
    }

    protected function getPagePath($lang, $page = '')
    {
        if ($page == '') {
            $page = $this->_page;
        }
        return REVIEW_PAGE_PATH . $page . '.' . $lang . '.html';
    }

    /**
     * Retourne le contenu d'une page
     * @param string $lang
     * @return string
     */
    public function getContent($lang)
    {
        $filename = $this->getPagePath($lang);
        if (file_exists($filename)) {
            return file_get_contents($filename);
        }
        return '';
    }

    public function getPreviousVersions($lang)
    {
        $versions = [];

        // Parcours du répertoire
        if ($dir = opendir(REVIEW_PAGE_PATH)) {
            while ($file = readdir($dir)) {
                $details = $this->getDetails($file);
                $filepath = REVIEW_PAGE_PATH . $details['name'] . '.' . $details['lang'] . '.' . $details['extension'];
                // On cherche les fichiers qui portent le même nom (après suppression du timestamp) que celui qu'on souhaite enregistrer
                if (array_key_exists('timestamp', $details) && $filepath == $this->getPagePath($lang)) {
                    $versions[$details['timestamp']] = $file;
                }
            }
            closedir($dir);
        }
        return $versions;
    }

    protected function getDetails($filename)
    {
        $details = [];
        $parts = explode('.', $filename);

        $details['extension'] = array_pop($parts);
        if (is_numeric($parts[count($parts) - 1])) {
            $details['timestamp'] = array_pop($parts);
            $details['lang'] = array_pop($parts);
        } else {
            $details['lang'] = array_pop($parts);
        }
        $details['name'] = implode('.', $parts);
        return $details;
    }

    /**
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public function getContentForm()
    {
        $content = [];

        foreach ($this->_languages as $lang) {
            $content[$lang] = $this->getContent($lang);
        }

        $form = new Ccsd_Form();
        $form->setName("page_modification");
        $form->setMethod("post");
        $form->setAttrib("class", "form-horizontal");

        $form->addElement('MultiTextAreaLang', 'content', [
            'populate' => ['class' => 'Episciences_Tools', 'method' => 'getLanguages'],
            'value' => $content,
            'tiny' => true,
            'display' => Ccsd_Form_Element_MultiText::DISPLAY_SIMPLE
        ]);

        $form->getElement('content')->getDecorator('HtmlTag')->setOption('class', 'col-md-12');

        $form->setActions(true)->createSubmitButton('submit');

        return $form;
    }

    /**
     * @param $old
     * @param $new
     */
    public function renamePage($old, $new)
    {
        foreach ($this->_languages as $lang) {
            $filename = $this->getPagePath($lang, $old);
            if (file_exists($filename)) {
                rename($filename, $this->getPagePath($lang, $new));
            }
        }
    }

}
