<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	Url keywords and file names from any text: "Как выбрать ноутбук" -> "kak-vybrat-noutbuk".

	A thin wrapper of jbroadway/urlify (vendor/, with voku/portable-ascii)
	with the API of the KaURLify class of CSV Product Import and of the OpenCart 4 library.

	$language is a language code: 'ru', 'ru-ru', 'en-gb' (only the part before '-' is used).
	The language gives its own transliteration rules, e.g. 'Größe' is 'groesse' for 'de' and
	'grosse' for 'en'; Russian text needs 'ru' ('щука' is 'schuka', 'shhuka' with 'en').
*/
class KaURLify {

	const URL_NATIONAL = 'national';
	const URL_FILE     = 'file';
	const URL_REGULAR  = 'regular';

	// portable-ascii has only the letters specific to these languages ('uk': і, ї, є, ґ...), the
	// other letters are converted by the rules of the fallback language ('ж' is 'z' without it)
	//
	protected static $fallback_languages = array(
		'uk' => 'ru',
	);

	protected static $is_loader_registered = false;

	// words added by remove_words(): language => words
	//
	protected static $remove_words = array();


	/**
		Converts a text to a url keyword: latin letters, digits and '-' in lowercase.

		$length   - maximum length of the result
		$language - language code, the default is English
		$url_type - URL_REGULAR, URL_FILE (keeps '.') or URL_NATIONAL (keeps letters of any language)
	*/
	public static function filter($text, $length = 90, $language = '', $url_type = self::URL_REGULAR) {

		$text = (string)$text;

		if ($url_type == static::URL_NATIONAL) {
			$text = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $text), '-');
			return trim(mb_substr(mb_strtolower($text, 'UTF-8'), 0, (int)$length, 'UTF-8'), '-');
		}

		static::registerLoader();

		$language = static::getLanguageCode($language);
		$text     = static::applyFallbackLanguage($text, $language);

		return \URLify::filter($text, (int)$length, $language, $url_type == static::URL_FILE);
	}


	/**
		Transliterates a text to ASCII characters (no other changes).
	*/
	public static function downcode($text, $language = '') {

		static::registerLoader();

		$language = static::getLanguageCode($language);
		$text     = static::applyFallbackLanguage((string)$text, $language);

		return \URLify::downcode($text, $language);
	}


	public static function transliterate($text, $language = '') {
		return static::downcode($text, $language);
	}


	/**
		Adds characters to the transliteration: array of character => replacement.
	*/
	public static function add_chars($map) {
		static::registerLoader();
		\URLify::add_chars($map);
	}


	/**
		Adds words removed by filter() of the language.
	*/
	public static function remove_words($words, $language = 'en') {

		static::registerLoader();

		$language = static::getLanguageCode($language);
		$words    = is_array($words) ? $words : array($words);

		static::$remove_words[$language] = array_merge(static::$remove_words[$language] ?? array(), $words);

		// without merging: the stop words of urlify (voku/stop-words) are not bundled
		//
		\URLify::remove_words(static::$remove_words[$language], $language, false);
	}


	/**
		'ru-ru' -> 'ru', '' -> 'en'.
	*/
	protected static function getLanguageCode($language) {

		$language = strtolower(trim((string)$language));
		$language = preg_replace('/[-_].*$/', '', $language);

		return $language === '' ? 'en' : $language;
	}


	/**
		Converts the letters of a language with the fallback language by its own table and returns
		the text, $language becomes the fallback language.
	*/
	protected static function applyFallbackLanguage($text, &$language) {

		if (empty(static::$fallback_languages[$language])) {
			return $text;
		}

		$chars = \voku\helper\ASCII::charsArrayWithOneLanguage($language);
		$text  = str_replace($chars['orig'], $chars['replace'], $text);

		$language = static::$fallback_languages[$language];

		return $text;
	}


	/**
		Loads the classes of vendor/ (URLify and voku\helper\*) without composer.
	*/
	protected static function registerLoader() {

		if (static::$is_loader_registered) {
			return;
		}
		static::$is_loader_registered = true;

		$dir = __DIR__ . '/vendor/';

		spl_autoload_register(function ($class) use ($dir) {

			if ($class == 'URLify') {
				$files = array($dir . 'jbroadway/urlify/URLify.php');
			} elseif (strpos($class, 'voku\\helper\\') === 0) {
				$path  = str_replace('\\', '/', $class) . '.php';
				$files = array(
					$dir . 'voku/portable-ascii/src/' . $path,
				);
			} else {
				return;
			}

			foreach ($files as $file) {
				if (is_file($file)) {
					include_once($file);
					return;
				}
			}
		});
	}
}
