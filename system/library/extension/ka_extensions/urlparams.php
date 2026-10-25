<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
	
namespace extension\ka_extensions;

/**
	This is a service class for storing url parameters and building URLs

	// define available page parameters
	//
		$params = array(
			'path'    => '',
			'letters' => '', // letter
		'sort'    => 'name',
			'order'   => 'ASC', 
			'page'    => '1',
		);
	$url_params = new UrlParams($this->request, $params);
	$params = $url_params->getParams();
	if (strlen($params['letters']) > 1) {
  		$params['subletter'] = substr($params['letters'], 1, 1);
  	}

	
  	...
  	'href' => $this->url->link('product/category', $url_params->getUrl(['path' => $path]))
  	...
  	$pagination->url = $this->url->link('product/category', $url_params->getUrl(['page' => '{page}']));

	@package Service
*/
class UrlParams {
	protected $request;

	protected $default_params;
	protected $params;
	protected $url_array;
	
	function __construct($request, $default_params, $keep_original = false) {

		$this->request = $request;
		$this->default_params = $default_params;
	
  		// init page parameters
  		//
  		$params = array();
  		foreach ($default_params as $k => $v) {
			if (isset($this->request->get[$k])) {
				if ($keep_original) {
					$params[$k] = $this->request->get[$k];
				} else {
					$value = $this->cleanValue($this->request->get[$k], $v);
					if (!is_null($value)) {
						$params[$k] = $value;
					}
				}
	  		}
	  	}
	  	
	  	$this->params = $params;
	}


	/*
		Trims a value from the URL. An array (e.g. 'filter_name[]=x') is accepted only for a parameter with
		an array default value, its values are trimmed then. For other parameters the array is ignored and
		the default value is used (trim() of an array is a fatal error in PHP 8).

		Returns null when the value is ignored.
	*/
	protected function cleanValue($value, $default_value) {

		if (!is_array($value)) {
			return trim((string)$value);
		}

		if (!is_array($default_value)) {
			return null;
		}

		$result = array();
		foreach ($value as $k => $v) {
			$v = $this->cleanValue($v, array());
			if (!is_null($v)) {
				$result[$k] = $v;
			}
		}

		return $result;
	}
	
	public function getParams() {
	
		$params = array_merge($this->default_params, $this->params);
	
		return $params;
	}

	
	public function getSetParams() {
		return $this->params;
	}
	
	
	public function getParam($name) {
	
		if (isset($this->params[$name])) {
			return $this->params[$name];
		}
		
		if (isset($this->default_params[$name])) {
			return $this->default_params[$name];
		}
	
		return null;
	}

	
	function updateParams($params) {
		$this->params = array_merge($this->params, $params);
	}

	
	/*
		Builds the query string. The values are URL-encoded (e.g. 'a+b@x.com' stays 'a+b@x.com' after
		decoding), array values become 'name[key]=value' pairs. A placeholder like '{page}' is kept as is
		for the pagination.
	*/
	protected function buildParams($params) {
  		$url_array = array();
  		foreach ($params as $k => $v) {
			if (is_array($v)) {
				// an empty array gives an empty string, it is skipped
				$query = http_build_query(array($k => $v), '', '&', PHP_QUERY_RFC3986);
				if ($query !== '') {
					$url_array[$k] = $query;
				}
			} else {
				$url_array[$k] = $k . '=' . $this->encodeValue($v);
			}
	  	}
	  	
		$url = implode('&', $url_array);
		
		return $url;
	}
	
	
	/*
		URL-encodes a parameter value, a placeholder like '{page}' is returned as is.
	*/
	protected function encodeValue($value) {

		$value = (string)$value;
		if (preg_match('/^\{[a-z_]+\}$/i', $value)) {
			return $value;
		}

		return rawurlencode($value);
	}


	function getUrlSortParams($field) {

		$params = $this->params;
		
		if (!empty($params['sort']) && $params['sort'] == $field) {
			if (!empty($params['order'])) {
			  	if ($params['order'] == 'ASC') {
			  		$params['order'] = 'DESC';
			  	} else {
			  		$params['order'] = 'ASC';
			  	}
			} elseif (!empty($this->default_params['order'])) {
				$params['order'] = 'DESC';
			}
		}
		
		$params['sort'] = $field;
		
		return $params;
	}

	/*
		Return url for sorting
	*/
	function getUrlSort($field) {

		$params = $this->getUrlSortParams($field);
		$url = $this->buildParams($params);
		return $url;
	}
	
	
	/*
		Returns URL parameters exploded by '&'. Parameters can be extended or overwritten with $params
		array.
		
		The parameter can be removed if it is set to null
		
	*/
	function getUrl($params = null) {

		$params = $this->getUrlParams($params);

		$url = $this->buildParams($params);
		
		return $url;
	}
	
	
	function getUrlParams($params = null) {
		
		if (!empty($params)) {
			$params = array_merge($this->params, $params);
			$params = array_filter($params, function($val) { 
				return !is_null($val); 
			});
		} else {
			$params = $this->params;
		}

		return $params;
	}
}