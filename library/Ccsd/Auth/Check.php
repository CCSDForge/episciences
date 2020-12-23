<?php

class Ccsd_Auth_Check {
	public $user;
	public $pwd;
	
	public function Basic()
	{
		global $HTTP_SERVER_VARS, $HTTP_ENV_VARS;
		global $REMOTE_USER, $AUTH_USER, $REMOTE_PASSWORD, $AUTH_PASSWORD;
		if ( empty($this->user) ) {
			if ( !empty($_SERVER) && isset($_SERVER['PHP_AUTH_USER']) ) {
				$this->user = $_SERVER['PHP_AUTH_USER'];
			} else if ( !empty($HTTP_SERVER_VARS) && isset($HTTP_SERVER_VARS['PHP_AUTH_USER']) ) {
				$this->user = $HTTP_SERVER_VARS['PHP_AUTH_USER'];
			} else if ( isset($REMOTE_USER) ) {
				$this->user = $REMOTE_USER;
			} else if ( !empty($_ENV) && isset($_ENV['REMOTE_USER']) ) {
				$this->user = $_ENV['REMOTE_USER'];
			} else if ( !empty($HTTP_ENV_VARS) && isset($HTTP_ENV_VARS['REMOTE_USER']) ) {
				$this->user = $HTTP_ENV_VARS['REMOTE_USER'];
			} else if ( @getenv('REMOTE_USER') ) {
				$this->user = getenv('REMOTE_USER');
			} else if ( isset($AUTH_USER) ) {
				$this->user = $AUTH_USER;
			} else if ( !empty($_ENV) && isset($_ENV['AUTH_USER']) ) {
				$this->user = $_ENV['AUTH_USER'];
			} else if ( !empty($HTTP_ENV_VARS) && isset($HTTP_ENV_VARS['AUTH_USER']) ) {
				$this->user = $HTTP_ENV_VARS['AUTH_USER'];
			} else if ( @getenv('AUTH_USER') ) {
				$this->user = getenv('AUTH_USER');
			}
		}
		if ( empty($this->pwd) ) {
			if ( !empty($_SERVER) && isset($_SERVER['PHP_AUTH_PW']) ) {
				$this->pwd = $_SERVER['PHP_AUTH_PW'];
			} else if ( !empty($HTTP_SERVER_VARS) && isset($HTTP_SERVER_VARS['PHP_AUTH_PW']) ) {
				$this->pwd = $HTTP_SERVER_VARS['PHP_AUTH_PW'];
			} else if ( isset($REMOTE_PASSWORD) ) {
				$this->pwd = $REMOTE_PASSWORD;
			} else if ( !empty($_ENV) && isset($_ENV['REMOTE_PASSWORD']) ) {
				$this->pwd = $_ENV['REMOTE_PASSWORD'];
			} else if ( !empty($HTTP_ENV_VARS) && isset($HTTP_ENV_VARS['REMOTE_PASSWORD']) ) {
				$this->pwd = $HTTP_ENV_VARS['REMOTE_PASSWORD'];
			} else if ( @getenv('REMOTE_PASSWORD') ) {
				$this->pwd = getenv('REMOTE_PASSWORD');
			} else if ( isset($AUTH_PASSWORD) ) {
				$this->pwd = $AUTH_PASSWORD;
			} else if ( !empty($_ENV) && isset($_ENV['AUTH_PASSWORD']) ) {
				$this->pwd = $_ENV['AUTH_PASSWORD'];
			} else if ( !empty($HTTP_ENV_VARS) && isset($HTTP_ENV_VARS['AUTH_PASSWORD']) ) {
				$this->pwd = $HTTP_ENV_VARS['AUTH_PASSWORD'];
			} else if ( @getenv('AUTH_PASSWORD') ) {
				$this->pwd = getenv('AUTH_PASSWORD');
			}
		}
		if ( empty($this->user) || empty($this->pwd) ) {
			return false;
		} else {
			if ( get_magic_quotes_gpc() ) {
				$this->user = stripslashes($this->user);
				$this->pwd   = stripslashes($this->pwd);
			}
			return true;
		}
	}
}
