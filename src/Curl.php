<?php

namespace HttpHelper;

/**
 * Class Curl
 * @package HttpHelper
 * @author Jan Novotny <naj.yntovon@gmail.com>
 */
class Curl {

	/** @var Resource */
	protected $handle;

	/** @var int */
	private $redirectCount = 0;

	public function __construct() {
		if (function_exists('curl_init')) {
			$this->handle = curl_init();
			@curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
			@curl_setopt($this->handle, CURLOPT_HEADER, 1);
		}
	}

	/**
	 * @return Resource
	 */
	public function getHandle() {
		return $this->handle;
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
	 * Set request connection timeout.
	 * @param int $seconds number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
	 */
	public function setConnectTimeout($seconds) {
		@curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, $seconds);
	}

	/**
	 * @param array $headers
	 */
	public function setHeaders($headers) {
		if (count($headers)>0) {
			$tmp = array();
			foreach ($headers as $key => $value) {
				$tmp[] = "$key: $value";
			}
			@curl_setopt($this->handle, CURLOPT_HTTPHEADER, $tmp);
		}
	}

	/**
	 * @param array $cookies
	 */
	public function setCookies($cookies) {
		if (count($cookies)>0) {
			$tmp = array();
			foreach ($cookies as $cookie) {
				$tmp[] = $cookie;
			}
			if (count($tmp) > 0) {
				@curl_setopt($this->handle, CURLOPT_COOKIE, implode('; ', $tmp));
			}
		}
	}

	/**
	 * Sets CURLOPT_POSTFIELDS
	 * @param array $fields
	 * @param string $method
	 * @param string $contentType
	 */
	private function setPostFields($fields, $method, $contentType) {
		if (($method == Request::POST || $method == Request::PUT ||
			$method == Request::DELETE)) {
			if ($contentType && preg_match('/urlencoded/i', $contentType)) {
				@curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($fields));
			} else {
				@curl_setopt($this->handle, CURLOPT_POSTFIELDS, $fields);
			}
		}
	}

	/**
	 * @param string $method
	 */
	private function setMethod($method) {
		switch ($method){
			case Request::GET: curl_setopt($this->handle, CURLOPT_HTTPGET, TRUE);
				break;
			case Request::PUT: curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, Request::PUT);
				break;
			case Request::POST: curl_setopt($this->handle, CURLOPT_POST, TRUE);
				break;
			case Request::HEAD: curl_setopt($this->handle, CURLOPT_NOBODY, TRUE);
				break;
			case Request::DELETE: curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, Request::DELETE);
				break;
		}
	}

	/**
	 * Sets following request and sends it
	 * @param Request $request
	 * @param Response $response
	 * @param int $maxRedirects
	 * @return Response
	 * @throws RequestException
	 */
	private function doFollow(Request $request, Response $response, $maxRedirects) {
		/// Change method to GET
		$request->setMethod(Request::GET);
		/// Find out location
		$location = $response->getHeader('Location');
		if (strpos($location, '/') == 0 && $request->getUrl() != NULL) {
			$parsed = @parse_url($request->getUrl());
			if ($parsed == FALSE){
				throw new \InvalidArgumentException('Invalid URL, got: ' . $request->getUrl());
			}
			$url = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
			if (isset($parsed['user']) && isset($parsed['pass'])) {
				$url .= $parsed['user'] . ':' . $parsed['pass'] . '@';
			}
			$url .= isset($parsed['host']) ? $parsed['host'] : '';
			$url .= isset($parsed['port']) ? ':' . $parsed['port'] : '';
			$url .= $location;
			$location = $url;
		}
		$request->setUrl($location);
		$request->addHeaders(array(
			'Referer' => $request->getUrl()
		));
		$this->redirectCount++;
		return $this->send($request, true, $maxRedirects);
	}

	/**
	 * @param Request $request
	 * @param bool $redirectsEnabled
	 * @param int $maxRedirects
	 * @return Response
	 * @throws RequestException
	 */
	public function send(Request $request, $redirectsEnabled=false, $maxRedirects = 20) {
		/// Is there curl_init function
		if (!function_exists('curl_init')) {
			throw new RequestException('curl_init doesn\'t exists. Is curl extension instaled and enabled?');
		}

		/// Dont send request without url
		if (!$request->getUrl()) {
			return new Response();
		}
		$url = $request->getUrl();
		if (!empty($this->params)) {
			$url .= '?' . http_build_query($this->params);
		}
		@curl_setopt($this->handle, CURLOPT_URL, $url);

		if ($request->getJSON() !== null) {
			$request->addHeaders(array('Content-Type' => 'application/json'));
		}

		$this->setCookies($request->getCookies());
		$this->setHeaders($request->getHeaders());
		$this->setMethod($request->getMethod());

		/// Set post fields to CURL handle
		if (count($request->getPostFields())>0 || $request->getJSON()) {
			$fields = $request->getJSON() ? json_encode($request->getJSON()) : $request->getPostFields();
			$this->setPostFields($fields, $request->getMethod(), $request->getHeader('Content-Type'));
		}

		/// Execute
		$response = curl_exec($this->handle);

		/// Remove content type header to not be used in further requests
		$request->unsetHeader('Content-Type');

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
		$response = new Response(curl_getinfo($this->handle, CURLINFO_HTTP_CODE), $headers, $body);

		/// If cookiesEnabled then call addCookies with response cookies
		if ($request->isCookiesEnabled()) {
			$request->addCookies($response->getCookies());
		}

		/// Are redirects enabled? (Also check redirects count)
		if ($redirectsEnabled && ($response->getCode() == 301 ||
				$response->getCode()==302 || $response->getCode()==303)
			&& $this->redirectCount < $maxRedirects) {
			$response = $this->doFollow($request, $response, $maxRedirects);
		} else {
			if ($this->redirectCount == $maxRedirects) {
				throw new RequestException("Maximum of " . $maxRedirects . " redirects reached.");
			}
			$this->redirectCount = 0;
		}
		return $response;
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		curl_close($this->handle);
	}

}