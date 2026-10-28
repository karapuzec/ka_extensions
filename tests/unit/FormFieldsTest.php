<?php

namespace KaExtensionsTests\Unit;

/*
	A class using the form trait the way ControllerForm does, with stub services as properties.
*/
class FormHost {
	use \extension\ka_extensions\TraitControllerForm;

	public $config;
	public $language;
	public $currency;
	public $load;
	public $model_tool_image;
	public $model_localisation_language;
}

class FakeCurrency {
	public function formatValue($value, $currency) {
		return 'formatted:' . $value . ':' . $currency;
	}
}

class FakeImageModel {
	public function resize($file, $width, $height) {
		return 'thumb:' . $file . ':' . $width . 'x' . $height;
	}
}

class FakeLanguageModel {
	public function getLanguages() {
		return array(
			'en-gb' => array('language_id' => 1, 'code' => 'en-gb'),
			'ru-ru' => array('language_id' => 2, 'code' => 'ru-ru'),
		);
	}
}

/**
 * TraitControllerForm: field values from the record and from POST, validation.
 *
 * @group forms
 */
class FormFieldsTest extends UnitTestCase {

	/** @var FormHost */
	protected $form;

	protected function setUp(): void {
		parent::setUp();
		$this->form = new FormHost();
		$this->form->config   = $this->registry->get('config');
		$this->form->language = $this->registry->get('language');
		$this->form->load     = $this->registry->get('load');
		$this->form->currency = new FakeCurrency();
		$this->form->model_tool_image = new FakeImageModel();
		$this->form->model_localisation_language = new FakeLanguageModel();
		$this->form->language->set('error_field_is_empty', 'Field %s is empty');
	}

	protected function field($k, $f, $old_data, $new_data = null) {
		return $this->call($this->form, 'getFieldWithData', $k, $f, $old_data, $new_data);
	}

	// --- values without POST (the form is opened)

	public function testNotPostedTakesTheRecordValue() {
		$f = $this->field('name', array('type' => 'text'), array('name' => 'Old'));
		$this->assertSame('Old', $f['value']);
		$this->assertSame('name', $f['code']);
	}

	public function testNotPostedKeepsZeroOfTheRecord() {
		$f = $this->field('status', array('type' => 'checkbox', 'default_value' => 1), array('status' => '0'));
		$this->assertSame('0', $f['value']);
	}

	public function testNotPostedUsesTheDefaultValueForANewRecord() {
		$f = $this->field('status', array('type' => 'checkbox', 'default_value' => 1), array());
		$this->assertSame(1, $f['value']);
	}

	public function testNotPostedWithoutDefaultIsEmptyString() {
		$f = $this->field('name', array('type' => 'text'), array());
		$this->assertSame('', $f['value']);
	}

	public function testNotPostedCheckboxesTakeTheRecordList() {
		$f = $this->field('groups', array('type' => 'checkboxes'), array('groups' => array(1, 3)));
		$this->assertSame(array(1, 3), $f['value']);
	}

	public function testFieldCodeOverridesTheArrayKey() {
		$f = $this->field('first', array('code' => 'name', 'type' => 'text'), array('name' => 'By code', 'first' => 'By key'));
		$this->assertSame('By code', $f['value']);
		$this->assertSame('name', $f['code']);
	}

	// --- values from POST (the form is submitted)

	public function testPostedValueWins() {
		$f = $this->field('name', array('type' => 'text'), array('name' => 'Old'), array('name' => 'New'));
		$this->assertSame('New', $f['value']);
	}

	public function testPostedEmptyStringWins() {
		$f = $this->field('name', array('type' => 'text'), array('name' => 'Old'), array('name' => ''));
		$this->assertSame('', $f['value']);
	}

	public function testNotPostedTextFieldKeepsTheRecordValue() {
		$f = $this->field('name', array('type' => 'text'), array('name' => 'Old'), array('other' => 'x'));
		$this->assertSame('Old', $f['value']);
	}

