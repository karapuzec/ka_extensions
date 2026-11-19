<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\Format;

/*
	formatDateTime() of the Format class: IntlDateFormatter when it is available,
	otherwise PHP date().
*/
class FormatWithoutIntl extends Format {

	protected function isIntlDateFormatterAvailable() {
		return false;
	}
}

class FormatDateTimeTest extends UnitTestCase {

	protected $timestamp;

	protected function setUp(): void {
		parent::setUp();
		date_default_timezone_set('Europe/Amsterdam');
		$this->timestamp = strtotime('2026-11-18 12:00:00');
		$this->registry->get('language')->set('date_format_long', 'l d F Y');
		$this->registry->get('language')->set('date_format_short', 'd/m/Y');
		$this->registry->get('language')->set('datetime_format', 'd/m/Y H:i:s');
		$this->registry->get('language')->set('time_format', 'H:i:s');
	}

	protected function formatForLanguage($code) {
		$this->registry->get('config')->set('config_language', $code);
		return new Format();
	}

	public function testRussianLongDateUsesIntlMonthAndWeekdayNames() {
		if (!class_exists('\IntlDateFormatter')) {
			$this->markTestSkipped('ext-intl is not available');
		}

		$format = $this->formatForLanguage('ru-ru');
		$this->assertSame(
			'среда 18 ноября 2026',
			$format->formatDateTime($this->timestamp, 'l d F Y')
		);
	}

	public function testEnglishLongDateKeepsTheOrdinalSuffix() {
		if (!class_exists('\IntlDateFormatter')) {
			$this->markTestSkipped('ext-intl is not available');
		}

		$format = $this->formatForLanguage('en-gb');
		$this->assertSame(
			'Wednesday 18th November 2026',
			$format->formatDateTime($this->timestamp, 'l dS F Y')
		);
	}

	public function testNumericFormatsStayUnchanged() {
		if (!class_exists('\IntlDateFormatter')) {
			$this->markTestSkipped('ext-intl is not available');
		}

		$format = $this->formatForLanguage('ru-ru');
		$this->assertSame('18.11.2026', $format->formatDateTime($this->timestamp, 'd.m.Y'));
		$this->assertSame('18/11/2026', $format->formatDateTime($this->timestamp, 'd/m/Y'));
	}

	public function testEmptyAndZeroDatesReturnAnEmptyString() {
		$format = $this->formatForLanguage('en-gb');
		$this->assertSame('', $format->formatDateTime(''));
		$this->assertSame('', $format->formatDateTime(null));
		$this->assertSame('', $format->formatDateTime('0000-00-00'));
		$this->assertSame('', $format->formatDateTime('0000-00-00 00:00:00'));
	}

	public function testLanguageKeysAreResolvedFromTheRegistryLanguage() {
		if (!class_exists('\IntlDateFormatter')) {
			$this->markTestSkipped('ext-intl is not available');
		}

		$format = $this->formatForLanguage('ru-ru');
		$this->registry->get('language')->set('date_format_long', 'l d F Y');
		$this->registry->get('language')->set('date_format_short', 'd.m.Y');
		$this->assertSame('среда 18 ноября 2026', $format->formatDateTime($this->timestamp, 'date_format_long'));
		$this->assertSame('18.11.2026', $format->formatDateTime($this->timestamp, 'date_format_short'));
		$this->assertSame('18.11.2026', $format->formatDateTime($this->timestamp, null));
	}

	public function testDateTimeObjectAndDateStringAreAccepted() {
		if (!class_exists('\IntlDateFormatter')) {
			$this->markTestSkipped('ext-intl is not available');
		}

		$format = $this->formatForLanguage('en-gb');
		$date = new \DateTime('2026-11-18 12:00:00', new \DateTimeZone('Europe/Amsterdam'));
		$this->assertSame('18/11/2026', $format->formatDateTime($date, 'd/m/Y'));
		$this->assertSame('18/11/2026', $format->formatDateTime('2026-11-18 12:00:00', 'd/m/Y'));
	}

	public function testFallbackWithoutIntlUsesPhpDate() {
		$format = new FormatWithoutIntl();
		$this->assertSame(
			'Wednesday 18 November 2026',
			$format->formatDateTime($this->timestamp, 'l d F Y')
		);
		$this->assertSame(
			'Wednesday 18th November 2026',
			$format->formatDateTime($this->timestamp, 'l dS F Y')
		);
		$this->assertSame('18.11.2026', $format->formatDateTime($this->timestamp, 'd.m.Y'));
		$this->assertSame('', $format->formatDateTime('0000-00-00'));
	}

	public function testExistingFormatDateParserIsUnchanged() {
		$format = $this->formatForLanguage('en-gb');
		$date = '18.11.2026';
		$this->assertTrue($format->formatDate($date));
		$this->assertSame('2026-11-18', $date);
	}
}
