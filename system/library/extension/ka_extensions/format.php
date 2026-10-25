<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 592 $)
*/
	
namespace extension\ka_extensions;

/**
	Formatting values with language based
	
	@package Service
*/
class Format {

	/**
		Default labels of byte size units. They are used when the language file does not
		define the text_unit_* keys and by the static formatBytes() method.
	*/
	const DEFAULT_SIZE_UNITS = [
		'bytes' => 'bytes',
		'kb'    => 'Kb',
		'mb'    => 'Mb',
		'gb'    => 'Gb',
	];

	/**
		Default labels of time units. They are used when the language file does not
		define the text_days, text_hours, text_minutes, text_seconds keys.
	*/
	const DEFAULT_TIME_UNITS = [
		'days'    => 'days',
		'hours'   => 'hours',
		'minutes' => 'minutes',
		'seconds' => 'seconds',
	];

	/**
		The language file of the class (admin and catalog areas)
	*/
	const LANGUAGE_FILE = 'extension/ka_extensions/ka_extensions/ka_format';

	protected $registry;
	protected $db;
	protected $config;

	protected $model_localisation_language;
	
	protected $language;     // language object
	protected $language_id;

	/**
		@internal
		Languages found during the current request, by the area ('admin' or 'catalog')
	*/
	protected static $languages_cache = [];

	/**
		$language_id - language identifier. When it is empty, the current language of the area is used
		(config_language).
		
		When the language is not found, the class works with the default (English) labels
		instead of stopping with a fatal error.
	*/
	public function __construct($language_id = null) {

		$this->registry = \KaGlobal::getRegistry();
		if (!empty($this->registry)) {
			$this->db     = $this->registry->get('db');
			$this->config = $this->registry->get('config');
		}

		setlocale(LC_NUMERIC, 'C');

		$language = $this->findLanguage($language_id);

		if (empty($language)) {
			$this->writeLog("Format::__construct: language not found (language_id: " . var_export($language_id, true) . "), default labels are used");
			
			$language = [
				'language_id' => (int)(!empty($language_id) ? $language_id : $this->getConfig('config_language_id')),
				'code'        => $this->getConfig('config_language'),
			];
			if (empty($language['code'])) {
				$language['code'] = 'en-gb';
			}
		}
		
		$this->language_id = $language['language_id'];
		if (empty($language['directory'])) {
			$language['directory'] = $language['code'];
		}
		
		$this->language = new \Language($language['directory']);
		
		// the old file location is loaded first for compatibility with existing overrides
		$this->language->load('ka_format');
		$this->language->load(static::LANGUAGE_FILE);
	}

	
	/**
		Returns the language record (language_id, code, directory) or false if it is not found.
		The list of languages is read once per request and area.
	*/
	protected function findLanguage($language_id = null) {
	
		try {
			$area = (defined('DIR_CATALOG') ? 'admin' : 'catalog');
			
			if (!isset(static::$languages_cache[$area])) {
				$this->registry->get('load')->model('localisation/language');
				$this->model_localisation_language = $this->registry->get('model_localisation_language');
				
				$languages = $this->model_localisation_language->getLanguages();
				static::$languages_cache[$area] = (!empty($languages) ? $languages : []);
			}
			$languages = static::$languages_cache[$area];

			if (empty($language_id)) {
				$language_code = $this->getConfig('config_language');
				foreach ($languages as $lng) {
					if ($lng['code'] == $language_code) {
						return $lng;
					}
				}
				return false;
			}

			foreach ($languages as $lng) {
				if ($lng['language_id'] == $language_id) {
					return $lng;
				}
			}

			// the language may be missing in the list (for example, a disabled language in the catalog)
			//
			if (empty($this->model_localisation_language)) {
				$this->registry->get('load')->model('localisation/language');
				$this->model_localisation_language = $this->registry->get('model_localisation_language');
			}
			$language = $this->model_localisation_language->getLanguage($language_id);
			if (!empty($language)) {
				return $language;
			}

		} catch (\Throwable $e) {
			$this->writeLog("Format::findLanguage: " . $e->getMessage());
		}
		
		return false;
	}


	/**
		@internal
	*/
	protected function getConfig($key) {
		if (empty($this->config)) {
			return null;
		}
		return $this->config->get($key);
	}
	
	
	/**
		@internal
	*/
	protected function writeLog($msg) {
		if (empty($this->registry)) {
			return;
		}
		$log = $this->registry->get('log');
		if (!empty($log)) {
			$log->write($msg);
		}
	}
	
	
	/**
		Returns a language text or the default value when the language does not have the key.
	*/
	protected function getText($key, $default) {
	
		if (empty($this->language)) {
			return $default;
		}

		if (method_exists($this->language, 'has')) {
			if ($this->language->has($key)) {
				return $this->language->get($key);
			}
			return $default;
		}

		$text = $this->language->get($key);
		if ($text === $key) {
			return $default;
		}
		
		return $text;
	}
	
	
	/**
		Returns labels of byte size units ('bytes', 'kb', 'mb', 'gb') from the language file
		with the English defaults.
	*/
	public function getSizeUnits() {
	
		$units = [];
		foreach (static::DEFAULT_SIZE_UNITS as $k => $v) {
			$units[$k] = $this->getText('text_unit_' . $k, $v);
		}
		
		return $units;
	}
	
