<?php

require_once('config.php');

function writeLog ($msg) {
  file_put_contents(WORKER_LOG_PATH . '/' . BOT_NAME . '.error.log', date('Y-m-d H:i:s', time()) . '   ' . "$msg\n", FILE_APPEND | LOCK_EX);
}

function saveLastCommand ($command, $chatId) {
  file_put_contents(WORKER_CACHE_PATH . '/' . $chatId . '/last_command', $command);
}

function getLastCommand ($chatId) {
  return file_get_contents(WORKER_CACHE_PATH . '/' . $chatId . '/last_command');
}

function emoji ($code) {
  return mb_convert_encoding('&#x' . $code . ';', 'UTF-8', 'HTML-ENTITIES');
}

function requestApi ($url, $msg = false, $httpOptions = false) {
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
    $options['http']['header'] = "Content-type: application/json\r\n";
    $options['http']['content'] = json_encode($msg);
  }

  if ($httpOptions !== false) {
    foreach ($httpOptions as $k => $v) {
      $options['http'][$k] = $v;
    }
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

function requestApiWithRetry ($url, $msg = false, $httpOptions = false) {
  $try = 0;

  while ($try < MAX_RETRIES) {
    try {
      $ret = requestApi($url, $msg,  $httpOptions);
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

function sendRichMessageWithRetry ($msg) {
  $url = 'https://api.telegram.org/bot' . TOKEN . '/sendRichMessage';
  return requestApiWithRetry($url, $msg);
}

function sendPhotoWithRetry ($msg) {
  if (! file_exists($msg['photo'])) {
    return false;
  }

  $boundary = '----' . uniqid();
  $fileData = file_get_contents($msg['photo']);

  $data = "--$boundary\r\n";
  $data .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n";
  $data .= "{$msg['chat_id']}\r\n";
  $data .= "--$boundary\r\n";
  $data .= "Content-Disposition: form-data; name=\"photo\"; filename=\"photo.png\"\r\n";
  $data .= "Content-Type: image/png\r\n\r\n";
  $data .= $fileData . "\r\n";
  $data .= "--$boundary\r\n";
  $data .= "Content-Disposition: form-data; name=\"caption\"\r\n\r\n";
  $data .= "{$msg['caption']}\r\n";
  $data .= "--$boundary--\r\n";

  $httpOptions = [
    'method' => 'POST',
    'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\nContent-Length: " . strlen($data) . "\r\n",
    'content' => $data
  ];
  $url = 'https://api.telegram.org/bot' . TOKEN . '/sendPhoto';

  requestApiWithRetry($url, false, $httpOptions);
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
