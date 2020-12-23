<?php

class Ccsd_Acl extends Zend_Acl 
{
	const DEFAULT_ROLE = 'guest';
    
    protected $_roles = array ('guest' => null, 'member' => 'guest', 'administrator' => 'member');
	protected $_defaultAcl = array();
    protected $_defaultResources = array();
    
    public function __construct() {}
    
    /**
     * Chargement des Acl par défaut (règles pour les actions non présentes dans 
     * la navigation du site
     */
    public function loadDefaultAcl()
    {
    	//Initialisation des ACL par défaut
    	if (is_array($this->_defaultAcl)) {
    		foreach ($this->_defaultAcl as $role => $rules) {
    			if (isset($rules['allow']) && is_array($rules['allow'])) {
	    			foreach (array_keys($rules['allow']) as $resource) {
	    				if (! $this->has($resource)) {
	    					$this->addResource(new Zend_Acl_Resource($resource));
	    				}
	    				$this->allow($role, new Zend_Acl_Resource($resource));
	    			}
    			}
    			
    		}		
    	}
    }
    
    /**
     * Chargement des ACL à partir d'un fichier
     * @param unknown_type $filename
     */
    public function loadFromFile($filename)
    {
        if ($filename != null && is_file($filename)) {
            $config = new Zend_Config_Ini($filename);
            $config = $config->toArray();
            $this->loadRoles($config['roles']);
            $this->loadResources($config['resources']);
            $this->loadRules($config);
        }
    }
       
    /**
     * Chargement des Acl à partir du menu du site
     * @param array $filenames
     */
    public function loadFromNavigation($filenames)
    {
    	$this->loadRoles();
        if (! is_array($filenames)) {
    	    $filenames = array($filenames);
    	}
    	$config = array();
    	foreach ($filenames as $filename) {
    	    if (file_exists($filename)) {
        	    $c = new Zend_Config_Json($filename, null, [ 'ignore_constants' => true ]);
                $config = array_merge($config, $c->toArray());
    	    }
    	}
    	$navigation = new Ccsd_Navigation(new Zend_Config($config));

        $this->loadNavigationLevel($navigation->getPages());
		
		$this->loadDefaultAcl();
		
		//Ajoutd des ressources par défaut
		foreach ($this->_defaultResources as $resource) {
			$this->addResource(new Zend_Acl_Resource($resource));
			$this->allow(self::DEFAULT_ROLE, new Zend_Acl_Resource($resource));
		}
		
		/*foreach ($navigation->getPages() as $page) {
			$resource = $this->getResource($page);
			if ($resource != '') {
			    $privilege = $page->getPrivilege();
			    $this->loadResource($resource, $privilege);
			}
			if ($page->hasChildren()) {
				foreach ($page->getPages() as $spage) {
					$resource = $this->getResource($spage);
					if ($resource != '') {
					    $privilege = $spage->getPrivilege();
					    $this->loadResource($resource, $privilege);
					}
				}
			}
		}*/   		
    }

    /**
     * @param $pages array(Ccsd_Website_Navigation_Page
     */
    private function loadNavigationLevel($pages)
    {
        /** @var Zend_Navigation_Page $page  */
        foreach ($pages as $page) {
            $resource = $this->getResource($page);
            if ($resource != '') {
                $privilege = $page->getPrivilege();
                $this->loadResource($resource, $privilege);
            }
            if ($page->hasChildren()) {
                $this->loadNavigationLevel($page->getPages());
            }
        }
    }
    
    public function loadRoles($roles = null)
    {
    	if ($roles === null) {
    		$roles = $this->_roles;
    	} 
    	foreach ($roles as $role => $parents)    {
    	    $this->addRole(new Zend_Acl_Role($role), $parents == '' ? null : explode(',', $parents));
    	}
    }
    
    protected function loadResources($resources)
    {
    	foreach ($resources as $resource => $parents)    {
    		if (! $this->has($resource)) {
    			$this->addResource(new Zend_Acl_Resource($resource));
    		}
    	}
    	return $this ;
    }

    protected function loadResource($resource, $privilege = null)
    {
    	if ($resource != '') {
    		$r = new Zend_Acl_Resource($resource);
    		
    		if (! $this->has($r)) {
	    		$this->addResource(new Zend_Acl_Resource($resource), null);
	    		
	    		if ($privilege === null) {
	    			$privileges = array(self::DEFAULT_ROLE);
	    		} else {
	    		    $privileges = explode(',', $privilege);
	    		}
	    		
	    		foreach ($this->getRoles() as $role){
	    		    $rule = 'deny';
                    /* On regarde d'abord si le droit est direct sans heritage (plus rapide)
                    in_array moins couteux que getRoleRegistry()->inherits
                    */
                    if (in_array($role, $privileges)) {
                        $rule = 'allow';
                    } else {
	    		        /* Pas trouve en direct, on essaye l'heritage */
                        foreach ($privileges as $priv) {
                            if ($role == $priv || $this->_getRoleRegistry()->inherits($role, $priv)) {
                                $rule = 'allow';
                                break;
                            }
                        }
                    }
	    		    $this->$rule($role, $resource);
	    		}
    		}
    	}
    }
    
    protected function loadRules($config)
    {
    	foreach ($this->getRoles() as $role){
    		if (isset($config[$role]) && is_array($config[$role])) {
    			foreach ($config[$role] as $right => $resources) {
    				foreach ($resources as $resource => $parents) {
    					$this->$right($role, $resource);
    				}
    			}
    		}	
    	}
    }
    
    public function write($dirname = null, $filename = null)
    {
    	$config = new Zend_Config(array(), true);
    	$config->roles = array();
    	$config->resources = array();
    	
    	//Section Role
    	foreach ($this->getRoles() as $role){
    		$parents = is_array($this->_getRoleRegistry()->getParents($role)) ? implode(',', $this->_getRoleRegistry()->getParents($role)) : null;
    	    $config->roles->$role = $parents;
    		$config->$role = array();
    		$config->$role->allow = array();
    		$config->$role->deny = array();
    	}
    	
    	//Section Resources
    	$config->resources = array();
    	foreach($this->getResources() as $resource) {
    		if ($resource != '') {
    			$config->resources->$resource = null;
    			//Sections Droits
    			foreach ($this->getRoles() as $role){
    				$action = 'deny';
    				if ($this->isAllowed($role, $resource)) {
    					$action = 'allow';
    				} 
    				$config->$role->$action->$resource	=	null;
    			}
    			
    		}	
    	}
    	if ($dirname && ! is_dir($dirname)) {
    		mkdir($dirname, 0777, true);
    	}
    	
    	
    	$writer = new Zend_Config_Writer_Ini(array('config' => $config, 'filename' => $dirname . $filename));
    	if ($filename === null) {
    		echo '<pre>' . $writer->render() . '</pre>';
    	} else {
    		$writer->write();
    	}
    }
    
    public function getResource($page)
    {
        $resource = $page->get('controller');
        $action = $page->get('action');
        $action = $action == '' ? 'index' : $action;
        if ($resource != '') {
            $resource .= '-' . $action;
        }
        return $resource;
    }
}
