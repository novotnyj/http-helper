# HttpHelper

This PHP library was created as an alternative to the pecl_http. Goal was to make working with cURL easier and object oriented.

Basic methods has names similar to pecl_http HttpRequest. This should make switching between pecl_http and HttpHelper easier.

HttpHelper\Request is using cURL - make sure that you have cURL extension enabled in php.ini

## Requirements

HttpHelper requires PHP 5.3.1 or later with php5-curl installed.

## Instalation

HttpHelper can be installed using [Composer](https://getcomposer.org/). Package can be found in [Packagist](https://packagist.org/packages/novotnyj/http-helper) repository.

```
composer require novotnyj/http-helper
```

## Usage examples

### Get request

You can send HTTP request simillar to pecl_http:

```php
use HttpHelper\Request;

$request = new Request("http://www.google.com");
$request->send();
if ($request->getResponseCode() == 200) {
	echo $request->getResponseBody();
}
```

Or you can use HttpHelper\Response object returned by send method. Also wrap send method in try/catch block in case something went wrong:

```php
...
try {
	$response = $request->send();
	if ($response->getCode() == 200) {
		echo $response->getBody();
	}
} catch (RequestException $e) {
	echo $e->getMessage();
}
```

### Post request

To send a post request change method to POST

```php
use HttpHelper\Request;

$request = new Request("http://www.foo.com/sign/in", Request::POST);
$request->setPostFields(array(
	'login' => 'user',
	'password' => 'p4ssW0rd'
));
try {
	$response = $request->send();
	if ($response->getCode() == 200) {
		echo $response->getBody();
	}
} catch (RequestException $e) {
	echo $e->getMessage();
}
```

To send a file using POST request:

```php
...
$request->setMethod(Request::POST);
$request->setPostFields(array(
	'upload' => '@/absolute/path/to/file.ext'
));
...
```

To send a JSON POST request:
```php
...
$request->setMethod(Request::POST);
$request->setJSON(array(
	'login' => 'foo'
));
...
```

### Follow redirects

To enable automatic following Location header (for 301, 302 and 303 response codes):

```php
...
$request->enableRedirects();
...
```

By default this will follow Location only 20 times. After that RequestException will be raised from send method.
You can pass different limit as parameter to enableRedirects (0 means follow Location infinite times).
To follow Location for 999 times:

```php
$request->enableRedirects(999);
```

**Note:** This function is not using CURLOPT_FOLLOWLOCATION, so you should be fine even with open_basedir or safe_mode in your php.ini

### Use response cookies

To enable automatic using of response cookies for next request:

```php
...
$request->enableCookies();
...
```

NOTE: filtering response cookies by expiration date is not implemented yet. 

