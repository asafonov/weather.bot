<?php

function doLogic ($data) {
  $text = $data['message']['text'];
  $chatId = $data['message']['chat']['id'];

  return [
    'text' => 'Received: ' . $text,
    'chat_id' => $chatId
  ];
}
