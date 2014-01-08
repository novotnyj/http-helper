<?php

/*
New BSD License

Copyright (c) 2014 Jan Novotny (naj.yntovon@gmail.com)
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

	* Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	* Redistributions in binary form must reproduce the above copyright notice,
	this list of conditions and the following disclaimer in the documentation
	and/or other materials provided with the distribution.

	* Neither the name of author nor the names of its contributors
	may be used to endorse or promote products derived from this software
	without specific prior written permission.

This software is provided by the copyright holders and contributors "as is" and
any express or implied warranties, including, but not limited to, the implied
warranties of merchantability and fitness for a particular purpose are
disclaimed. In no event shall the copyright owner or contributors be liable for
any direct, indirect, incidental, special, exemplary, or consequential damages
(including, but not limited to, procurement of substitute goods or services;
loss of use, data, or profits; or business interruption) however caused and on
any theory of liability, whether in contract, strict liability, or tort
(including negligence or otherwise) arising in any way out of the use of this
software, even if advised of the possibility of such damage.
*/

namespace Http;

class Request {

	const GET = 'GET';
	const PUT = 'PUT';
	const POST = 'POST';
	const DELETE = 'DELETE';
	const HEAD = 'HEAD';

	private $handle;

	private $response;

	private $cookies = array();

	private $headers = array();

	private $method;

	private $hasUrl = FALSE;

	private $post = array();

	private $cookiesEnabled = FALSE;

	private $autoFollow = FALSE;
	
	private $maxRedirects;

	private $redirectCount = 0;

	private $url = NULL;

	private $rawUrl = '';

	public function __construct($url = NULL, $method = NULL){
		$this->response = new Response();
		$this->handle = curl_init();
		@curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
		@curl_setopt($this->handle, CURLOPT_HEADER, 1);
		if ($url) $this->setUrl($url);
		if ($method) $this->setMethod($method);
	}

	public function setMethod($method){
		switch ($method){
			case self::GET: curl_setopt($this->handle, CURLOPT_HTTPGET, TRUE); 
							$this->method = self::GET; 
							break;
			case self::PUT: curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, self::PUT); 
							$this->method = self::PUT; 
							break;
			case self::POST: curl_setopt($this->handle, CURLOPT_POST, TRUE); 
							$this->method = self::POST;
							break;
			case self::HEAD: curl_setopt($this->handle, CURLOPT_NOBODY, TRUE); 
							$this->method = self::HEAD;
							break;
			case self::DELETE: curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, self::DELETE); 
							$this->method = self::DELETE;				
							break;
			default:
				throw new InvalidArgumentException('Unknown method: ' . $method);
		}
	}

	public function getMethod() {
		return $this->method;
	}

	public function setUrl($url){
		if (is_string($url)){
			$this->url = @parse_url($url);
			if ($this->url == FALSE){
				throw new InvalidArgumentException('Invalid URL, got: ' . $url);			
			}
			$this->rawUrl = $url;
			@curl_setopt($this->handle, CURLOPT_URL, $url);
			$this->hasUrl = TRUE;
		} else {
			throw new InvalidArgumentException('String required, got: ' . gettype($url));
		}
	}

	public function getResponseCode(){
		return $this->response->getCode();
	}

	public function getResponseBody(){
		return $this->response->getBody();
	}

	public function getResponseHeaders(){
		return $this->response->getHeaders();
	}

	public function getResponseCookies(){
		return $this->response->getCookies();
	}

	public function enableCookies() {
		$this->cookiesEnabled = TRUE;
	}

	public function disableCookies() {
		$this->cookiesEnabled = FALSE;
	}

	public function setCookies($cookies){
		$this->cookies = array();
		return $this->addCookies($cookies);
	}

	public function addCookies($cookies){
		if (is_array($cookies)){
			foreach($cookies as $name => $value){
				if ($value instanceof Cookie){
					$this->cookies[$value->name] = $value;
					continue;
				}
				$this->cookies[$key] = new Cookie($key, $value);
			}
			return TRUE;
		} else {
			throw new InvalidArgumentException("Array required, got:" . gettype($cookies));
		}
	}

	public function getHeaders() {
		return $this->headers;
	}

	public function setHeaders($headers) {
		$this->headers = array();
		$this->addHeaders($headers);
	}

	public function addHeaders($headers) {
		if (!is_array($headers)){
			throw new InvalidArgumentException("Array required, got: " . gettype($headers));
		}
		array_merge($this->headers, $headers);
	}

	public function getCookies(){
		return $this->cookies;
	}

	public function setPostFields($data){
		$this->post = array();
		return $this->addPostFields($data);
	}

	public function addPostFields($data) {
		if (!is_array($data)) {
			throw new InvalidArgumentException("Array required, got: " . gettype($data));
		}
		foreach ($data as $key => $value) {
			if (is_string($value)){
				$this->post[$key] = $value;
			}
		}
		return @curl_setopt($this->handle, CURLOPT_POSTFIELDS, $this->post);
	}

	public function enableAutoFollow($limit = 20) {
		$this->autoFollow = TRUE;
		$this->maxRedirects = $limit;
	}

	public function disableAutoFollow() {
		$this->autoFollow = FALSE;
	}

	public function send() {
		if (!$this->hasUrl) {
			/// Cannot send request without url
			throw new LogicException("Cannot send request without URL.");
		}
		/// @Todo: filter cookies -> check domain, path, Secure, HttpOnly and maybe Expires and Max-Age properties
		if (count($this->cookies)>0) {
			@curl_setopt($this->handle, CURLOPT_COOKIE, implode('; ', $this->cookies));
		}
		$response = curl_exec($this->handle);
		/// CURL error
		if ($response == FALSE) {
			throw new RequestException("CURL error [" . curl_errno($this->handle) . "]: " . curl_error($this->handle),
										curl_errno($this->handle));
		}
		/// Http 100 workaround
		$parts = explode("\r\n\r\nHTTP/", $response);
		$parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
		list($headers, $body) = explode("\r\n\r\n", $parts, 2);
		$this->response = new Response(curl_getinfo($this->handle, CURLINFO_HTTP_CODE), $headers,$body);
		
		/// If cookiesEnabled call setCookies with response cookies
		if ($this->cookiesEnabled) {
			$this->addCookies($this->response->getCookies());
		}
		if ($this->autoFollow && ($this->response->getCode() == 302 || $this->response->getCode()==301) 
			&& $this->redirectCount < $this->maxRedirects) {
			$this->setMethod(self::GET);
			$location = $this->response->getHeaders()['Location'];
			if (strpos($location, '/') == 0 && $this->url != NULL){
				$url = isset($this->url['scheme']) ? $this->url['scheme'] . '://' : '';
				if (isset($this->url['user']) && isset($this->url['pass'])){
					$url .= $this->url['user'] . ':' . $this->url['pass'] . '@';
				}
				$url .= isset($this->url['host']) ? $this->url['host'] : '';
				$url .= isset($this->url['port']) ? ':' . $this->url['port'] : '';
				$url .= $location;
				$location = $url;
			}
			$this->setUrl($this->response->getHeaders()['Location']);
			$this->redirectCount++;
			$this->response = $this->send();
		} else {
			$this->redirectCount = 0;
		}
		return $this->response;
	}

}

