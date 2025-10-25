<?php
/*
	Module  : OCMOD and VQMOD XML Generator
	Author  : Karapuz <karapuzec@aol.com>

	Version : 3.3.3

$Revision: 32 $

DESCRIPTION:
- it supports: after, before, replace tags.
- modifiers: regex, all, skip (ignoreif - disabled for now)
- patches are applied to: php, tpl, twig
- 'replace' and 'before' have to be placed above the searching text
- multiple replaces with the same pattern are allowed for ocmod since 3.2.0 version

Pattern format:
<marker>: <the rest>   <--- The space is important here

<the rest> is a line with a command, modifiers, and the pattern

[regex|][all|][skip|]command:<pattern>

*/

$cfg = array();

$cfg['manifest_file'] = "../manifest.ini";
$cfg['src_dir']  = '.';
$cfg['marker']   = '//karapuz';
$cfg['gen_type'] = 'ocmod';

$params = parse_ini_file($cfg['manifest_file']);

if (empty($params)) {
	die("manifest file not found");
}

if (!isset($params['id'])) {
	$params['id'] = '';
}

// this parameter can be used to apply global pathes
$params['replace_paths'] = array(
// example:
//	'/default/template/common/header.twig' => '/*/template/common/header.twig',
);


$cfg['params']   = $params;

function ka_log($str) {
	echo $str . "\n";
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

	function __construct($cfg) {
		$this->cfg = $cfg;

		if (empty($this->cfg['params']['xml_file'])) {
			$this->cfg['params']['xml_file'] = 'install.xml';
		}
		
		if (empty($this->cfg['params']['author'])) {
			$this->cfg['params']['author'] = "karapuz team (support@ka-station.com)";
		}
		
		if (!empty($this->cfg['params']['marker'])) {
			$this->cfg['marker'] = $this->cfg['params']['marker'];
		}
		
		ka_App::$instance = $this;
	}

	static function getInstance() {
		return ka_App::$instance;
	}

	function getConfig() {
		return $this->cfg;
	}
		
	function init() {
		return true;
	}
	
	function done() {
	
	}

	function run() {
	
		if ($this->cfg['gen_type'] == 'ocmod') {
			$ocmod = new OCMODGen($this->cfg['params']);
			$ocmod->generateXML($this->cfg['src_dir'], $this->cfg['params']['xml_file']);
		} elseif ($this->cfg['gen_type'] == 'vqmod') {
			$ocmod = new VQMODGen($this->cfg['params']);			
			$ocmod->generateXML($this->cfg['src_dir'], $this->cfg['params']['xml_file']);
		} else {
			die('unknown gen type');
		}		
	}
}


class OCMODGen extends ka_Object {
	protected $params;
	protected $file;

	static $modifiers = array('all','skip','regex');
	static $commands  = array('before', 'after', 'replace');
	
	function __construct($params) {
		$this->params = $params;
	}
		
	protected function processEntry($path) {

		$files = glob_ex($path);

		foreach ($files as $filename) {
			$entry = $filename;
			if (is_dir($entry)) {
				$this->processEntry($entry);

			} else {
			
				if (in_array(basename($filename), array('ka_gen.php', 'ka_build.php')))
					continue;
			
				if (!in_array(pathinfo($filename, PATHINFO_EXTENSION), array('twig', 'tpl','php')))
					continue;
			
				ka_log($entry);			
				$ops = $this->parseFile($entry);
				$this->generateXMLFile($entry, $ops);
			}
		}
	}

	
	protected function writeXMLHeader() {

		$str = "<modification>
		<id>" . $this->params['id'] . "</id>
		<name>" . $this->params['name'] . "</name>
		<code>" . $this->params['code'] . "</code>
		<version>" . $this->params['version'] . "</version>
		<author>" . $this->params['author'] . "</author>
		<link>" . htmlspecialchars($this->params['link']) . "</link>
	";

		fwrite($this->file, $str);
	}

	protected function writeXMLFooter() {
		$str = "</modification>";
		fwrite($this->file, $str);
	}

	public function generateXML($src_dir, $xml_file) {

		if (!$this->file = fopen($xml_file, 'w')) {
		  die("Cannot open file ($xml_file)");
	  }

	  $this->writeXMLHeader();
	  $this->processEntry($src_dir);
	  $this->writeXMLFooter();

	  fclose($this->file);
	  $this->file = null;
	}
	
