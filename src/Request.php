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

	/** @var Curl */
	private $curl;

	/** @var Response */
	private $response;

	/** @var array */
	private $cookies = array();

	/** @var array */
	private $headers = array();

	/** @var string */
	private $method = self::GET;

	/** @var array */
	private $post = array();

	/** @var null|array */
	private $json = null;

	/** @var array */
	private $params = array();

	/** @var bool */
	private $cookiesEnabled = FALSE;

	/** @var bool */
	private $autoFollow = FALSE;

	/** @var int */
	private $maxRedirects = 20;

	/** @var string|null */
	private $url = NULL;

	/** @var string */
	private $rawUrl = '';

	/** @var int */
	private $connectionTimeout = 0;

	public function __construct($url = NULL, $method = NULL){
		$this->response = new Response();
		$this->curl = new Curl();
		if ($url) $this->setUrl($url);
		if ($method) $this->setMethod($method);
	}

	/**
	 * Set the request method.
	 * @param string $method Method to be used.
	 * @throws \InvalidArgumentException
	 */
	public function setMethod($method){
		switch ($method){
			case self::GET: $this->method = self::GET;
				break;
			case self::PUT: $this->method = self::PUT;
				break;
			case self::POST: $this->method = self::POST;
				break;
			case self::HEAD: $this->method = self::HEAD;
				break;
			case self::DELETE: $this->method = self::DELETE;
				break;
			default:
				throw new \InvalidArgumentException('Unknown method: ' . $method);
		}
	}

	/**
	 * Enables verbose output of CURL, usable for debugging.
	 */
	public function enableVerbose() {
		$this->curl->enableVerbose();
	}

	/**
	 * Disables verbose output of CURL.
	 */
	public function disableVerbose() {
		$this->curl->disableVerbose();
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
		$this->curl->setConnectTimeout($seconds);
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
	 * @return bool
	 */
	public function isCookiesEnabled() {
		return $this->cookiesEnabled;
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
	 * Gets JSON data
	 * @return array|null
	 */
	public function getJSON() {
		return $this->json;
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
	 * Send the HTTP request.
	 * @return Response
	 * @throws \LogicException
	 * @throws RequestException
	 */
	public function send() {
		$this->response = $this->curl->send($this, $this->autoFollow, $this->maxRedirects);
		return $this->response;
	}

	/**
	 * Returns curl handle
	 * @return resource
	 */
	public function getCurlHandle() {
		return $this->curl->getHandle();
	}

}