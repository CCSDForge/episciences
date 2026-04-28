<?php
/*
 * The files right and owner need to be change to process the change
 */
const FILEMENU = "menu.php";

require_once "JournalScript.php";


class changeLabelMenu extends JournalScript
{
    public function __construct()
    {
        $args = [
            'rvcode=s'  => "Set journal code",
            'oldlabel=s'  => "Actual label",
            'newlabel=s'  => "New label",
            'language=s' => "translations language"
        ];
        // missing required parameters will be asked later
        $msg = '*** This script will change label inside menu files to do a tricky reset label (useful when we want to change default label)';
        $msg.= ' don\'t forget to change rights and owner of these files ***';
        // missing required parameters will be asked later

        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $args));
        $this->displayTrace($msg, true);
        parent::__construct();
    }


    /**
     * @throws Zend_Translate_Exception
     * @throws Zend_Exception
     * @throws Zend_Locale_Exception
     */
    public function run()
    {
        $continue = $this->ask("Do you want to continue ? (y or n)");
        if ($continue === 'y' || $continue === '' ) {
            $oldlabel="";

            $newlabel="";

            $language="";

            defineSQLTableConstants();
            $this->initApp(false);
            $this->initDb();

            $journals = Episciences_ReviewsManager::getList();

            $oldlabel = !$this->hasParam('oldlabel') ? $this->ask("Please enter the label you want to change") : $this->getParam('oldlabel');
            $newlabel = !$this->hasParam('newlabel') ? $this->ask("Please enter the label the new label") : $this->getParam('newlabel');
            $language = !$this->hasParam('language') ? $this->ask("Please enter the language where is the label") : $this->getParam('language');
            if ($oldlabel !== "" && $newlabel !== "" && $language !== "") {
                foreach ($journals as $journal) {
                    if ($journal->getCode() === 'portal') {
                        continue;
                    }
                    $journalPath = APPLICATION_PATH . '/../data/' . APPLICATION_ENV . '/' . $journal->getCode() . '/';
                    $languagesPath = $journalPath . 'languages/';
                    $file = $languagesPath.$language."/".FILEMENU;
                    if (is_dir($languagesPath) && file_exists($file)) {
                        $oldlabelReg = '/\b'.$oldlabel.'\b/';
                        file_put_contents($file,preg_replace($oldlabelReg,$newlabel,file_get_contents($file)));
                    }
                }
            }
        }
    }
}
$script = new changeLabelMenu();
$script->run();