	protected function generateXMLFile($filename, $ops) {

		if (empty($ops)) {
			return;
		}
		
		$config = $this->params;

		$filename = preg_replace("/^(\.[\\\\\/])(.*)/", "$2", $filename);
		$filename = str_replace("\\", "/", $filename); // unix like path

		// replace filenames with data if required
		if (!empty($this->params['replace_paths'])) {
			foreach ($this->params['replace_paths'] as $rpk => $rpv) {				
				$filename = str_replace($rpk, $rpv, $filename);				
			}
		}
		
		$str = "	<file path=\"" . $filename . "\">\n";

		foreach ($ops as $op) {
			$str .= "		<operation";
			
			if (!empty($op['error'])) {
				$str .= " error=\"" . $op['error'] . "\"";
			}
			
			$str .= ">\n";
			
			if (!empty($op['ignoreif'])) {
				$str .= " <ignoreif><![CDATA[" . $op['ignoreif'] . "]]></ignoreif>\n";
			}
			
			$str .= "			<search";

			if (isset($op['index']) && empty($op['regex'])) {
				$str .= " index=\"" . ($op['index'] - 1) . "\"";
			}

			if (!empty($op['regex'])) {
				$str .= ' regex="true"';
			}
			
			$str .= "><![CDATA[" . $op['search'] . "]]></search>\n";
			
			if (!empty($op['regex'])) {
				$str .= "			<add";
			} else {
				$str .= "			<add position=\"" . $op['position'] . "\"";
			}
			
			if (!empty($op['offset'])) {
				$str .= " offset=\"" . $op['offset'] . "\"";
			}
			
			$str .= "><![CDATA[";
			
			if ($op['position'] != 'replace') {
				$str .= $op['first_line_prefix'] . "//karapuz (" . htmlspecialchars($this->params['name']) . ") " . $op['first_line_postfix'];
			}
			foreach ($op['lines'] as $olk => $olv) {
				$str .= $olv;
			}
			$str  = rtrim($str);
			if ($op['position'] != 'replace') {
				$str .= "\n" . $op['last_line_prefix'] . "///karapuz (" . htmlspecialchars($this->params['name']) . ")" . rtrim($op['last_line_postfix']);
			}
		
			$str .= "]]></add>\n";
			$str .= "		</operation>\n";
		}

		$str .= "	</file>\n";

		fwrite($this->file, $str);
	}


	function parseOpParams($line) {
		$decoded = null;

		$decoded = json_decode("{" . $line . "}", true);
		return $decoded;
	}

	protected function getCommandPairs($lines) {
		$config = ka_App::getInstance()->getConfig();
		$marker = $config['marker'];
	
		$commands = array();
		$command = array();
		
		foreach ($lines as $lk => $line) {
			$matches = array();

			if (!preg_match("/.*" . preg_quote($marker,'/') . ".*/us", $line, $matches)) {
				continue;
			}
			
			if (empty($command['from'])) {
				$command['from'] = $lk+1;
				continue;
			}
			
			$command['to'] = $lk+1;
			
			$commands[] = $command;
			
			$command = array();
		}
		
		if (!empty($command) && empty($command['to'])) {
			die('ERROR: Command pairs do not match');
		}
		
		return $commands;
	}
	
	/* 

	Returns:
		operations - array with data
	*/
	protected function parseFile($file) {
		
		$config = ka_App::getInstance()->getConfig();
		$marker = $config['marker'];

		$lines = file($file);
		if (empty($lines)) {
			die('Cannot parse the file: $file');
		}

		$operations = array();
	
		$command_pairs = $this->getCommandPairs($lines);
		
		if (empty($command_pairs)) {
			return $operations;
		}
		
		// loop through all found pairs
		//
		foreach ($command_pairs as $pair) {

			$op = array();
		
			$line_from = $lines[$pair['from'] - 1];
			$line_to = $lines[$pair['to'] - 1];

			$matches = array();

			// test the end line
			//
			if (!preg_match("/(.*)\/" . preg_quote($marker,'/') . "(.*)/us", $line_to, $matches)) {			
				die("ERROR: unknown command closure (" . $line_to . ")");
			}
			
			$op['last_line_prefix']  = $matches[1];
			$op['last_line_postfix'] = $matches[2];
			
			// test the start line
			//
			if (!preg_match("/(.*)" . preg_quote($marker,'/') . ":(.*)/us", $line_from, $matches)) {
				die('ERROR: incorrect from:' . $line_from . "\n\n");
			}

			$cmd = $this->parseCmd($line_from);

			// prepare an operation block
			//
			$op['search']   = $cmd['pattern'];
			$op['position'] = $cmd['command'];
			$op['start']    = $pair['from'];
			$op['end']      = $pair['to'];
			
			$op['first_line_prefix']  = $cmd['prefix'];
			$op['first_line_postfix'] = $cmd['postfix'];
			$op['lines']  = array();

			// collect the code between start and end lines in $op['lines']
			// for the 'replace' operation we collect the code without starting double slashes
			//
			for ($i = $pair['from']; $i < $pair['to']-1; $i++) {
				$line = $lines[$i];
				if ($op['position'] == 'replace') {
					$line = preg_replace("/^\/\//", '', trim($line));
				}
				$op['lines'][] =  $line;
			}			
			
			// find the pattern from the first line (already saved in $op)
			// and fetch addtional data
			//
			$data = $this->findPattern($lines, $command_pairs, $op);

			if (empty($data)) {
				die("ERROR: Pattern is not found. FILE: $file, PATTERN: $op[search]");
			}

			// add the data to the operation
			//
			if (!empty($cmd['skip'])) {
				$op['error'] = 'skip';
			}
			
			if (!empty($cmd['regex'])) {
				$op['regex'] = true;
			}
			
			if (!empty($cmd['all'])) {
				$ops[$cmd['command'] . '_' . $op['search']] = $op;
			} else {
				$op['offset']  = $data['offset'];
				$op['index']   = $data['index'];				
				$ops[] = $op;
			}
		}

		return $ops;
	}

	
	protected function isInCommands($line, $commands) {
		foreach ($commands as $cmd) {
			if (($cmd['from'] <= $line+1) && ($line+1 <= $cmd['to'])) {
				return true;
			}
		}
		
		return false;
}
	
