<?php

require_once('config.php');

try {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  $text = isset($data['message']['text']) ? $data['message']['text'] : null;
  $chatId = isset($data['message']['chat']['id']) ? $data['message']['chat']['id'] : null;

  if ($text && $chatId) {
    $jobId = uniqid();
    file_put_contents(WORKER_CACHE_PATH . "/$jobId", $input);
    $workerCommand = PHP_BIN . ' ' . WORKER_PATH . '/worker.php ' . $jobId . ' > /dev/null 2>&1 &';
    exec($workerCommand);
  }
} catch (Exception $e) {
  die();
}

die();
