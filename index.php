<?php

try {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  $text = isset($data['message']['text']) ? $data['message']['text'] : null;
  $chatId = isset($data['message']['chat']['id']) ? $data['message']['chat']['id'] : null;

  if ($text && $chatId) {
    $jobId = uniqid();
    file_put_contents("/tmp/$jobId", $input);
    exec('php ./worker.php --job-id=' . $jobId . ' > /dev/null 2>&1 &');
  }
} catch (Exception $e) {
  die();
}

die();
