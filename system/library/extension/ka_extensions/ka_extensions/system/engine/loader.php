<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\engine;

class Loader extends \Loader {

	protected $static_model = [];

	protected $ka_render_depth = array(0);
	protected $disable_render_templates = array(null);
	
	
	public function increaseRenderLevel() {
		array_unshift($this->ka_render_depth, 0);
		array_unshift($this->disable_render_templates, null);
	}

	
	public function decreaseRenderLevel() {
		array_shift($this->disable_render_templates);
		array_shift($this->ka_render_depth);
	}	

	
	public function view($route, $data = array()) {

		if ($this->isRenderDisabled($route)) {
			$this->registry->set('ka_tmp_view_data', $data);
			$this->registry->set('ka_tmp_view_route', $route);
			return;
		}
		
		return parent::view($route, $data);
	}
		

	public function kamodel($route) {
		// Sanitize the call
		$route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);
		
		$model_name = 'model_' . str_replace('/', '_', (string)$route);
		
		if (!$this->registry->has($model_name)) {
		
			$class_pos  = strrpos($route, '/');			
			$class_path = '\\';
			if ($class_pos) {
				$class_file = substr($route, 0, $class_pos);
				$class_path .= str_replace('/', '\\', substr($route, 0, $class_pos)) . '\\';
			}
			$class =  $class_path . 'Model' . str_replace('_','', substr($route, $class_pos + 1));				
			
			// first we try to load the class with namespaces
			if (!class_exists($class)) {
				$class = '\Model' . preg_replace('/[^a-zA-Z0-9]/', '', $route);
				if (!class_exists($class)) {
					$file  = DIR_APPLICATION . 'model/' . $route . '.php';
					if (is_file($file)) {
						include_once(modification($file));
					}
				}
			}

			if (class_exists($class)) {
				$proxy = new $class ($this->registry);
				$this->registry->set($model_name, $proxy);
			} else {
				if (defined('KAMOD_DEBUG')) {
					debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); 
				}
				throw new \Exception('Error: Could not load model ' . $route . " with class: $class");
			}
		}
		
		return $this->registry->get($model_name);
	}
	
	
	public function isRenderDisabled($route = null) {

		if (empty($this->ka_render_depth[0])) {
			return false;
		}
		
		if (is_null($this->disable_render_templates[0]) || is_null($route)) {
			return true;
		}
		
		if (!in_array($route, $this->disable_render_templates[0])) {
			return false;
		}
		
		return true;
	}
	
	public function disableRender($templates = null) {
		if ($this->ka_render_depth[0] == 0) {
			if (!empty($templates)) {
				if (is_array($templates)) {
					$this->disable_render_templates[0] = $templates;
				} else {
					$this->disable_render_templates[0] = array($templates);
				}
			}
		}
		
		$this->ka_render_depth[0]++;
		return;
	}

	public function enableRender() {
		$this->ka_render_depth[0]--;
		$this->ka_render_depth[0] = max($this->ka_render_depth[0], 0);
		return;		
	}
}