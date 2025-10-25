<?php

namespace extension\ka_extensions;

class Directory {


	public static function deleteDirectory($path) {
		static::clearDirectory($path);
		if (file_exists($path)) {
			if (is_dir($path)) {
				rmdir($path);
			} else {
				unlink($path);
			}
		}
	}

	public static function clearDirectory($path, $exclude = array()) {
	
		$path = rtrim($path, '/\\');
		static::_clearDirectory($path, $exclude);
	}

	
	protected static function _clearDirectory($path, $exclude = array()) {
		static $level = 0;

		if (!file_exists($path)) {
			return;
		}

		$level++;
		
		$files = glob($path . '/{,.}*[!.]', GLOB_BRACE);
		foreach ($files as $file) {
		
			if (in_array($file, array('.','..'))) {
				continue;
			}

			if (!empty($exclude)) {
				if (in_array(basename($file), $exclude)) {
					continue;
				}
			}
				
			is_dir($file) ? static::_clearDirectory($file) : unlink($file);
		}
		
		$level--;
		
		if (!empty($level)) {
			if (is_dir($path)) {
				if (!rmdir($path)) {
					throw new \Exception("The path cannot be deleted:" . $path);
				}
			}
		}

		return;
	}		
	
	
	/*
		Makes sure that the directory exists. If it does not exist, the function tries to create it.

		on error - an exception will occur
	*/
	public static function checkDirectory($file) {
	
		$dir = dirname($file);
	
		if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
			throw new \Exception("Cannot create directory: " . $dir);
		}
	}

}