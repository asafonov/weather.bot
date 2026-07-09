<?php

require_once('config.php');
require_once('weather.php');

function preparePlace ($place) {
  $place = strtolower(preg_replace('/[^A-z ]/', '', $place));
  return trim($place);
}

function getDataByDays ($data) {
  $ret = ['now' => $data[0]];
  $now = time();
  $today = date('Y-m-d', $now + $data[0]['timezone']);
  $tomorrow = date('Y-m-d', $now + $data[0]['timezone'] + 24*3600);
  $prev_date = $today;
  $dayNum = 1;
  $index = 'today';

  for ($i = 0, $j = count($data); $i < $j; ++$i) {
    $date = substr($data[$i]['date'], 0, 10);
    $time = substr($data[$i]['date'], 11, 5);

    if ($date !== $prev_date  || $i === 0) {
      if ($i > 0) {
        asort($ret[$index]['wind_direction'], SORT_NUMERIC);
        $ret[$index]['wind_direction'] = array_reverse(array_keys($ret[$index]['wind_direction']));
        asort($ret[$index]['description'], SORT_NUMERIC);
        $ret[$index]['description'] = array_reverse(array_keys($ret[$index]['description']));
      }

      $index = $date === $today ? 'today' : 'tomorrow';

      if ($prev_date === $tomorrow) {
        break;
      }

      $dayNum = 1;
      $ret[$index]['max_temp'] = -255;
      $ret[$index]['min_temp'] = 255;
      $ret[$index]['wind'] = 0;
      $ret[$index]['gust'] = 0;
      $ret[$index]['max_rain'] = 0;
      $ret[$index]['total_rain'] = 0;
      $ret[$index]['total_snow'] = 0;
      $ret[$index]['max_snow'] = 0;
      $ret[$index]['clouds'] = 0;
      $ret[$index]['pressure'] = 0;
      $ret[$index]['wind_direction'] = [];
      $ret[$index]['description'] = [];
    }

    if ($i > 0 && isset($data[$i]['rain']) && $data[$i]['rain'] > 0 && ! isset($ret[$index]['rain_start'])) {
      $ret[$index]['rain_start'] = $time;
    }

    if ($i > 1 && isset($data[$i - 1]['rain']) && $data[$i - 1]['rain'] > 0 && ! isset($data[$i]['rain'])) {
      $ret[$index]['rain_end'] = $time;
    }

    if ($i > 0 && isset($data[$i]['snow']) && $data[$i]['snow'] > 0 && ! isset($ret[$index]['snow_start'])) {
      $ret[$index]['snow_start'] = $time;
    }

    if ($i > 1 && isset($data[$i - 1]['snow']) && $data[$i - 1]['snow'] > 0 && ! isset($data[$i]['snow'])) {
      $ret[$index]['snow_end'] = $time;
    }

    $ret[$index]['max_temp'] = max($ret[$index]['max_temp'], $data[$i]['temp']);
    $ret[$index]['min_temp'] = min($ret[$index]['min_temp'], $data[$i]['temp']);
    $ret[$index]['wind'] = max($ret[$index]['wind'], $data[$i]['wind_speed']);
    $ret[$index]['gust'] = max($ret[$index]['gust'], $data[$i]['gust']);
    $ret[$index]['max_rain'] = max($ret[$index]['max_rain'], isset($data[$i]['rain']) ? $data[$i]['rain'] : 0);
    $ret[$index]['total_rain'] += isset($data[$i]['rain']) ? $data[$i]['rain'] : 0;
    $ret[$index]['max_snow'] = max($ret[$index]['max_snow'], isset($data[$i]['snow']) ? $data[$i]['snow'] : 0);
    $ret[$index]['total_snow'] += isset($data[$i]['snow']) ? $data[$i]['snow'] : 0;
    $ret[$index]['clouds'] += $data[$i]['clouds'];
    $ret[$index]['pressure'] += $data[$i]['pressure'];
    $ret[$index]['numDays'] = $dayNum;
    $ret[$index]['wind_direction'][$data[$i]['wind_direction']] = isset($ret[$index]['wind_direction'][$data[$i]['wind_direction']]) ? $ret[$index]['wind_direction'][$data[$i]['wind_direction']] + 1 : 1;
    $ret[$index]['description'][$data[$i]['description']] = isset($ret[$index]['description'][$data[$i]['description']]) ? $ret[$index]['description'][$data[$i]['description']] + 1 : 1;

    $prev_date = $date;
    ++$dayNum;
  }

  return $ret;
}

function getWindSpeedDescription ($wind_speed) {
  if ($wind_speed < 3.4) {
    return 'light breeze';
  } elseif ($wind_speed < 5.5) {
    return 'gentle breeze';
  } elseif ($wind_speed < 8) {
    return 'moderate breeze';
  } elseif ($wind_speed < 10.8) {
    return 'fresh breeze';
  } elseif ($wind_speed < 13.9) {
    return 'strong breeze';
  } elseif ($wind_speed < 17.2) {
    return 'near gale';
  } elseif ($wind_speed < 20.8) {
    return 'gale';
  } elseif ($wind_speed < 24.5) {
    return 'strong gale';
  } elseif ($wind_speed < 28.5) {
    return 'storm';
  } elseif ($wind_speed < 32.7) {
    return 'violent storm';
  } else {
    return 'hurricane';
  }
}

