<?php

require_once APPLICATION_PATH.'/modules/common/controllers/UserDefaultController.php';

class UserController extends UserDefaultController
{

	// Page d'accueil d'un utilisateur connecté
	public function dashboardAction()
	{


	    /** @var  $user Episciences_User */

		// Récupération des infos sur les revues où l'utilisateur a été actif
		$user = Episciences_Auth::getInstance()->getIdentity();
		$reviews = $user->getReviews();
		$this->view->reviews = $reviews;

		
		// Blocs "Rédacteur en chef" (par journal)
		if (Episciences_Auth::isChiefEditor('all')) {
			$reviewsPapers = array();
			$settings = array('isNot' => array('status'=> array(Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED)) );
			foreach ($reviews as $rvid=>$review) {
				if ($rvid != 0 && Episciences_Auth::isChiefEditor($rvid, true)) {
					$reviewsPapers[$rvid] = $review->getPapers($settings);
				}
			}
			$this->view->reviewsPapers = $reviewsPapers;
		}
		
		
		
		// Blocs "Rédacteur" (par journal)
		if (Episciences_Auth::isEditor('all', true)) {
			$editor = new Episciences_Editor();
			$editor->find(Episciences_Auth::getUid());
			$editor->loadAssignedPapers();
			
			$managedPapers = $editor->getAssignedPapers(); 
			$managedPapers = Episciences_PapersManager::sortBy($managedPapers, 'rvid');
			$this->view->reviewsManagedPapers = $managedPapers;
		}
		
		
		
		// Bloc "Mes articles"
		$settings = array(
				'is'	=> array('uid'=>Episciences_Auth::getUid()),
				'isNot' => array('status'=> array(Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED)) );
		$submittedPapers = Episciences_PapersManager::getList($settings);
		$submittedPapers = Episciences_PapersManager::sortBy($submittedPapers, 'rvid');		
		$this->view->submittedPapers = $submittedPapers;
		
		
		// Bloc "Mes relectures"
		/*
		if (Episciences_Auth::isReviewer('all')) {
			$reviewer = new Episciences_Reviewer();
    		$reviewer->find(Episciences_Auth::getUid());
			$reviewer->loadReviewings();
			
			$reviewings = $reviewer->getReviewings();
			$this->view->reviewings = Episciences_Reviewer_ReviewingsManager::sortReviewingsByRvid($reviewings);
		}
		*/
		
		// Bloc Mon compte
		$this->view->user = $user->toArray();

		
	}
	
}