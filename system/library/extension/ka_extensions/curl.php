<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 566 $)
*/
	
namespace extension\ka_extensions;

class Curl {

	protected $lastError = '';
	const MAX_RESOLVE_ATTEMPTS = 3;
	
	protected $last_file_info = array();
	
	public function getLastError() {
		return $this->lastError;
	}
	

	/*
		RETURNS:
			false - on error
			array - on success. It looks like:
				array(
					'status'
						'http_version'  =>
						'status_code'   =>
						'reason_phrase' =>
					'headers'
						'<hdr1>' => value
						'<hdr2>' => value
				)
	*/
	protected function parseHttpHeader($header) {
	
		if (!preg_match("/^(.*)\s(.*)\s(.*)\x0D\x0A/U", $header, $matches)) {
			return false;
		}

		$status = array(
			'http_version'  => $matches[1],
			'status_code'   => $matches[2],
			'reason_phrase' => $matches[3]
		);
		
		$headers = array();		
		$header_lines = explode("\x0D\x0A", $header);
		
		foreach ($header_lines as $line) {
			$pair        = array();
			$value_start = strpos($line, ': ');
			if ($value_start === false) continue;
			
			$name        = substr($line, 0, $value_start);
			$value       = substr($line, $value_start + 2);
						
			$headers[strtolower($name)] = $value;
		}
		
		$result = array(
			'status' => $status,
			'headers' => $headers
		);
		
		return $result;					
	}

	/*
		$data - can be a plain text or array of fields
		options - array of possible values
			user-agent   - text for user-agent value
			extr_headers - can add extra headers to the request
	*/
	public function request($url, $data = array(), $options = array()) {

		if (empty($options['timeout'])) {
			$options['timeout'] = 8;
		}
	
		if (empty($options['request_method'])) {
			$options['request_method'] = 'POST';
		}
		
		if (!function_exists('curl_init')) {
			trigger_error(__METHOD__ . ": CURL does not exist");
			return false;
		}
	
		$message = null;
		$this->lastError = '';
		
		$tmp_url        = $url;
		$redirect_count = 0;
		$parsed_url = parse_url($url);

		while (++$redirect_count <= 5) {
			$headers = '';
			$message = null;
			
			if (preg_match("/^\/\/.*/", $tmp_url)) {
				$tmp_url = "http:" . $tmp_url;
			}
				
			$parsed_tmp_url = parse_url($tmp_url);

			$curl = curl_init($tmp_url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_TIMEOUT, $options['timeout']);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $options['request_method']);
			
			if (empty($options['verify_ssl'])) {
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			}
			
			if (!empty($data)) {
				if (is_array($data)) {
					if (empty($options['with_files'])) {
						$post = http_build_query($data, '', '&');							
					} else {
						$post = $this->flattenFormFields($data);
					}
				} else {
					$post = $data;
				}

				curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
			}
				
			// add custom headers to emulate a regular user activity so it will prevent bans
			// by the user-agent string
			//
			$opt_headers = array();
			$opt_headers[] = 'Expect:'; // this parameter prevents double headers from the server

			if (!empty($options['user-agent'])) {
				$opt_headers[] = 'User-Agent: ' . $options['user-agent'];
			}  else {
				$opt_headers[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0";
			}
			$opt_headers[] = "Host: " . $parsed_tmp_url['host'];

			if (preg_match("/\.yimg\.com/i", $parsed_tmp_url['host'])) {
				$opt_headers[] = "Accept-Language: en-US,en;q=0.5";
				$opt_headers[] = "Accept-Encoding: gzip, deflate";
				$opt_headers[] = "DNT: 1";
				$opt_headers[] = "Upgrade-Insecure-Requests: 1";
			}

			// add cookies to the server
			//
			if (!empty($options['cookies'])) {
				$opt_headers[] = 'Cookie: ' . http_build_query($options['cookies'], '', '; ');
			}

			if (!empty($options['extra_headers'])) {
				foreach ($options['extra_headers'] as $k => $v) {
					$opt_headers[] = $k . ': ' . $v;
				}
			}
			
			if (!empty($opt_headers)) {
				curl_setopt($curl, CURLOPT_HTTPHEADER, $opt_headers);
			}

			// specify web authorization
			//
			if (!empty($options['webauth_username']) && !empty($options['webauth_password'])) {
				curl_setopt($curl, CURLOPT_USERPWD, $options['webauth_username'] . ":" . rawurlencode($options['webauth_password']));
			}
				
			// use more attempts to resolve host name. Sometimes curl fails at resolving 
			// a valid host name.
			//
			$resolve_attempt = 0;
			while (true) {
				$response = curl_exec($curl);

				if ($response === false) {
					
					// curl_error code 6 means 'could not resolve host name'
					//
					if (curl_errno($curl) == 6) {
						if ($resolve_attempt++ < self::MAX_RESOLVE_ATTEMPTS) {
							continue;
						}
					}
					$this->lastError = 'CURL error (' . curl_errno($curl) . '): ' . curl_error($curl);
				}
					
				break;					
			}				
			curl_close($curl);
				
			if ($response === false) {
				break;
			}
			
			$msg_start    = strpos($response, "\x0D\x0A\x0D\x0A");
			$header_block = substr($response, 0, $msg_start);
			$headers      = $this->parseHttpHeader($header_block);				
			
			if (empty($headers)) {
			
				if (strlen($response) > 1000) {
					$this->lastError = 'No headers received. Response size is ' . strlen($response);
				} else {
					$this->lastError = 'No headers received. Response is "' . $response . '"';
				}
				break;
			}

			$message = substr($response, $msg_start+4);
			
			if ($headers['status']['status_code'] >= 200 && $headers['status']['status_code'] < 300) {
				break;
				
			} elseif ($headers['status']['status_code'] >= 300 && $headers['status']['status_code'] < 400) {
				$tmp_url = $headers['headers']['location'];
				continue;
			} elseif ($headers['status']['status_code'] == 400 and preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
				$this->lastError = "Status code: 400";
				continue;
			} else {
				$this->lastError = 'Invalid status code: ' . $headers['status']['status_code'];
				break;
			}
		};
		
		return $message;
	}
	
	
	
