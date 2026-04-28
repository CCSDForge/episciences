<?php
require_once 'Zend/View/Helper/Navigation.php';

class Ccsd_View_Helper_Nav extends Zend_View_Helper_Navigation
{
	protected $_roles = null;
	
	public function nav(Zend_Navigation_Container $container = null) 
	{
		return $this->navigation($container);
	}
	
	public function setRoles($roles)
	{
		if (is_string($roles)) {
			$roles = array($roles);
		}
		$this->_roles = $roles;
		
		return $this;
	}
	
	public function getRoles()
	{
		return $this->_roles;
	}
	
	protected function _acceptAcl(Zend_Navigation_Page $page)
	{
		if (!$acl = $this->getAcl()) {
			// no acl registered means don't use acl
			return true;
		}
	
		$roles = $this->getRoles();
		$resource = $page->getResource();
		$privilege = $page->getPrivilege();
	
		if ($resource || $privilege) {
			// determine using helper role and page resource/privilege
			$accept = false;
			foreach ($roles as $role) {
				//Si la ressource n'est pas dans les ACL c'est un lien exterieur ou un fichier
				if (! $acl->has($resource) || (!$accept && $acl->isAllowed($role, $resource, $privilege))) {
					$accept = true;
				}
			}
			return $accept;
		}
	
		return true;
	}
}