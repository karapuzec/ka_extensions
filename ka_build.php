<?php
/*
	Module  : Extension Package Builder
	Author  : Karapuz <karapuz@ka-station.com>

	Version : 3.3.3.0

	$Revision: 275 $

USAGE:
	php ka_build.php [manifest.ini] [--release=true] [--set-keywords] [--out=<dir>] [--build_dir=<dir>]

	--set-keywords   sets svn:keywords on all module files in the working copy and stops. It never commits:
	                 commit the listed files yourself (svn writes the new values into the files on commit).
	--release=true   builds a release. The version is written to the version file first. When the version
	                 file was changed or svn:keywords of the module files are wrong or not committed, they are
	                 set and COMMITTED in one commit ("Release X: ..."), then the package is built.
	                 It stops when the module files have other uncommitted changes.
	without --release a wrong svn:keywords value gives a warning only, nothing is set or committed.

CHANGES 3.3.3.0:
	- the header keywords $Project$, $Author$, $Version$, $Revision$ are written into the package copies
	  of the packed files (copyPackFiles(), the source files are not changed): '$Project: <keywords_project> $',
	  '$Author: <keywords_author> $', '$Version: <version> $', '$Revision: <revision> $'. Defaults as for
	  svn:keywords (keywords_project = name, keywords_author = "karapuz team <support@ka-station.com>").
	  The revision: git - the number of commits of HEAD (git rev-list --count HEAD), svn - the revision of
	  the working copy (svnversion -c, the highest one).
	  git: bare and already expanded keywords (e.g. stale values copied from svn) are written again;
	  svn: only bare keywords are written, values expanded by svn are kept (the file revision of svn);
	  text files only (php, tpl, twig, js, css, scss, less, xml, txt, ini, html, htm, json, md), not files
	  in vendor/ and node_modules/ folders, not files with a zero byte; only these four keyword names;
	  manifest 'expand_keywords = "N"' turns it off.

CHANGES 3.3.2.2:
	- manifest_version 2: the built-in exclude masks ('*.txt', '*.sql', '*.bat', 'builds/*', 'xml/*', 'sql/*')
	  keep their version 1 meaning (they are converted to '**' masks); before, files like 'dir/info.txt'
	  were packed with manifest_version 2. Masks of the manifest are not changed.

CHANGES 3.3.2.1:
	- the version is written to the version file before svn:keywords are processed;
	- --release=true commits the version file changed by the build together with the svn:keywords
	  changes in one commit. Other uncommitted changes of the module files (including earlier
	  changes of the version file) still stop the release;
	- --set-keywords lists the changed version file among the files to commit.

CHANGES 3.3.2.0:
	- svn:keywords of the module files (packed files and include_files, without excluded files) are checked:
	  "Revision Version=<version> Project=<keywords_project> Author=<keywords_author>" from manifest.ini
	  (keywords_project defaults to 'name', keywords_author to "karapuz team <support@ka-station.com>",
	  spaces are written as '%_'); manifest 'svn_keywords = "N"' turns the check off;
	- --set-keywords option, --release=true commits svn:keywords property changes (see USAGE);
	- svn is called without a shell for these commands (argument arrays, values and file lists in files),
	  so spaces, '%' and '<>' work on Windows too; requires PHP 7.4 or newer.

CHANGES 3.3.1.0:
	- version control abstraction (ka_Vcs) with svn and git implementations;
	- manifest 'vcs' option: "svn" (default) or "git";
	- 'start_revision' is a revision number for svn and a commit/tag for git ("root" means the empty tree);
	- when the git history starts with the whole store in one commit, existing manifest keys are used:
	  'added_later_files[]' - store files patched by the module (processed as modified, not packed);
	  'exclude_files[]'     - other store files matched by upload masks;
	- manifest 'manifest_version': 1 (default) - masks work as in 3.3.0.2 ('*' matches '/' too);
	  2 - in all lists '*' does not match '/', '**' matches anything including '/';
	  '[...]*' is still a regexp quantifier (for example '[!/]*');
	- KA_BUILD_GIT environment variable can override the git command (default: git).

CHANGES 3.3.0.3:
	- svn diff and ka_gen.php exit codes are checked, the build stops with a non-zero exit code on failure;
	- all fatal errors (die) exit with code 1 now;
	- KA_BUILD_SVN environment variable can override the svn command (default: svn);
	- the archive is packed with ZipArchive when it is available, otherwise with external zip or 7z;
	- winrar (upgrade archive) is called on Windows only, a warning is shown on other systems;
	- ka_gen.php is called with the same PHP binary (PHP_BINARY) and an absolute manifest path;
	- ka_gen.php is taken from the ka_build.php directory or, if it is absent there, from the current directory;
	- the saved working directory is restored instead of chdir("..");
	- files without an extension do not cause a warning;
	- the first argument is not treated as a manifest file when it is an option (--...);
	- optional --out=<dir> keeps temporary files and the result outside of the working copy.
	  Without --out the behaviour is the same as before.

TODO:
	- detect working dir by getting a difference between working copy path and svn root;
	  At the present moment we set it manually in the code;
	
*/

define('USE_COLOR_OUTPUT', 1);

// customFnmatch flag: '*' does not match '/' (manifest_version 2)
define('KA_FNM_SEGMENT', 1 << 16);

/*
	stops the script with an error message and a non-zero exit code
*/
function fail($msg = '') {
	if (strlen($msg)) {
		show($msg, 'E');
	}
	exit(1);
}

