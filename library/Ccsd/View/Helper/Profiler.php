<?php
/**
 * Helper de vue pour le profilage de l'adaptateur DB
 * @author loic
 *
 */
class Ccsd_View_Helper_Profiler
{
	/**
	 * Créé le lien d'une page
	 * @param Zend_Navigation_Page $page
	 * @param string $prefixUrl
	 * @return string
	 */
	public function profiler ()
	{
		/* @var $profiler Zend_Db_Profiler */
		$profiler = Zend_Db_Table_Abstract::getDefaultAdapter()->getProfiler();
		
		if (!$profiler->getEnabled())
			return "";
		
		$cache = Zend_Db_Table_Abstract::getDefaultMetadataCache();
		
		$xhtml = "";
		
		$xhtml .= "<span class='label label" . ($cache ? "-success" : "-danger") . "'>";
		
		$xhtml .= "Cache " . ($cache ? "On" : "Off");
		
		$xhtml .= "</span>";
		
		$xhtml .= "<table class='table'>";
		
		$xhtml .= "<thead>";
		$xhtml .= "<tr>";
		$xhtml .= "<th>#</th>";
		$xhtml .= "<th>SQL</th>";
		$xhtml .= "<th>Durée</th>";
		$xhtml .= "</tr>";
		$xhtml .= "</thead>";
		
		$xhtml .= "<tbody>";
		
		/* @var $query Zend_Db_Profiler_Query */
		foreach ($profiler->getQueryProfiles() as $i => $query) {
			$xhtml .= "<tr>";
			$xhtml .= "<td>$i</td>";
			$xhtml .= "<td>" . $query->getQuery() . "</td>";
			$xhtml .= "<td>";
			$xhtml .= $query->getElapsedSecs();
			$xhtml .= "</td>";
			$xhtml .= "</tr>";
		}

		$xhtml .= "</tbody>";
		$xhtml .= "</table>";
		
		return $xhtml;
	}

}