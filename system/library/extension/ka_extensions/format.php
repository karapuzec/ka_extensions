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
	protected $language_code;

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
		$this->language_code = $language['code'];
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
		
			$qb = new QB();
			$qb->select('*', 'weight_class_description');
			$qb->where('unit', $matches[2]);
			$qry = $qb->query();
	
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
		
			$qb = new QB();
			$qb->select('*', 'length_class_description');
			$qb->where('unit', $matches[2]);
			$qry = $qb->query();

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
			$qb = new QB();
			$qb->select('*', 'weight_class_description');
			$qb->where('weight_class_id', (int)$class_id);
			$qb->where('language_id', (int)$this->language_id);
			$qry = $qb->query();
						
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
			$qb = new QB();
			$qb->select('*', 'length_class_description');
			$qb->where('length_class_id', (int)$class_id);
			$qb->where('language_id', (int)$this->language_id);
			$qry = $qb->query();

			if (!empty($qry->row)) {
				$lengths[$class_id] = $qry->row;
			} else {
				$lengths[$class_id] = array('unit' => '');
			}
		}
		
		return $lengths[$class_id]['unit'];
	}

	/*
		Formats a date or time for display in the current language of this Format instance.

		$value is a Unix timestamp, a date string or a DateTime object. An empty value and
		the zero date 0000-00-00 become an empty string.

		$format is a PHP date() pattern, or one of the OpenCart language keys date_format_long,
		date_format_short, datetime_format and time_format (resolved from the registry language).
		When $format is empty, date_format_short is used.

		When IntlDateFormatter is available, the pattern is converted to ICU and formatted
		in the locale of the language code (ru-ru -> ru_RU, en-gb -> en_GB). Otherwise
		PHP date() is used (weekday and month names stay English).
	*/
	public function formatDateTime($value, $format = null) {

		$timestamp = $this->parseDateTimeValue($value);
		if ($timestamp === false) {
			return '';
		}

		$format = $this->resolveDateTimeFormat($format);

		if ($this->isIntlDateFormatterAvailable()) {
			$formatted = $this->formatDateTimeWithIntl($timestamp, $format);
			if ($formatted !== false) {
				return $formatted;
			}
		}

		return date($format, $timestamp);
	}

	/*
		True when IntlDateFormatter can be used. Tests may override this hook
		to check the date() fallback.
	*/
	protected function isIntlDateFormatterAvailable() {

		return class_exists('\IntlDateFormatter');
	}

	/*
		Unix timestamp of $value, or false when the value is empty or not a date.
	*/
	protected function parseDateTimeValue($value) {

		if ($value instanceof \DateTimeInterface) {
			return $value->getTimestamp();
		}

		if (is_int($value)) {
			return $value;
		}

		if (is_float($value)) {
			return (int)$value;
		}

		if (!is_string($value)) {
			return false;
		}

		$value = trim($value);
		if ($value === '' || strpos($value, '0000-00-00') === 0) {
			return false;
		}

		if (preg_match('/^-?[0-9]+$/', $value) && strlen($value) >= 9) {
			return (int)$value;
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return false;
		}

		return $timestamp;
	}

	/*
		PHP date() pattern for $format. OpenCart language keys are read from the
		registry language. Missing keys fall back to a numeric pattern.
	*/
	protected function resolveDateTimeFormat($format) {

		$keys = [
			'date_format_long',
			'date_format_short',
			'datetime_format',
			'time_format',
		];

		if ($format === null || $format === '') {
			$format = 'date_format_short';
		}

		if (!in_array($format, $keys, true)) {
			return $format;
		}

		$pattern = $this->getRegistryDateFormat($format);
		if ($pattern !== '') {
			return $pattern;
		}

		if ($format == 'time_format') {
			return 'H:i:s';
		}

		if ($format == 'datetime_format') {
			return 'Y-m-d H:i:s';
		}

		return 'Y-m-d';
	}

	/*
		Value of an OpenCart date format key from the current registry language, or
		an empty string when the key is not defined.
	*/
	protected function getRegistryDateFormat($key) {

		if (empty($this->registry)) {
			return '';
		}

		$language = $this->registry->get('language');
		if (empty($language)) {
			return '';
		}

		$text = $language->get($key);
		if ($text === $key || $text === '') {
			return '';
		}

		return $text;
	}

	/*
		Formats $timestamp with IntlDateFormatter in the locale of this instance.
		Returns false when the formatter cannot be created or fails.
	*/
	protected function formatDateTimeWithIntl($timestamp, $format) {

		$locale = $this->getIcuLocale();
		$pattern = $this->convertPhpDateFormatToIcu($format, $timestamp);
		if ($pattern === '') {
			return false;
		}

		try {
			$formatter = new \IntlDateFormatter(
				$locale,
				\IntlDateFormatter::NONE,
				\IntlDateFormatter::NONE,
				date_default_timezone_get(),
				\IntlDateFormatter::GREGORIAN,
				$pattern
			);
		} catch (\Throwable $e) {
			return false;
		}

		if (empty($formatter)) {
			return false;
		}

		$formatted = $formatter->format($timestamp);
		if ($formatted === false) {
			return false;
		}

		return $formatted;
	}

	/*
		ICU locale of the OpenCart language code of this instance (ru-ru -> ru_RU).
	*/
	protected function getIcuLocale() {

		$code = !empty($this->language_code) ? $this->language_code : 'en-gb';
		$code = str_replace('_', '-', $code);
		$parts = explode('-', $code);
		$language = strtolower($parts[0]);
		if (empty($parts[1])) {
			return $language;
		}

		return $language . '_' . strtoupper($parts[1]);
	}

	/*
		Converts a PHP date() pattern to an ICU pattern for $timestamp.
		The S token (English ordinal suffix) is kept only for English locales.
		Unmapped letters are inserted as the date() value of that token, quoted
		as ICU literals. A backslash escapes the next character as a literal.
	*/
	protected function convertPhpDateFormatToIcu($format, $timestamp) {

		$map = [
			'd' => 'dd',
			'j' => 'd',
			'D' => 'EEE',
			'l' => 'EEEE',
			'F' => 'MMMM',
			'M' => 'MMM',
			'm' => 'MM',
			'n' => 'M',
			'Y' => 'yyyy',
			'y' => 'yy',
			'o' => 'YYYY',
			'H' => 'HH',
			'G' => 'H',
			'h' => 'hh',
			'g' => 'h',
			'i' => 'mm',
			's' => 'ss',
			'a' => 'a',
			'A' => 'a',
			'z' => 'D',
			'W' => 'w',
			'e' => 'VV',
			'O' => 'xx',
			'P' => 'xxx',
			'T' => 'z',
			'v' => 'SSS',
		];

		$is_english = $this->isEnglishLocale();
		$icu = '';
		$literals = '';
		$length = strlen($format);

		for ($i = 0; $i < $length; $i++) {
			$char = $format[$i];

			if ($char == '\\' && $i + 1 < $length) {
				$literals .= $format[$i + 1];
				$i++;
				continue;
			}

			if (!preg_match('/[A-Za-z]/', $char)) {
				$literals .= $char;
				continue;
			}

			if ($char == 'S') {
				if ($is_english) {
					$literals .= date('S', $timestamp);
				}
				continue;
			}

			if (!isset($map[$char])) {
				$literals .= date($char, $timestamp);
				continue;
			}

			if ($literals !== '') {
				$icu .= $this->quoteIcuLiteral($literals);
				$literals = '';
			}

			$icu .= $map[$char];
		}

		if ($literals !== '') {
			$icu .= $this->quoteIcuLiteral($literals);
		}

		return $icu;
	}

	/*
		True when the language code of this instance is English (en, en-gb, en-us).
	*/
	protected function isEnglishLocale() {

		$locale = $this->getIcuLocale();
		return (strpos($locale, 'en') === 0);
	}

	/*
		Wraps a literal string for an ICU date pattern.
	*/
	protected function quoteIcuLiteral($text) {

		if ($text === '') {
			return '';
		}

		return "'" . str_replace("'", "''", $text) . "'";
	}
}