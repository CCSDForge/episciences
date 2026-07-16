<?php


/**
 * View Helper to generate a list of files
 *
 */
class  Episciences_View_Helper_MasterFileFormSelect extends Zend_View_Helper_FormSelect
{

    public const EMPTY_LABEL =   'Sélectionner un fichier';

    public function masterFileFormSelect($name, $docId, $value = null, $attribs = []): string
    {
        $files = $this->buildForm($docId);

        if (!$files) {
            return '';
        }

        if (!isset($attribs['id'])) {
            $attribs['id'] = $name;
        }

        $attribs['class'] = $attribs['class'] ?? sprintf('%s-select', $name);

        return $this->view->formSelect($name, $value, $attribs, $files);
    }

    protected function buildForm($docId = null): ?array
    {
        if (!$docId) {
            return null;
        }

        $files = Episciences_Paper_FilesManager::findByType($docId);

        if(empty($files)){
            return null;
        }

        $options = ['' => sprintf('--- %s ---', $this->view->translate(self::EMPTY_LABEL))];

        /** @var Episciences_Paper_File $file */

        foreach ($files as $id => $file) {
            $options[$id] = $file->getName();
        }

        ksort($options);
        return $options;
    }

}