	public function testFieldWithoutTypeKeepsTheRecordValue() {
		$f = $this->field('name', array(), array('name' => 'Old'), array());
		$this->assertSame('Old', $f['value']);
	}

	public function testUncheckedCheckboxIsEmpty() {
		$f = $this->field('status', array('type' => 'checkbox'), array('status' => '1'), array());
		$this->assertSame('', $f['value']);
	}

	public function testCheckedCheckbox() {
		$f = $this->field('status', array('type' => 'checkbox'), array('status' => '0'), array('status' => '1'));
		$this->assertSame('1', $f['value']);
	}

	/**
	 * Fixed in 5e83bff: the old value was kept when all boxes were unchecked.
	 */
	public function testAllUncheckedCheckboxesGiveAnEmptyList() {
		$f = $this->field('groups', array('type' => 'checkboxes'), array('groups' => array(1, 2)), array('name' => 'x'));
		$this->assertSame(array(), $f['value']);
	}

	public function testAllUncheckedCheckboxesInAnEmptyPost() {
		$f = $this->field('groups', array('type' => 'checkboxes'), array('groups' => array(1, 2)), array());
		$this->assertSame(array(), $f['value']);
	}

	public function testPostedCheckboxesReplaceTheRecordList() {
		$f = $this->field('groups', array('type' => 'checkboxes'), array('groups' => array(1, 2)), array('groups' => array('3')));
		$this->assertSame(array('3'), $f['value']);
	}

	public function testNotPostedSelectKeepsTheRecordValue() {
		$f = $this->field('mode', array('type' => 'select', 'options' => array('a' => 'A', 'b' => 'B')), array('mode' => 'b'), array());
		$this->assertSame('b', $f['value']);
	}

	public function testPostedSelect() {
		$f = $this->field('mode', array('type' => 'select'), array('mode' => 'b'), array('mode' => 'a'));
		$this->assertSame('a', $f['value']);
	}

	// --- types and formats

	public function testDateIsCutToTheDate() {
		$f = $this->field('date_added', array('type' => 'date'), array('date_added' => '2026-10-26 12:34:56'));
		$this->assertSame('2026-10-26', $f['value']);
	}

	public function testPriceFormat() {
		$this->form->config->set('config_currency', 'USD');
		$f = $this->field('price', array('format' => 'price'), array('price' => '12.5'));
		$this->assertSame('formatted:12.5:USD', $f['value']);
	}

	public function testEmptyPriceIsNotFormatted() {
		$f = $this->field('price', array('format' => 'price'), array('price' => ''));
		$this->assertSame('', $f['value']);
	}

	public function testMissingImageGivesNoImageThumb() {
		$f = $this->field('image', array('type' => 'image'), array('image' => 'catalog/not-existing-' . uniqid() . '.png'));
		$this->assertSame('', $f['value']);
		$this->assertSame('thumb:no_image.png:200x200', $f['thumb']);
		$this->assertSame('no_image.png', $f['default_value']);
	}

	public function testExistingImageGetsAThumb() {
		$dir = DIR_IMAGE . 'catalog/';
		@mkdir($dir, 0777, true);
		$file = 'catalog/unit-' . uniqid() . '.png';
		file_put_contents(DIR_IMAGE . $file, 'x');
		try {
			$f = $this->field('image', array('type' => 'image'), array('image' => $file));
			$this->assertSame($file, $f['value']);
			$this->assertSame('thumb:' . $file . ':200x200', $f['thumb']);
		} finally {
			unlink(DIR_IMAGE . $file);
		}
	}

	// --- field lists

	public function testInitFieldsSetsCodeAndValue() {
		$fields = $this->call($this->form, 'initFields', array(
			'name'  => array('type' => 'text'),
			'other' => array('code' => 'real', 'value' => 'v'),
		));
		$this->assertSame('name', $fields['name']['code']);
		$this->assertSame('', $fields['name']['value']);
		$this->assertSame('real', $fields['other']['code']);
		$this->assertSame('v', $fields['other']['value']);
	}

