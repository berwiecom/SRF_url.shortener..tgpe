<?php
require('database.php');

$code = substr($_SERVER['REQUEST_URI'], 1); // $_SERVER['REQUEST_URI'] = string(5) "/path"

$code = preg_replace('#\?.*#', '', $code);

if ($code == '') { // index homepage
	include('web.php');
	exit;
}

if (!preg_match('#^[\w_-]+(\.[a-z]+)?$#', $code))
	error(400, 'Code invalid');

[$code, $ext] = explode('.', $code, 2);

$db = new MyDB();

if (!$data = $db->findByCode($code))
	error(404, 'Code not found');

$url = $data['url'];

if (preg_match("#(TelegramBot|TwitterBot|PlurkBot|facebookexternalhit|ZXing)#i", $_SERVER['HTTP_USER_AGENT'])
	|| (substr($url, -strlen($ext)) == $ext && in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp']))) {
	header("Location: $url");
	exit;
}

echo <<<EOF
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nosnippet">
</head>
<body>
	<p>Redirecting to <a id="url" href="$url">$url</a>....</p>
	<script>
		window.onload = function() {
			var target = document.getElementById('url').href;
			window.location.replace(target);
		}
	</script>
</body>
</html>
EOF;


function error(int $code, string $msg) {
	switch ($code) {
	case 400:
		header('HTTP/2 400 Bad Request');
		break;
	case 404:
		header("HTTP/2 404 Not Found");
		break;
	}

	if (strlen($_SERVER['HTTP_USER_AGENT']) < 20)
		exit($msg); // Message Only for bots

	echo <<<EOF
<html>
<head>
	<meta http-equiv="refresh" content="10;url=https://tg.pe/" />
</head>
<body>
	<center>
		<h1>$msg</h1>
		<hr>
		<p>Please check your URL again.</p>
		<p>Here is <a href="https://tg.pe/">tg.pe</a> URL Shortener, provided by <a href="https://tg.pe/dev">@SeanChannel</a></p>
	</center>
</body>
EOF;
	exit;
}
