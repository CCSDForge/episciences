<?php

class Episciences_Reviewer_ReviewingsManager
{
    /**
     * Compte le nombre de relectures d'une liste qui correspondent au(x) statut(s) en paramètre
     * @param array $reviewings
     * @param $status
     * @return int
     */
	public static function countByStatus(array $reviewings, $status): int
    {
		$count = 0;
	
		foreach ($reviewings as $oReviewing) {
			
			// var_dump ($oReviewing->getStatus());
				
			if (is_array($status)) {
				if (in_array($oReviewing->getStatus(), $status)) {
					$count++;
				}
			} else if ($oReviewing->getStatus() == $status) {
                $count++;
            }
		}
	
		return $count;
	}

    /**
     *  Renvoie une liste filtrée des relectures
     * @param array $reviewings
     * @param $params
     * @return array
     */
	public static function getReviewingsWith(array $reviewings, $params): array
    {
		$result = array();
	
		foreach ($reviewings as $oReviewing) {
	
			foreach ($params as $param=>$value) {
				$method = 'get'.ucfirst(strtolower($param));
				if (method_exists($oReviewing, $method) && $oReviewing->$method() != $value) {
					continue 2;
				}
			}
	
			$result[] = $oReviewing;
		}
	
		return $result;
	}

    /**
     * Renvoie la liste des relectures, triée par rvid
     * @param array $reviewings
     * @return array
     */
	public static function sortReviewingsByRvid(array $reviewings): array
    {
		$result = array();
		foreach ($reviewings as $oReviewing) {
			$result[$oReviewing->getRvid()][] = $oReviewing;
		}
		return $result;
	}
}