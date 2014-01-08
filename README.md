# HTTP
Recently I have to work a lot with sending HTTP requests and parsing responses in PHP. I have found 2 options to do this:
* curl
* pecl_http
Curl works nice (except CURLOPT_FOLLOWLOCATION and open_basedir or safe_mode). But I didn't like coding it in C-like style, I wanted something object oriented.
HttpRequest from pecl_http was ok for my usage and it is object oriented. But I had to install pecl_http everywere I wanted to use it.
That's why I have decided to write this library on top of CURL to ease up dealing with HTTP requests.
