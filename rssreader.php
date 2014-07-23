<?php

function getRssFeed($feed_uri, $reload = false)
{
	$meta_file = md5($feed_uri) . '.json';
	$meta = @json_decode(@file_get_contents($meta_file), true);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $feed_uri);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	if (!$reload) {
		if (isset($meta['etag']))
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('If-None-Match: ' . $meta['etag']));
		if (isset($meta['lastModified']))
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('If-Modified-Since: ' . $meta['lastModified']));
	}

	$response = curl_exec($ch);
	$info = curl_getinfo($ch);

	$http_code = $info['http_code'];
	if ($http_code !== 200)
		return false;

	$headers = substr($response, 0, $info['header_size']);
	$headers_arr = array();
	foreach (explode("\n", $headers) as $h) {
		$h = explode(':', $h, 2);
		if (!isset($h[1])) continue;
		$key = $h[0];
		$value = trim($h[1]);
		if (!isset($headers_arr[$key]))
			$headers_arr[$key] = $value;
		elseif (is_array($headers[$key]))
			$headers_arr[$key][] = $value;
		else
			$headers_arr[$key] = array($headers_arr[$key], $value);
	}

	$meta = array();
	if (isset($headers_arr['Etag']))
		$meta['etag'] = $headers_arr['Etag'];
	if (isset($headers_arr['Last-Modified']))
		$meta['lastModified'] = $headers_arr['Last-Modified'];
	file_put_contents($meta_file, json_encode($meta));

	$body = substr($response, $info['header_size']);

	return @simplexml_load_string($body);
}
