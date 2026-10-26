<?php
/*
	Minimal OpenCart 3 classes for the unit tests. They keep only what the tested library code uses.
*/

if (!function_exists('modification')) {
	function modification($file) {
		return $file;
	}
}

class Registry {
	protected $data = array();

	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}

	public function has($key) {
		return isset($this->data[$key]);
	}
}

abstract class Controller {
	protected $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function __set($key, $value) {
		$this->registry->set($key, $value);
	}
}

abstract class Model {
	protected $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function __set($key, $value) {
		$this->registry->set($key, $value);
	}
}

/*
	OpenCart DB: the adaptor is given to the constructor here (OpenCart creates it from the driver name).
*/
class DB {
	protected $adaptor;

	public function __construct($adaptor) {
		$this->adaptor = $adaptor;
	}

	public function query($sql) {
		return $this->adaptor->query($sql);
	}

	public function escape($value) {
		return $this->adaptor->escape($value);
	}

	public function getLastId() {
		return $this->adaptor->getLastId();
	}
}

class Config {
	protected $data = array();

	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}
}

class Language {
	protected $data = array();

	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : $key;
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}
}

class Session {
	public $data = array();
}

class Request {
	public $get = array();
	public $post = array();
	public $server = array('REQUEST_METHOD' => 'GET');
}

// the OpenCart 3.0.5.1 pagination fields used by \extension\ka_extensions\Pagination
class Pagination {
	public $total = 0;
	public $page = 1;
	public $limit = 20;
	public $num_links = 8;
	public $url = '';

	public function render() {
		return 'pagination:' . $this->total . ':' . $this->page . ':' . $this->limit . ':' . $this->url;
	}
}

/*
	A database adaptor recording queries. Results for queries are given with addResult()
	(the first registered pattern found in the SQL wins), other queries return an empty result.
*/
class FakeDbAdaptor {
	public $queries = array();
	public $results = array();
	public $last_id = 0;

	public function query($sql) {
		$this->queries[] = $sql;
		foreach ($this->results as $pattern => $rows) {
			if (strpos($sql, $pattern) !== false) {
				return static::makeResult($rows);
			}
		}
		if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE)/i', $sql)) {
			return static::makeResult(array());
		}
		return true;
	}

	public function addResult($pattern, $rows) {
		$this->results[$pattern] = $rows;
	}

	public static function makeResult($rows) {
		$result = new \stdClass();
		$result->num_rows = count($rows);
		$result->row = isset($rows[0]) ? $rows[0] : array();
		$result->rows = $rows;
		return $result;
	}

	// the same escaping as the OpenCart mysqli driver (mysqli::real_escape_string with utf8)
	public function escape($value) {
		return strtr((string)$value, array(
			"\\"   => "\\\\",
			"\0"   => "\\0",
			"\n"   => "\\n",
			"\r"   => "\\r",
			"'"    => "\\'",
			'"'    => '\\"',
			"\x1a" => "\\Z",
		));
	}

	public function getLastId() {
		return $this->last_id;
	}

	public function lastQuery() {
		return end($this->queries);
	}
}

class Document {
	protected $title = '';
	public $styles = array();

	public function setTitle($title) {
		$this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	public function addStyle($href) {
		$this->styles[] = $href;
	}
}

class Url {
	// 'route' . '?' . query, a query given as array is joined like OpenCart does it
	public function link($route, $args = '', $secure = false) {
		if (is_array($args)) {
			$args = http_build_query($args);
		}
		return $route . ($args !== '' ? '?' . $args : '');
	}

	// added by Ka Extensions: link with an array of url parameters
	public function linka($route, $args = array(), $secure = false) {
		return $this->link($route, http_build_query($args), $secure);
	}
}

class TestRedirectException extends \Exception {
	public $url;

	public function __construct($url) {
		parent::__construct('Redirect to ' . $url);
		$this->url = $url;
	}
}

class Response {
	public $output = '';

	public function setOutput($output) {
		$this->output = $output;
	}

	public function redirect($url, $status = 302) {
		throw new TestRedirectException($url);
	}
}

/*
	Loader: languages are ignored, models are taken from setModel(), views return the template name
	and the data keys.
*/
class Loader {
	public $models = array();
	public $views = array();
	protected $render_disabled = false;

	public function language($route) {
		return array();
	}

	public function model($route) {
	}

	public function setModel($route, $model) {
		$this->models[$route] = $model;
	}

	public function kamodel($route) {
		if (!isset($this->models[$route])) {
			throw new \Exception("Model is not set: $route");
		}
		return $this->models[$route];
	}

	public function view($template, $data = array()) {
		$this->views[] = array('template' => $template, 'data' => $data);
		return 'view:' . $template;
	}

	public function controller($route) {
		return '';
	}

	public function isRenderDisabled() {
		return $this->render_disabled;
	}

	public function disableRender($templates = null) {
		$this->render_disabled = true;
	}

	public function enableRender() {
		$this->render_disabled = false;
	}
}
