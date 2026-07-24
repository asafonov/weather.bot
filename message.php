<?php

require_once('config.php');

function writeLog ($msg) {
  file_put_contents(WORKER_LOG_PATH . '/' . BOT_NAME . '.error.log', date('Y-m-d H:i:s', time()) . '   ' . "$msg\n", FILE_APPEND | LOCK_EX);
}

function requestApi ($url, $msg = false) {
  $options = [
    'http' => [
      'method' => $msg === false ? 'GET' : 'POST',
      'timeout' => REQUEST_TIMEOUT
    ],
    'socket' => [
      'timeout' => REQUEST_TIMEOUT
    ]
  ];

  if ($msg !== false) {
    $options['http']['header'] = "Content-type: application/x-www-form-urlencoded\r\n";
    $options['http']['content'] = http_build_query($msg);
  }

  $context = stream_context_create($options);

  return file_get_contents($url, false, $context);
}

function getFileWithRetry ($url) {
  $try = 0;

  while ($try < MAX_RETRIES) {
    try {
      $ret = requestApi($url);
      return $ret;
    } catch (Exception $e) {
      ++$try;
    }
  }
}

function requestApiWithRetry ($url, $msg = false) {
  $try = 0;

  while ($try < MAX_RETRIES) {
    try {
      $ret = requestApi($url, $msg);
      $ret = json_decode($ret, true);

      if (isset($ret['ok']) && $ret['ok']) {
        return $ret;
      } else {
        ++$try;
        writeLog('API returned the following result: ' . json_encode($ret) . "\n  url: $url\n  msg: " . json_encode($msg));
      }
    } catch (Exception $e) {
      ++$try;
      writeLog(json_encode($e) . "\n  url: $url\n  msg: " . json_encode($msg));
    }
  }

  return null;
}

function sendMessage ($msg) {
  $url = 'https://api.telegram.org/bot' . TOKEN . '/sendMessage';
  return requestApi($url, $msg);
}

function sendMessageWithRetry ($msg) {
  $url = 'https://api.telegram.org/bot' . TOKEN . '/sendMessage';
  return requestApiWithRetry($url, $msg);
}

function isMessageWithPhoto ($msg) {
  return isset($msg['message']['photo']);
}

function getPhotoUrl ($msg) {
  if (! isMessageWithPhoto($msg)) return null;

  $fileId = end($msg['message']['photo'])['file_id'];
  $getFileUrl = 'https://api.telegram.org/bot' . TOKEN . "/getFile?file_id={$fileId}";
  $filePath = requestApiWithRetry($getFileUrl);

  if (! isset($filePath['result']['file_path'])) {
    return null;
  }

  return 'https://api.telegram.org/file/bot' . TOKEN . "/{$filePath['result']['file_path']}";
}

function isCallbackQuery ($input) {
  return ! empty($input['callback_query']);
}

function getCallbackQueryData ($input) {
  return [
    'id' => $input['callback_query']['id'],
    'data' => $input['callback_query']['data'],
    'chat_id' => $input['callback_query']['message']['chat']['id']
  ];
}

function replyCallback ($id, $text) {
  $url = 'https://api.telegram.org/bot' . TOKEN . '/answerCallbackQuery';
  $msg = [
    'callback_query_id' => $id,
    'text' => $text
  ];

  requestApiWithRetry($url, $msg);
}
