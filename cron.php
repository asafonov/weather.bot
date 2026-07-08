<?php

require_once('config.php');
require_once('bot.php');

$hour = date('H', time());
$workingDir = WORKER_CACHE_PATH . '/' . $hour;

if (file_exists($workingDir)) {
  $files = scandir($workingDir);

  for ($i = 0, $j = count($files); $i < $j; ++$i) {
    if ($files[$i] === '.' || $files[$i] === '..') {
      continue;
    }

    $data = json_decode("{$workingDir}/{$files[$i]}", true);
    doCronLogic($data);
  }
}