	/**
	 * initFields() returns an empty array for no fields (it returned null, ControllerForm::validate() then
	 * called foreach on null).
	 */
	public function testInitFieldsOfNoFieldsGivesAnArray() {
		$this->assertSame(array(), $this->call($this->form, 'initFields', array()));
	}

	public function testGetFieldsWithDataAddsErrors() {
		$fields = $this->call($this->form, 'getFieldsWithData',
			array('name' => array('type' => 'text'), 'email' => array('type' => 'text')),
			array('name' => 'Old', 'email' => 'a@b.c'),
			array('name' => ''),
			array('name' => 'Name is empty')
		);
		$this->assertSame('', $fields['name']['value']);
		$this->assertSame('Name is empty', $fields['name']['error']);
		$this->assertSame('a@b.c', $fields['email']['value']);
		$this->assertArrayNotHasKey('error', $fields['email']);
	}

	public function testGetFieldValuesSkipsPlainText() {
		$values = $this->call($this->form, 'getFieldValues', array(
			'name' => array('code' => 'name', 'value' => 'N'),
			'info' => array('code' => 'info', 'type' => 'plaintext', 'value' => 'text'),
			'k'    => array('code' => 'real_code', 'value' => 'R'),
		));
		$this->assertSame(array('name' => 'N', 'real_code' => 'R'), $values);
	}

	// --- validation

	protected function validate($code, $field, $post) {
		$result = $this->call($this->form, 'validateField', $code, $field, $post);
		return array($result, $this->getProperty($this->form, 'errors'));
	}

	public function testRequiredEmptyFieldUsesTheTitle() {
		list($result, $errors) = $this->validate('name', array('type' => 'text', 'required' => true, 'title' => 'Name'), array('name' => ''));
		$this->assertFalse($result);
		$this->assertSame('Field Name is empty', $errors['name']);
	}

	public function testRequiredEmptyFieldUsesTheLanguageTitle() {
		$this->form->language->set('txt_title_name', 'Lang name');
		list($result, $errors) = $this->validate('name', array('type' => 'text', 'required' => true), array('name' => ''));
		$this->assertFalse($result);
		$this->assertSame('Field Lang name is empty', $errors['name']);
	}

	/**
	 * A value of spaces is empty for a required field (the trim() check was reached only when empty()
	 * was true).
	 */
	public function testRequiredWhitespaceIsEmpty() {
		list($result, $errors) = $this->validate('name', array('type' => 'text', 'required' => true, 'title' => 'Name'), array('name' => "  \t"));
		$this->assertFalse($result);
		$this->assertArrayHasKey('name', $errors);
	}

	/**
	 * 'max_length' of a text or textarea field: a longer value is an error with the limit in the
	 * message, the length is counted in characters (UTF-8), not bytes.
	 */
	public function testMaxLength() {
		$this->form->language->set('error_field_too_long', 'Max %s');

		list($result, $errors) = $this->validate('name', array('type' => 'text', 'max_length' => 5), array('name' => 'abcdef'));
		$this->assertFalse($result);
		$this->assertSame('Max 5', $errors['name']);

		$this->setProperty($this->form, 'errors', array());
		list($result, $errors) = $this->validate('name', array('type' => 'text', 'max_length' => 5), array('name' => 'абвгд'));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);

