<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\UrlParams;

/**
 * UrlParams: page parameters from the URL and building links.
 *
 * @group urlparams
 */
class UrlParamsTest extends UnitTestCase {

	protected function make($get, $defaults, $keep_original = false) {
		$request = new \Request();
		$request->get = $get;
		return new UrlParams($request, $defaults, $keep_original);
	}

	public function testOnlyDeclaredParametersAreTaken() {
		$p = $this->make(array('page' => '2', 'route' => 'x/y', 'evil' => '1'), array('page' => 1, 'sort' => ''));
		$this->assertSame(array('page' => '2'), $p->getSetParams());
		$this->assertSame(array('page' => '2', 'sort' => ''), $p->getParams());
	}

	public function testValuesAreTrimmed() {
		$p = $this->make(array('filter_name' => "  ab c \n"), array('filter_name' => ''));
		$this->assertSame('ab c', $p->getParam('filter_name'));
	}

	public function testKeepOriginal() {
		$p = $this->make(array('filter_name' => ' a '), array('filter_name' => ''), true);
		$this->assertSame(' a ', $p->getParam('filter_name'));
	}

	public function testArrayForAScalarParameterIsIgnored() {
		$p = $this->make(array('filter_name' => array('x')), array('filter_name' => 'def'));
		$this->assertSame(array(), $p->getSetParams());
		$this->assertSame('def', $p->getParam('filter_name'));
	}

	public function testArrayParameter() {
		$p = $this->make(array('ids' => array(' 1 ', 'a' => array(' 2')), 'one' => ' 3 '), array('ids' => array(), 'one' => array()));
		$this->assertSame(array(0 => '1', 'a' => array('2')), $p->getParam('ids'));
		$this->assertSame('3', $p->getParam('one'));
	}

	public function testGetParamDefaultsAndUnknown() {
		$p = $this->make(array(), array('order' => 'ASC'));
		$this->assertSame('ASC', $p->getParam('order'));
		$this->assertNull($p->getParam('unknown'));
	}

	public function testUpdateParams() {
		$p = $this->make(array('page' => '2'), array('page' => 1, 'sort' => ''));
		$p->updateParams(array('sort' => 'name'));
		$this->assertSame(array('page' => '2', 'sort' => 'name'), $p->getSetParams());
	}

	public function testGetUrlHasOnlyUrlParameters() {
		$p = $this->make(array('page' => '2'), array('page' => 1, 'sort' => 'name', 'order' => 'ASC'));
		$this->assertSame('page=2', $p->getUrl());
	}

	public function testGetUrlOverridesAndRemoves() {
		$p = $this->make(array('page' => '2', 'sort' => 'name'), array('page' => 1, 'sort' => ''));
		$this->assertSame('page=3&sort=name', $p->getUrl(array('page' => 3)));
		$this->assertSame('sort=name', $p->getUrl(array('page' => null)));
		$this->assertSame('page=2&sort=name&product_id=5', $p->getUrl(array('product_id' => 5)));
	}

	public function testValuesAreEncoded() {
		$p = $this->make(array('filter_email' => 'a+b@x.com', 'filter_name' => 'a&b=c d"<'), array('filter_email' => '', 'filter_name' => ''));
		$url = $p->getUrl();
		$this->assertSame('filter_email=a%2Bb%40x.com&filter_name=a%26b%3Dc%20d%22%3C', $url);
		parse_str($url, $decoded);
		$this->assertSame(array('filter_email' => 'a+b@x.com', 'filter_name' => 'a&b=c d"<'), $decoded);
	}

	public function testPlaceholderIsKept() {
		$p = $this->make(array('sort' => 'name'), array('sort' => '', 'page' => 1));
		$this->assertSame('sort=name&page={page}', $p->getUrl(array('page' => '{page}')));
		// only a {word} is a placeholder
		$this->assertSame('sort=%7Bpage%7D%27', $p->getUrl(array('sort' => "{page}'")));
	}

	public function testArrayValuesInTheUrl() {
		$p = $this->make(array('ids' => array('1', '2')), array('ids' => array(), 'empty' => array()));
		$this->assertSame('ids%5B0%5D=1&ids%5B1%5D=2', $p->getUrl());
		$this->assertSame('ids%5B0%5D=1&ids%5B1%5D=2', $p->getUrl(array('empty' => array())));
	}

	public function testSortParamsToggleTheOrder() {
		$p = $this->make(array('sort' => 't.name', 'order' => 'ASC'), array('sort' => '', 'order' => 'ASC'));
		$this->assertSame(array('sort' => 't.name', 'order' => 'DESC'), $p->getUrlSortParams('t.name'));

		$p = $this->make(array('sort' => 't.name', 'order' => 'DESC'), array('sort' => '', 'order' => 'ASC'));
		$this->assertSame(array('sort' => 't.name', 'order' => 'ASC'), $p->getUrlSortParams('t.name'));
	}

	public function testSortParamsOfTheDefaultOrder() {
		// the order is not in the URL: the default order (ASC) is current, the link gives DESC
		$p = $this->make(array('sort' => 't.name'), array('sort' => '', 'order' => 'ASC'));
		$this->assertSame(array('sort' => 't.name', 'order' => 'DESC'), $p->getUrlSortParams('t.name'));
	}

	public function testSortParamsOfAnotherColumn() {
		$p = $this->make(array('sort' => 't.name', 'order' => 'DESC', 'page' => '3'), array('sort' => '', 'order' => 'ASC', 'page' => 1));
		$this->assertSame(array('sort' => 't.email', 'order' => 'DESC', 'page' => '3'), $p->getUrlSortParams('t.email'));
		$this->assertSame('sort=t.email&order=DESC&page=3', $p->getUrlSort('t.email'));
	}
}