	/*
		PARAMETERS:
			$str   - string
			$chars - a character or array of characters
	*/
	public function strip($str, $chars) {
		$str = trim($str);

		if (empty($chars)) {
			return $str;
		}

		if (!is_array($chars)) {
			$chars = array($chars);
		}

		$pat = array();
		$rep = array();
		foreach($chars as $char) {
			$pat[] = "/(" . preg_quote($char, '/') . ")*$/";
			$rep[] = '';
			$pat[] = "/^(" . preg_quote($char, '/') . ")*/";
			$rep[] = '';
		}

		$res = preg_replace($pat, $rep, $str);
		
		return $res;
	}

	/*
		supports values like '.99', '0,99', '$1,200.00', '123,456.78' => '123456.78'
	*/
	public function parsePrice($price) {
	
		// remove all useless characters from the price
		//
		$price = preg_replace("([^\d\-\.`,])", "", $price);
		
		if (!preg_match("/([\d\-,\.` ]*)([\.,])(\d*)$/U", $price, $matches)) {
			return doubleval($price);
		}

		$matches[1] = preg_replace("/[^\d\-]/", "", $matches[1]);
		$res = doubleval($matches[1] . '.' . $matches[3]);
		
		return $res;
	}

	
	public function formatPrice($price, $new = false) {
		
		if (!$new) {
			return $this->parsePrice($price);
		}
		
		$price = (float) $price;
		
		return $price;
	}

		
	/*
		this function should parse the date and try to return formated as YYYY-MM-DD.
	*/
	public function formatDate(&$date) {
	
		$date = trim($date);
		
		// yyyy-mm-dd
		if (preg_match("/^\d{4}-\d{1,2}-\d{1,2}$/", $date, $matches)) {
			return true;

		// mm/dd/yyyy
		} elseif (preg_match("/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/", $date, $matches)) {
			if ($matches[3] < 100) {
				$matches[3] += 2000;
			}
			$date = sprintf("%04d-%02d-%02d", $matches[3], $matches[1], $matches[2]);			
			return true;
			
		// dd.mm.yyyy
		} elseif (preg_match("/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/", $date, $matches)) {
			if ($matches[3] < 100) {
				$matches[3] += 2000;
			}
			$date = sprintf("%04d-%02d-%02d", $matches[3], $matches[2], $matches[1]);
			return true;
		}
		
		return false;
	}
	
	/*
		$diff - number of seconds since specific moment
	
	*/
	public function formatPassedTime($diff) {

 		$periods = array( //suffixes
	    	'd' => array(86400, $this->getText('text_days', static::DEFAULT_TIME_UNITS['days'])),
	   		'h' => array(3600, $this->getText('text_hours', static::DEFAULT_TIME_UNITS['hours'])),
      		'm' => array(60, $this->getText('text_minutes', static::DEFAULT_TIME_UNITS['minutes'])),
			's' => array(1, $this->getText('text_seconds', static::DEFAULT_TIME_UNITS['seconds']))
  		);

		$ret = '';
		foreach ($periods as $k => $v) {
			$num = floor($diff / $v[0]);
				if ($num || !empty($ret) || $k == 's') {
					$ret .= $num . ' ' . $v[1] . ' ';
				}
				$diff -= $v[0] * $num;
		}

	    return $ret;
	}

	
  	/*
  		function converts values like 10M to bytes
  		
  		See parseBytes() for supported formats.
	*/
	public function convertToByte($file_size) {
		return static::parseBytes($file_size);
	}


	/*
		Function converts value to human readable format like 10.1 Mb 
		
		Unit labels are taken from the language file (text_unit_bytes, text_unit_kb, text_unit_mb, text_unit_gb),
		the English labels are used by default.
	*/
	public function convertToMegabyte($val) {
		return static::formatBytes($val, 1, $this->getSizeUnits());
	}


