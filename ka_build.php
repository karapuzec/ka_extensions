<?php
/*
	Module  : Extension Package Builder
	Author  : Karapuz <karapuz@ka-station.com>

	Version : 3.3.0.1

	$Revision: 253 $
	
TODO:
	- detect working dir by getting a difference between working copy path and svn root;
	  At the present moment we set it manually in the code;
	
*/

define('USE_COLOR_OUTPUT', 1);

/*
	$arg_options - array of options like 'release', 'build_dir'
*/
function getoptEx($arg_options) {
	global $argv;

	$result = [];
	foreach ($arg_options as $arg_option) {
		foreach ($argv as $arg) {
			$needle = '--' . $arg_option . '=';
			if (strpos($arg, $needle) === 0) {
				$result[$arg_option] = substr($arg, strlen($needle));
			}
		}
	}
	
	return $result;
}


function fnmatchEx($patterns, $string, $flags = 0) {

	if (empty($patterns)) {
		return false;
	}

	$string = str_replace('\/', '/', $string);
	
	if (!is_array($patterns)) {
		$patterns = array($patterns);
	}

	foreach ($patterns as $pattern) {
		if (fnmatch($pattern, $string, $flags)) {
			return true;
		}
	}

	return false;
}


// I - info, E - error, W - warning
//
function show($msg, $type = 'I') {

	$msg = rtrim($msg). "\r\n";
		if ($type == 'E') {
		echo "\n\n!!! ERROR !!!\n\n";
	}
	echo ($msg);
	
	return true;
}

function glob_ex($path) {
	$files = array();

	if ($handle = opendir($path)) {
		while (false !== ($file = readdir($handle))) {
			// do something with the file
			// note that '.' and '..' is returned even
			if (in_array($file, array('.','..')))
				continue;

			$files[] = $path.DIRECTORY_SEPARATOR.$file;
		}
		closedir($handle);
	}
	return $files;
}


function normalizeFilename($filename) {
	return str_replace('\\', '/', $filename);
}


function rmdir_ex($dir) {

  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir") rmdir_ex($dir."/".$object); else unlink($dir."/".$object);
      }
    }
    if (!rmdir($dir)) {
    	die("rm dir failed at $dir");
    }
  }
}

class ka_Object {
	var $db = null;
	var $session = null;

	function init() {
		return true;
	}
}


class ka_App extends ka_Object {
	protected $cfg;
	static $instance;

	// list of files to exclude from 'upload' directory.  
	//
	static $exclude_files = array(
		'ka_build.php', 'ka_gen.php',
		'*.sql', '*.bat',
		'builds', 'builds/*',
		'manifest.ini',
		'*.txt',
		'comments.txt',
		'xml', 'xml/*',
		'sql', 'sql/*'
	);

	// these files are included into a root directory of the distribution package
	//
	static $include_files = array(
		// 'install.php',
	);

	static $src_files = array();
	
	// constains absolute path with the trailing slash
	static $store_root_dir = '';
	
	static $tmp_file =  'tmp_files.txt';
	static $mod_dir  =  'tmp_mod_files';
	static $pack_dir =  'tmp_pack';
	
	static $zip_file = '';
	static $release  = false;

	protected $manifest_file;
	
	// directory where manifest.ini is stored and other build-related data
	// It contains an absolute path since 3.2.1
	//
	static $build_dir = '';
	
	static $xml_files = array();

	function __construct($cfg) {
	
		$default = array(
			'exclude_files' => array(),
			'upload_dirs'   => array(),
			'upload_files'  => array(),
		);
		$this->cfg = array_merge($default, $cfg);
		
		ka_App::$instance = $this;
	}

	static function getInstance() {
		return ka_App::$instance;
	}

	function getConfig() {
		return $this->cfg;
	}
		
	function init() {
		global $argv;
	
		if (!empty($argv[1])) {
			$this->manifest_file = $argv[1];
		}
	
		return true;
	}


