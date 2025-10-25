<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

trait TraitController {

	protected $data     = array();
	protected $children = array();
	protected $template = '';

	protected function getRenderData() {

		$data = $this->data;
		if ($this->registry->has('ka_tmp_view_data')) {
			$data = array_merge($data, $this->registry->get('ka_tmp_view_data'));
		}
		
		return $data;
	}
	
	protected function getRenderTemplate() {
		$template = $this->template;
		if ($this->registry->has('ka_tmp_view_route')) {
			$template = $this->registry->get('ka_tmp_view_route');
		}
		
		return $template;
	}

	/*
		These two methods are added to unify the interface with our "KaController" 
	*/
	protected function disableRender($templates = null) {
		$this->load->disableRender($templates);
	}

	protected function enableRender() {
		$this->load->enableRender();
	}
	
	
	protected function addTopMessage($msg, $type = 'I') {
	
		if (!is_array($msg)) {
			$msg = array($msg);
		}

		foreach ($msg as $text) {
			$this->session->data['ka_top_messages'][] = array(
				'type'    => $type,
				'content' => $text
			);
		}
	}

	
	protected function getTopMessages($clear = true) {

		if (isset($this->session->data['ka_top_messages'])) {
			$top = $this->session->data['ka_top_messages'];
		} else {
			$top = null;
		}

		if ($clear) {
			$this->session->data['ka_top_messages'] = null;
		}
		return $top;
	}
	
	/*
		Rendering can be disabled for parent classes thus child classes may change the data
		or template file before output. Example:
		
		public function index() {
			$this->disableRender();
			parent::index();
			$this->enableRender();
			$this->response->setOutput($this->render());
		}
		
	*/
	protected function render($tpl = '', $data = array()) {

		if (empty($tpl)) {
			$tpl = $this->template;
		}		
		if (empty($data)) {
			$data = $this->data;
		}
		
		if ($this->load->isRenderDisabled()) {
			$this->template = $tpl;
			$this->data     = $data;
			$this->registry->set('ka_tmp_view_data', $this->data);
			$this->registry->set('ka_tmp_view_route', $this->template);
			
			return;
		}

		//
		// using ka_top and ka_breadcrumbs varialbes in templates directly are discouraged
		// they will be replaced with twig embedded templates and removed in future versions
		//
		
		// load top messages
		$data['top_messages'] = $this->getTopMessages();
		$file = 'extension/ka_extensions/common/top_messages';
		$data['ka_top'] = $this->load->view($file, $data);

		// load breadcrumbs
		$file = 'extension/ka_extensions/common/breadcrumbs';
		$data['ka_breadcrumbs'] = $this->load->view($file, $data);
		
		if (!empty($this->children)) {
			foreach ($this->children as $child) {
				$data[basename($child)] = $this->load->controller($child);
			}
		}

		return $this->load->view($tpl, $data);
	}
	

	/*
		$template - name of the template inside the module directory (when namespace is available)
		$data     - array of template variables
	*/		
	protected function showPage($template = '', $data = array()) {

		if (empty($this->data['heading_title'])) {
			$this->data['heading_title'] = $this->document->getTitle();
		}	
		
		if (!empty($data)) {
			$this->data = array_merge($this->data, $data);
		}
		
		if (!empty($template)) {
			$this->template = $template;
		}
		
		// the page id is generated from the template name when it is not defined explicitly
		//
		if (empty($this->data['page_id'])) {
			$this->data['page_id'] = preg_replace("/^extension\//", '', $this->template);
		}
		$this->data['page_id'] = str_replace('/', '-', $this->data['page_id']);

		if (\KaGlobal::isAdminArea()) {
			$this->loadAdminPageBlocks();
		} else {
			$this->loadCustomerPageBlocks();
		}
		
		$this->response->setOutput($this->render($this->template));
	}
	
	
	protected function loadAdminPageBlocks() {
		$this->data['header']      = trim($this->load->controller('common/header'));
		$this->data['column_left'] = trim($this->load->controller('common/column_left'));
		$this->data['footer']      = trim($this->load->controller('common/footer'));
	}

	
	protected function loadCustomerPageBlocks() {
		$this->data['column_left']    = trim($this->load->controller('common/column_left'));
		$this->data['column_right']   = trim($this->load->controller('common/column_right'));
		$this->data['content_top']    = trim($this->load->controller('common/content_top'));
		$this->data['content_bottom'] = trim($this->load->controller('common/content_bottom'));
		$this->data['footer']         = trim($this->load->controller('common/footer'));
		$this->data['header']         = trim($this->load->controller('common/header'));
	}	
}