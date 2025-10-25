<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

class Arrays {

	/*
		Service function, helps to insert an array inside another array after a specific key
		
		Returns a new array
	*/
	static function insertAfterKey($array, $key, $value) {
	    $key_pos = array_search($key, array_keys($array), true);
	    if($key_pos !== false){
	        $key_pos++;
	        $second_array = array_splice($array, $key_pos);
	        $array = array_merge($array, $value, $second_array);
	    }
	    return $array;
	}

	static function insertBeforeKey($array, $key, $value) {
	    $key_pos = array_search($key, array_keys($array), true);
	    if($key_pos !== false){
	        $second_array = array_splice($array, $key_pos);
	        $array = array_merge($array, $value, $second_array);
	    }
	    return $array;
	}
	
	
	static function fillObject($obj, $array) {
		foreach ($array as $k => $v) {
			$obj->{$k} = $v;
		}
	}
	
}