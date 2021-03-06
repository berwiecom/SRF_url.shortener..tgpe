<?php
/* This file hard-linked from sean.taipei/telegram/tgpe.php to tg.pe/telegram.php */
if (!isset($TG))
	exit;

require('/usr/share/nginx/tg.pe/config.php');
require('/usr/share/nginx/tg.pe/database.php');
$db = new MyDB();


/* Message Texts */
$msg_help = <<<EOF
Hello, just send me an URL and I'll short it.

You can also specifiy your custom short code.

<b>Usage</b>
For instance, send me the following text:
<pre>https://t.me/tgpebot bot</pre> or,

Note: You can use <b>a-z</b>, <b>A-Z</b> and <b>0-9</b>.
Minimum length is <b>3</b> characters.


<b>Commands</b>
/my - Show all your links.
/help - Show this message.

<b>About</b>
Developer: @SeanChannel
Source Code: tg.pe/repo
EOF;

if (strpos($TG->data['message']['from']['language_code'], 'de') !== false)
	$msg_help = <<<EOF
"Jetzt mach ich kurzen Prozess ;-)"

Hallo! Ich bin Dein kleiner "URL-Bot" und mache aus langen Web-Adressen kurze.
Sende mir einfach eine lange <a href="https://de.wikipedia.org/wiki/Uniform_Resource_Locator" title="URL: Mehr bei Wikipedia">URL</a> (Web-Adresse) und ich k&uuml;rze sie mit einer zufaelligen Buchstabenkombination.

<b>Beispiel</b>
Sende mir
<pre>https://de.wikipedia.org/wiki/Telegram</pre>
Und Du erh&uuml;lst:
<pre>tg.pe/n4x</pre>

Alternativ h&auml;ngst Du ein (aussagekraeftiges) Wort K&uuml;rzel an:
<pre>https://de.wikipedia.org/wiki/Telegram TlgRM</pre>
Mindestens 3 Zeichen aus aus <b>a-z</b>, <b>A-Z</b> and <b>0-9</b>.


<b>Kommandos</b>
Sende mir:
/my
und ich zeige Dir alle Deine (Kurz-)Links.
Diese Hilfe hier bekommst Du mit:
/help


<b>Allg. Info:</b>
Entwickler: @SeanChannel
&Uuml;bersetzer: @Berwie_com
Quell-Code: tg.pe/repo
EOF;


/* Allow Text in both message and photo caption */
$text = $TG->data['message']['text'] ?? $TG->data['message']['photo']['caption'] ?? '';

if (empty($text)) {
	if ($TG->ChatID > 0) # Private Message
		$TG->sendMsg([
			'parse_mode' => 'HTML',
			'text' => $msg_help
		]);
	exit;
}


/* Handle commands */
if (preg_match('#^[/!](?<cmd>\w+)(?:@' . $TG->botName . ')?(?:\s+(?<args>.+))?$#', $text, $matches)) {
	$cmd = strtolower($matches['cmd']);
	$args = $matches['args'] ?? '';
	switch ($cmd) {
	case 'my':
		$author = "TG{$TG->FromID}";
		$data = $db->findByAuthor($author);
		if (count($data) == 0) {
			$TG->sendMsg([
				'parse_mode' => 'HTML',
				'text' => $msg_help
			]);
			break;
		}

		$text = "You have <b>" . count($data) . "</b> shortened URLs:\n";
		for ($i=0; $i<count($data) && strlen($text)<4000; $i++) {
			if (mb_strlen($data[$i]['url']) > 40)
				$url = mb_substr($data[$i]['url'], 0, 25) . '...' . mb_substr($data[$i]['url'], -5);
			else
				$url = $data[$i]['url'];
			$url = $TG->enHTML($url);

			if (!($i%5))
				$text .= "\n";
			$text .= ($i+1) . ". tg.pe/{$data[$i]['code']}  ";
			$text .= "(<code>$url</code>)\n";
		}
		$TG->sendMsg([
			'text' => $text,
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true
		]);
		break;
	case 'start':
	case 'help':
	default:
		$TG->sendMsg([
			'parse_mode' => 'HTML',
			'text' => $msg_help
		]);
		break;
	}

	exit;
}


