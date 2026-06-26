<?php

require_once('config.php');

function workerLog ($msg) {
  file_put_contents(WORKER_LOG_PATH . '/weather.bot.error.log', date('Y-m-d H:i:s', time()) . '   ' . "$msg\n", FILE_APPEND | LOCK_EX);
}

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
  return file_get_contents($url, false, $context);
}

if (isset($argv[1]) && file_exists(WORKER_CACHE_PATH . '/' . $argv[1])) {
  $data = file_get_contents(WORKER_CACHE_PATH . '/' . $argv[1]);
  $data = json_decode($data, true);
  $text = $data['message']['text'];
  $chatId = $data['message']['chat']['id'];
  $try = 0;

  while ($try < MAX_RETRIES) {
    try {
      $ret = sendMessage($chatId, 'Got ' . $text);
      $ret = json_decode($ret, true);

      if (isset($ret['ok']) && $ret['ok']) {
        unlink(WORKER_CACHE_PATH . '/' . $argv[1]);
        break;
      } else {
        ++$try;
        workerLog('API returned the following result: ' . json_encode($ret));
      }
    } catch (Exception $e) {
      ++$try;
      workerLog(json_encode($e));
    }
  }
}