	/*
	Parameters:
	  $lines      - file lines (starts with 0);
		$commands  - array of commands (from/to);
		$pattern  - pattern string
		direction - before|after|replace

	Returns:
		array(
			'offset' - 
			'index'  -
		);

	*/
	protected function findPattern($lines, $commands, $op) {

		$pattern   = $op['search'];
		$direction = $op['position'];
	
		$config = ka_App::getInstance()->getConfig();
		$found = array();

		if (empty($pattern)) {
			die("WARNING: pattern parameter is empty");
			return false;
		}

		//find all occurencies of the pattern in the file
		//
		foreach ($lines as $lk => $lv) {

			if ($this->isInCommands($lk, $commands)) {
				continue;
			}
				
			if (strstr(trim($lv), trim($pattern))) {
				$found[] = $lk+1;
			}
		}
		
		if (empty($found)) {
			if ($direction != 'replace') {
				echo "\n\n dir:" . $direction . "\n\n";
				die('WARNING: pattern not found:' . $pattern);
			}
		}
		
	//	$found = array([0] => 10, [1]=>15, [2]=>35)
	//
		$index  = 0;
		$offset = 0;

		if ($direction == 'before') {
			foreach ($found as $fk => $fv) {
				if ($op['end'] < $fv) {
					$index = $fk;
					break;
				}
			}

			// this code skips our insertions not available in the patched file when the patch is executed
			//
			$offset = 0;
			for ($i = $op['end']+1; $i < $found[$index]; $i++) {
				if ($this->isInCommands($i, $commands)) {
					continue;
				}
				$offset++;
			}
			
			$index  = $index + 1;

		} else if ($direction == 'after') {

			foreach ($found as $fk => $fv) {
				if ($op['start'] > $fv) {
					$index = $fk;
				}
			}
			$offset = $op['start'] - $found[$index] - 1;
			$index  = $index + 1;
		
		} else if ($direction == 'replace') {

			if (!empty($found)) {
				foreach ($found as $fk => $fv) {
					if ($op['end'] < $fv) {
						$index = $fk;
						break;
					}
				}
				
				// the 'replace' operation will always have offest equal to 0
				//
				$offset = 0;
			}
			$index  = $index + 1;
			
		} else {
			die("Unexpected direction parameter: $direction");
		}

		$ret = array(
			'index'  => $index,
			'offset' => $offset
		);

		return $ret;
	}
	