if (strpos($text, '.') !== false // Looks like URL
	&& strtolower(substr($text, 0, 4)) !== 'http') // Not start with HTTP or HTTPS
	$text = "https://$text"; // Prepend HTTPS scheme

/* Vaildate URL */
if (!preg_match('#^(?P<url>(?P<scheme>https?)://(?P<domain>[^\n\s@%/]+\.[^\n\s@%/]+)(?<path>/[^\n\s]*)?)(?:[\n\s]+(?P<code>[a-zA-Z0-9]+))?$#iu', $text, $matches)) {
	if ($TG->ChatID > 0) # Private Message
		$TG->sendMsg([
			'parse_mode' => 'HTML',
			'text' => $msg_help
		]);
	exit;
}

$scheme = $matches['scheme'];
$url = $matches['url'];
$domain = $matches['domain'];
$code = $matches['code'] ?? '';
$author = "TG{$TG->FromID}";

if (idn_to_ascii($domain) !== $domain) {
	$domain = idn_to_ascii($domain);
	$path = $matches['path'] ?? '/';
	$url = "$scheme://$domain$path";
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
	$TG->sendMsg([
		'text' => 'Please send a vaild URL.'
	]);
	exit;
}

if (strtolower(substr($domain, -5)) == 'tg.pe') {
	$TG->sendMsg([
		'text' => 'This URL is short enough.'
	]);
	exit;
}


if (strlen($code) > 16) { /* Check Code Length */
	$TG->sendMsg([
		'text' => "Code too long"
	]);
	exit;
} else if (strlen($code) >= 3) { /* Check Code Existance */
	if ($data = $db->findByCode($code)) {
		$TG->sendMsg([
			'text' => "Already Exist: https://tg.pe/$code\n\n" .
			"Original URL: {$data['url']}"
		]);
		exit;
	}
} else if (strlen($code) === 0) { /* Allocate 3-char not-exists code */
	if ($code = $db->findCodeByUrl($url)) {
		$TG->sendMsg([
			'text' => "tg.pe/$code"
		]);
		exit;
	} else
		$code = $db->allocateCode();
} else { /* 1 or 2 char only allow admins */
	if (!in_array($TG->FromID, TG_ADMINS)) {
		$TG->sendMsg([
			'text' => "ERROR: Custom word must be at least 3 chars long."
		]);
		exit;
	}
}

if (strpos($url, "fbclid=")) {
	$TG->sendMsg([
		'parse_mode' => 'HTML',
		'text' => "Hey! Please remove <b>fbclid=ForExampleBlaBlaBla</b> before sharing URLs.\n\n" .
		"The &#39;Facebook Click Identifier&#39; for interaction tracking against user privacy.\n\n" .
		"To <b>auto-remove</b> it, install this <a title="Browser addon removes ads and user interaction tracking on Facebook™" href='https://addons.mozilla.org/en-US/firefox/addon/facebook-tracking-removal/'>browser addon</a>.",
		'reply_markup' => [
			'inline_keyboard' => [
				[
					[
						'text' => 'Firefox',
						'url' => 'https://addons.mozilla.org/en-US/firefox/addon/facebook-tracking-removal/'
					],
					[
						'text' => 'Chrome',
						'url' => 'https://chrome.google.com/webstore/detail/tracking-ad-removal-for-f/ldeofbdmhnnocclkaddcnamhbhanaiaj'
					]
				]
			]
		]
	]);
	exit;
}
/* Both $url and $code should be clean */


/* Create Record */
$error = $db->insert($code, $url, $author);

if ($error[0] === '00000')
	$TG->sendMsg([
		'text' => "Success!\n\nhttps://tg.pe/$code"
	]);
else
	$TG->sendMsg([
		'text' => "ERROR: Something went wrong, please contact @S_ean\n\n" .
		"Code: $code\n" .
		"URL: $url\n" .
		"Author: $author\n\n" .
		"PDO Error Info:\n" .
		json_encode($error, JSON_PRETTY_PRINT)
	]);
