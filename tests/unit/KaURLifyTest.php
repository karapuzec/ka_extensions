<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\KaURLify;

/**
 * KaURLify: url keywords by jbroadway/urlify from vendor/ (loaded without composer).
 *
 * @group helpers
 */
class KaURLifyTest extends UnitTestCase {

	public function testTheLibraryIsLoadedFromVendor() {
		$this->assertSame('privet-mir', KaURLify::filter('Привет, мир!', 90, 'ru'));
		$file = (new \ReflectionClass('URLify'))->getFileName();
		$this->assertStringContainsString('/ka_extensions/vendor/jbroadway/urlify/', str_replace('\\', '/', $file));
	}

	public function testLanguageRules() {
		$this->assertSame('schuka-tsaplya-yozhik-yogurt', KaURLify::filter('Щука, Цапля, Ёжик, йогурт', 90, 'ru-ru'));
		$this->assertSame('groesse-aepfel', KaURLify::filter('Größe Äpfel', 90, 'de'));
		$this->assertSame('grosse-and-co', KaURLify::filter('Größe & Co', 90, ''));
		$this->assertSame('shlvm', KaURLify::filter('שלום', 90, 'he'));
	}

	/**
	 * The CJK and Hangul tables of portable-ascii are not bundled (vendor/README.md): Chinese,
	 * Japanese and Korean characters are dropped, a title of them only gives an empty string
	 * (the caller adds a fallback such as 'article-N').
	 */
	public function testCjkIsDropped() {
		$this->assertSame('', KaURLify::filter('中文标题', 90, 'zh'));
		$this->assertSame('', KaURLify::filter('한국어', 90, 'ko'));
		$this->assertSame('news-2026', KaURLify::filter('News 中文 2026', 90, 'en'));
	}

	/**
	 * Ukrainian letters by the Ukrainian table, the common Cyrillic ones by the Russian rules
	 * ('ж' is not in the Ukrainian table of portable-ascii).
	 */
	public function testUkrainianFallsBackToRussian() {
		$this->assertSame('yizhak-i-ganok-ye-zhuk', KaURLify::filter('Їжак і Ґанок є, жук', 90, 'uk-ua'));
	}

	public function testUrlTypesAndLength() {
		$this->assertSame('photo-1.jpg', KaURLify::filter('Photo 1.jpg', 90, 'en', KaURLify::URL_FILE));
		$this->assertSame('фото-1-jpg', KaURLify::filter('Фото 1.jpg', 90, 'ru', KaURLify::URL_NATIONAL));
		$this->assertLessThanOrEqual(10, strlen(KaURLify::filter('word word word word', 10)));
		$this->assertSame('', KaURLify::filter('!!! ???', 90, 'en'));
		$this->assertSame('', KaURLify::filter('', 90, 'en'));
	}

	/**
	 * remove_words() works without voku/stop-words (not bundled). filter() does not remove words
	 * (urlify removes them only on request), as the old KaURLify with its empty word list.
	 */
	public function testRemoveWordsWithoutStopWordsPackage() {
		KaURLify::remove_words('foo', 'en');
		KaURLify::remove_words(array('bar'), 'en-gb');
		$this->assertSame('foo-bar-baz', KaURLify::filter('Foo bar baz', 90, 'en'));
		$this->assertFalse(class_exists('voku\\helper\\StopWords'));
	}

	public function testDowncode() {
		$this->assertSame('Privet', KaURLify::downcode('Привет', 'ru'));
	}
}