	function copyModFiles($tmp_file, $mod_dir) {

		rmdir_ex($mod_dir);
		
		$file_lines = array();
		if (file_exists($tmp_file)) {
			$file_lines = file($tmp_file);
		} else {
			show("Cannot open file: $tmp_file");
		}
		
		$found = false;
		
		foreach ($file_lines as $line) {
		
			if (empty($line)) {
				continue;
			}
			
			$matches = array();
			if (!preg_match("/[ \w]?([\w])[\s]*(.*)$/", $line, $matches)) {
				die("Wrong file format");
			}

			$filename = trim($matches[2]);			
			$filename = str_replace("\\", "/", $filename);

			if (fnmatchEx($this->cfg['src_files'], $filename)) {
				continue;
			}
			
			if ($matches[1] != 'M' && !fnmatchEx($this->cfg['added_later_files'], $filename)) {
				continue;
			}
			
			if (fnmatchEx($this->cfg['exclude_files'], $filename)) {
				continue;
			}			
			if (empty($filename) || !is_file($filename)) {
				continue;
			}

			$path_info = pathinfo($filename);
			if (!in_array($path_info['extension'], array('php', 'tpl', 'twig'))) {
				continue;
			}
			
			$file_dir = $path_info['dirname'];
			if ($file_dir != '.') {
				$dest_dir = $mod_dir.DIRECTORY_SEPARATOR.$file_dir;
			} else {
				$dest_dir = $mod_dir;
			}
			if (!empty($dest_dir) && !file_exists($dest_dir)) {
				mkdir($dest_dir, 0777, true);
			}
			$dest_file = trim($mod_dir . DIRECTORY_SEPARATOR . $filename);
			if (!copy($filename, $dest_file)) {
				die("Copy operation failed. from=$filename to $dest_file");
			}
			$found = true;
		}
		
		return $found;
	}


	function copyPackFiles($tmp_file, $pack_dir) {

		rmdir_ex($pack_dir);
		
		if (!file_exists($pack_dir)) {
			mkdir($pack_dir);
		}

		if (!empty($this->cfg['target_oc_version'])) {
	        if (version_compare($this->cfg['target_oc_version'], '2.0.0.0', '>=')) {
	    		$pack_dir = $pack_dir. "/upload";
	        }
		}

		$file_lines = array();
		if (file_exists($tmp_file)) {
			$file_lines = file($tmp_file);
		} else {
			show("Cannot open file: $tmp_file");
		}

		// add src files as new files to the file list
		//
		if (!empty($this->cfg['src_files'])) {
			foreach($this->cfg['src_files'] as $sf) {
				$file_lines[] = 'A ' . $sf . "\n";
			}
		}

		$packed_files = array();

		foreach ($file_lines as $line) {
		
			if (empty($line)) {
				continue;
			}
			$matches = array();
			if (!preg_match("/([\w]*)[\s]*(.*)$/", $line, $matches)) {
				die("Wrong file format");
			}

			$filename = trim(str_replace('\\', '/', $matches[2]));
			
			// exclude files excplicitely defined
			if (fnmatchEx($this->cfg['exclude_files'], $filename)) {
				continue;
			}
			
			$is_allowed = false;
			if (fnmatchEx($this->cfg['upload_files'], $filename, FNM_PATHNAME)) {
				$is_allowed = true;				
			} else {
				if (empty($this->cfg['upload_dirs'])) {
					$is_allowed = true;
				} else {
					foreach ($this->cfg['upload_dirs'] as $dir) {
						if (strpos($filename, $dir) !== false) {
							$is_allowed = true;
							break;
						}
					}
				}
			}
			if (!$is_allowed) {
				continue;
			}
			
			
			if ($matches[1] == 'A' && !fnmatchEx($this->cfg['added_later_files'], $filename)) {
				// ok
			} elseif (fnmatchEx($this->cfg['src_files'], $filename)) {
				// ok
			} elseif ($matches[1] =='M' && $this->cfg['include_modified_files']) {
				// ok
			} else {
				continue;
			}
			
			if (!empty($this->cfg['working_dir'])) {
				$filename = substr($filename, strlen($this->cfg['working_dir']));
			}
			if (empty($filename)) {
				continue;
			}	
			
			$path_info = pathinfo($filename);
			if (fnmatchEx(self::$exclude_files, $filename)) {
				continue;
			}
			
			$file_dir = $path_info['dirname'];
			
			if ($file_dir == '.')
				$file_dir = '';

			$dest_dir = $pack_dir.DIRECTORY_SEPARATOR.$file_dir;
			if (!empty($dest_dir) && !file_exists($dest_dir)) {
				mkdir($dest_dir, 0777, true);
			}
			$dest_file = trim($pack_dir . DIRECTORY_SEPARATOR . $filename);
			
			if (is_dir($filename)) {
				if (!file_exists($dest_file)) {
					if (!mkdir($dest_file, 0777, true)) {
						die("Mkdir operation failed for $dest_file");
					}
				}
			} elseif (!copy($filename, $dest_file)) {
				die("Copy operation failed. from=$filename to $dest_file");
			}

			if (!is_dir(__DIR__ . '/' . $filename)) {
				$packed_files[] = $filename . "\n";
			}
		}
		
		file_put_contents('packed_files.txt', $packed_files);

		show("Number of packed files: " . count($packed_files));
		
		return true;
	}
	
	
		
