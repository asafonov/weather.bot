<?php

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$text = $data['message']['text'];
$chatId = $data['message']['chat']['id'];

if ($text && $chatId) {
  $jobId = uniqid();
  file_put_contents("/tmp/$jobId", $input);
  exec('php ./worker.php --job-id=' . $jobId . ' > /dev/null 2>&1 &');
}

die();