		list($result, $errors) = $this->validate('descr', array('type' => 'textarea', 'max_length' => 3), array('descr' => 'abcd'));
		$this->assertFalse($result);
		$this->assertSame('Max 3', $errors['descr']);
	}

	public function testRequiredMissingField() {
		list($result, $errors) = $this->validate('name', array('required' => true, 'title' => 'Name'), array());
		$this->assertFalse($result);
		$this->assertSame('Field Name is empty', $errors['name']);
	}

	public function testRequiredZeroIsAValue() {
		list($result, $errors) = $this->validate('qty', array('type' => 'number', 'required' => true), array('qty' => '0'));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);
	}

	public function testNotPostedTextFieldIsAnError() {
		list($result, $errors) = $this->validate('name', array('type' => 'text'), array());
		$this->assertFalse($result);
		$this->assertArrayHasKey('name', $errors);
	}

	public function testNotPostedCheckboxIsNotAnError() {
		list($result, $errors) = $this->validate('status', array('type' => 'checkbox'), array());
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);
	}

	public function testNumberLimits() {
		$field = array('type' => 'number', 'min_value' => 1, 'max_value' => 10);

		// a value out of the limits fails validation (validateField() returned true before 4.1.1.29)
		list($result, $errors) = $this->validate('qty', $field, array('qty' => '0'));
		$this->assertFalse($result);
		$this->assertSame('Minimum value is 1', $errors['qty']);

		$this->setProperty($this->form, 'errors', array());
		list($result, $errors) = $this->validate('qty', $field, array('qty' => '11'));
		$this->assertFalse($result);
		$this->assertSame('Maximum value is 10', $errors['qty']);

		$this->setProperty($this->form, 'errors', array());
		list($result, $errors) = $this->validate('qty', $field, array('qty' => '5'));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);
	}

	/**
	 * An empty value of a non-required number field is not checked against the limits (a minimum above
	 * zero gave the error 'Minimum value is ...' before 4.1.1.29). A required field is still empty.
	 */
	public function testEmptyOptionalNumberHasNoLimits() {
		$field = array('type' => 'number', 'min_value' => 1, 'max_value' => 10);
		foreach (array('', '  ') as $value) {
			$this->setProperty($this->form, 'errors', array());
			list($result, $errors) = $this->validate('qty', $field, array('qty' => $value));
			$this->assertTrue($result);
			$this->assertSame(array(), $errors);
		}

		$this->setProperty($this->form, 'errors', array());
		list($result, $errors) = $this->validate('qty', $field + array('required' => true, 'title' => 'Qty'), array('qty' => ''));
		$this->assertFalse($result);
		$this->assertSame('Field Qty is empty', $errors['qty']);
	}

	/**
	 * A child class checking more after parent::validateField() gets false for a value out of the limits
	 * and keeps the limit error.
	 */
	public function testMinimumErrorStopsTheValidation() {
		$field = array('type' => 'number', 'min_value' => 5, 'max_value' => 3);
		list($result, $errors) = $this->validate('qty', $field, array('qty' => '4'));
		$this->assertFalse($result);
		$this->assertSame('Minimum value is 5', $errors['qty']);
	}

	/**
	 * A non-numeric value of a number field with limits is an error (it passed: 'abc' < 1 is false in PHP 8).
	 */
	public function testNonNumericValueOfANumberField() {
		list($result, $errors) = $this->validate('qty', array('type' => 'number', 'min_value' => 1), array('qty' => 'abc'));
		$this->assertFalse($result);
		$this->assertSame('The value must be a number', $errors['qty']);

		$this->setProperty($this->form, 'errors', array());
		list(, $errors) = $this->validate('qty', array('type' => 'number', 'max_value' => 10), array('qty' => '1e9x'));
		$this->assertArrayHasKey('qty', $errors);

		// numeric values and an empty optional value are checked as before
		$this->setProperty($this->form, 'errors', array());
		list($result, $errors) = $this->validate('qty', array('type' => 'number', 'max_value' => 10), array('qty' => ''));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);

		list($result, $errors) = $this->validate('qty', array('type' => 'number', 'min_value' => 1), array('qty' => '2.5'));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);
	}

	/**
	 * A required 'checkboxes' field posted as an empty array is empty (trim() was called on the array,
	 * a TypeError in PHP 8).
	 */
	public function testRequiredCheckboxesPostedEmpty() {
		list($result, $errors) = $this->validate('groups', array('type' => 'checkboxes', 'required' => true, 'title' => 'Groups'), array('groups' => array()));
		$this->assertFalse($result);
		$this->assertSame('Field Groups is empty', $errors['groups']);
	}

	public function testRequiredCheckboxesWithValues() {
		list($result, $errors) = $this->validate('groups', array('type' => 'checkboxes', 'required' => true), array('groups' => array('0')));
		$this->assertTrue($result);
		$this->assertSame(array(), $errors);
	}

	public function testValidateFields() {
		$fields = array(
			'name'  => array('type' => 'text', 'required' => true, 'title' => 'Name'),
			'email' => array('type' => 'text'),
		);
		$this->assertFalse($this->call($this->form, 'validateFields', $fields, array('name' => '', 'email' => 'x')));
		$this->assertSame(array('name'), array_keys($this->getProperty($this->form, 'errors')));

		$this->setProperty($this->form, 'errors', array());
		$this->assertTrue($this->call($this->form, 'validateFields', $fields, array('name' => 'N', 'email' => '')));
	}

	public function testValidateLanguageFieldsKeepsOtherErrors() {
		$this->setProperty($this->form, 'errors', array('name' => 'Common error'));
		$lang_fields = array(
			1 => array('title' => array('type' => 'text', 'required' => true, 'title' => 'Title')),
			2 => array('title' => array('type' => 'text', 'required' => true, 'title' => 'Title')),
		);
		$post = array('description' => array(1 => array('title' => 'T'), 2 => array('title' => '')));

		$this->assertFalse($this->call($this->form, 'validateLanguageFields', 'description', $lang_fields, $post));
		$errors = $this->getProperty($this->form, 'errors');
		$this->assertSame('Common error', $errors['name']);
		$this->assertSame(array(2), array_keys($errors['description']));
		$this->assertSame('Field Title is empty', $errors['description'][2]['title']);
	}

	public function testValidateLanguageFieldsWithoutErrors() {
		$lang_fields = array(1 => array('title' => array('type' => 'text', 'required' => true)));
		$post = array('description' => array(1 => array('title' => 'T')));
		$this->assertTrue($this->call($this->form, 'validateLanguageFields', 'description', $lang_fields, $post));
		$this->assertSame(array(), $this->getProperty($this->form, 'errors'));
	}

	public function testLanguageFieldsWithData() {
		$lang_fields = array(
			1 => array('title' => array('type' => 'text'), 'tags' => array('type' => 'checkboxes')),
			2 => array('title' => array('type' => 'text'), 'tags' => array('type' => 'checkboxes')),
		);
		$old = array(1 => array('title' => 'Old 1', 'tags' => array('a')), 2 => array('title' => 'Old 2', 'tags' => array('b')));

		// the form is opened
		$fields = $this->call($this->form, 'getLanguageFieldsWithData', 'description', $lang_fields, $old);
		$this->assertSame('Old 1', $fields[1]['title']['value']);
		$this->assertSame(array('b'), $fields[2]['tags']['value']);

		// the form is submitted: language 1 posted, nothing for language 2; errors are copied
		$post = array('description' => array(1 => array('title' => 'New 1', 'tags' => array('c'))));
		$errors = array('description' => array(1 => array('title' => 'Too long')));
		$fields = $this->call($this->form, 'getLanguageFieldsWithData', 'description', $lang_fields, $old, $post, $errors);
		$this->assertSame('New 1', $fields[1]['title']['value']);
		$this->assertSame(array('c'), $fields[1]['tags']['value']);
		$this->assertSame('Too long', $fields[1]['title']['error']);
		$this->assertSame('Old 2', $fields[2]['title']['value']);
		$this->assertSame(array(), $fields[2]['tags']['value']);
	}

	public function testCopyByLanguages() {
		$lang_fields = $this->call($this->form, 'copyByLanguages',
			array('title' => array('type' => 'text')),
			'description[#language_id#][#code#]'
		);
		$this->assertSame(array(1, 2), array_keys($lang_fields));
		$this->assertSame('description[2][title]', $lang_fields[2]['title']['name']);
		$this->assertSame('title', $lang_fields[2]['title']['code']);
	}
}