	/*
		Parses the first line with commands and splits it to the parts.
		
		Returns: array of command parts
			pattern - 
			postfix -
			prefix  - 
			<modifier> - optional. it works for 'all' for now
	*/
	public function parseCmd($line) {

		$config = ka_App::getInstance()->getConfig();
		$marker = $config['marker'];
	
		$cmd = array();
	
		if (!preg_match("/(.*)" . preg_quote($marker,'/') . ": (.*)/us", $line, $matches)) {
			die("ERROR: unknown command (" . $line . ")");
		}

		// split the line to three parts
		// prefix  - 
		// pattern - 
		// postfix - 
		//
		$cmd['prefix'] = $matches[1];
		$cmd['pattern'] = $matches[2];
		
		$tail = '';
		if (preg_match("/(.*)(\*\/\?\>.*)$/us", $cmd['pattern'], $tmp)) {
			$cmd['pattern'] = $tmp[1];
			$tail           = $tmp[2];
					
		// this is for twig templates
		} else if (preg_match("/(.*)(\#\}.*)$/us", $cmd['pattern'], $tmp)) {
			$cmd['pattern'] = $tmp[1];
			$tail           = $tmp[2];
		}					
		if (empty($tail)) {
			$tail .= "\n";
		} else {
			$tail = ' ' . $tail;
		}

		$cmd['pattern'] = trim($cmd['pattern']);
		$cmd['postfix'] = $tail;

		// parse the pattern
		//		
		$parts = explode("|", $cmd['pattern']);
		$rest_parts = $parts;
		
		foreach ($parts as $k => $part) {
			unset($rest_parts[$k]);
		
			if (in_array(trim($part,':'), self::$modifiers)) {
				$cmd[$part] = 1;
				continue;
			}

			if (!empty($rest_parts)) {
				$part = $part . '|' . implode("|", $rest_parts);
			}
			
			$exploded = explode(':', $part);
			if (!in_array($exploded[0], self::$commands)) {
				die("Command not found: $exploded[0]");
				continue;
			}
			
			$cmd['command'] = array_shift($exploded); // remove the command from the array
			$cmd['pattern'] = implode(':', $exploded);
			break;
		}
		
		return $cmd;		
	}
}


class VQMODGen extends ka_Object {
	protected $params;
	protected $file;
	
	function __construct($params) {
		$this->params = $params;
	}
	
	protected function processEntry($path) {

		$files = glob_ex($path);

		foreach ($files as $filename) {
			$entry = $filename;
			if (is_dir($entry)) {
				$this->processEntry($entry);

			} else {
						
				if (in_array(basename($filename), array('ka_gen.php', 'ka_build.php')))
					continue;
			
				if (!in_array(pathinfo($filename, PATHINFO_EXTENSION), array('tpl','php')))
					continue;
			
				ka_log($entry);			
				$ops = $this->parseFile($entry);
				$this->generateXMLFile($entry, $ops);
			}
		}
	}

	protected function writeXMLHeader() {

		$str = "<modification>
		<id>" . $this->params['id'] . "</id>
		<version>" . $this->params['version'] . "</version>
		<vqmver>" . $this->params['vqmver'] . "</vqmver>
		<author>" . $this->params['author'] . "</author>
	";

		fwrite($this->file, $str);
	}

	protected function writeXMLFooter() {
		$str = "</modification>";
		fwrite($this->file, $str);
	}


	public function generateXML($src_dir, $dest_file) {

		if (!$this->file = fopen($dest_file, 'w')) {
		  die("Cannot open file ($dest_file)");
	  }

	  $this->writeXMLHeader();
	  $this->processEntry($src_dir);
	  $this->writeXMLFooter();

	  fclose($this->file);
	  $this->file = null;
	}


	protected function generateXMLFile($filename, $ops) {
	
		$dest_file = $this->params['xml_file'];
	
		if (empty($ops)) {
			return;
		}

		$filename = preg_replace("/^(\.[\\\\\/])(.*)/", "$2", $filename);

		$filename = str_replace("\\", "/", $filename); // unix like path

		$str = "	<file name=\"" . $filename . "\">\n";

		foreach ($ops as $op) {
			$str .= "		<operation>\n";
			$str .= "			<search position=\"" . $op['position'] . "\"";

			$str .= " index=\"" . $op['index'] . "\"";
			if ($op['offset'] > 0) {
				$str .= " offset=\"" . $op['offset'] . "\"";
			}
			$str .= "><![CDATA[" . $op['search'] . "]]></search>\n";
			$str .= "			<add><![CDATA[";
			
			if ($op['position'] != 'replace') {
				$str .= $op['first_line_prefix'] . "//karapuz ($dest_file) " . $op['first_line_postfix'];
			}		
			foreach ($op['lines'] as $olk => $olv) {
				$str .= $olv;
			}
			$str  = rtrim($str);
			if ($op['position'] != 'replace') {
				$str .= "\n" . $op['last_line_prefix'] . "///karapuz ($dest_file) " . $op['last_line_postfix'];
			}		
		
			$str .= "]]></add>\n";
			$str .= "		</operation>\n";
		}

		$str .= "	</file>\n";

		fwrite($this->file, $str);
	}


