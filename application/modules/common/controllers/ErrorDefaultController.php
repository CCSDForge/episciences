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
	        
	        // Log exception, if logger available
	        if ($log = $this->getLog()) {
	        	$log->log("******* " . $this->view->message . " : " . $errors->exception->getMessage(), $priority, $errors->exception);
	        	$log->log("Exception thrown in : " . $errors->exception->getFile() . " - l." .$errors->exception->getLine(), $priority);
	            $log->log('Request Parameters : ' . Zend_Json::encode($errors->request->getParams()), $priority, $errors->request->getParams());
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
	        
	        
        }
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


}