	//
	// http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
	//
	public function getFileContentsByUrl($url, $from = 0) {

		$message = null;
		$this->lastError = '';
		
		if ($from == 0) {
			$this->last_file_info = array();
		}

		// add a protocol to the URL
		//
		if (preg_match("/^\/\/.*/", $url)) {
			$url = "http:" . $url;
		}
		
		$parsed_url = parse_url($url);
		
		if (empty($parsed_url['host'])) {
			$this->lastError = 'Incorrect url. Host parameter was not found';
			return $message;
		}
		
		// add an extra parameter for dropbox images
		// // https://www.dropboxforum.com/t5/Dropbox-files-folders/Getting-downloading-link-of-files-in-Dropbox-automatically/td-p/263073
		//
		if (preg_match("/dropbox\.com/i", $parsed_url['host'])) {
			if (empty($parsed_url['query'])) {
				$url = $url . '?raw=1';
			} else {
				$url = $url . '&raw=1';
			}
		}
		
		if (function_exists('curl_init')) {
		
			$tmp_url        = $url;
			$redirect_count = 0;
			
			while (++$redirect_count <= 5) {

				$tmp_url = str_replace(array('%20'), array(' '), $tmp_url);
			    $tmp_url = $this->encodeUrl($tmp_url);
			    $tmp_url = str_replace(array('+'), array('%20'), $tmp_url);
			    
				$headers = '';
				$message = null;

				$parsed_url = parse_url($tmp_url);
				
				if (empty($parsed_url) || empty($parsed_url['host'])) {
					$this->lastError = 'URL cannot be parsed:' . $tmp_url;
					break;
				}
				
				$curl = curl_init($tmp_url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				// depracated in php 5.5
				// curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_TIMEOUT, 23);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				
				// download the file partially if requested
				if (!empty($from)) {
					$range = $from . '-';
					curl_setopt($curl, CURLOPT_RANGE, $range);
				}
				
				// add custom headers to emulate a regular user activity so it will prevent bans
				// by the user-agent string
				//
				$opt_headers = array();
 				$opt_headers[] = "Host: " . $parsed_url['host'];
				$opt_headers[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0";
				$opt_headers[] = 'Accept: */*;q=0.1';
				$opt_headers[] = 'Accept-Encoding: gzip, deflate, br';
				
				if (preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
					$opt_headers[] = "Accept-Language: en-US,en;q=0.5";
					$opt_headers[] = "DNT: 1";
					$opt_headers[] = "Upgrade-Insecure-Requests: 1";
				}

				// use more attempts to resolve host name. Sometimes curl fails at resolving 
				// a valid host name.
				//
				$resolve_attempt = 0;
				while (true) {
					$response = curl_exec($curl);

					if ($response === false) {
					
						// curl_error code 6 means 'could not resolve host name'
						//
						if (curl_errno($curl) == 6) {
							if ($resolve_attempt++ < self::MAX_RESOLVE_ATTEMPTS) {
								continue;
							}
						}
						$this->lastError = 'CURL error (' . curl_errno($curl) . '): ' . curl_error($curl);
					}
					
					break;					
				}
				curl_close($curl);
				
				if ($response === false) {
					break;
				}
				
				$msg_start    = strpos($response, "\x0D\x0A\x0D\x0A");
				$header_block = substr($response, 0, $msg_start);
				$headers      = $this->parseHttpHeader($header_block);

				if (empty($headers)) {
					if (strlen($response) > 1000) {
						$this->lastError = 'No headers received. Response size is ' . strlen($response);
					} else {
						$this->lastError = 'No headers received. Response is "' . $response . '"';
					}
					break;
				}
/*
A sample header received from nginx server which forced the partial download

["status"]=> array(3) { ["http_version"]=> string(8) "HTTP/1.1" ["status_code"]=> string(3) "200" 
["reason_phrase"]=> string(2) "OK" } ["headers"]=> array(13) / [""]=> string(13) "TP/1.1 200 OK" 
["server"]=> string(5) "nginx" ["date"]=> string(29) "Tue, 22 Jun 2021 23:39:57 GMT" ["content-type"]=> string(10) "image/jpeg" 
["content-length"]=> string(5) "47957" ["last-modified"]=> string(29) "Fri, 04 Dec 2020 12:40:55 GMT" 
["connection"]=> string(10) "keep-alive" ["vary"]=> string(15) "Accept-Encoding" 
["etag"]=> string(15) ""5fca2e57-bb55"" ["expires"]=> string(29) "Wed, 22 Jun 2022 23:39:57 GMT" 
["cache-control"]=> string(6) "public" 
["x-frame-options"]=> string(10) "SAMEORIGIN" ["accept-ranges"]=> string(5) "bytes" 

*/				
				if ($headers['status']['status_code'] >= 200 && $headers['status']['status_code'] < 300) {
				
					$message = substr($response, $msg_start+4);
					
					if (!empty($headers['headers']['content-length'])) {						
						if ($headers['status']['status_code'] != 206) {
							$file_length = (int)$headers['headers']['content-length'];
						} else {
							$file_length = $headers['headers']['content-length'];
						}
						
						// if the file was not downloaded fully
						//
						if ($file_length && strlen($message) != $file_length) {
						
							if (!empty($from) || !empty($headers['headers']['accept-ranges'])) {
								// download the rest of the file
								do {
									$partial_message = $this->getFileContentsByUrl($tmp_url, strlen($message));
									if (!empty($partial_message)) {
										$message .= $partial_message;
									} else {
										$this->lastError = 'File could not be downloaded partially';
										$message = '';
									}
								} while (strlen($message) < $file_length);
							
							} else {
								$this->lastError = "File length mismatch";
								$message = '';
							}
						}
					}
					
					// the content might be encoded
					if (!empty($headers['headers']['content-encoding']) && $headers['headers']['content-encoding'] == 'gzip') {
						if (function_exists('gzdecode')) {
							$message = gzdecode($message);
						} else {
							$message = '';
							$this->lastError = "gzdecode function is required to decode incoming images";
						}
					}
					
					if (!empty($headers['headers']['content-disposition'])) {
						$pairs = $this->parseContentDisposition($headers['headers']['content-disposition']);
						if (!empty($pairs['filename'])) {
							$this->last_file_info['filename'] = $pairs['filename'];
						}
					}
					
					if (!empty($headers['headers']['content-type'])) {
						$this->last_file_info['content-type'] = $headers['headers']['content-type'];
					}
					
					break;
					
				} elseif ($headers['status']['status_code'] >= 300 && $headers['status']['status_code'] < 400) {
					$tmp_url = $headers['headers']['location'];

					// the location may be only a path from dropbox like this:
					// location: /s/raw/gm76vh16anbju2m/JARG1%20IMG_2876.JPG 
					//
					$new_parsed_url = parse_url($tmp_url);
					
					if (empty($new_parsed_url['host'])) {
						$tmp_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $tmp_url;
						
					} elseif (empty($new_parsed_url['scheme'])) {
						$tmp_url = $parsed_url['scheme'] . ':' . $tmp_url;
					}
					
					continue;
				} elseif ($headers['status']['status_code'] == 400 and preg_match("/\.yimg\.com/i", $parsed_url['host'])) {
					$this->lastError = "Status code: 400";
					continue;
				} else {
					$this->lastError = 'Invalid status code: ' . $headers['status']['status_code'];
					break;
				}
				
			};
			
		} else {
			if (ini_get('allow_url_fopen')) {
				$message = file_get_contents($url);
			}
		}
		
		return $message;
	}

	
	public function getLastFileInfo() {
		return $this->last_file_info;
	}
	
	
	/* 
		The function encodes an image URL to a url-compliant string
	*/
	function encodeURL($url) {
    	$encodedUrl = preg_replace_callback('/[^\*$:\/@?&=#]+/usD', function ($matches) {
    		$str = $matches[0];
    		
    		// to prevent urlencode to covert '+' to %2b we have to replace pluses with 
    		// spaces in advance which are later properly converted to '+' by urlencode
    		//    		
    		$str = str_replace('+', ' ', $str);
    		$str = urlencode($str);
    		return $str;
    		
    	}, $url);
    	
    	return $encodedUrl;
	}
	
/*	
	Example of content dispostion string:
	attachment;filename="BK914.jpg";filename*=UTF-8''BK914.jpg
*/	
	function parseContentDisposition($str) {
	
		$blocks = explode(";", $str);
		
		$pairs = array();
		foreach ($blocks as $block) {
			$values = explode("=", $block);
			
			if (count($values) != 2) {
				continue;
			}
			
			$pairs[$values[0]] = str_replace("\"", '', $values[1]);
		}
	
		return $pairs;	
	}
	
	
	function flattenFormFields(array $data, string $prefix = null) {
    	$flat = [];
	
    	foreach ($data as $key => $value) {
    	
	        // Construct the full key with bracket notation if needed
    	    $fullKey = (!is_null($prefix)) ? "{$prefix}[{$key}]" : $key;
    	    
    	    if (empty($value)) {
    	    	$flat[$fullKey] = '';

    	    } elseif (is_array($value)) {
	            // Recursively flatten arrays
        	    $flat += $this->flattenFormFields($value, $fullKey);

    	    } else {
	            $flat[$fullKey] = $value;
    	    }
	    }

    	return $flat;
	}	
}