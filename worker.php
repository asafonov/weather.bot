<?php

require_once('config.php');

function sendMessage ($chatId, $text) {
  $url = 'https://api.telegram.org/bot' . TOKEN . '/sendMessage';
  $options = [
    'http' => [
      'method' => 'POST',
      'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query([
        'chat_id' => $chatId,
        'text' => $text
      ])
    ]
  ];
  $context = stream_context_create($options);
  file_get_contents($url, false, $context);
}

if (isset($argv[1]) && file_exists(WORKER_CACHE_PATH . '/' . $argv[1])) {
  $data = file_get_contents(WORKER_CACHE_PATH . '/' . $argv[1]);
  $data = json_decode($data, true);
  $text = $data['message']['text'];
  $chatId = $data['message']['chat']['id'];
  $try = 0;

  while ($try < MAX_RETRIES) {
    try {
      sendMessage($chatId, 'Got ' . $text);
      unlink(WORKER_CACHE_PATH . '/' . $argv[1]);
      break;
    } catch (Exception $e) {
      ++$try;
      file_put_contents(WORKER_LOG_PATH . '/worker.bot.error.log', json_encode($e));
    }
  }
}
