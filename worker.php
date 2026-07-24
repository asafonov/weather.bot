<?php

require_once('config.php');
require_once('bot.php');

if (isset($argv[1]) && file_exists(WORKER_CACHE_PATH . '/' . $argv[1])) {
  $data = file_get_contents(WORKER_CACHE_PATH . '/' . $argv[1]);
  $data = json_decode($data, true);
  $reply = doLogic($data);
  sendMessageWithRetry($reply);

  if (! isset($argv[2]) || $argv[2] !== 'debug') {
    unlink(WORKER_CACHE_PATH . '/' . $argv[1]);
  }
}