	function done() {
	
	}
	
	function makeSvnFilesList($tmp_file = '') {

		if (empty($tmp_file)) {
			$tmp_file = self::$tmp_file;
		}

		$cmd = "svn diff -r " . $this->cfg['start_revision'] . ":head --summarize > $tmp_file";
		exec($cmd);
		if (!file_exists($tmp_file)) {
			return false;
		}
		
		return true;
	}

	
	function makeXmlPatch($tmp_file, $mod_dir) {

		if ($this->copyModFiles($tmp_file, $mod_dir)) {		
			show("- mod files copied to $mod_dir"); 

			// 3) generate xml patch
			//
			@unlink($this->cfg['xml_file']);

			if (!copy('ka_gen.php', $mod_dir.'/ka_gen.php')) {
				die("Builder not copied.");
			}
			
			if (!copy($this->manifest_file, $mod_dir . '/manifest.ini')) {
				die("Manifest.ini cannot be copied:" . $this->manifest_file);
			}

			chdir($mod_dir);
			$args = [];
			
			if (!empty(self::$build_dir)) {
				$args[] = "--build_dir='" . self::$build_dir . "'";
			}
			
			$args[] = '"--manifest=' . $this->manifest_file . '"';
			
			passthru("php ka_gen.php " . implode(" ", $args));
			chdir("..");

			if (!copy($mod_dir.'/' . $this->cfg['xml_file'], "./" . $this->cfg['xml_file'])) {
				die("Builder did not generate xml file");
			}
			rmdir_ex($mod_dir);
			self::$xml_files[] = $this->cfg['xml_file'];
		}

		return true;
	}
	
	
	function copyXmlFiles($xml_dir) {

		if (!empty(self::$xml_files)) {
			foreach (self::$xml_files as $file) {
				if (!copy($file, $xml_dir."/" . basename($file))) {
					die("xml file $file was not copied to dir $xml_dir");
				}
			}
		}
	}

	/* 
		replaced server variables with their values in the string. It should give more flexibility for defining 
		paths in different environments
	*/
	function replaceEnvVariables($str) {
	
		preg_match_all("/%(.*)%/U", $str, $matches, PREG_SET_ORDER);
		if (empty($matches)) {
			return $str;
		}

		$matches = $matches[0];
		array_shift($matches);
		
		foreach ($matches as $match) {
			if (isset($_SERVER[$match])) {
				$str = str_replace('%' . $match . '%', $_SERVER[$match], $str);
			}
		}
		
		return $str;
	}

