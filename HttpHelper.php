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

/**
 * @version 1.0.0
 */
namespace HttpHelper;

/**
 * Represents HTTP Request.
 * @package HttpHelper
 * @author Jan Novotny <naj.yntovon@gmail.com>
 */
class Request {

	/** GET method constant */
	const GET = 'GET';
	/** PUT method constant */
	const PUT = 'PUT';
	/** POST method constant */
	const POST = 'POST';
	/** DELETE method constant */
	const DELETE = 'DELETE';
	/** HEAD method constant */
	const HEAD = 'HEAD';

	/**
	 * @var resource
	 */
	private $handle;

	/**
	 * @var Response
	 */
	private $response;

	/**
	 * @var array
	 */
	private $cookies = array();

	/**
	 * @var array
	 */
	private $headers = array();

	/**
	 * @var string
	 */
	private $method = self::GET;

	/**
	 * @var bool
	 */
	private $hasUrl = FALSE;

	/**
	 * @var array
	 */
	private $post = array();

	/**
	 * @var bool
	 */
	private $cookiesEnabled = FALSE;

	/**
	 * @var bool
	 */
	private $autoFollow = FALSE;

	/**
	 * @var int
	 */
	private $maxRedirects = 20;

	/**
	 * @var int
	 */
	private $redirectCount = 0;

	/**
	 * @var string|null
	 */
	private $url = NULL;

	/**
	 * @var string
	 */
	private $rawUrl = '';

	/**
	* @var int
	*/
	private $connectionTimeout = 0;

	public function __construct($url = NULL, $method = NULL){
		$this->response = new Response();
		if (function_exists('curl_init')) {			
			$this->handle = curl_init();
			@curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
			@curl_setopt($this->handle, CURLOPT_HEADER, 1);		
			if ($url) $this->setUrl($url);
			if ($method) $this->setMethod($method);
		}				
	}

