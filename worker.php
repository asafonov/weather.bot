<?php

require_once('config.sample.php');

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

if (isset($argv[1])) {
  $data = file_get_contents('/tmp/' . $argv[1]);
  $data = json_decode($data, true);
  $text = $data['message']['text'];
  $chatId = $data['message']['chat']['id'];
  sendMessage($chatId, 'Got ' . $text);
}