	function readConfig() {
	
		$options = getoptEx(["build_dir", "release"]);

		self::$store_root_dir = getcwd() . '/';
		
		if (!empty($options['build_dir'])) {
			self::$build_dir = getcwd() . '/' . $options['build_dir'];
		}
		
		if (!empty($options['release'])) {
			self::$release = true;
		}

		if (empty($this->manifest_file)) {
			$this->manifest_file = self::$build_dir . 'manifest.ini';
		}
		
		if (!file_exists($this->manifest_file)) {
			die("manifest.ini not found at: " . $this->manifest_file);
		}

		$_cfg = parse_ini_file($this->manifest_file);
		if (empty($_cfg)) {
			return false;
		}
		$this->cfg = array_merge($this->cfg, $_cfg);
		
		if (!isset($this->cfg['xml_dir'])) {
			$this->cfg['xml_dir'] = '';
		}
		
		// update the path with server variables like %PROJECTS_KA_DIR%
		//
		if (!empty($this->cfg['releases_dir'])) {
			$this->cfg['releases_dir'] = $this->replaceEnvVariables($this->cfg['releases_dir']);
		}

		if (empty($this->cfg['include_modified_files'])	|| $this->cfg['include_modified_files'] != 'Y') {
			$this->cfg['include_modified_files'] = false;
		} else {
			$this->cfg['include_modified_files'] = true;
		}

		if (empty($this->cfg['src_files'])) {
			$this->cfg['src_files'] = array();
		}
		
		if (!empty($this->cfg['target_oc_version'])) {
			if (version_compare($this->cfg['target_oc_version'], '3.0.0.0', '>=')) {

				if (empty($this->cfg['xml_file'])) {
					$this->cfg['xml_file'] = 'install.xml';
				}
			
			} elseif (version_compare($this->cfg['target_oc_version'], '2.0.0.0', '>=')) {
				if (empty($this->cfg['xml_dir'])) {
					$this->cfg['xml_dir'] = '/xml';
				}
			} else {
				if (empty($this->cfg['xml_dir'])) {
					$this->cfg['xml_dir'] = '/vqmod/xml';
				}
			}
		}
		
		if (empty($this->cfg['comments_file'])) {
			$this->cfg['comments_file'] = 'comments.txt';
		}
		
		if (empty($this->cfg['readme_file'])) {
			$this->cfg['readme_file'] = 'readme.' . $this->cfg['code'] . '.txt';
		}
		
		if (empty($this->cfg['upgrade_instructions_file'])) {
			$this->cfg['upgrade_instructions_file'] = 'upgrade_instructions.txt';
		}
		
		if (!empty($this->cfg['include_files'])) {
			self::$include_files = array_unique(array_merge(self::$include_files, $this->cfg['include_files']));
		}

		if (!empty($this->cfg['src_files'])) {
			self::$src_files = array_unique(array_merge(self::$src_files, $this->cfg['src_files']));
		}
		
		if (!empty($this->cfg['exclude_files'])) {
			self::$exclude_files = array_unique(array_merge(self::$exclude_files, $this->cfg['exclude_files']));
		}
		
		if (!empty($this->cfg['include_xml_files'])) {
			self::$xml_files = array_unique(array_merge(self::$xml_files, $this->cfg['include_xml_files']));
		}
		
		if (!empty($this->cfg['added_later_files'])) {
			$this->cfg['added_later_files'] = array_map('trim', $this->cfg['added_later_files']);
		}
		
		$this->cfg['working_dir'] = ''; // example: "ka_term\\trunk\\"
		
		return true;
	}

	function copyIncludedFiles($pack_dir) {


		if (empty($pack_dir) || empty(self::$include_files)) {
			return true;
		}

		foreach (self::$include_files as $file) {
		
			if (empty($file)) {
				continue;
			}
		
			// first, try to find extension-specific files
			//
			if (file_exists(self::$build_dir . $file)) {	
				$file = self::$build_dir . $file;
				
			// try to find a regular file
			//
			} elseif (file_exists($file)) {
			
			} else {
				show("file for copying not found: " . $file);
				continue;
			}			

			if (!copy($file, self::$pack_dir . '/' . basename($file))) {
				show("cannot copy: " . $file);
			} else {
				show("file copied: " . $file);
			}
		}

		return true;
	}

	
	function packArchive($pack_dir) {

		// $>zip tmp_pack/tmp.zip tmp_pack/*
		//
        if (!empty($this->cfg['target_oc_version']) && version_compare($this->cfg['target_oc_version'], '2.0.0.0', '>=')) {
    		self::$zip_file = $this->cfg['file'] . $this->cfg['version'] . ".ocmod.zip";
        } else {
            self::$zip_file = $this->cfg['file'] . $this->cfg['version'] . ".zip";
        }
        
		chdir($pack_dir);
		exec("zip -r " . self::$zip_file . " *");

		// http://acritum.com/software/manuals/winrar/
		// $>winrar a -afzip -ptest test.zip rar.txt tmp_files.txt
		if (!empty($this->cfg['upgrade_key'])) {
			$comments_file     = self::$build_dir . $this->cfg['comments_file'];
			$instructions_file = $this->cfg['upgrade_instructions_file'];
			$upgrade_file      = "upgrade_to_" . self::$zip_file . ".zip ";
			
			exec("winrar a -afzip -z" . $comments_file . " -p" . $this->cfg['upgrade_key'] . ' ' . $upgrade_file . ' '  . self::$zip_file);
			exec("winrar a -afzip -z" . $comments_file . ' ' . $upgrade_file . ' ' . $instructions_file);
		}
		chdir("..");

		return true;
	}

	
	protected function getReleaseDir() {
		$files = glob($this->cfg['releases_dir'] . '*');

		$version = quotemeta($this->cfg['version']);
		
		$counter = 1;
		if (!empty($files)) {
			$_files = array();
			foreach ($files as $file) {			
				$file = pathinfo($file, PATHINFO_BASENAME);				
				if (!preg_match("/^ver" . $version . "([^\.]*)/", $file, $match)) {
					continue;
				}
				
				if (preg_match("/b[eta]*(\d*)/", $match[1], $match)) {
					$match[1] = (int)$match[1];
					if ($match[1] > $counter) 
						$counter = $match[1];
				}
				
				$counter++;
			}
		}

		// release dir format examples:
		// ver1.0.0rc1b1
		//
		$release_dir = $this->cfg['releases_dir'] . 'ver' . $this->cfg['version'] . 'b' . $counter;
		if (!mkdir($release_dir, 0777, true)) {
			show("release_dir cannot be created:" . $release_dir) && die;
		}
		
		return	$release_dir;
	}
	
	
	function copyRelease($zip_file) {
		
		if (empty($zip_file)) {
			show('zip file not found') && die();
		}
		
		if (empty($this->cfg['releases_dir'])) {
			show('releases_dir not found') && die();
		}

		$release_dir = $this->getReleaseDir();
		$dest_file = $release_dir . '/' . basename($zip_file);
		
		if (!copy($zip_file, $dest_file)) {
			show('cannot copy zip file:' . $dest_file) && die;
		}
		
		if (!empty($this->cfg['upgrade_key'])) {
			$zip_file_upgrade = pathinfo($zip_file, PATHINFO_DIRNAME) . "/upgrade_to_" . basename($zip_file) . ".zip";
			$dest_file = $release_dir . '/' . basename($zip_file_upgrade);
			if (!copy($zip_file_upgrade, $dest_file)) {
				show('cannot copy zip file:' . $dest_file) && die;
			}
		}
		
		return $dest_file;	
	}
	

