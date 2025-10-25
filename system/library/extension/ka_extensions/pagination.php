<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 523 $)
*/
	
namespace extension\ka_extensions;

class Pagination {

	protected $pagination;

	protected $registry;
	protected $load;
	protected $language;
	protected $config;
	protected $text;
	
	public function __construct($params = []) {

		$this->pagination = new \Pagination();
	
		$this->registry = \KaGlobal::getRegistry();
		$this->load     = $this->registry->get('load');
		$this->language = $this->registry->get('language');
		$this->config   = $this->registry->get('config');
		
		if (empty($params)) {
			return;
		}
		
		$this->total = $params['total'];
		$this->page  = $params['page'];

		if (!empty($params['limit'])) {
			$this->limit = $params['limit'];
		} else {
			$store_limit = $this->config->get('config_pagination_admin');
		 	if (!empty($store_limit)) {
		 		$this->limit = $store_limit;
		 	}
		}
		
		if (!empty($params['url'])) {
			$this->url = $params['url'];
		}
	}

	
	public function __set($name, $value) { // : void
	
		if ($name == 'text') {
			$this->text = $value;
			return;
		}
	
		$this->pagination->{$name} = $value;
	}

	
	public function __get($name) { // : mixed
	
		if ($name == 'text') {
			return $this->text;
		}
	
		return $this->pagination->{$name};
	}
	
	
	public function render() {
		return $this->pagination->render();
	}
	
	
	public function getResults($text = '') {
	
		if (empty($text)) {
			$text = $this->language->get('text_pagination');
		}
	
		$from  = ($this->total) ? (($this->page - 1) * $this->limit) + 1 : 0;
		$to    = ((($this->page - 1) * $this->limit) > ($this->total - $this->limit)) ? $this->total : ((($this->page - 1) * $this->limit) + $this->limit);
		if ($this->limit <= 0) {
			$pages = 1;
		} else {
			$pages = ceil($this->total / $this->limit);
		}
	
		// 'Showing %d to %d of %d (%d Pages)'
		//
		$str = sprintf($text, $from, $to, $this->total, $pages);
		
		return $str;
	}
}