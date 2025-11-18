<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 575 $)
*/
	
namespace extension\ka_extensions;

//
// this option adds a filter for processing \r character to \n in files because fgetcsv function 
// may fail randomly in detrmining the end of MacOS file line. It can be disabled to improve 
// the speed of file processing for the import scripts where MacOS csv files are not used.
//

/**
	@internal
*/
class macfix_filter extends \php_user_filter {
	// this suppress a notice on php 8.1
	#[\ReturnTypeWillChange]
	function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {

			$bucket->data = preg_replace("/\r([^\n])/mu", "\n$1", $bucket->data);

			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
  	}
}

stream_filter_register("macfix", '\extension\ka_extensions\macfix_filter') or die("Failed to register filter");

/**
	Reads and writes files in UTF-8, UTF-16 modes.
*/
class FileUTF8 {

	protected $lastError = '';

	public $handle = null;
	protected $filename = '';
	protected $charset = '';
	
	protected $filter = null;
	protected $options = array();
	
	protected $bom_size = 0;
	
	protected $is_filter_applied = false;

	public function getLastError() {
		return $this->lastError;
	}

	public function rewind() {
		rewind($this->handle);
		if ($this->bom_size) {
			fread($this->handle, $this->bom_size);
		}
	}
		
	public function fopen($filename, $mode, $options = array(), $charset = 'UTF-8') {

		$this->is_filter_applied = false;
		$this->handle   = null;
		$this->filename = $filename;
		$this->options  = $options;
		$this->charset  = $charset;
		$this->bom_size = 0;
		
		if (!in_array($mode, array('a','w','r'))) {
			$this->lastError = "fopen: invalid mode ($mode)";
			return false;
		}
		
		if (empty($filename)) {
			$this->lastError = "fopen: file name is not defined";
			return false;
		}
			
		if (empty($charset)) {
			$this->lastError = "fopen: charset was not specified for file";
			return false;
		}

		$filesize = 0;
		if (file_exists($filename)) {
			$filesize = filesize($filename);
		}

		if (version_compare(PHP_VERSION, '8.0.0', '<')) {
			@ini_set("auto_detect_line_endings", "1");
		}

		if (!($this->handle = fopen($filename, $mode))) {
			$this->lastError = "fopen: file ($filename) cannot be open for unknown reason";
			return false;
		}

		if ($mode == 'w' || $mode == 'a') {
		
			if ($mode == 'a' && $filesize == 0) {
				if ($charset == 'UTF-16LE') {
					fwrite($this->handle, chr(0xFF).chr(0xFE), 2);
				}
			}
			
			if ($charset) {
				$this->filter = stream_filter_append($this->handle, 'convert.iconv.UTF-8/'. $charset . '//TRANSLIT');				
				setlocale(LC_ALL, 'en_US.UTF8', 'en_US.UTF-8');
				$this->is_filter_applied = true;
			} else {
				fclose($this->handle);
				$this->handle = FALSE;
			}
			
		} elseif ($mode == 'r') {
				
			$bom = fread($this->handle, 2);
			rewind($this->handle);

/*
				http://www.unicode.org/faq/utf_bom.html#UTF8
				00 00 FE FF UTF-32, big-endian
				FF FE 00 00 UTF-32, little-endian
				FE FF       UTF-16, big-endian
				FF FE       UTF-16, little-endian
				EF BB BF    UTF-8
*/
			if ($charset == 'UTF-16') {
				if ($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)) {
					// UTF16 Byte Order Mark present
					$charset = 'UTF-16';
				} else {
					$charset = '';
				}
			} elseif ($charset == 'UTF-8') {
				if ($bom === chr(0xef).chr(0xbb)) {
					fread($this->handle, 3);
					$this->bom_size = 3;
				}
			}

			if ($charset != 'UTF-8') {
				$this->filter = stream_filter_append($this->handle, 'convert.iconv.'.$charset.'/UTF-8//IGNORE');
				$this->is_filter_applied = true;
			}
			setlocale(LC_ALL, 'en_US.UTF8', 'en_US.UTF-8');

			if (!empty($options['enable_macfix'])) {
				stream_filter_append($this->handle, 'macfix');
				$this->is_filter_applied = true;
			}
		}

		return $this->handle;
	}

	/*
		fseek - works in bytes always (regardless of the stream encoding)
		ftell - measures length in bytes for UTF-8 charset
		fread - reads a number of utf characters (not bytes)

		PARAMETERS:
			offset - length in bytes for utf-8 stream
	*/
	public function fseek($offset) {
	
		if (!$this->is_filter_applied) {
			return fseek($this->handle, $offset);
		}

		rewind($this->handle);

		$remainder = $offset;	 // remainder in bytes for utf-8 stream

		while ($remainder && !feof($this->handle)) {

			// calculate max_length in bytes
			//
			// 4 bytes are reserved for possible BOL
			// we assume that the whole string may consists of 2-byte characters
			//
			$max_length = (int)($remainder / 2) - 4;

			if ($max_length > 1) {
				$block_size = min(1024*32, $max_length);
			} else {
				$block_size = 1;
			}

			$buf = fread($this->handle, $block_size);			
			if ($buf === false) {
				return -1;
			} 
			
			if (strlen($buf) === 0) {
				return 0;
			}

			$pos = ftell($this->handle);

			if ($pos > $offset) {
				die('ERROR: ftell() function failed, please contact author of this extension.');
			}

			$remainder = $offset - $pos;
			if ($remainder < 0) {
				die('ERROR: fseek() function failed, please contact author of this extension.');
			}
		}

		return 0;
	}

	
	public function fclose() {
		$result = fclose($this->handle);
		$this->handle = null;
		return $result;
	}
}