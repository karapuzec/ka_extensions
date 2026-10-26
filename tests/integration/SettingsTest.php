<?php

namespace KaExtensionsTests\Integration;

/**
 * ControllerSettings: the settings page of a module (fields from the settings, saving, validation).
 *
 * @group settings
 * @group forms
 */
class SettingsTest extends LibraryTestCase {

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->grantTestPages();
		$this->deleteSettings('ka_lib_test');
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->deleteSettings('ka_lib_test');
	}

	protected function post($post) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		return $this->oc->request(static::MODULE_ROUTE, array(), $post);
	}

	public function testPageShowsDefaultsOfANewModule() {
		$output = $this->oc->request(static::MODULE_ROUTE)['output'];
		$this->assertStringContainsString('Ka Library Test (ver.1.0.0.0)', $output);
		$this->assertMatchesRegularExpression('/name="ka_lib_test_title"[^>]*value="Default title"|value="Default title"[^>]*name="ka_lib_test_title"/', $output);
		$this->assertMatchesRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="1"\s+checked/', $output);
		$this->assertDoesNotMatchRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="3"\s+checked/', $output);
	}

	public function testPageShowsStoredSettings() {
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Stored title');
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_groups', array(3));
		$output = $this->oc->request(static::MODULE_ROUTE)['output'];
		$this->assertStringContainsString('value="Stored title"', $output);
		$this->assertMatchesRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="3"\s+checked/', $output);
		$this->assertDoesNotMatchRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="1"\s+checked/', $output);
	}

	/**
	 * Field templates print values without escaping (OpenCart 3 Twig has autoescape off): a value with
	 * a quote breaks the input and HTML in a value is output as is (common/fields/text.twig, textarea.twig...).
	 *
	 * @group known-bug
	 */
	public function testFieldValuesAreEscaped() {
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Stored "title" <script>alert(1)</script>');
		$output = $this->oc->request(static::MODULE_ROUTE)['output'];
		$this->assertStringNotContainsString('<script>alert(1)</script>', $output);
		$this->assertStringContainsString('value="Stored &quot;title&quot; &lt;script&gt;alert(1)&lt;/script&gt;"', $output);
	}

	public function testSave() {
		$result = $this->post(array(
			'ka_lib_test_title'   => "New 'title'",
			'ka_lib_test_enabled' => '1',
			'ka_lib_test_groups'  => array('2', '3'),
			'ka_lib_test_mode'    => 'b',
		));
		$this->assertStringContainsString('route=marketplace/extension', $result['redirect']);
		$this->assertStringContainsString('type=ka_extensions', $result['redirect']);
		$this->assertSame(array('S'), array_column($this->topMessages(), 'type'));
		$this->assertEquals(array(
			'ka_lib_test_title'   => "New 'title'",
			'ka_lib_test_enabled' => '1',
			'ka_lib_test_groups'  => array('2', '3'),
			'ka_lib_test_mode'    => 'b',
		), $this->getSettings('ka_lib_test'));
	}

	/**
	 * Fixed in 5e83bff: with all boxes unchecked the old list was kept.
	 */
	public function testSaveWithAllBoxesUnchecked() {
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_enabled', '1');
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_groups', array(1, 2));

		$this->post(array('ka_lib_test_title' => 'T', 'ka_lib_test_mode' => 'a'));

		$settings = $this->getSettings('ka_lib_test');
		$this->assertSame(array(), $settings['ka_lib_test_groups']);
		$this->assertSame('', $settings['ka_lib_test_enabled']);

		// the page shows nothing checked (the config of this process still has the old values)
		$this->oc->get('config')->set('ka_lib_test_groups', $settings['ka_lib_test_groups']);
		$this->oc->get('config')->set('ka_lib_test_enabled', $settings['ka_lib_test_enabled']);
		$output = $this->oc->request(static::MODULE_ROUTE)['output'];
		$this->assertDoesNotMatchRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="\d"\s+checked/', $output);
		$this->assertDoesNotMatchRegularExpression('/name="ka_lib_test_enabled"[^>]*checked/', $output);
	}

	public function testRequiredFieldIsValidated() {
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Old');
		$result = $this->post(array('ka_lib_test_title' => '', 'ka_lib_test_groups' => array('3')));
		$this->assertSame('', $result['redirect']);
		$this->assertSame('Old', $this->getSettings('ka_lib_test')['ka_lib_test_title']);
		// the error and the posted values are shown
		$this->assertStringContainsString('<div class="text-danger">Field is empty</div>', $result['output']);
		$this->assertMatchesRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="3"\s+checked/', $result['output']);
		$this->assertDoesNotMatchRegularExpression('/name="ka_lib_test_groups\[\]"\s+value="1"\s+checked/', $result['output']);
	}

	public function testSaveNeedsTheModifyPermission() {
		$group_id = $this->getUserGroupId();
		$permissions = $this->getPermissions($group_id);
		$permissions['modify'] = array_values(array_diff($permissions['modify'], array(static::MODULE_ROUTE)));
		$this->setPermissions($group_id, $permissions);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);

		$result = $this->post(array('ka_lib_test_title' => 'Hacked'));
		$this->assertSame('', $result['redirect']);
		$this->assertArrayNotHasKey('ka_lib_test_title', $this->getSettings('ka_lib_test'));
	}
}
