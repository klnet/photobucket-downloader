<?php

$start = null;
$end = null;
if ($argc > 1) {
	$start = intval($argv[1]);
	echo("\$start = {$start}\n");
}
if ($argc > 2) {
	$end = intval($argv[2]);
	echo("\$end = {$end}\n");
}

error_reporting(E_ALL);
function strictErrorHandler($errno, $errstr, $errfile, $errline) {
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('strictErrorHandler');

$GLOBALS['gotSignal'] = 0;
function sigHandler($signo) {
	echo("\n\nReceived signal {$signo}\n\n");
	$GLOBALS['gotSignal'] = $signo;
}
pcntl_async_signals(true);
pcntl_signal(SIGINT, 'sigHandler');
pcntl_signal(SIGTERM, 'sigHandler');

require(__DIR__ . '/vendor/autoload.php');

$client = new GuzzleHttp\Client();

$errorLogPath = __DIR__ . '/error.' . time() . '.log';
# find . -type f -name 'showthread*.html' -exec grep -H 'src="http[^"]*photobucket[^"]*"' {} \; | tee ./grep.log
$lines = file(__DIR__ . '/grep.log');
$lineCount = count($lines);
echo("\$lineCount = {$lineCount}\n");
$i = 0;
$downloadCount = 0;
$errorCount = 0;
$skipCount = 0;
$recentCount = 100;
$recentAlertCount = 101;
$recentSizes = [];
foreach ($lines as $line) {
	$i++;
	if ($start !== null && $i < $start) {
		continue;
	}
	if ($end !== null && $i > $end) {
		break;
	}
	if ($GLOBALS['gotSignal'] !== 0) {
		break;
	}
	if (!preg_match('#src="(http[^"]+photobucket[^"]+)"#', $line, $matches)) {
		continue;
	}

	$src = $matches[1];

	$src = preg_replace('#^http://\[IMG\]#', '', $src);
	$src = preg_replace('#%5b/IMG%5d$#', '', $src);

	$path = $src;
	$path = str_replace('%20', '_', $path);
	$path = preg_replace('#^https?://#', '', $path);
	$path = preg_replace('#%\d+#', '', $path, -1, $count);
	$path = rtrim($path, '/');
	if ($count > 0) {
		var_dump($src);
		exit;
	}
	$path = __DIR__ . '/files/' . preg_replace('#[^a-z0-9/\.\-_]#i', '', $path);
	if (file_exists($path) && filesize($path) > 0) {
		$skipCount++;
		continue;
	}

	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}

	$percent = sprintf('%.2f', $i / $lineCount * 100);
	$srcShorten = substr(preg_replace('#^https?://#', '', $src), 0, 6) . '..' . substr($src, -30);
	echo("Downloading {$percent}%: {$srcShorten} ");
	$data = '';
	try {
		$downloadCount++;
		$response = $client->get($src);

		$contentType = '';
		$contentTypes = $response->getHeader('Content-Type');
		if (is_array($contentTypes)) {
			$contentType = reset($contentTypes);
		}

		if (preg_match('#^image/#', $contentType)) {
			$data = strval($response->getBody());
		}
	} catch (Exception $e) {
		$errorCount++;
		$message = $e->getMessage();
		echo("-> exception {$message} ");
		file_put_contents($errorLogPath, "{$src} -> {$message}\n", FILE_APPEND);
	}
	file_put_contents($path, $data);
	$size = strlen($data);
	echo("-> size={$size}\n");

	if (count($recentSizes) >= $recentCount) {
		$recentSizes = array_slice($recentSizes, 1);
		$recentSizes = array_values($recentSizes);
	}
	$recentSizes[] = $size;
	$recentZeroCount = 0;
	foreach ($recentSizes as $recentSize) {
		if ($recentSize === 0) {
			$recentZeroCount++;
		}
	}
	if ($recentZeroCount >= $recentAlertCount) {
		die("Too many zeros recently: {$recentZeroCount}!\n");
	}
}

echo("\$downloadCount = {$downloadCount}; \$errorCount = {$errorCount}; \$skipCount = {$skipCount}\n");
echo("\$gotSignal = {$GLOBALS['gotSignal']}\n");