function isWindows() {
	return (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
}

function customFnmatch(string $pattern, string $string, int $flags = 0): bool {
	$regex = '';
	$length = strlen($pattern);

	$is_last_square_bracket = false;
	
	for ($i = 0; $i < $length; $i++) {
		$char = $pattern[$i];

		switch ($char) {
			case '*':
				if ($is_last_square_bracket) {
					$regex .= '*';
				} elseif (isset($pattern[$i + 1]) && $pattern[$i + 1] === '*') {
					// '**' matches any characters including '/'
					$regex .= '.*';
					$i++;
				} else {
					// manifest_version 2: '*' does not match '/'; version 1: '*' matches anything
					$regex .= ($flags & KA_FNM_SEGMENT) ? '[^/]*' : '.*';
				}
				$is_last_square_bracket = false;
				break;

			case '[':
				$end = strpos($pattern, ']', $i);

				if ($end === false) {
					$regex .= '\[';
					break;
				}

				$class = substr($pattern, $i + 1, $end - $i - 1);

				// [!abc] -> [^abc]
				if (isset($class[0]) && $class[0] === '!') {
					$class = '^' . substr($class, 1);
				}

				$regex .= '[' . $class . ']';

				$i = $end;
				$is_last_square_bracket = true;
				break;

			default:
				$regex .= preg_quote($char, '#');
				$is_last_square_bracket = false;
		}
	}

	return preg_match('#^' . $regex . '$#u', $string) === 1;
}


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


/*
	returns true when the option is given as '--name' or '--name=<not empty, not false/0/no>'
*/
function hasOption($name) {
	global $argv;

	foreach ($argv as $arg) {
		if ($arg === '--' . $name) {
			return true;
		}
		if (strpos($arg, '--' . $name . '=') === 0) {
			$value = strtolower(substr($arg, strlen($name) + 3));
			return !in_array($value, array('', '0', 'false', 'no', 'n'));
		}
	}

	return false;
}


function fnmatchEx($patterns, $string, $flags = 0) {

	if (empty($patterns)) {
		return false;
	}

	$string = str_replace('\/', '/', $string);
	
	if (!is_array($patterns)) {
		$patterns = array($patterns);
	}

	if (ka_App::$manifest_version >= 2) {
		$flags |= KA_FNM_SEGMENT;
	}

	foreach ($patterns as $pattern) {
		if (customFnmatch($pattern, $string, $flags)) {
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
    	fail("rm dir failed at $dir");
    }
  }
}

/*
	Runs a command given as an array of arguments without a shell (no quoting problems with spaces,
	'%', '<', '>' on Windows and Linux). Returns the exit code, stdout and stderr are returned in
	$output and $errors.
*/
function runCommand(array $cmd, &$output = null, &$errors = null) {

	$out_file = tempnam(sys_get_temp_dir(), 'kab');
	$err_file = tempnam(sys_get_temp_dir(), 'kab');
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('file', $out_file, 'w'),
		2 => array('file', $err_file, 'w'),
	);

	if (PHP_VERSION_ID < 70400) {
		fail("PHP 7.4 or newer is required for svn:keywords (proc_open with an argument array)");
	}

	$process = proc_open($cmd, $descriptors, $pipes);
	if (!is_resource($process)) {
		@unlink($out_file);
		@unlink($err_file);
		$output = '';
		$errors = 'cannot start: ' . implode(' ', $cmd);
		return -1;
	}
	fclose($pipes[0]);
	$result = proc_close($process);

	$output = file_get_contents($out_file);
	$errors = file_get_contents($err_file);
	@unlink($out_file);
	@unlink($err_file);

	return $result;
}


/*
	svn:keywords of the module files. The property value is built from manifest.ini:
	"Revision Version=<version> Project=<keywords_project> Author=<keywords_author>".
	Spaces are written as '%_' and '%' as '%%' (svn custom keyword format).
*/
class ka_SvnKeywords {

	const PROPERTY = 'svn:keywords';

	protected $cfg;

	// file => true for the module files under version control
	protected $files = array();

	function __construct($cfg, $files) {
		$this->cfg = $cfg;
		$this->files = $files;
	}

	static function encode($value) {
		return str_replace(array('%', ' '), array('%%', '%_'), trim($value));
	}

	function getValue() {
		$project = $this->cfg['keywords_project'] ?? '';
		if (!strlen(trim($project))) {
			$project = $this->cfg['name'];
		}
		$author = $this->cfg['keywords_author'] ?? '';
		if (!strlen(trim($author))) {
			$author = 'karapuz team <support@ka-station.com>';
		}

		return 'Revision Version=' . self::encode($this->cfg['version'])
			. ' Project=' . self::encode($project)
			. ' Author=' . self::encode($author);
	}

	/*
		svn command as an array: KA_BUILD_SVN may be a path to the svn binary or wrapper
		(spaces are allowed when the file exists) or a command with arguments
	*/
	static function getSvn() {
		$svn = getenv('KA_BUILD_SVN');
		if (empty($svn)) {
			return array('svn');
		}
		if (is_file($svn)) {
			// batch wrappers are run by cmd on Windows (values are passed in files, not on the command line)
			if (isWindows() && preg_match('/\.(bat|cmd)$/i', $svn)) {
				return array('cmd', '/c', $svn);
			}
			return array($svn);
		}
		return preg_split('/\s+/', trim($svn));
	}

