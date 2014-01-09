<?php

require 'Tester/bootstrap.php';
require '../Http.php';

use Tester\Assert,
	HttpHelper\Request;

$obj = new Request();

Assert::same(array(), $obj->getHeader());
Assert::same(NULL, $obj->getHeader('Location'));
Assert::same('secret agent', $obj->getHeader('user-agent','secret agent'));

$obj->setHeaders(array(
	'user-agent' => 'secret agent'
	));

Assert::same('secret agent', $obj->getHeader('user-agent'));

$obj->addHeaders(array(
	'Referer' => 'http://foo.com'
	));

Assert::same('secret agent', $obj->getHeader('user-agent'));
Assert::same('http://foo.com', $obj->getHeader('Referer'));
Assert::notSame('Chrome', $obj->getHeader('user-agent', 'Chrome'));

Assert::exception(function() { 
	$obj = new Request();
	$obj->setHeaders('Header');
}
, 'InvalidArgumentException');
