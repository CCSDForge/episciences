<?php

class Episciences_SectionsManager 
{
	public const TRANSLATION_PATH = REVIEW_LANG_PATH;
	public const TRANSLATION_FILE = 'sections.php';

    /**
     * Renvoie la liste de toutes les rubriques d'une revue
     * @param array|null $options
     * @param bool $toArray
     * @return array
     */
	public static function getList(array $options = null, $toArray = false)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sections = array();
		
	    $select = $db->select()->from(T_SECTIONS)->order('POSITION', 'ASC');
	    if ($options) {
	    	foreach ($options as $cmd=>$params) {
	    		$select->$cmd($params);
	    	}
	    } else {
	    	$select->where('RVID = ?', RVID);
	    }
	    $data = $db->fetchAll($select);
	    
		if ($data) {
			foreach ($data as $sectionOptions) {
				$oSection = new Episciences_Section($sectionOptions);
				$sections[$oSection->getSid()] = ($toArray) ? $oSection->toArray() : $oSection;
			}
		}
		return $sections;
	}

    /**
     * Renvoie la rubrique dont l'id est passé en paramètre
     * @param $sid
     * @return bool|Episciences_Section
     */
	public static function find($sid)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$select = $db->select()->from(T_SECTIONS)->where('SID = ?', $sid);
		$options = $db->fetchRow($select);
		if (empty($options)) {
			return false;
		}
		$oSection = new Episciences_Section($options);
		$oSection->loadSettings();
		
		return $oSection;
	}

    /**
     * Supprime une rubrique
     * @param $id
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
	public static function delete($id)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$path = self::TRANSLATION_PATH;
		$file = self::TRANSLATION_FILE;
		 
		// Si des articles sont rattachés à cette rubrique, on empêche sa suppression
		/*
		$sql = $db->select()->from(T_PAPERS, 'COUNT(DOCID)')->where('SID = ?', $id);
		if ($db->fetchOne($sql)) {
			echo 'Des articles ont déjà été publiés dans cette rubrique.';
			return false;
		}
		*/
		 
		// Récupération de l'id de position pour MAJ des autres rubriques
		$select = $db->select()->from(T_SECTIONS)->where('SID = ?', $id);
		$data = $select->query()->fetch();
		$position = $data['POSITION'];
		$rvid = $data['RVID'];
			
		if ($db->delete(T_SECTIONS, 'SID = '.$id)) {
			
			// Suppression des traductions
			$translations = Episciences_Tools::getOtherTranslations($path, $file, '#section_'.$id.'_#');
			Episciences_Tools::writeTranslations($translations, $path, $file);
	
			// Mise à jour de l'id de position des autres rubriques
			$db->update(
					T_SECTIONS,
					array('POSITION' => new Zend_DB_Expr('POSITION-1')),
					array('RVID = ?' => $rvid, 'POSITION > ?' => $position)
			);
			
			// Suppression des paramètres de la rubrique
			$db->delete(T_SECTION_SETTINGS, 'SID = '.$id);
	
			return true;
		}
	
		return false;
	}

    /**
     * @Deprecated. Use Episciences_VolumesAndSectionsManager::sort
     * @param $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
	public static function sort($params)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		$select = $db->select()
		->from(T_SECTIONS, 'COUNT(SID) AS results')
		->where('RVID = ?', RVID);

		if ((int)$db->fetchOne($select) < 1) {
			return false;
		}

		// Si c'est bien le cas, on update les positions
		foreach ($params['sorted'] as $i => $section)
		{
			preg_match("#section_(.*)#", $section, $matches);
			if (empty($matches)) {
				continue;
			}
			$sid = $matches[1];
			$to = $i+1;
		
			// Update position de la rubrique déplacée
			$db->update(T_SECTIONS, array('POSITION' => $to), array('SID = ?' => $sid));
		}
		
		return true;

	}

    /**
     * Renvoie le formulaire d'assignation de rédacteurs à une rubrique
     * @param null $currentEditors
     * @return bool|Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
	public static function getEditorsForm($currentEditors = null)
	{
		// Passer les param par défaut (currentEditors)
		$editors = Episciences_Review::getEditors(false);
		
		if ($editors) {
	
			$form = new Zend_Form();
			$form->setAction('/section/saveeditors');

			// Filtrer les résultats
			$form->addElement(new Zend_Form_Element_Text(array(
					'name'		=> 'filter',
					'class'		=> 'form-control',
					'style'		=> 'margin-bottom: 10px',
					'placeholder'=> Zend_Registry::get('Zend_Translate')->translate('Rechercher un rédacteur')
			)));

			// Checkbox
			foreach($editors as $uid=>$editor) {
				$options[$uid] = $editor->getFullname();
			}
			
			$form->addElement(new Zend_Form_Element_MultiCheckbox(array(
					'name'			=>	'editors',
					'multiOptions'	=>	$options,
					'separator'		=>	'<br/>',
					'decorators'	=>	array('ViewHelper', array('HtmlTag', array('tag' => 'div','class' => 'editors-list')) )
			)));
			
			if (is_array($currentEditors)) {
				$form->populate(array('editors'=>array_keys($currentEditors)));
			}
			
			// Bouton de validation
			$form->addElement(new Zend_Form_Element_Button(array(
					'name'		=> 'submit',
					'type'		=> 'submit',
					'class'		=> 'btn btn-default',
					'label'		=> 'Valider',
					'decorators'=> array(array('HtmlTag', array('tag' => 'div', 'openOnly' => true, 'class'=>'control-group')), 'ViewHelper' )
			)));

			// Bouton d'annulation
			$form->addElement(new Zend_Form_Element_Button(array(
					'name'		=> 'cancel',
					'class'		=> 'btn btn-default',
					'label'		=> 'Annuler',
					'onclick'	=> 'closeResult()',
					'decorators'=> array('ViewHelper', array('HtmlTag', array('tag' => 'div', 'closeOnly' => true)))
			)));
			
			return $form;
		}
	
		return false;
	}

    /**
     * Renvoie le formulaire de création/modification d'une rubrique
     * @param null $defaults
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
	public static function getForm($defaults=null)
	{
		$form = new Ccsd_Form;
		$form->setAttrib('class', 'form-horizontal');
		
		$lang = array('class'=>'Episciences_Tools', 'method'=>'getLanguages');
		$reqLang = array('class'=>'Episciences_Tools', 'method'=>'getRequiredLanguages');
 		
		// Nom de la rubrique
		$form->addElement(new Ccsd_Form_Element_MultiTextSimpleLang(array(
				'name'		=> 'title',
				'label'		=> 'Nom',
				'populate'	=> $lang,
				'validators'=> array(new Ccsd_Form_Validate_RequiredLang(array('populate' => $reqLang))),
				'required'	=> true,
				'display'	=> Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED
		)));
		
		// Description de la rubrique
		$form->addElement('MultiTextAreaLang', 'description', array(
				'label'		=> 'Description',
				'populate'	=> $lang,
				'tiny'		=> true,
				'rows'		=> 5,
				'display'	=> Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED
		));
		
		// Statut (ouvert/fermé)
		$form->addElement('select', 'status', array(
				'label'			=>	'Statut',
				'multioptions'	=>	array(0=>'Fermé', 1=>'Ouvert'),
				'value'			=> 1,
				'style'			=>	'width:300px'
		));
		
		// Boutons : Valider et Annuler
		$form->setActions(true)->createSubmitButton('submit', array(
				'label' => 'Valider',
				'class'	=> 'btn btn-primary'
		));
		$form->setActions(true)->createCancelButton('back', array(
				'label' => 'Annuler',
				'class'	=> 'btn btn-default',
				'onclick'=> "window.location='/section'"));
		
		if ($defaults) {
			$form->setDefaults($defaults);
		}
		 
		return $form;
	}
}