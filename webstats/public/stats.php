<?php

namespace Paheko\Plugin\Webstats;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	http_response_code(405);
	return;
}

$bots_regexp = '/Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|Exabot|facebookexternalhit|facebot|curl|ia_archiver|GoogleImageProxy|MJ12bot|MegaIndex|https?:\/\/|urllib|libwww|Yacy|wget/i';

if (preg_match($bots_regexp, $_SERVER['HTTP_USER_AGENT'] ?? '')) {
	http_response_code(404);
	return;
}

if (empty($_SERVER['CONTENT_LENGTH'])) {
	http_response_code(400);
	return;
}

$body = file_get_contents('php://input');
$data = json_decode($body);

if (!($data instanceof \stdClass)) {
	http_response_code(400);
	return;
}

Stats::store($data);

http_response_code(204);