	/*
		runs svn with the module files passed in a --targets file (propset, commit)
	*/
	protected function svn(array $args, $files, &$output = null) {

		$targets_file = tempnam(sys_get_temp_dir(), 'kat');
		file_put_contents($targets_file, implode("\n", $files) . "\n");

		$cmd = array_merge(self::getSvn(), $args, array('--targets', $targets_file));
		$errors = '';
		$result = runCommand($cmd, $output, $errors);
		@unlink($targets_file);

		if ($result != 0) {
			fail("svn " . $args[0] . " failed with exit code " . $result . ":\n" . trim($errors));
		}

		return true;
	}

	/*
		runs svn for commands without the --targets option (status, proplist): the files are
		passed in small groups to keep the command line short (Windows limit). Returns outputs.
	*/
	protected function svnChunks(array $args, $files) {

		$outputs = array();
		foreach (array_chunk($files, 50) as $chunk) {
			$cmd = array_merge(self::getSvn(), $args, $chunk);
			$output = '';
			$errors = '';
			$result = runCommand($cmd, $output, $errors);
			if ($result != 0) {
				fail("svn " . $args[0] . " failed with exit code " . $result . ":\n" . trim($errors));
			}
			$outputs[] = $output;
		}

		return $outputs;
	}

	protected function loadXml($xml) {
		$doc = @simplexml_load_string($xml);
		if ($doc === false) {
			fail("svn returned wrong xml:\n" . $xml);
		}
		return $doc;
	}

	/*
		returns [file => [status, props status]] for the files. status: normal, modified,
		added, unversioned... props: none, normal, modified, conflicted
	*/
	function getStatus($files) {

		$result = array();
		foreach ($this->svnChunks(array('status', '--xml', '-v', '--depth', 'empty'), $files) as $output) {
			foreach ($this->loadXml($output)->xpath('//entry') as $entry) {
				$status = $entry->{'wc-status'};
				$result[normalizeFilename((string)$entry['path'])] = array(
					'item'  => (string)$status['item'],
					'props' => (string)$status['props'],
				);
			}
		}
		return $result;
	}

	/*
		returns [file => [property => value]] of the working copy or the BASE revision
	*/
	function getProperties($files, $base = false) {
		$args = array('proplist', '--xml', '-v');
		if ($base) {
			$args[] = '-r';
			$args[] = 'BASE';
		}
		$result = array();
		foreach ($this->svnChunks($args, $files) as $output) {
			foreach ($this->loadXml($output)->xpath('//target') as $target) {
				$props = array();
				foreach ($target->property as $property) {
					$props[(string)$property['name']] = (string)$property;
				}
				$result[normalizeFilename((string)$target['path'])] = $props;
			}
		}
		return $result;
	}

	/*
		Checks the module files. Returns an array:
			'files'       - versioned module files
			'unversioned' - module files that are not under version control
			'wrong'       - files with a wrong or missing svn:keywords in the working copy
			'uncommitted' - files with a correct svn:keywords that is not committed yet
			'other'       - files with other uncommitted changes: file => description
	*/
	function check() {

		$value = $this->getValue();
		$result = array('files' => array(), 'unversioned' => array(), 'wrong' => array(), 'uncommitted' => array(), 'other' => array());

		if (empty($this->files)) {
			return $result;
		}

		$status = $this->getStatus($this->files);
		foreach ($this->files as $file) {
			$item = $status[$file]['item'] ?? 'unversioned';
			if (in_array($item, array('unversioned', 'ignored', 'none', 'external'))) {
				$result['unversioned'][] = $file;
				continue;
			}
			$result['files'][] = $file;
			if ($item != 'normal') {
				$result['other'][$file] = $item;
			}
		}
		if (empty($result['files'])) {
			return $result;
		}

		$working = $this->getProperties($result['files']);
		$modified = array();
		foreach ($result['files'] as $file) {
			if (($status[$file]['props'] ?? '') == 'modified' || ($status[$file]['props'] ?? '') == 'conflicted') {
				$modified[] = $file;
			}
			if (($working[$file][self::PROPERTY] ?? null) !== $value) {
				$result['wrong'][] = $file;
			}
		}

		// property changes other than svn:keywords are not committed by ka_build
		if (!empty($modified)) {
			$base = $this->getProperties($modified, true);
			foreach ($modified as $file) {
				if ($status[$file]['props'] == 'conflicted') {
					$result['other'][$file] = 'property conflict';
					continue;
				}
				$w = $working[$file] ?? array();
				$b = $base[$file] ?? array();
				$keywords_changed = (($w[self::PROPERTY] ?? null) !== ($b[self::PROPERTY] ?? null));
				unset($w[self::PROPERTY], $b[self::PROPERTY]);
				ksort($w);
				ksort($b);
				if ($w !== $b) {
					$result['other'][$file] = (isset($result['other'][$file]) ? $result['other'][$file] . ', ' : '') . 'other properties modified';
				}
				if ($keywords_changed && !in_array($file, $result['wrong'])) {
					$result['uncommitted'][] = $file;
				}
			}
		}

		return $result;
	}

	/*
		sets svn:keywords on the files in the working copy
	*/
	function set($files) {
		$value_file = tempnam(sys_get_temp_dir(), 'kav');
		file_put_contents($value_file, $this->getValue());
		$this->svn(array('propset', self::PROPERTY, '-F', $value_file), $files);
		@unlink($value_file);
	}

	/*
		commits the files (property changes only, the caller checks that there are no other changes)
	*/
	function commit($files, $message) {
		$this->svn(array('commit', '-q', '--depth', 'empty', '-m', $message), $files, $output);
		return trim($output);
	}
}


class ka_Object {
	var $db = null;
	var $session = null;

