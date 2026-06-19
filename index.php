<?php

require_once('config.sample.php');

$data = file_get_contents('php://input');
$data = json_decode($data, true);
$text = $data['message']['text'];
$chatId = $data['message']['chat']['id'];

function sendMessage ($chatId, $text) {
  $url = "https://api.telegram.org/bot${TOKEN}/sendMessage";
  $options = [
    'http' => [
      'method' => 'POST',
      'content' => http_build_query([
        'chat_id' => $chatId,
        'text' => $text
      ])
    ]
  ];
  $context = stream_context_create($options);
  file_get_contents($url, false, $context);
}

sendMessage($chatId, "Got ${text}");