	/**
	 * Set the request method.
	 * @param string $method Method to be used.
	 * @throws \InvalidArgumentException
	 */
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
				throw new \InvalidArgumentException('Unknown method: ' . $method);
		}
	}
	
	/**
	* Enables verbose output of CURL, usable for debugging.
	*/
	public function enableVerbose() {
		@curl_setopt($this->handle, CURLOPT_VERBOSE, TRUE);
	}

	/**
	* Disables verbose output of CURL.
	*/
	public function disableVerbose() {
		@curl_setopt($this->handle, CURLOPT_VERBOSE, FALSE);
	}

	/**
	 * Get the previously set request method.
	 * @return string
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	* Set request connection timeout.
	* @param int $seconds number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
	*/
	public function setConnectTimeout($seconds) {
		$this->connectionTimeout = $seconds;
		@curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, $seconds);
	}

	/**
	* Get previously set request connect timeout.
	* @return int
	*/
	public function getConnectTimeout() {
		return $this->connectionTimeout;
	}

	/**
	 * Set the request URL.
	 * @param $url
	 * @throws \InvalidArgumentException
	 */
	public function setUrl($url){
		if (is_string($url)){
			$this->url = @parse_url($url);
			if ($this->url == FALSE){
				throw new \InvalidArgumentException('Invalid URL, got: ' . $url);
			}
			$this->rawUrl = $url;
			@curl_setopt($this->handle, CURLOPT_URL, $url);
			$this->hasUrl = TRUE;
		} else {
			throw new \InvalidArgumentException('String required, got: ' . gettype($url));
		}
	}

	/**
	* Get the previously set request URL.
	* @return string
	*/
	public function getUrl() {
		return $this->rawUrl;
	}

	/**
	 * Get the response code after the request has been sent.
	 * If redirects were allowed and several responses were received, the data references the last received response.
	 * @return int
	 */
	public function getResponseCode(){
		return $this->response->getCode();
	}

	/**
	 * Get the response body after the request has been sent.
	 * If redirects were allowed and several responses were received, the data references the last received response.
	 * @return string
	 */
	public function getResponseBody(){
		return $this->response->getBody();
	}

	/**
	 * Get response header(s) after the request has been sent.
	 * @param string $name Header name (optional), If name was given and and not found NULL is returned
	 * @param string $default Default value in case header with $name was not found
	 * @return array|string|NULL
	 */
	public function getResponseHeader($name = NULL, $default= NULL){
		return $this->response->getHeader($name, $default);
	}

	/**
	 * Get response cookie(s) after the request has been sent.
	 * If redirects were allowed and several responses were received, the data references the last received response.
	 * @return array Array of Cookie objects
	 */
	public function getResponseCookies(){
		return $this->response->getCookies();
	}

	/**
	 * Enable automatic sending of received cookies.
	 */
	public function enableCookies() {
		$this->cookiesEnabled = TRUE;
	}

	/**
	 * Disable automatic sending of received cookies.
	 */
	public function disableCookies() {
		$this->cookiesEnabled = FALSE;
	}

	/**
	 * Set custom cookies.
	 * @param $cookies
	 */
	public function setCookies($cookies){
		$this->cookies = array();
		$this->addCookies($cookies);
	}

	/**
	 * Add custom cookies.
	 * @param Cookie|array $cookies Array of Cookie objects, or $name => $value pairs
	 * @throws \InvalidArgumentException
	 */
	public function addCookies($cookies){
		if ($cookies instanceof Cookie) {
			$cookies = array($cookies);
		}
		if (is_array($cookies)){
			foreach($cookies as $name => $value){
				if ($value instanceof Cookie){
					$this->cookies[$value->name] = $value;
					continue;
				}
				$this->cookies[$name] = new Cookie($name, $value);
			}
		} else {
			throw new \InvalidArgumentException("Array required, got:" . gettype($cookies));
		}
	}

	/**
	* Get previously set request headers.
	* @return array Array of headers
	*/
	public function getHeaders() {
		return $this->getHeader();
	}

	/**
	 * Get previously set request headers.
	 * @param string $name (optional) Header name. If ommited array of all headers is returned.
	 * @param string $default (optional) Default value in case header with $name doesn't exists
	 * @return array|string
	 */
	public function getHeader($name = NULL, $default = NULL) {
		if ($name != NULL) {
			return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
		}
		return $this->headers;
	}

	/**
	 * Set request header name/value pairs.
	 * @param $headers
	 */
	public function setHeaders($headers) {
		$this->headers = array();
		$this->addHeaders($headers);
	}

	/**
	 * Add request header name/value pairs.
	 * @param $headers
	 * @throws \InvalidArgumentException
	 */
	public function addHeaders($headers) {
		if (!is_array($headers)){
			throw new \InvalidArgumentException("Array required, got: " . gettype($headers));
		}
		$this->headers = array_merge($this->headers, $headers);
	}

	/**
	 * Get previously set cookies.
	 * @return array
	 */
	public function getCookies(){
		return $this->cookies;
	}

	/**
	 * Set the POST data entries, overwriting previously set POST data.
	 * To send a file by POST request simply add it's absolute path as value.
	 * @param $data Array of name=>value pairs
	 */
	public function setPostFields($data){
		$this->post = array();
		return $this->addPostFields($data);
	}

	/**
	 * Adds POST data entries, leaving previously set unchanged, unless a post entry with the same name already exists.
	 * To send a file by POST request simply add it's absolute path as value.
	 * @param $data Array of name=>value pairs
	 * @throws \InvalidArgumentException
	 */
	public function addPostFields($data) {
		if (!is_array($data)) {
			throw new \InvalidArgumentException("Array required, got: " . gettype($data));
		}
		foreach ($data as $key => $value) {
			if (is_string($value)){
				$this->post[$key] = $value;
			}
		}
	}

	/**
	 * Enables automatic following of Location headers;
	 * @param int $limit
	 */
	public function enableRedirects($limit = 20) {
		$this->autoFollow = TRUE;
		$this->maxRedirects = $limit;
	}

	/**
	 * Disables automatic following of Location headers;
	 */
	public function disableRedirects() {
		$this->autoFollow = FALSE;
	}

	/**
	 * Send the HTTP request.
	 * @return Response
	 * @throws \LogicException
	 * @throws RequestException
	 */
	public function send() {
		if (!function_exists('curl_init')) {
			throw new RequestException('curl_init doesn\'t exists. Is curl extension instaled and enabled?');
		}
		if (!$this->hasUrl) {
			/// Cannot send request without url
			/// throw new \LogicException("Cannot send request without URL.");
			return new Response();
		}
		/// @Todo: filter cookies -> check domain, path, Secure, HttpOnly and maybe Expires and Max-Age properties
		if (count($this->cookies)>0) {
			$tmp = array();
			foreach ($this->cookies as $cookie) {
				if (isset($cookie->domain) && isset($this->url['host'])) {
					if (!preg_match('/'.preg_quote($cookie->domain).'/', $this->url['host'])){
						continue;
					}
				}
				if (isset($cookie->path) && isset($this->url['path'])) {
					if (!preg_match('/'.preg_quote($cookie->path, '/').'/', $this->url['path'])){
						continue;
					}
				}
				if (isset($cookie->Secure) && isset($this->url['scheme'])) {
					if (!preg_match('/^https$/', $this->url['scheme'])){
						continue;
					}
				}
				$tmp[] = $cookie;
			}
			if (count($tmp) > 0) {
				@curl_setopt($this->handle, CURLOPT_COOKIE, implode('; ', $tmp));
			}
		}
		/// Set Cookies to CURL handle
		if (count($this->headers)>0) {
			$tmp = array();	
			foreach ($this->headers as $key => $value) {				
				$tmp[] = "$key: $value";
			}
			@curl_setopt($this->handle, CURLOPT_HTTPHEADER, $tmp);
		}
		/// Set post fields to CURL handle
		if (count($this->post)>0 &&
			($this->method == self::POST || $this->method == self::PUT || $this->method == self::DELETE)) {
			@curl_setopt($this->handle, CURLOPT_POSTFIELDS, $this->post);
		}
		/// Execute
		$response = curl_exec($this->handle);
		/// Handle CURL error
		if ($response == FALSE) {
			throw new RequestException("CURL error [" . curl_errno($this->handle) . "]: " . curl_error($this->handle),
										curl_errno($this->handle));
		}
		/// Separate response header and body
		/// Http 100 workaround
		$parts = explode("\r\n\r\nHTTP/", $response);
		$parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
		list($headers, $body) = explode("\r\n\r\n", $parts, 2);
		$this->response = new Response(curl_getinfo($this->handle, CURLINFO_HTTP_CODE), $headers, $body);
		
		/// If cookiesEnabled then call addCookies with response cookies
		if ($this->cookiesEnabled) {
			$this->addCookies($this->response->getCookies());
		}
		/// Are redirects enabled? (Also check redirects count)
		if ($this->autoFollow && ($this->response->getCode() == 301 || 
			$this->response->getCode()==302 || $this->response->getCode()==303) 
			&& $this->redirectCount < $this->maxRedirects) {
			/// Change method to GET
			$this->setMethod(self::GET);
			/// Find out location
			$location = $this->response->getHeader('Location');
			if (strpos($location, '/') == 0 && $this->url != NULL) {
				$url = isset($this->url['scheme']) ? $this->url['scheme'] . '://' : '';
				if (isset($this->url['user']) && isset($this->url['pass'])) {
					$url .= $this->url['user'] . ':' . $this->url['pass'] . '@';
				}
				$url .= isset($this->url['host']) ? $this->url['host'] : '';
				$url .= isset($this->url['port']) ? ':' . $this->url['port'] : '';
				$url .= $location;
				$location = $url;
			}
			$this->setUrl($location);
			$this->addHeaders(array(
				'Referer' => $this->rawUrl
			));
			$this->redirectCount++;
			$this->response = $this->send();
		} else {
			if ($this->redirectCount == $this->maxRedirects) {
				throw new RequestException("Maximum of " . $this->maxRedirects . " redirects reached.");
			}
			$this->redirectCount = 0;
		}
		return $this->response;
	}

}