	function init() {
		return true;
	}
}


/*
	Version control system wrapper. It writes a list of changed files between
	the start revision and the head to a file. Each line is "<status> <path>"
	where status is A (added), M (modified) or D (deleted).
*/
/*
	Header keywords of the package copies: $Project$, $Author$, $Version$, $Revision$ (see CHANGES 3.3.3.0).
*/
class ka_Keywords {

	const NAMES = array('Project', 'Author', 'Version', 'Revision');

	const EXTENSIONS = array('php', 'tpl', 'twig', 'js', 'css', 'scss', 'less', 'xml', 'txt', 'ini', 'html', 'htm', 'json', 'md');

	// name => value
	protected $values = array();

	// true - expanded keywords are written again too, false - only bare ones ($Name$)
	protected $overwrite = true;

	function __construct($values, $overwrite = true) {
		$this->values    = $values;
		$this->overwrite = $overwrite;
	}

	/*
		Returns the values of the keywords from the manifest config and the revision
	*/
	static function getValues($cfg, $revision) {
		$project = $cfg['keywords_project'] ?? '';
		if (!strlen(trim($project))) {
			$project = $cfg['name'] ?? '';
		}
		$author = $cfg['keywords_author'] ?? '';
		if (!strlen(trim($author))) {
			$author = 'karapuz team <support@ka-station.com>';
		}

		return array(
			'Project'  => trim($project),
			'Author'   => trim($author),
			'Version'  => trim($cfg['version'] ?? ''),
			'Revision' => trim((string)$revision),
		);
	}

	static function isTextFile($filename) {
		$filename = str_replace('\\', '/', $filename);
		if (preg_match('#(^|/)(vendor|node_modules)/#i', $filename)) {
			return false;
		}
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return in_array($ext, self::EXTENSIONS);
	}

	/*
		Returns the text with the keywords written
	*/
	function expand($text) {
		if (strpos($text, "\0") !== false) {
			return $text;
		}

		$values = $this->values;
		$overwrite = $this->overwrite;

		return preg_replace_callback('/\$(' . implode('|', self::NAMES) . ')(:[^$\r\n]*)?\$/', function($m) use ($values, $overwrite) {
			if (!empty($m[2]) && !$overwrite) {
				return $m[0];
			}
			if (!isset($values[$m[1]]) || !strlen($values[$m[1]])) {
				return $m[0];
			}
			return '$' . $m[1] . ': ' . $values[$m[1]] . ' $';
		}, $text);
	}

	/*
		Writes the keywords into a file (a package copy). $source - the path in the working copy for the
		file type check. Returns true when the file was changed.
	*/
	function expandFile($filename, $source = '') {
		if (!self::isTextFile(strlen($source) ? $source : $filename) || !is_file($filename)) {
			return false;
		}
		$text = file_get_contents($filename);
		if ($text === false) {
			return false;
		}
		$result = $this->expand($text);
		if ($result === $text) {
			return false;
		}
		if (file_put_contents($filename, $result) === false) {
			fail("Cannot write keywords to $filename");
		}
		return true;
	}
}


abstract class ka_Vcs {
	protected $cfg;

	function __construct($cfg) {
		$this->cfg = $cfg;
	}

	static function create($cfg) {
		$type = empty($cfg['vcs']) ? 'svn' : strtolower(trim($cfg['vcs']));
		if ($type == 'svn') {
			return new ka_VcsSvn($cfg);
		} elseif ($type == 'git') {
			return new ka_VcsGit($cfg);
		}
		return null;
	}

	abstract function makeFilesList($tmp_file);

	// the revision written to the $Revision$ keyword of the package copies
	abstract function getRevision();

	// true - keywords already expanded in the files are written again (svn expands them itself)
	function overwriteKeywords() {
		return true;
	}

	protected function execToFile($cmd, $tmp_file) {
		$output = array();
		$result = 0;
		exec($cmd . " > \"$tmp_file\"", $output, $result);
		if ($result != 0) {
			show("diff failed with exit code " . $result . ". Command: " . $cmd, 'E');
			return false;
		}
		return file_exists($tmp_file);
	}
}

class ka_VcsSvn extends ka_Vcs {

	function makeFilesList($tmp_file) {
		$svn = getenv('KA_BUILD_SVN');
		if (empty($svn)) {
			$svn = 'svn';
		}
		$cmd = $svn . " diff -r " . $this->cfg['start_revision'] . ":head --summarize";
		return $this->execToFile($cmd, $tmp_file);
	}

	// the highest revision of the working copy (svnversion -c '100:123M' gives 123)
	function getRevision() {
		$output = array();
		$result = 0;
		exec('svnversion -c . 2>&1', $output, $result);
		if ($result == 0 && !empty($output[0]) && preg_match('/(\d+)[MSP]*$/', trim($output[0]), $m)) {
			return $m[1];
		}
		return '';
	}

	// svn writes the values into the files (the file revision), only bare keywords are expanded
	function overwriteKeywords() {
		return false;
	}
}

class ka_VcsGit extends ka_Vcs {

	// hash of the empty tree, used when start_revision is "root"
	const EMPTY_TREE = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

	function makeFilesList($tmp_file) {
		$git = getenv('KA_BUILD_GIT');
		if (empty($git)) {
			$git = 'git';
		}
		$start = trim((string)$this->cfg['start_revision']);
		if ($start === '' || strtolower($start) == 'root') {
			$start = self::EMPTY_TREE;
		}
		// committed changes only (like svn diff to head), paths relative to the current directory
		$cmd = $git . " -c core.quotepath=off diff --name-status --no-renames --relative "
			. escapeshellarg($start) . " HEAD";
		return $this->execToFile($cmd, $tmp_file);
	}

