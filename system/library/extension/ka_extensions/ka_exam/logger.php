<?php

namespace extension\ka_extensions\ka_exam;

class Logger {

	static protected $log;

	static protected function getInstance() {
	
		if (empty(static::$log)) {
			static::$log = new \Log('ka_exam.log');
			static::$log->write("--- NEW REQUEST (" . $_SERVER['REMOTE_ADDR'] . ")");
		}
		
		return static::$log;			
	}
	
	
	static public function log($msg) {
	
		if (!Config::isLoggerEnabled()) {
			return;
		}
	
		static::getInstance()->write($msg);	
	}
}