/**
 * Class Response, represents HTTP response
 * @package HttpHelper
 * @author Jan Novotny <naj.yntovon@gmail.com>
 */
class Response {

	/**
	 * @var int
	 */
	private $code;
	/**
	 * @var string
	 */
	private $body;
	/**
	 * @var array
	 */
	private $headers = array();
	/**
	 * @var array
	 */
	private $cookies = array();

	/**
	 * @param int $code
	 * @param string $headers
	 * @param string $body
	 */
	public function __construct($code = 0, $headers = '', $body = '') {
		$this->code = $code;
		if (preg_match_all('/^(?<key>[^\:\n]+)\:(?<value>.+)$/m', $headers, $m)) {
			foreach ($m['key'] as $i => $key) {
				/// Parse response cookies
				if (strtolower($key) == 'set-cookie') {
					$this->parseCookie(trim($m['value'][$i]));
					/// Keep set-cookie out of responseHeaders array
					continue;
				}
				$this->headers[$key] = trim($m['value'][$i]);
			}
		}
		$this->body = $body;
	}

	/**
	 * Try to find response body encoding and try to decode it.
	 * @return string
	 */
	private function decodeBody() {
		/**
		 * Credits to Paul Tarjan's answer on:
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
			if (strstr($type, "text/html") === 0) {
				$charset = "ISO 8859-1";
			}
		}
		/* Convert it if it is anything but UTF-8 */
		/* You can change "UTF-8"  to "UTF-8//IGNORE" to ignore conversion errors and still output something reasonable */
		if (isset($charset) && strtoupper($charset) != "UTF-8") {
			return iconv($charset, 'UTF-8', $this->body);
		}
		return $this->body;
	}

