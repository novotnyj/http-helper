<?php

namespace HttpHelper;

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
		return null;
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