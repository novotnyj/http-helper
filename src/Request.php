<?php

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
	 * @var null|array
	 */
	private $json = null;

	/**
	 * @var array
	 */
	private $params = array();

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
	 * Unsets given header
	 * @param string $header
	 */
	public function unsetHeader($header) {
		if (array_key_exists($header, $this->headers)) {
			unset($this->headers[$header]);
		}
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
	 * @param $data Array of name=>value pairs
	 */
	public function setPostFields($data){
		$this->post = array();
		$this->addPostFields($data);
	}

	/**
	 * Sets JSON data to be sent.
	 * @param array $data
	 */
	public function setJSON($data) {
		if (!is_array($data)) {
			throw new \InvalidArgumentException('JSON must be array');
		}
		$this->json = $data;
	}

	/**
	 * Set the URL params, overwriting previously set URL params.
	 * @param $params Array of name=>value pairs
	 */
	public function setParams($params) {
		$this->params = array();
		$this->addParams($params);
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
			$this->post[$key] = (string)$value;
		}
	}

	/**
	 * Adds URL params, leaving previously set unchanged, unless a post entry with the same name already exists.
	 * @param $data Array of name=>value pairs
	 * @throws \InvalidArgumentException
	 */
	public function addParams($data) {
		if (!is_array($data)) {
			throw new \InvalidArgumentException("Array required, got: " . gettype($data));
		}
		foreach ($data as $key => $value) {
			$this->params[$key] = (string)$value;
		}
	}

	/**
	 * Returns Post fields
	 * @return array
	 */
	public function getPostFields() {
		return $this->post;
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
	 *  Filters and sets cookies to cURL handle.
	 */
	private function setUpCookies() {
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
	}

	/**
	 * Sets headers to cURL handle.
	 */
	private function setUpHeaders() {
		if (count($this->headers)>0) {
			$tmp = array();
			foreach ($this->headers as $key => $value) {
				$tmp[] = "$key: $value";
			}
			@curl_setopt($this->handle, CURLOPT_HTTPHEADER, $tmp);
		}
	}

	/**
	 * Sets CURLOPT_POSTFIELDS
	 * @param array $fields
	 */
	private function setCurlPostFields($fields) {
		if (($this->method == self::POST || $this->method == self::PUT || $this->method == self::DELETE)) {
			if (isset($this->headers['Content-Type']) && preg_match('/urlencoded/i', $this->headers['Content-Type'])) {
				@curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($fields));
			} else {
				@curl_setopt($this->handle, CURLOPT_POSTFIELDS, $fields);
			}
		}
	}

	/**
	 * Sets following request and sends it
	 * @throws RequestException
	 */
	private function doFollow() {
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
	}

	/**
	 * Send the HTTP request.
	 * @return Response
	 * @throws \LogicException
	 * @throws RequestException
	 */
	public function send() {

		/// Is there curl_init function
		if (!function_exists('curl_init')) {
			throw new RequestException('curl_init doesn\'t exists. Is curl extension instaled and enabled?');
		}

		/// Dont send request without url
		if (!$this->hasUrl) {
			return new Response();
		}
		$url = $this->rawUrl;
		if (!empty($this->params)) {
			$url .= '?' . http_build_query($this->params);
		}
		@curl_setopt($this->handle, CURLOPT_URL, $url);

		if ($this->json !== null) {
			$this->addHeaders(array('Content-Type' => 'application/json'));
		}

		$this->setUpCookies();
		$this->setUpHeaders();

		/// Set post fields to CURL handle
		if (count($this->post)>0 || $this->json) {
			$this->setCurlPostFields($this->json ? json_encode($this->json) : $this->post);
		}

		/// Execute
		$response = curl_exec($this->handle);

		/// Remove content type header to not be used in further requests
		$this->unsetHeader('Content-Type');

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
			$this->doFollow();
		} else {
			if ($this->redirectCount == $this->maxRedirects) {
				throw new RequestException("Maximum of " . $this->maxRedirects . " redirects reached.");
			}
			$this->redirectCount = 0;
		}
		return $this->response;
	}

	/**
	 * Returns curl handle
	 * @return resource
	 */
	public function getCurlHandle() {
		return $this->handle;
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		curl_close($this->handle);
	}

}