class Response {

	private $code;
	private $body;
	private $headers = array();	
	private $cookies = array();
	
	public function __construct($code = 0, $headers = '', $body = '') {
		$this->code = $code;
		if (preg_match_all('/^(?<key>[^\:\n]+)\:(?<value>.+)$/m', $headers, $m)){
			foreach ($m['key'] as $i => $key){
				/// Parse response cookies
				if (strtolower($key) == 'set-cookie'){
					$this->parseCookie(trim($m['value'][$i]));
					/// Keep set-cookie out of responseHeaders array
					continue;
				}
				$this->headers[$key] = trim($m['value'][$i]);
			}			
		}
		$this->body = $body;
	}

	private function decodeBody(){
		/**
		* Credit to Paul Tarjan's answer on:
		* http://stackoverflow.com/questions/2510868/php-convert-curl-exec-output-to-utf8
		*/
		
		$type = $this->getHeader("Content-Type","");
		/* 1: HTTP Content-Type: header */
		preg_match('@([\w/+]+)(;\s*charset=(\S+))?@i', $type, $m);
	    if (isset($m[3])) {
    	    $charset = $m[3];
		}
		/* 2: <meta> element in the page */
		if (!isset($charset)) {
        	preg_match('@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $this->body, $m);
        	if (isset($m[3])) {
        	    $charset = $m[3];
			}
		}
    	/* 3: <xml> element in the page */
    	if (!isset($charset)) {
    	    preg_match('@<\?xml.+encoding="([^\s"]+)@si', $this->body, $m);
    	    if (isset($m[1])) {
    	        $charset = $m[1];
			}
	    }
	   /* 4: PHP's heuristic detection */
    	if (!isset($charset)) {
    	    $encoding = mb_detect_encoding($this->body);
    	    if ($encoding) {
    	        $charset = $encoding;
			}
	    }
		/* 5: Default for HTML */
    	if (!isset($charset)) {
    	    if (strstr($type, "text/html") === 0)
    	        $charset = "ISO 8859-1";
	    }
		/* Convert it if it is anything but UTF-8 */
    	/* You can change "UTF-8"  to "UTF-8//IGNORE" to 
	       ignore conversion errors and still output something reasonable */
    	if (isset($charset) && strtoupper($charset) != "UTF-8")
    	    return iconv($charset, 'UTF-8', $this->body);
		return $this->body;
	}

	private function parseCookie($value){
		if (preg_match_all('/(?<key>[^\=]+)[\=]{0,1}(?<value>[^\;]*)[\;]{0,1}\s*/m', $value, $m)){
			$cookie = new Cookie($m['key'][0]);
			foreach($m['key'] as $i => $key) {
				if ($key == $cookie->name){
					$cookie->value = $m['value'][$i];
					continue;
				}
				///$cookie[$key] = $m['value'][$i];
				$cookie->__set($key, $m['value'][$i]);
			}			
			$this->cookies[] = $cookie;
		}
	}

	public function getCode() {
		return $this->code;
	}

	public function getCookies() {
		return $this->cookies;
	}

	public function getHeaders() {
		return $this->headers;
	}

	public function getHeader($name, $default = null){
		return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
	}

	public function getBody() {
		return $this->decodeBody();
	}

	public function getRawBody() {
		return $this->body;
	}

}

class Cookie {

	private $data = array();

	public function __construct($name = '', $value = ''){
		$this->data['name'] = $name;
		$this->data['value'] = $value;
	}

	public function __get($name) { 
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
	}

	public function __set($name, $value){
		$this->data[$name] = $value;
	}	

	public function __toString(){
		return $this->data['name'] . '=' . $this->data['value'];
	}
}

class RequestException extends Exception { }
