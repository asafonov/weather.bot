<?php

require_once('config.php');

$url = 'https://api.telegram.org/bot' . TOKEN . '/deletewebhook';
$data = file_get_contents($url);
die($data . "\n");
