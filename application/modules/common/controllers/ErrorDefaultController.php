<?php

class ErrorDefaultController extends Zend_Controller_Action
{
	
	public function indexAction()
	{
		$this->errorAction();
	}

    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');
        
        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = $this->_getParam('error_message');
            $this->view->description = $this->_getParam('error_description');
            if (in_array($this->view->message, ["Accès refusé", "Access denied", "Erreur d'authentification", "Erreur d'autorisation"])) {
                $this->getResponse()->setHttpResponseCode(403);
            }
            //return;
        } else {
        	
        	switch ($errors->type) {
	            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
	            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
	            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
	                // 404 error -- controller or action not found
	                $this->getResponse()->setHttpResponseCode(404);
	                $priority = Zend_Log::NOTICE;
	                $this->view->message = 'Page not found';
	                break;
	            default:
	                // application error
	                $this->getResponse()->setHttpResponseCode(500);
	                $priority = Zend_Log::CRIT;
	                $this->view->message = 'Application error';
	                break;
	        }
	        
	        // Credential-like values are masked for both the log and the error page
	        $loggableParams = $this->redactSensitiveParams($errors->request->getParams());

	        // Log exception, if logger available
	        if ($log = $this->getLog()) {
	        	$log->log("******* " . $this->view->message . " : " . $errors->exception->getMessage(), $priority, $errors->exception);
	        	$log->log("Exception thrown in : " . $errors->exception->getFile() . " - l." .$errors->exception->getLine(), $priority);
	        	$log->log('Request Parameters : ' . Zend_Json::encode($loggableParams), $priority, $loggableParams);
	        }
	        
	        // conditionally display exceptions
	        if ($this->getInvokeArg('displayExceptions') == true) {
	            $this->view->exception = $errors->exception;
	        }
	        
	        // conditionally display last sql request, if there was an error
	        $config = $this->getInvokeArg('bootstrap')->getOption('resources');
	        if ($config['db']['params']['profiler'] == true) {
	            $lastQuery = Episciences_Tools::getLastQuery();
	            if ($lastQuery) {
	                $this->view->sql = $lastQuery;
	            }
	        }
	        
	        $this->view->request   = $errors->request;
	        $this->view->requestParams = $loggableParams;
	        
	        
        }
    }

    /**
     * Replace the value of credential-like parameters before they reach the logs.
     *
     * A parameter is masked when its lowercased name contains one of the sensitive
     * fragments (e.g. PASSWORD_VERIFY, API_PASSWORD_VERIFY, paperPassword, csrf_token),
     * or exactly matches a name too generic to be used as a fragment (e.g. the volume
     * access code). Role names containing a fragment (secretary) are not masked.
     *
     * @param array $params
     * @return array
     */
    public function redactSensitiveParams(array $params): array
    {
        $sensitive = ['password', 'passwd', 'pwd', 'token', 'secret', 'csrf', 'api_key', 'apikey'];
        $sensitiveExact = ['code', 'access_code'];
        $notSensitive = [Episciences_Acl::ROLE_SECRETARY, Episciences_Acl::ROLE_SECRETARY_PLURAL];

        foreach ($params as $key => $value) {
            $name = strtolower((string)$key);
            $isSensitive = in_array($name, $sensitiveExact, true)
                || (!in_array($name, $notSensitive, true) && self::isSensitiveKey($name, $sensitive));

            if ($isSensitive) {
                $params[$key] = '***';
            } elseif (is_array($value)) {
                $params[$key] = $this->redactSensitiveParams($value);
            }
        }

        return $params;
    }

    /**
     * @param string $key
     * @param string[] $fragments
     * @return bool
     */
    private static function isSensitiveKey(string $key, array $fragments): bool
    {
        $key = strtolower($key);

        foreach ($fragments as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public function getLog()
    {
        try {
        $logger = Zend_Registry::get('Logger');
        } catch (Exception $e) {
            $logger = false;
        }

        if (!$logger) {
            return false;
        }
        return $logger;
    }
    
    public function getLastQuery()
    {
        $profiler = Zend_Db_Table::getDefaultAdapter()->getProfiler();              
        if ($profiler->getTotalNumQueries()) {
            $lastQueryProfile = $profiler->getLastQueryProfile();
            $lastQuery = $lastQueryProfile->getQuery();
            $lastQueryParams = $lastQueryProfile->getQueryParams();
            foreach($lastQueryParams as $param) {
                $lastQuery = substr_replace($lastQuery, "`" . $param . "`", strpos($lastQuery, '?'), 1);
            }
            return ($lastQuery == '') ? "---" : $lastQuery;
        }
        return false;
    }
    
    public function denyAction()
    {
        $this->view->message = "Vous n'êtes pas autorisé à accéder à cette page.";
    }

    /**
     * Read-only maintenance mode: the requested page could write to the database (see ReadOnlyGate plugin).
     */
    public function readonlyAction(): void
    {
        if ($this->getResponse()->isRedirect()) {
            // Language switch turned into a redirect by the ReadOnlyGate plugin
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            return;
        }

        if (!\Episciences\Maintenance\ReadOnlyMode::isEnabled()) {
            // Only meaningful during a maintenance: otherwise the page does not exist
            $this->getResponse()->setHttpResponseCode(404);
            $this->view->message = 'Page not found';
            $this->_helper->viewRenderer->setScriptAction('error');
            return;
        }

        $this->getResponse()
            ->setHttpResponseCode(503)
            ->setHeader('Retry-After', (string) \Episciences\Maintenance\ReadOnlyMode::getRetryAfterSeconds(), true);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();
        }
    }


}