	protected function updateModuleVersion() {
	
		$version_file = '';
		if (!empty($this->cfg['version_file'])) {
			$version_file = self::$store_root_dir . $this->cfg['version_file'];
			
		} elseif (version_compare($this->cfg['target_oc_version'], '3', '>=')) {
			$version_file = self::$store_root_dir . 'admin/controller/extension/ka_extensions/' . $this->cfg['code'] . '.php';
			
			if (!file_exists($version_file)) {
				$code = preg_replace("/^ka_/", "", $this->cfg['code']);
				$version_file = self::$store_root_dir . 'admin/controller/extension/ka_extensions/' . $code . '.php';
			}			
		}
		
		if (!file_exists($version_file)) {
			show('version file was not found', 'W');
			return false;
		}

		//find file
		$file_data = file_get_contents($version_file);
		
		$matches = array();
		if (!preg_match("/extension_version = [\'\"]([^\'\"]*)[\'\"]/", $file_data, $matches)) {
			show('version was not found in the file', 'W');
			return false;
		}
		
		if ($matches[1] == $this->cfg['version']) {
			show("version is correct");
			return true;
		}
		
		$file_data = preg_replace(
			'/\$extension_version = ([\'\"])[^\'\"]*([\'\"])/',
			'$extension_version = ${1}' . $this->cfg['version'] . '$2',
			$file_data
		);
		
		file_put_contents($version_file, $file_data);
		
		show("version updated to " . $this->cfg['version']);
		
		return true;
	}
	
	
	function run() {

		// init settings and read config from manifest.ini
		//
		if (!$this->readConfig()) {
			die("configuration file not found");
		}

		$this->cfg['exclude_files'] = array_map('normalizeFilename', $this->cfg['exclude_files']);
		$this->cfg['upload_dirs']   = array_map('normalizeFilename', $this->cfg['upload_dirs']);
		$this->cfg['upload_files']  = array_map('normalizeFilename', $this->cfg['upload_files']);
		
		// updating the version in the controller
		//
		if (!empty($this->cfg['target_oc_version'])) {
			$this->updateModuleVersion();
		}
		
		// generate a list with changed files from the repository
		//
		if (!$this->makeSvnFilesList()) {
			die('svn diff failed');
		}
		show("- svn diff file generated");

		if (filesize(self::$tmp_file) == 0) {
			show('No files were modified');
		}

		// copy pack files to a distribution directory
		//
		$this->copyPackFiles(self::$tmp_file, self::$pack_dir);
		show("- pack files copied to " . self::$pack_dir);

		// copy xml files to a distribution directory
		//
		if (!empty($this->cfg['xml_file'])) {
			$this->makeXmlPatch(self::$tmp_file, self::$mod_dir);
		} else {
			show("INFO: xml file was not generated becasue it is not defined in manifest.");
		}
		
        if (!empty(self::$xml_files)) {
    		$xml_dir = self::$pack_dir . $this->cfg['xml_dir'];
    		if (!file_exists($xml_dir)) {
    			if (!mkdir($xml_dir, 0777, true)) {
    				die('cannot create xml dir:' . $xml_dir);
    			}
    		}

    		$this->copyXmlFiles($xml_dir);
        }
        
        // copy any other files explicityly mentioned in config to a distribution directory
        //
		$this->copyIncludedFiles(self::$pack_dir);

		// prepare a zip archive with the changes
		//
		if (!$this->packArchive(self::$pack_dir)) {
			die("Cannot pack archive");
		}

		// make winSCP script for backing up changed files
		//
		$this->makeWinSCPScript(self::$tmp_file);
		
		// copy the archive to the project versions directory if it is requested
		//
		if (self::$release) {
			$build = $this->copyRelease(self::$pack_dir . '/' . self::$zip_file);

			if (!empty($build)) {
				show("Uploaded to: " . $build);
			}
		}
	
		show("\r\nCongratulations! The package has been successufully built.");
		show("NOTE: Make sure you have updated to the latest version before building the package.");
	}
	
