<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	
	This class saves $_FILES data to a temporary directory and keeps its information in session.
*/

namespace extension\ka_extensions;

class PostedFiles {

	protected $files_tmp_dir = DIR_CACHE;
	protected $session, $request;
	
	public function __construct($tmp_dir = '') {
	
		if (!empty($tmp_dir)) {
			$this->files_tmp_dir = $tmp_dir;
		}
		
		$this->request = KaGlobal::getRegistry()->get('request');
		$this->session = KaGlobal::getRegistry()->get('session');
	}
	
	
	/*
		The function forgets the posted files data.
	*/
	public function clearPostedFiles($field_name = null) {

		if (empty($field_name)) {
			unset($this->session->data['ka_ext_posted_files']);
			return;
		}
		
		if (empty($this->session->data['ka_ext_posted_files'])) {
			return;
		}
		
		foreach ($this->session->data['ka_ext_posted_files'] as $fk => $fv) {
			if (strpos($fk, $field_name) !== NULL) {
				unset($this->session->data['ka_ext_posted_files'][$fk]);
			}
		}
  	}
	
  	/*
  		Return a posted file for one dimensional index. Multiple indexes are separated by a slash.
  		
  		Example:
  			$file = $this->getPostedFile('product_downloads/0/file');
  			
  		File array:
			'name' => real file name
			'type' => mime file type
			'size' => file size
			'file' => fill path to the temporary file
  			
  	*/
	public function getPostedFile($field_name) {

		if (empty($this->session->data['ka_ext_posted_files'])) {
			return [];
		}
	
		if (!empty($this->session->data['ka_ext_posted_files'][$field_name])) {
			return $this->session->data['ka_ext_posted_files'][$field_name];
		}
		return [];
	}

	/*
		$field_name - may contain a wildcard pattern. Example: 'product/downloads/ * /file'
	*/
	public function getPostedFiles($field_name) {

		if (empty($this->session->data['ka_ext_posted_files'])) {
			return [];
		}
	
		$files = [];
		foreach ($this->session->data['ka_ext_posted_files'] as $fk => $fv) {
			if (fnmatch($field_name, $fk)) {
				$files[] = $fv;
			}
		}

		return $files;
	}
	
	
  	/*
  		The function saves files from $this->request->files array to the $files_tmp_dir directory
  		and keeps information about these files.
  		
  		Examples:
	  		savePostedFiles('posted_files/ * /file)
	  		savePostedFiles('posted_files/file/*)
  		
  	*/
	public function savePostedFiles($field_name) {

  		if (empty($this->request->files)) {
  			return false;
  		}

		$files = $this->flattenFilesArray($this->request->files);

		$found = false;
  		foreach ($files as $fk => $file) {
			if (fnmatch($field_name, $fk)) {
				$this->savePostedFile($fk, $file);
				$found = true;
			}
		}
		
		return $found;
	}
	
	
	protected function savePostedFile($field_name, $file_data) {

		// Return any upload error
		if ($file_data['error'] != UPLOAD_ERR_OK) {
			return false;
		}
	
		if (!is_uploaded_file($file_data['tmp_name'])) {
			return false;
		}
			
		$filename = tempnam($this->files_tmp_dir, 'dn-');
		
		move_uploaded_file($file_data['tmp_name'], $filename);
		if (!file_exists($filename)) {
			return false;
		}

  		if (!isset($this->session->data['ka_ext_posted_files'])) {
			$this->session->data['ka_ext_posted_files'] = [];
		}
		
		$this->session->data['ka_ext_posted_files'][$field_name] = array(
			'name' => $file_data['name'],
			'type' => $file_data['type'],
			'size' => $file_data['size'],
			'file' => $filename,
		);

		return true;
	}
	
	
	/*
		$files - array of $_FILES data
		
		returns
			$array - one dimensional array of files where indexes are separated by a slash. The last element
			         contains the file data.
		
	*/
	protected function flattenFilesArray($files) {

		$result = [];

	    foreach ($files as $input => $fileData) {
    	    foreach ($fileData as $field => $data) {
        	    $this->flattenFilesRecurse($data, "$input", $field, $result);
	        }        
	    }

	    $new_result = [];
    	foreach ($result as $rk => $rv) {
    
	    	$last_slash_pos = strrpos($rk, '/');
    		$new_index = substr($rk, 0, $last_slash_pos);
    		$file_key = substr($rk, $last_slash_pos+1);

	    	if (empty($new_result[$new_index])) {
    			$new_result[$new_index] = [];
    		}
	    	$new_result[$new_index][$file_key] = $rv;
    	}
    
	    return $new_result;
	}

	
	protected function flattenFilesRecurse($data, string $path, string $field, &$result) {
	    if (is_array($data)) {
    	    foreach ($data as $key => $value) {
	            $this->flattenFilesRecurse($value, "$path/$key", $field, $result);
    	    }
	    } else {
    	    $result["$path/$field"] = $data;
	    }
	}	
}