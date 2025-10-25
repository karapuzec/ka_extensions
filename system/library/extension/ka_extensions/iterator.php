<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 514 $)
*/
	
namespace extension\ka_extensions;

abstract class Iterator extends Model implements \Iterator {

	protected $range_size = 100;
	protected $query = '';
	protected $limit_from = 0;
	protected $limit_to   = false;
	
	protected function onLoad() {
		parent::onLoad();
	}
	
	// array starts with 0
	//
    protected $position = 0;		// stores current position
    protected $from = 0, $to = 0;	// stores range of last requested data
    protected $ids = array();		// stores product_ids with positions in array keys
    protected $is_end = false;

	function setLimits($from = 0, $range_size = false) {
	
		if (empty($this->query)) {
			$this->defineQuery();
		}
	
		$this->limit_from = $from;
		
		if (!empty($range_size)) {
			$this->range_size = $range_size;
		}
	}
	

    #[\ReturnTypeWillChange]    
    function rewind() {
    	$this->ids      = array();
        $this->position = $this->limit_from;
        $this->from     = $this->limit_from;
        $this->to       = -1;
        $this->is_end   = false;
    }


    protected function fetchData($from) {

    	if (empty($this->query)) {
    		return false;
    	}
    
    	$qry = $this->db->query($this->query . " LIMIT $from, " . $this->range_size);
    	
    	$pos = $from;
    	foreach ($qry->rows as $row) {
    		$this->ids[$pos++] = $row;
    	}
    	
    	$this->from = $from;
    	$this->to   = $from + $this->range_size;
    }
    
    
    #[\ReturnTypeWillChange]
    function current() {
        return $this->ids[$this->position];
    }

	#[\ReturnTypeWillChange]
    function key() {
        return $this->position;
    }

    #[\ReturnTypeWillChange]
    function next() {
        ++$this->position;
    }
    
    
    function isEmpty() {
    	$this->rewind();
    	if ($this->valid()) {
    		return false;
    	}
    	
    	return true;
    }
    
    function isEnd() {
    	return $this->is_end;
    }    

    #[\ReturnTypeWillChange]
    function valid() {
    
    	if ($this->position >= $this->from && $this->position <= $this->to) {
    		if (isset($this->ids[$this->position])) {
    			return true;
    		}
    	}

    	$this->fetchData($this->position);
    	$res = isset($this->ids[$this->position]);
    	if ($res) {
    		return true;
    	}
    	
    	$this->is_end = true;
    	return false;
    }

    // The function has to be redefined to specify $this->query property
    //
    abstract protected function defineQuery();
}