	/*
		How to execute the script:
		"C:\Program Files (x86)\WinSCP\WinSCP.com" /script="winscp.txt"
	
		list of commands:
		https://winscp.net/eng/docs/scripting#commands
	*/
	function makeWinSCPScript($tmp_file) {

		if (!($handle = fopen("$tmp_file", "r"))) {
			die("Cannot open file: $tmp_file");
		}

		$script = array();
		$local_dir = "org";

		$script[] = ";Generated on " . date("c") . "\n";
		
		// continue working on any fails
		$script[] = "option batch on";
		
		// skip confirmation
		$script[] = "option confirm off";
		
		$script[] = "; Add your connection from WinSCP list.";
		$script[] = "; example: open automoto-blue/2018-08-03_automoto";
		$script[] = ';';
		$script[] = '; "C:\Program Files (x86)\WinSCP\WinSCP.com" /script="winscp.txt"';
		$script[] = "";
		if (!empty($this->cfg['winscp_connection'])) {
			$script[] = "open " . $this->cfg['winscp_connection'];
		} else {
			$script[] = "; !!!specify winscp_conneciton in manifest.ini";
		}
		if (!empty($this->cfg['remote_root_dir'])) {
			$script[] = "cd \"" . $this->cfg['remote_root_dir'] . "\"";
		} else {
			$script[] = "; !!!specify remote_root_dir in manifest.ini";
		}
		$script[] = "lcd \"" . __DIR__ . "\"";
		
		while (!feof($handle)) {
			$line = fgets($handle, 4096);
			if (empty($line)) {
				continue;
			}
			
			$matches = array();
			if (!preg_match("/[ \w]?([\w])[\s]*(.*)$/", $line, $matches)) {
				die("Wrong file format");
			}
			
			$filename = $win_filename = trim($matches[2]);			
			$filename = str_replace("\\", "/", $filename);
			if (in_array($filename, $this->cfg['exclude_files'])) {
				continue;
			}			
			if (empty($filename) || !is_file($filename)) {
				continue;
			}

			$path_info = pathinfo($filename);
			
			// path example in tmp_file.txt: admin\language\english\sale\invoice.php			
			// we need to prepare a line like this:
			// get "admin/controller/payment/fdl.php" "org\admin\controller\payment\fdl.php"
			//
			$line = "get \"$filename\" \"$local_dir\\$win_filename\"";
			$script[] = $line;			
		}
		fclose($handle);
		$script[] = "close";
		$script[] = "exit";
		
		file_put_contents("winscp.txt", implode("\n", $script));
		
		return true;
	}
}

$cfg = array(
	'added_later_files'  => array(),
//	'target_oc_version'  => '0.0.0.0',
	'xml_dir'            => ''
);

$app = new ka_App($cfg);
if ($app->init()) {
	$app->run();
}
$app->done();