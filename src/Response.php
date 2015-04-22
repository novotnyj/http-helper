<?php

namespace HttpHelper;

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
	 * @var string
	 */
	private $decodedBody;

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
		$contentEncoding = $this->getHeader('Content-Encoding','');
		//$transferEncoding = $this->getHeader('Transfer-Encoding','');

		if ($contentEncoding != '') {
			if (strtolower($contentEncoding) == 'gzip') {
				$this->body = @gzdecode($this->body);
			}
		}
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
			$this->decodedBody = iconv($charset, 'UTF-8', $this->body);
			return $this->decodedBody;
		}
		$this->decodedBody = trim($this->body);
		return $this->decodedBody;
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
		if ($this->decodedBody == '') {
			return $this->decodeBody();
		} else {
			return $this->decodedBody;
		}
	}

	/**
	 * Get raw response body.
	 * @return string
	 */
	public function getRawBody() {
		return $this->body;
	}

}