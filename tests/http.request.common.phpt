<?php

require 'Tester/bootstrap.php';
require '../Http.php';

use Tester\Assert,
	HttpHelper\Request;

$obj = new Request();

Assert::same(Request::GET, $obj->getMethod());
Assert::same('', $obj->getUrl());


Assert::same(array(), $obj->getResponseHeader());
Assert::same(NULL, $obj->getResponseHeader('Location'));
Assert::same('foo.com', $obj->getResponseHeader('Location','foo.com'));
Assert::same(array(), $obj->getResponseCookies());
Assert::same(0, $obj->getResponseCode());

Assert::Exception( function() {
		$tmp = new Request();
		$tmp->send();	
	},
	'LogicException', 'Cannot send request without URL.');
