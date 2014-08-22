<?php

require '../vendor/autoload.php';

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
$response = $obj->send();
Assert::same(0, $response->getCode());


$obj = new Request('http://foo.com', Request::POST);
Assert::same('http://foo.com', $obj->getUrl());
Assert::same(Request::POST, $obj->getMethod());

