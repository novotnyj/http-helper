<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Tester\Assert,
	HttpHelper\Request,
	HttpHelper\RequestException;

$obj = new Request();
$obj->enableCookies();
$obj->enableRedirects();

$obj->setParams(array(
	'q' => 'php'
));
$obj->setUrl('www.google.com');

try {
	$response = $obj->send();
	Assert::same($response->getCode(), 200);
} catch(RequestException $e) {
	echo $e->getMessage();
}
