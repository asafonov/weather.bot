<?php

require_once('config.php');

if ($argc < 2) {
  die('Usage: php setwebhook.php ${url}' . "\n");
}

$url = 'https://api.telegram.org/bot' . TOKEN . '/setwebhook?url=' . $argv[1];
$data = file_get_contents($url);
die($data . "\n");
