<?php

define('TOKEN', 'TELEGRAM_API_TOKEN');
define('PHP_BIN', '/usr/bin/php');
define('WORKER_PATH', '/var/www/html/weather.bot');
define('WORKER_CACHE_PATH', '/tmp');
define('WORKER_LOG_PATH', '/tmp');
define('MAX_RETRIES', 3);
define('WEATHER_API_KEY', 'WEATHER_API_KEY');
define('WEATHER_CACHE_ALIVE_TIME', 1800);
define('START_MESSAGE', "Hello!\nThis is Weather: Cool and Hot bot. Send me the city you want to know the weather in and I will give you the detailed forecast. Sorry English only accepted.");
define('PLACE_ERROR_MESSAGE', "Sorry, I didn't get it. Please write a city in English.");