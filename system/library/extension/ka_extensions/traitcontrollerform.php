<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
	
namespace extension\ka_extensions;

trait TraitControllerForm {

	protected $fields;
	protected $errors = array();
	protected $url_params;

	protected function getFields() {
		return [];
	}
	
	protected function initFields($fields) {
	
		if (empty($fields)) {
			return;
		}
		
		foreach ($fields as $k => $v) {
			if (empty($v['code'])) {
				$fields[$k]['code'] = $k;
			}
			if (!isset($v['value'])) {
				$fields[$k]['value'] = '';
			}
		}
		
		return $fields;
	}
	
	/*
		This method is called when the field array should be initialized with actual field value.
	
		$k - field code
		$f - field metadata array
		$old_data - old data information (data requested from db for example)
		$new_data - new data information (post array or null).
	*/
	protected function getFieldWithData($k, $f, $old_data, $new_data = null) {
	
		if (!empty($f['code'])) {
			$k = $f['code'];
		} else {
			$f['code'] = $k;
		}

		if (is_array($new_data)) {
			if (isset($new_data[$k])) {
				$f['value'] = $new_data[$k];
			} else {
				$f['value'] = '';
			}
		} elseif (isset($old_data[$k])) {
			$f['value'] = $old_data[$k];
		} elseif (!empty($f['default_value'])) {
			$f['value'] = $f['default_value'];
		} else {
			$f['value'] = '';
		}
		
		if (!empty($f['value']) && !empty($f['format'])) {
			if ($f['format'] == 'price') {
				$f['value'] = $this->currency->formatValue($f['value'], $this->config->get('config_currency'));
				
			}
		}

		if (!empty($f['type'])) {
			if ($f['type'] == 'image') {
				if (is_file(DIR_IMAGE . $f['value'])) {
					$f['value'] = $f['value'];
					$f['thumb'] = $this->model_tool_image->resize($f['value'], 200, 200);
				} else {
					$f['value'] = '';
					$f['thumb'] = $this->model_tool_image->resize('no_image.png', 200, 200);
				}
				
				$f['default_thumb'] = $this->model_tool_image->resize('no_image.png', 200, 200);
				$f['default_value'] = 'no_image.png';
			}				
		}
		
		return $f;
	}	
	

	/*
		Returns fields filled in with data from old and new arrays
		
		$new_data is not null when the form was submitted with new values.
	*/
	protected function getFieldsWithData($fields, $old_data, $new_data = null, $errors = array()) {

		foreach ($fields as $k => $f) {

			if (!empty($errors[$k])) {
				$f['error'] = $errors[$k];
			}
		
			$f = $this->getFieldWithData($k, $f, $old_data, $new_data);

			$fields[$k] = $f;
		}
		
		return $fields;
	}

	
	/*
		Returns a simple array of code->value pairs from the $fields array.
	*/
	protected function getFieldValues($fields) {
	
		$values = array();
	
		foreach ($fields as $k=>$f) {
			$values[$f['code']] = $f['value'];
		}
		
		return $values;
	}
	

	protected function validateField($code, $field, $post) {
	
		if (!empty($field['required'])) {
			if (empty($post[$code])) {
				$name = $code;

				if (!empty($field['title'])) {
					$name = $field['title'];
				} else {
					$langvar = $this->language->get('txt_title_' . $code);
					if (!empty($langvar)) {
						$name = $langvar;
					}
				}
				$this->errors[$code] = sprintf($this->language->get('error_field_is_empty'), $name);
				return false;
			}
		}

		if (!empty($field['type'])) {

			if (in_array($field['type'], array('number', 'text', 'textarea'))) {
				if (!isset($post[$code])) {				
					$this->errors[$code] = sprintf($this->language->get('The field %s was not found'), $this->language->get('txt_title_' . $code));
					return false;
				}
			}
		
			if ($field['type'] == 'number') {
				if (isset($field['min_value'])) {
					if ($field['min_value'] > $post[$code]) {
						$this->errors[$code] = sprintf($this->language->get("Minimum value is %s"), $field['min_value']);
					}
				} 
				if (isset($field['max_value'])) {
					if ($field['max_value'] < $post[$code]) {
						$this->errors[$code] = sprintf($this->language->get("Maximum value is %s"), $field['max_value']);
					}
				}
			}
		}
		
		return true;
	}
	

	protected function validateFields($fields, $post) {

		foreach ($fields as $k => $f) {
			$this->validateField($k, $f, $post);
		}
		
		
		if (empty($this->errors)) {
			return true;
		}

		return false;
	}	
	
	
	protected function validateLanguageFields($lang_group, $lang_fields, $lang_post) {

		$lang_errors = array();
		$old_errors  = $this->errors;
	
		foreach ($lang_fields as $language_id => $fields) {
		
			$post = (!empty($lang_post[$lang_group][$language_id]) ? $lang_post[$lang_group][$language_id] : array());
			
			$this->errors = array();
			foreach ($fields as $k => $f) {
				$this->validateField($k, $f, $post);
			}
			
			if (!empty($this->errors)) {
				$lang_errors[$lang_group][$language_id] = $this->errors;
			}
		}

		if (empty(!$old_errors)) {
			$this->errors = array_merge($old_errors, $lang_errors);
		} else {
			$this->errors = $lang_errors;
		}
		
		if (empty($this->errors)) {
			return true;
		}

		return false;
	}
	
	
	
	/*
		Returns fields filled in with data from old and new arrays
		
		$new_data is not null when the form was submitted with new values.
	*/
	protected function getLanguageFieldsWithData($lang_group, $lang_fields, $lang_old_data, $lang_new_data = null, $errors = array()) {

		foreach ($lang_fields as $language_id => $fields) {

			$old_data = (is_array($lang_old_data) && !empty($lang_old_data[$language_id]) ? $lang_old_data[$language_id] : array());
			$new_data = (is_array($lang_new_data) && !empty($lang_new_data[$lang_group][$language_id]) ? $lang_new_data[$lang_group][$language_id] : array());
			
			foreach ($fields as $k => $f) {

				if (!is_null($lang_new_data)) {
					$f = $this->getFieldWithData($k, $f, $old_data, $new_data);
				} else {
					$f = $this->getFieldWithData($k, $f, $old_data);
				}

				// copy errors to the field if the exist
				if (!empty($errors[$lang_group][$language_id][$k])) {
					$f['error'] = $errors[$lang_group][$language_id][$k];
				}
				
				$lang_fields[$language_id][$k] = $f;
			}
		}
		
		return $lang_fields;
	}	
	
	
	protected function copyByLanguages($fields, $name_pattern = null) {
	
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		
		$lang_fields = array();
		foreach ($languages as $l) {
		
			$new_fields = [];
			foreach ($fields as $fk => $fv) {
				if (!empty($name_pattern)) {
					$fv['code'] = $fk;
					$fv['name'] = str_replace(
						['#language_id#', '#code#'],
						[$l['language_id'], $fk],
						$name_pattern
					);
				}
				$new_fields[$fk] = $fv;				
			}
		
			$lang_fields[$l['language_id']] = $new_fields;
		}

		return $lang_fields;
	}	
}