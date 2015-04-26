<?php

require '../vendor/autoload.php';

use Tester\Assert,
	HttpHelper\Request,
	HttpHelper\RequestException;

$obj = new Request();
$obj->enableCookies();
$obj->enableRedirects();

$obj->setUrl('www.google.com');

try {
	$response = $obj->send();
	Assert::same($response->getCode(), 200);
} catch(RequestException $e) {
	echo $e->getMessage();
}
