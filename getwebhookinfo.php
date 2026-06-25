<?php

require_once('config.php');

$url = 'https://api.telegram.org/bot' . TOKEN . '/getwebhookinfo';
$data = file_get_contents($url);
die($data . "\n");