	// the number of commits of HEAD (git has no revision numbers)
	function getRevision() {
		$git = getenv('KA_BUILD_GIT');
		if (empty($git)) {
			$git = 'git';
		}
		$output = array();
		$result = 0;
		exec($git . ' rev-list --count HEAD 2>&1', $output, $result);
		if ($result == 0 && !empty($output[0]) && ctype_digit(trim($output[0]))) {
			return trim($output[0]);
		}
		return '';
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

	// optional directory for temporary files and the result (--out=<dir>), absolute path without the trailing slash
	static $out_dir = '';

	// manifest format version: 1 (default) - '*' in masks matches '/' too; 2 - '*' does not match '/', '**' does
	static $manifest_version = 1;

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
	
		if (!empty($argv[1]) && strpos($argv[1], '--') !== 0) {
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
				fail("Wrong file format");
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
			if (empty($path_info['extension']) || !in_array($path_info['extension'], array('php', 'tpl', 'twig'))) {
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
				fail("Copy operation failed. from=$filename to $dest_file");
			}
			$found = true;
		}
		
		return $found;
	}


	/*
		returns lines "<status> <path>" of the changed files list with src_files added as new files
	*/
	protected function getPackFileLines($tmp_file) {

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

		return $file_lines;
	}


	/*
		returns the path of a file to pack for a line of the changed files list or an empty string
		when the file is not packed
	*/
	protected function getPackFileName($line) {

		if (empty($line)) {
			return '';
		}
		$matches = array();
		if (!preg_match("/([\w]*)[\s]*(.*)$/", $line, $matches)) {
			fail("Wrong file format");
		}

		$filename = trim(str_replace('\\', '/', $matches[2]));
		
		// exclude files excplicitely defined
		if (fnmatchEx($this->cfg['exclude_files'], $filename)) {
			return '';
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
			return '';
		}
		
		
		if ($matches[1] == 'A' && !fnmatchEx($this->cfg['added_later_files'], $filename)) {
			// ok
		} elseif (fnmatchEx($this->cfg['src_files'], $filename)) {
			// ok
		} elseif ($matches[1] =='M' && $this->cfg['include_modified_files']) {
			// ok
		} else {
			return '';
		}
		
		if (!empty($this->cfg['working_dir'])) {
			$filename = substr($filename, strlen($this->cfg['working_dir']));
		}
		if (empty($filename)) {
			return '';
		}	
		
		if (fnmatchEx(self::$exclude_files, $filename)) {
			return '';
		}

		return $filename;
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

		$file_lines = $this->getPackFileLines($tmp_file);

		$packed_files = array();

		$keywords = $this->getKeywords();
		$keywords_files = 0;

		foreach ($file_lines as $line) {
		
			$filename = $this->getPackFileName($line);
			if ($filename === '') {
				continue;
			}
			
			$path_info = pathinfo($filename);
			
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
						fail("Mkdir operation failed for $dest_file");
					}
				}
			} elseif (!copy($filename, $dest_file)) {
				fail("Copy operation failed. from=$filename to $dest_file");
			} elseif (!empty($keywords) && $keywords->expandFile($dest_file, $filename)) {
				$keywords_files++;
			}

			if (!is_dir(__DIR__ . '/' . $filename)) {
				$packed_files[] = $filename . "\n";
			}
		}
		
		$packed_list_file = 'packed_files.txt';
		if (!empty(self::$out_dir)) {
			$packed_list_file = self::$out_dir . '/' . $packed_list_file;
		}
		file_put_contents($packed_list_file, $packed_files);

		show("Number of packed files: " . count($packed_files));
		if (!empty($keywords)) {
			show("Keywords written to $keywords_files files");
		}
		
		return true;
	}


	/*
		Returns the keywords of the package copies or null (manifest 'expand_keywords = "N"')
	*/
	protected function getKeywords() {

		if (isset($this->cfg['expand_keywords']) && strtoupper(trim($this->cfg['expand_keywords'])) == 'N') {
			return null;
		}

		$vcs = ka_Vcs::create($this->cfg);
		if (empty($vcs)) {
			return null;
		}

		$revision = $vcs->getRevision();
		if (!strlen($revision)) {
			show("the revision for the \$Revision\$ keyword is not known", 'W');
		}

		return new ka_Keywords(ka_Keywords::getValues($this->cfg, $revision), $vcs->overwriteKeywords());
	}
	
	
		
	function done() {
	
	}
	
	function makeVcsFilesList($tmp_file = '') {

		if (empty($tmp_file)) {
			$tmp_file = self::$tmp_file;
		}

		$vcs = ka_Vcs::create($this->cfg);
		if (empty($vcs)) {
			show("Unknown vcs in manifest: " . $this->cfg['vcs'], 'E');
			return false;
		}

		return $vcs->makeFilesList($tmp_file);
	}

	
	function makeXmlPatch($tmp_file, $mod_dir) {

		if ($this->copyModFiles($tmp_file, $mod_dir)) {		
			show("- mod files copied to $mod_dir"); 

			// 3) generate xml patch
			//
			@unlink($this->cfg['xml_file']);

			$ka_gen_file = __DIR__ . '/ka_gen.php';
			if (!file_exists($ka_gen_file)) {
				$ka_gen_file = 'ka_gen.php';
			}
			if (!copy($ka_gen_file, $mod_dir.'/ka_gen.php')) {
				fail("Builder not copied.");
			}
			
			if (!copy($this->manifest_file, $mod_dir . '/manifest.ini')) {
				fail("Manifest.ini cannot be copied:" . $this->manifest_file);
			}

			$manifest_path = realpath($this->manifest_file);
			$xml_dest = "./" . $this->cfg['xml_file'];
			if (!empty(self::$out_dir)) {
				$xml_dest = self::$out_dir . '/' . $this->cfg['xml_file'];
			}

			$cwd = getcwd();
			chdir($mod_dir);
			$args = [];
			
			if (!empty(self::$build_dir)) {
				$args[] = "--build_dir='" . self::$build_dir . "'";
			}
			
			$args[] = '"--manifest=' . $manifest_path . '"';
			
			$result = 0;
			passthru('"' . PHP_BINARY . '" ka_gen.php ' . implode(" ", $args), $result);
			chdir($cwd);
			if ($result != 0) {
				fail("ka_gen.php failed with exit code " . $result);
			}

			if (!copy($mod_dir.'/' . $this->cfg['xml_file'], $xml_dest)) {
				fail("Builder did not generate xml file");
			}
			rmdir_ex($mod_dir);
			self::$xml_files[] = $xml_dest;
		}

		return true;
	}
	
	
	function copyXmlFiles($xml_dir) {

		if (!empty(self::$xml_files)) {
			foreach (self::$xml_files as $file) {
				if (!copy($file, $xml_dir."/" . basename($file))) {
					fail("xml file $file was not copied to dir $xml_dir");
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
	
		$options = getoptEx(["build_dir", "release", "out"]);

		self::$store_root_dir = getcwd() . '/';
		
		if (!empty($options['build_dir'])) {
			self::$build_dir = getcwd() . '/' . $options['build_dir'];
		}
		
		if (!empty($options['release'])) {
			self::$release = true;
		}

		if (!empty($options['out'])) {
			$out_dir = normalizeFilename($options['out']);
			if (!preg_match('#^(/|[a-zA-Z]:)#', $out_dir)) {
				$out_dir = getcwd() . '/' . $out_dir;
			}
			if (!file_exists($out_dir) && !mkdir($out_dir, 0777, true)) {
				fail("cannot create out dir: " . $out_dir);
			}
			self::$out_dir  = rtrim(normalizeFilename(realpath($out_dir)), '/');
			self::$tmp_file = self::$out_dir . '/' . self::$tmp_file;
			self::$mod_dir  = self::$out_dir . '/' . self::$mod_dir;
			self::$pack_dir = self::$out_dir . '/' . self::$pack_dir;
		}

		if (empty($this->manifest_file)) {
			$this->manifest_file = self::$build_dir . 'manifest.ini';
		}
		
		if (!file_exists($this->manifest_file)) {
			fail("manifest.ini not found at: " . $this->manifest_file);
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
		
		// the built-in masks are written for manifest_version 1 ('*' matches '/'), keep their meaning in version 2
		if (!empty($this->cfg['manifest_version']) && (int)$this->cfg['manifest_version'] >= 2) {
			self::$exclude_files = array_map(function($mask) {
				return str_replace('*', '**', $mask);
			}, self::$exclude_files);
		}

		if (!empty($this->cfg['exclude_files'])) {
			self::$exclude_files = array_unique(array_merge(self::$exclude_files, $this->cfg['exclude_files']));
		}
		
		if (!empty($this->cfg['include_xml_files'])) {
			self::$xml_files = array_unique(array_merge(self::$xml_files, $this->cfg['include_xml_files']));
		}
		
		if (!empty($this->cfg['manifest_version'])) {
			self::$manifest_version = (int)$this->cfg['manifest_version'];
		}

		if (empty($this->cfg['vcs'])) {
			$this->cfg['vcs'] = 'svn';
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
        
		$cwd = getcwd();
		chdir($pack_dir);
		@unlink(self::$zip_file);
		if (!$this->zipDir(self::$zip_file)) {
			chdir($cwd);
			show("cannot create the archive: " . self::$zip_file, 'E');
			return false;
		}

		// http://acritum.com/software/manuals/winrar/
		// $>winrar a -afzip -ptest test.zip rar.txt tmp_files.txt
		if (!empty($this->cfg['upgrade_key'])) {
			$comments_file     = self::$build_dir . $this->cfg['comments_file'];
			$instructions_file = $this->cfg['upgrade_instructions_file'];
			$upgrade_file      = "upgrade_to_" . self::$zip_file . ".zip ";
			
			if (isWindows()) {
				exec("winrar a -afzip -z" . $comments_file . " -p" . $this->cfg['upgrade_key'] . ' ' . $upgrade_file . ' '  . self::$zip_file);
				exec("winrar a -afzip -z" . $comments_file . ' ' . $upgrade_file . ' ' . $instructions_file);
			} else {
				show("upgrade archive was not created: winrar is available on Windows only", 'W');
			}
		}
		chdir($cwd);

		return true;
	}

	/*
		packs all files of the current directory into $zip_file.
		ZipArchive is used when it is available, otherwise external zip or 7z.
	*/
	protected function zipDir($zip_file) {

		if (class_exists('ZipArchive')) {
			$zip = new ZipArchive();
			if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				return false;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ($iterator as $file) {
				$name = normalizeFilename(substr($file->getPathname(), 2));
				if ($name == $zip_file) {
					continue;
				}
				if ($file->isDir()) {
					$zip->addEmptyDir($name);
				} else {
					$zip->addFile($file->getPathname(), $name);
				}
			}

			return $zip->close() && file_exists($zip_file);
		}

		$output = array();
		$result = 0;
		exec("zip -r \"" . $zip_file . "\" *", $output, $result);
		if ($result == 0 && file_exists($zip_file)) {
			return true;
		}

		$output = array();
		$result = 0;
		exec("7z a -tzip \"" . $zip_file . "\" *", $output, $result);

		return ($result == 0 && file_exists($zip_file));
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
			show("release_dir cannot be created:" . $release_dir) && fail();
		}
		
		return	$release_dir;
	}
	
	
	function copyRelease($zip_file) {
		
		if (empty($zip_file)) {
			show('zip file not found') && fail();
		}
		
		if (empty($this->cfg['releases_dir'])) {
			show('releases_dir not found') && fail();
		}

		$release_dir = $this->getReleaseDir();
		$dest_file = $release_dir . '/' . basename($zip_file);
		
		if (!copy($zip_file, $dest_file)) {
			show('cannot copy zip file:' . $dest_file) && fail();
		}
		
		if (!empty($this->cfg['upgrade_key'])) {
			$zip_file_upgrade = pathinfo($zip_file, PATHINFO_DIRNAME) . "/upgrade_to_" . basename($zip_file) . ".zip";
			$dest_file = $release_dir . '/' . basename($zip_file_upgrade);
			if (!copy($zip_file_upgrade, $dest_file)) {
				show('cannot copy zip file:' . $dest_file) && fail();
			}
		}
		
		return $dest_file;	
	}
	

	/*
		returns the version file path (store root + relative path) or '' when it is not defined
	*/
	protected function getVersionFile() {

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

		return $version_file;
	}


	/*
		writes the manifest version to the version file.
		Returns true when the file was changed.
	*/
	protected function updateModuleVersion($version_file) {

		if (empty($version_file) || !file_exists($version_file)) {
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
			return false;
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
	
	
	/*
		module files for svn:keywords: the packed files (upload_dirs, upload_files, src_files
		without excluded files) and include_files
	*/
	function getModuleFiles($tmp_file) {

		$files = array();
		foreach ($this->getPackFileLines($tmp_file) as $line) {
			$filename = $this->getPackFileName($line);
			if ($filename !== '' && is_file($filename)) {
				$files[$filename] = true;
			}
		}

		$root = normalizeFilename(self::$store_root_dir);
		foreach (self::$include_files as $file) {
			if (empty($file)) {
				continue;
			}
			$path = normalizeFilename(self::$build_dir . $file);
			if (!is_file($path)) {
				$path = normalizeFilename($file);
			}
			if (!is_file($path)) {
				continue;
			}
			if (strpos($path, $root) === 0) {
				$path = substr($path, strlen($root));
			}
			$files[$path] = true;
		}

		$files = array_keys($files);
		sort($files);

		return $files;
	}


	protected function showFiles($files) {
		foreach ($files as $key => $value) {
			show("    " . (is_int($key) ? $value : "$key ($value)"));
		}
	}


	/*
		svn:keywords of the module files (svn only, manifest 'svn_keywords' is not "N"):
		- a normal build shows a warning when a file has a wrong or uncommitted value;
		- --set-keywords sets the value in the working copy and stops, the files have to be committed;
		- --release=true sets the value and COMMITS the property changes of the module files together
		  with the version file changed by the build, then builds the package. It stops when the module
		  files have other uncommitted changes.
		$version_file - relative path of the version file when the build changed it and it had no
		other local changes before, '' otherwise
	*/
	function processSvnKeywords($tmp_file, $version_file = '') {

		$set_mode = hasOption('set-keywords');

		if ($this->cfg['vcs'] != 'svn') {
			if ($set_mode) {
				fail("--set-keywords works with svn only");
			}
			return true;
		}
		if (isset($this->cfg['svn_keywords']) && strtoupper($this->cfg['svn_keywords']) == 'N') {
			if ($set_mode) {
				fail("svn_keywords = \"N\" in manifest.ini, svn:keywords are not managed by ka_build");
			}
			return true;
		}

		$keywords = new ka_SvnKeywords($this->cfg, $this->getModuleFiles($tmp_file));
		$check = $this->checkSvnKeywords($keywords, $version_file);
		$value = $keywords->getValue();

		show("- svn:keywords of " . count($check['files']) . " module files: " . $value);
		if (!empty($check['unversioned'])) {
			show("WARNING: module files not under version control (svn:keywords cannot be set):", 'W');
			$this->showFiles($check['unversioned']);
		}

		$to_commit = array_merge($check['wrong'], $check['uncommitted']);
		if (!empty($version_file)) {
			$to_commit[] = $version_file;
		}
		$to_commit = array_values(array_unique($to_commit));
		sort($to_commit);

		// normal build: a warning only
		//
		if (!$set_mode && !self::$release) {
			if (!empty($check['wrong'])) {
				show("WARNING: " . count($check['wrong']) . " module files have a wrong or missing svn:keywords:", 'W');
				$this->showFiles($check['wrong']);
			}
			if (!empty($check['uncommitted'])) {
				show("WARNING: svn:keywords of " . count($check['uncommitted']) . " module files are not committed:", 'W');
				$this->showFiles($check['uncommitted']);
			}
			if (!empty($check['wrong'])) {
				show("Run 'php ka_build.php <manifest> --set-keywords' and commit the files, or build with --release=true"
					. " which sets and commits them. Versions in this package are not correct.", 'W');
			} elseif (!empty($check['uncommitted'])) {
				show("Commit these files, or build with --release=true which commits them."
					. " Versions in this package are not correct.", 'W');
			}
			return true;
		}

		// --set-keywords without --release: set in the working copy, never commit
		//
		if ($set_mode && !self::$release) {
			if (!empty($check['wrong'])) {
				$keywords->set($check['wrong']);
				show("svn:keywords set on " . count($check['wrong']) . " files:");
				$this->showFiles($check['wrong']);
			}
			if (empty($to_commit)) {
				show("svn:keywords and the version are correct and committed. Nothing to do.");
			} else {
				show("\r\nCOMMIT these " . count($to_commit) . " files now (ka_build does not commit without --release=true)."
					. " svn writes the new values into the files on commit:");
				$this->showFiles($to_commit);
			}
			exit(0);
		}

		// --release=true: set and commit the property changes of the module files
		//
		if (!empty($check['other'])) {
			show("WARNING: module files have uncommitted changes, the package is built from the working copy:", 'W');
			$this->showFiles($check['other']);
		}
		if (empty($to_commit)) {
			show("- svn:keywords and the version are correct and committed");
			return true;
		}

		if (!empty($check['other'])) {
			fail(count($to_commit) . " files have to be committed for the release, but the module files"
				. " have other uncommitted changes (listed above). ka_build commits only svn:keywords and the version"
				. " it writes itself. Commit or revert these changes, or run --set-keywords and commit everything yourself.");
		}

		show("RELEASE: these " . count($to_commit) . " module files will be COMMITTED (svn:keywords"
			. (empty($version_file) ? '' : ' and the version in ' . $version_file) . "):");
		$this->showFiles($to_commit);

		if (!empty($check['wrong'])) {
			$keywords->set($check['wrong']);
		}

		$check = $this->checkSvnKeywords($keywords, $version_file);
		if (!empty($check['wrong']) || !empty($check['other'])) {
			fail("svn:keywords could not be set correctly, nothing was committed. Check 'svn status'.");
		}

		$parts = array();
		if (!empty($version_file)) {
			$parts[] = "version";
		}
		if (!empty($check['uncommitted'])) {
			$parts[] = "svn:keywords";
		}
		$message = "Release " . $this->cfg['version'] . ": set " . implode(' and ', $parts);
		show($keywords->commit($to_commit, $message));
		show("- committed: " . $message);

		$check = $keywords->check();
		if (!empty($check['wrong']) || !empty($check['uncommitted'])
			|| (!empty($version_file) && isset($check['other'][$version_file]))
		) {
			fail("the release changes are still not committed. Check 'svn status'.");
		}

		return true;
	}


	/*
		ka_SvnKeywords::check() where the content change of the version file written by the build
		is expected (it is committed together with svn:keywords on release)
	*/
	protected function checkSvnKeywords($keywords, $version_file) {
		$check = $keywords->check();
		if (!empty($version_file) && ($check['other'][$version_file] ?? '') == 'modified') {
			unset($check['other'][$version_file]);
		}
		return $check;
	}


	/*
		returns the relative path of the version file when it is under svn and has no local changes,
		'' otherwise
	*/
	protected function getCleanVersionFile($version_file) {

		if ($this->cfg['vcs'] != 'svn' || empty($version_file) || !file_exists($version_file)) {
			return '';
		}

		$file = normalizeFilename($version_file);
		$root = normalizeFilename(self::$store_root_dir);
		if (strpos($file, $root) === 0) {
			$file = substr($file, strlen($root));
		}

		$keywords = new ka_SvnKeywords($this->cfg, array($file));
		$status = $keywords->getStatus(array($file));
		if (($status[$file]['item'] ?? '') != 'normal' || !in_array($status[$file]['props'] ?? '', array('none', 'normal', ''))) {
			return '';
		}

		return $file;
	}


	function run() {

		// init settings and read config from manifest.ini
		//
		if (!$this->readConfig()) {
			fail("configuration file not found");
		}

		$this->cfg['exclude_files'] = array_map('normalizeFilename', $this->cfg['exclude_files']);
		$this->cfg['upload_dirs']   = array_map('normalizeFilename', $this->cfg['upload_dirs']);
		$this->cfg['upload_files']  = array_map('normalizeFilename', $this->cfg['upload_files']);
		
		// generate a list with changed files from the repository
		//
		if (!$this->makeVcsFilesList()) {
			fail($this->cfg['vcs'] . ' diff failed');
		}
		show("- " . $this->cfg['vcs'] . " diff file generated");

		// updating the version in the controller. It is done before svn:keywords, so --release=true
		// commits the version file together with svn:keywords
		//
		$changed_version_file = '';
		if (!empty($this->cfg['target_oc_version'])) {
			$version_file = $this->getVersionFile();
			$clean_version_file = $this->getCleanVersionFile($version_file);
			if ($this->updateModuleVersion($version_file)) {
				$changed_version_file = $clean_version_file;
			}
		}

		// svn:keywords of the module files (--set-keywords stops here)
		//
		$this->processSvnKeywords(self::$tmp_file, $changed_version_file);

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
    				fail('cannot create xml dir:' . $xml_dir);
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
			fail("Cannot pack archive");
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
			fail("Cannot open file: $tmp_file");
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
				fail("Wrong file format");
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
		
		$winscp_file = "winscp.txt";
		if (!empty(self::$out_dir)) {
			$winscp_file = self::$out_dir . '/' . $winscp_file;
		}
		file_put_contents($winscp_file, implode("\n", $script));
		
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
