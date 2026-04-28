<?php

class Episciences_EditorsManager
{
    /**
     * Returns the list of editors of a journal
     * @return Episciences_User[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getList(): array
    {
        return Episciences_Review::getEditors();
    }

    /**
     * Renvoie les suggestions de rédacteurs pour un papier
     * @param $docId
     * @return array
     */
    public static function getSuggestedEditors($docId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPER_SETTINGS, 'value')
            ->where('DOCID = ?', $docId)
            ->where('SETTING = \'suggestedEditor\'');

        return $db->fetchCol($select);
    }

    /**
     * @param $docId
     * @param int $row
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getRefusedMonitoringForm($docId, int $row = 5): \Ccsd_Form
    {
        $cStr = 'refused_monitoring';
        $id = $cStr . '_form';
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('id', $id);
        $form->setAction('/administratepaper/saverefusedmonitoring?id=' . $docId);

        $form->addElement('textarea', $cStr . '_comment', [
            'label' => "Commentaire",
            'id' => $cStr . '_comment',
            'description' => 'Veuillez détailler les raisons de votre refus :',
            'rows' => $row
        ]);

        $form->addElement('submit', 'confirm_' . $cStr, [
            'label' => "Confirmer",
            'id' => 'confirm_' . $cStr,
            'class' => 'btn btn-primary',
            'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'form-actions text-center']], 'ViewHelper']
        ]);

        $form->addElement('button', 'cancel_' . $cStr, [
            'label' => 'Annuler',
            'id' => 'cancel_' . $cStr,
            'class' => 'btn btn-default',
            'onclick' => "$('#$id').remove(); $( \"button[id^='$cStr\_button-']\").show();",
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]
        ]);

        return $form;
    }

    /**
     * @param int $docId
     * @return array
     */
    public static function getEditorsSuggestionsByPaper(int $docId): array
    {
        $types = [
            'types' => [
                Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION,
                Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,
                Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION
            ]
        ];

        return self::getCommentsByTypes($docId, $types);
    }

    /**
     * @param $docId
     * @return array
     */
    public static function getRejectionComments($docId): array
    {
        return self::getCommentsByTypes($docId, ['type' => Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED]);
    }

    /**
     * @param int $docId
     * @param array $types
     * @return array
     */
    private static function getCommentsByTypes(int $docId, array $types): array
    {
        $list = Episciences_CommentsManager::getList($docId, $types);
        return ($list) ?: [];
    }

    /**
     * Vérifié si un rédacteur a refusé de suivre l'article
     * @param $uid
     * @param $docId
     * @return bool
     */
    public static function isMonitoringRefused($uid, $docId): bool
    {
        $isRefused = false;
        /**  @var  array $rejection */
        foreach (self::getRejectionComments($docId) as $rejection) {
            if ($uid === (int)$rejection['UID']) {
                return true;
            }
        }
        return $isRefused;
    }

}