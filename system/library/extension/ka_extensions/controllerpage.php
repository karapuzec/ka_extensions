<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	This is a controller class for a basic module settings page.
*/
namespace extension\ka_extensions;

abstract class ControllerPage extends Controller {

	protected $pagination;
	
	protected function onLoad() {

		$this->load->language('extension/ka_extensions/common');

		// set page url parameters
		//
		$page_url_params = array();
		
		if (\KaGlobal::isAdminArea()) {
			if (!empty($this->session->data['user_token'])) {
				$page_url_params['user_token'] = $this->session->data['user_token'];
			}
			$this->addBreadcrumb($this->language->get('text_home'), $this->url->link('common/dashboard', $page_url_params, true));
		} else {
	   		$this->addBreadcrumb($this->language->get('text_home'), $this->url->link('common/home', $page_url_params, true)); 
		}
		
		$page_params = $this->getPageUrlParams();
		if (!empty($page_params)) {
			$page_url_params = array_merge($page_url_params, $page_params);
		}		
		$this->url_params = new UrlParams($this->request, $page_url_params);
		
		parent::onLoad();
	}

	
  	protected function getPageUrlParams() {
  		$params = array();
		return $params;
	}

	
	protected function addBreadcrumb($text, $href = '') {
		if (!isset($this->data['breadcrumbs'])) {
			$this->data['breadcrumbs'] = array();
		}
		
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $text,
			'href'      => $href,
   		);
	}

	
	protected function showPage($page = '', $data = array()) {

		$title = $this->document->getTitle();
		if (empty($title)) {
			$this->document->setTitle($this->language->get('heading_title'));
			$this->addBreadcrumb($this->language->get('heading_title'));
		}
	
		if (!empty($this->pagination)) {
			$this->data['pagination'] = $this->pagination->render();
			$this->data['results']    = $this->pagination->getResults();
		}
		
		return parent::showPage($page, $data);
	}
}