	/* 

	Returns:
		operations - array with data
	*/
	protected function parseFile($file) {

		$config = ka_App::getInstance()->getConfig();
		$marker = $config['marker'];

		$lines = file($file);
		if (empty($lines)) {
			die('Cannot parse the file: $file');
		}

		$ops = array();

	/*
			'search'   => '',
			'position' => 0.
			'add' => array(),
	*/

		$op = array();
		$is_op_open = false;
		foreach ($lines as $lk => $line) {
			$matches = array();

			if (!$is_op_open) {

				if (!preg_match("/(.*)".preg_quote($marker,'/').":[ ]*(before|after|replace):(.*)/", $line, $matches)) {
					continue;
				}

				if (in_array($matches[2], array('before', 'after', 'replace'))) {
					$pattern = $matches[3];

					$tail = '';
					if (preg_match("/(.*)(\*\/\?\>.*)$/", $pattern, $tmp)) {
						$pattern = $tmp[1];
						$tail    = $tmp[2];
					}

					$is_op_open  = true;
					if (empty($tail)) {
						$tail .= "\n";
					} else {
						$tail = ' ' . $tail;
					}

					$op = array(
						'search'   => trim($pattern),
						'position' => $matches[2],
						'start'    => $lk+1,
						'first_line_prefix'  => $matches[1],
						'first_line_postfix' => $tail,
						'lines'     => array(),
					);
				}

			} else {

				if (!preg_match("/(.*)\/".preg_quote($marker,'/') . "(.*)/", $line, $matches)) {
					if ($op['position'] == 'replace') {
						$line = preg_replace("/^\/\//", "", $line);
					}
					
					$op['lines'][] = $line;
					continue;
				}
				
				//find the pattern in the code
				$op['end'] = $lk;
				$markers = array($op['start'], $op['end']+1);

				$data = $this->findPattern($lines, $markers, $op['search'], $op['position']);

				if ($op['position'] == 'replace') {	
					if (empty($data['index'])) {
						$data['index'] = 1;
					} else {
						$data['index']++;
						ka_log('WARNING: search pattern for replacement is not unique:' . $file);
					};
				} else {
					if (empty($data)) {
						die("Pattern is not found. File: $file, line ($lk): $line");
					}
				}			

				$is_op_open     = false;

				$op['offset']   = $data['offset'];
				$op['index']    = $data['index'];
				$op['last_line_prefix']  = $matches[1];
				$op['last_line_postfix'] = $matches[2];
				
				$ops[]          = $op;
			}
		}

		if ($is_op_open) {
			die('Error: pattern was not closed. File:' . $file);
		}

		return $ops;
	}


	/*
	Parameters:
	  $lines    - file lines;
		$markers  - array(from, to);
		$pattern  - pattern string
		direction - before|after.

	Returns:
		array(
			'offset' - 
			'index'  -
		);

	*/
	protected function findPattern($lines, $markers, $pattern, $direction) {

		$config = ka_App::getInstance()->getConfig();
		$marker = $config['marker'];
	
		$found = array();

		//find all occurencies of the pattern in the file
		//
		foreach ($lines as $lk => $lv) {
			if (($markers[0] <= $lk+1) && ($lk+1 <= $markers[1])) {
				continue;
			}

			// line with marker is skipped
			if (strstr(trim($lv), $marker)) {
				continue;
			}

			if (empty($pattern)) {
				ka_log("WARNING: pattern parameter is empty.");
				return false;
			}

			if (strstr(trim($lv), trim($pattern))) {
				$found[] = $lk+1;
			}
		}

		if (empty($found)) {
			return false;
		}

	//	$found = array([0] => 10, [1]=>15, [2]=>35)
	//
		$index  = 0;
		$offset = 0;

		if ($direction == 'before') {
			foreach ($found as $fk => $fv) {
				if ($markers[1] < $fv) {
					$index = $fk;
					break;
				}
			}
			
			$offset = $found[$index] - $markers[1] - 1;
			$index  = $index + 1;

		} else if ($direction == 'after') {

			foreach ($found as $fk => $fv) {
				if ($markers[0] > $fv) {
					$index = $fk;
				}
			}
			$offset = $markers[0] - $found[$index] - 1;
			$index  = $index + 1;
		
		} else if ($direction == 'replace') {

			foreach ($found as $fk => $fv) {
				if ($markers[0] > $fv) {
					$index++;
				}
			}
			$offset = 0;

		} else {
			die("Unexpected direction parameter: $direction");
		}

		$ret = array(
			'index'  => $index,
			'offset' => $offset
		);
		
		return $ret;
	}
}


$app = new ka_App($cfg);
if ($app->init()) {
	$app->run();
}
$app->done();