function makeSenseOfData ($data) {
  $data = getDataByDays($data);
  $words = [
    'now' => [
      'wind_description' => getWindSpeedDescription($data['now']['wind_speed']),
      'feels_like' => abs($data['now']['temp'] - $data['now']['feels_like']) > 1 ? ", while the perceived temperature is {$data['now']['feels_like']}°C due to the {$data['now']['humidity']}% humidity" : ''
    ],
    'today' => [
      'wind_description' => getWindSpeedDescription($data['today']['wind']),
      'description_add' => count($data['today']['description']) > 1 ? ' accompanied by ' . $data['today']['description'][1] : '',
      'pressure' => intval($data['today']['pressure'] / $data['today']['numDays']),
      'rain' => isset($data['today']['rain_start']) ? "Rainfall is forecast between {$data['today']['rain_start']} " . (isset($data['today']['rain_end']) ? "and {$data['today']['rain_end']}" : 'and the end of the day') . '. ' : '',
      'snow' => isset($data['today']['snow_start']) ? "Snowfall is forecast between {$data['today']['snow_start']} " . (isset($data['today']['snow_end']) ? "and {$data['today']['snow_end']}" : 'and the end of the day') . '. ' : '',
      'temp' => $data['today']['min_temp'] < $data['today']['max_temp'] ? "temperatures are expected to fluctuate between {$data['today']['min_temp']}°C and {$data['today']['max_temp']}°C" : "temperature will remain at {$data['today']['min_temp']}°C"
    ],
    'tomorrow' => [
      'wind_description' => getWindSpeedDescription($data['tomorrow']['wind']),
      'description_add' => count($data['tomorrow']['description']) > 1 ? ' with ' . $data['tomorrow']['description'][1] : '',
      'pressure' => intval($data['tomorrow']['pressure'] / $data['tomorrow']['numDays']),
      'rain' => isset($data['tomorrow']['rain_start']) ? "Precipitation is predicted to start at {$data['tomorrow']['rain_start']} and last until " . (isset($data['tomorrow']['rain_end']) ? "{$data['tomorrow']['rain_end']}" : 'the end of the day')  . '. ' : '',
      'snow' => isset($data['tomorrow']['snow_start']) ? "Snow is from {$data['tomorrow']['snow_start']} " . (isset($data['tomorrow']['snow_end']) ? "until {$data['tomorrow']['snow_end']}" : 'until the end of the day') . '. ' : ''
    ]
  ];

  $reply = "The current weather in {$data['now']['place']} is characterised by {$data['now']['description']}. The air temperature stands at {$data['now']['temp']}°C{$words['now']['feels_like']}. The wind blows as a {$words['now']['wind_description']} coming from the {$data['now']['wind_direction']} at {$data['now']['wind_speed']} m/s with occasional gusts reaching up to {$data['now']['gust']} m/s. Atmospheric pressure is recorded at {$data['now']['pressure']} mm Hg.";

  $reply .= "\n\nLater today, {$words['today']['temp']}. You will notice {$data['today']['description'][0]}{$words['today']['description_add']} and a {$words['today']['wind_description']} from the {$data['today']['wind_direction'][0]} with a speed of {$data['today']['wind']} m/s; brief gusts may reach {$data['today']['gust']} m/s. {$words['today']['rain']}{$words['today']['snow']}The pressure will remain around {$words['today']['pressure']} mm Hg.";

  $reply .= "\n\nTomorrow, overnight temperatures are projected to drop to around {$data['tomorrow']['min_temp']}°C, rising to a maximum of {$data['tomorrow']['max_temp']}°C during the day. The sky will feature {$data['tomorrow']['description'][0]}{$words['tomorrow']['description_add']}. {$words['tomorrow']['rain']}{$words['tomorrow']['snow']}The wind will be a {$words['tomorrow']['wind_description']} from the {$data['tomorrow']['wind_direction'][0]}, with speeds up to {$data['tomorrow']['wind']} m/s and gusts potentially reaching up to {$data['tomorrow']['gust']} m/s. Atmospheric pressure is anticipated to be approximately {$words['tomorrow']['pressure']} mm Hg.";

  return $reply;
}

function getForecastMessageAndData ($input) {
  $text = $input['message']['text'];
  $chatId = $input['message']['chat']['id'];

  if ($text == '/start') {
    return [[
      'text' => START_MESSAGE,
      'chat_id' => $chatId
    ], null];
  }

  $place = preparePlace($text);

  if (! $place) {
    return [[
      'text' => PLACE_ERROR_MESSAGE,
      'chat_id' => $chatId
    ], null];
  }

  $data = weather($place);

  if (! isset($data[0]['timezone'])) {
    return [[
      'text' => PLACE_ERROR_MESSAGE,
      'chat_id' => $chatId
    ], $data];
  }

  $reply = makeSenseOfData($data);

  return [[
    'text' => $reply,
    'chat_id' => $chatId
  ], $data];
}

function doCronLogic ($input) {
  [$reply, $data] = getForecastMessageAndData($input);
  return $reply;
}

function doLogic ($input) {
  [$reply, $data] = getForecastMessageAndData($input);

  if (isset($data[0]['timezone'])) {
    $chatId = $input['message']['chat']['id'];
    $scheduleUpdateTime = SCHEDULE_UPDATE_HOUR * 3600 - $data[0]['timezone'];
    $scheduleUpdateTime < 0 && ($scheduleUpdateTime += 24 * 3600);
    $scheduleUpdateTime > 24 * 3600 && ($scheduleUpdateTime -= 24 * 3600);
    $scheduleUpdateHour = date('H', $scheduleUpdateTime);
    $taskDir = WORKER_CACHE_PATH . "/{$scheduleUpdateHour}";
    mkdir($taskDir);
    file_put_contents("{$taskDir}/{$chatId}", json_encode($input));
  }

  return $reply;
}

function test($city) {
  $input = ['message' => [
    'text' => $city,
    'chat' => ['id' => 'chat_id']
  ]];
  $reply = doLogic($input);
  print_r($reply);
}