	/**
		Converts a size value to bytes. It does not need a language and can be called statically.
		
		Supported values: 512, '512', '1.5G', '1,5G', '2M', '128K', '128k', '10 Mb', '10 KB', '100 bytes', '-1'.
		Suffixes K, M, G, T (case-insensitive) are multiples of 1024, an optional 'b' or 'bytes' after them is allowed.
		Values like PHP ini '-1' are returned as -1.
		
		Unrecognized values are converted with (int) as is.
		
		Returns: integer number of bytes
	*/
	public static function parseBytes($value) {
	
		if (is_int($value)) {
			return $value;
		}
		
		if (is_float($value)) {
			return (int)round($value);
		}
	
		$val = strtolower(trim((string)$value));
		
		if (!preg_match("/^([+-]?(?:\d+(?:[\.,]\d*)?|[\.,]\d+))\s*([kmgt]?)(?:b|bytes?)?$/", $val, $matches)) {
			return (int)$val;
		}
		
		$number = (float)str_replace(',', '.', $matches[1]);
		
		$multipliers = [
			''  => 1,
			'k' => 1024,
			'm' => 1048576,
			'g' => 1073741824,
			't' => 1099511627776,
		];
		
		return (int)round($number * $multipliers[$matches[2]]);
	}
	
	
	/**
		Converts a number of bytes to a human readable string like '10.1 Mb'. It does not need a language
		and can be called statically.
		
		Parameters:
			$bytes     - number of bytes or a size string supported by parseBytes() like '10M'
			$precision - number of decimal digits
			$units     - optional labels of units: ['bytes' => .., 'kb' => .., 'mb' => .., 'gb' => ..].
			             Missing labels are taken from DEFAULT_SIZE_UNITS.
	*/
	public static function formatBytes($bytes, $precision = 1, $units = []) {

		$val = $bytes;
		if (!is_numeric($val)) {
			$val = static::parseBytes($val);
		}
		
		$units = array_merge(static::DEFAULT_SIZE_UNITS, (array)$units);

		if ($val >= 1073741824) {
			$val = round($val/1073741824, $precision) . " " . $units['gb'];

		} elseif ($val >= 1048576) {
			$val = round($val/1048576, $precision) . " " . $units['mb'];

		} elseif ($val >= 1024) {
			$val = round($val/1024, $precision) . " " . $units['kb'];
		} else {
			$val = $val . " " . $units['bytes'];
		}

		return $val;
	}
	
	
	/*
		PARAMETERS:
			weight - value like this 0.0234g

		RETURNS:
			array (
				value           -> 0.0234
				weight_class_id -> 4
			)

		NOTES:
			function does NOT create a new weight class
	*/	
	public function parseWeight($weight) {

		$pair = array(
			'value'           => 0,
			'weight_class_id' => $this->config->get("config_weight_class_id"),
		);
	
		$matches = array();
		if (preg_match("/([\d\.\,]*)([\D]*)$/", $weight, $matches)) {
			$pair['value'] = $matches[1];
		
			$qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "weight_class_description
				WHERE unit = '" . $this->db->escape($matches[2]) . "'"
			);
	
			if (!empty($qry->row)) {
				$pair['weight_class_id'] = $qry->row['weight_class_id'];
			}
		}
		
		return $pair;
	}

	
	public function parseLength($length) {
	
		$pair = array(
			'value'           => 0,
			'length_class_id' => $this->config->get("config_length_class_id"),
		);
	
		$matches = array();
		if (preg_match("/(.*)([\D]*)$/U", $length, $matches)) {
			$pair['value'] = $matches[1];
		
			$qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class_description
				WHERE unit = '" . $this->db->escape($matches[2]) . "'"
			);

			if (!empty($qry->row)) {
				$pair['length_class_id'] = $qry->row['length_class_id'];
			}
		}

		return $pair;
	}
	
	
	public function formatWeight($val, $class_id, $decimal_point = '.', $thousand_point = '') {
		
		$val = doubleval($val);
		$unit = $this->getWeightUnit($class_id);
		
		$val = number_format($val, 2, $decimal_point, $thousand_point) . $unit;

		return $val;
	}
		
	public function getWeightUnit($class_id) {
		static $weights;
				
		if (empty($weights[$class_id])) {
			$qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "weight_class_description
				WHERE weight_class_id = '$class_id' 
					AND language_id = '" . $this->language_id. "'"
			);
						
			if (!empty($qry->row)) {
				$weights[$class_id] = $qry->row;
			} else {
				$weights[$class_id] = array(
					'unit' => ''
				);
			}
		}

		return	$weights[$class_id]['unit'];
	}
	
	public function formatLength($val, $class_id, $decimal_point = '.', $thousand_point = '') {

		$val = doubleval($val);
		$unit = $this->getLengthUnit($class_id);
		
		$val = number_format($val, 2, $decimal_point, $thousand_point) . $unit;

		return $val;
	}
	
	public function getLengthUnit($class_id) {
		static $lengths;
	
		if (empty($lengths[$class_id])) {
			$qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class_description
				WHERE length_class_id = '$class_id'
				AND language_id = '" . $this->language_id. "'"
			);

			if (!empty($qry->row)) {
				$lengths[$class_id] = $qry->row;
			} else {
				$lengths[$class_id] = array('unit' => '');
			}
		}
		
		return $lengths[$class_id]['unit'];
	}
}