	/**
	 * Parses Set-Cookie value and adds it to $cookies array
	 * @param $value
	 */
	private function parseCookie($value) {
		if (preg_match_all('/(?<key>[^\=]+)[\=]{0,1}(?<value>[^\;]*)[\;]{0,1}\s*/m', $value, $m)) {
			$cookie = new Cookie($m['key'][0]);
			foreach($m['key'] as $i => $key) {
				if ($key == $cookie->name) {
					$cookie->value = $m['value'][$i];
					continue;
				}
				///$cookie[$key] = $m['value'][$i];
				$cookie->__set($key, $m['value'][$i]);
			}
			$this->cookies[] = $cookie;
		}
	}

	/**
	 * Get the response code.
	 * @return int
	 */
	public function getCode() {
		return $this->code;
	}

	/**
	 * Get array of response cookies.
	 * @return array Array of Cookie objects
	 */
	public function getCookies() {
		return $this->cookies;
	}

	/**
	 * Get array of response headers.
	 * @return array Array header_name => value
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Get value of response header $name or array of response headers if no name was given.
	 * @param null $name
	 * @param null $default Default value to return if name doesn't exists
	 * @return array|null
	 */
	public function getHeader($name = NULL, $default = NULL) {
		if ($name) {
			return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
		} else {
			return $this->headers;
		}
	}

	/**
	 * Get response body. Tries to find body encoding and decode it to UTF-8.
	 * @return string
	 */
	public function getBody() {
		return $this->decodeBody();
	}

	/**
	 * Get raw response body.
	 * @return string
	 */
	public function getRawBody() {
		return $this->body;
	}

}

/**
 * Class Cookie
 * @package HttpHelper
 * @author Jan Novotny <naj.yntovon@gmail.com>
 */
class Cookie {

	/**
	 * @var array
	 */
	private $data = array();

	public function __construct($name = '', $value = '') {
		$this->data['name'] = $name;
		$this->data['value'] = $value;
	}

	/**
	 * Magic get.
	 * @param $name
	 * @return mixed
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
	}

	/**
	 * Magic set.
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}

	/**
	* @param string $name
	* @return bool
	*/
	public function __isset($name) {
		return array_key_exists($name, $this->data);
	}

	/**
	 * String representation of Cookie in name=value format.
	 * @return string
	 */
	public function __toString() {
		return $this->data['name'] . '=' . $this->data['value'];
	}
}

/**
 * Class RequestException
 * @package HttpHelper
 * @author Jan Novotny <naj.yntovon@gmail.com>
 */
class RequestException extends \Exception { }
