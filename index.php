<?php

require_once('config.php');
require_once('message.php');

try {
  $input = file_get_contents('php://input');
  file_put_contents(WORKER_CACHE_PATH . "/last_input", $input);
  $data = json_decode($input, true);
  $chatId = isset($data['message']['chat']['id']) ? $data['message']['chat']['id'] : null;
  $hasMessage = isset($data['message']['text']) || isset($data['message']['photo']);

  if (isCallbackQuery($data)) {
    $hasMessage = true;
    $chatId = getCallbackQueryData($data)['chat_id'];
  }

  if ($hasMessage && $chatId) {
    $jobId = uniqid();
    file_put_contents(WORKER_CACHE_PATH . "/$jobId", $input);
    $workerCommand = PHP_BIN . ' ' . WORKER_PATH . '/worker.php ' . $jobId . ' >> ' . WORKER_LOG_PATH . '/' . BOT_NAME . '.worker.log &';
    exec($workerCommand);
  }
} catch (Exception $e) {
  die();
}

die();
