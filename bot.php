<?php

require_once('config.php');
$retry = 0;

function getGeoApiUrl ($place) {
  $encoded = urlencode($place);
  return "https://api.openweathermap.org/geo/1.0/direct?q=${encoded}&APPID=" . WEATHER_API_KEY;
}

function getGeoDataFromCache ($cache) {
  if (is_array($cache) && count($cache) > 0) {
    return [
      'lat' => $cache[0]['lat'],
      'lon' => $cache[0]['lon']
    ];
  }
}

function getGeoData ($place) {
  $cacheFilename = "./geo/${place}";

  if (file_exists($cacheFilename)) {
    $cache = json_decode(file_get_contents($cacheFilename), true);
    return getGeoDataFromCache($cache);
  }

  $url = getGeoApiUrl($place);
  $data = requestWithRetry($url);
  file_put_contents($cacheFilename, json_encode($data));
  return getGeoDataFromCache($data);
}

function getApiUrlFromGeo ($type, $lat, $lon) {
  return "https://api.openweathermap.org/data/2.5/${type}?lat=${lat}&lon=${lon}&APPID=" . WEATHER_API_KEY;
}

function getApiUrl ($type, $place) {
  $geo = getGeoData($place);
  $encoded = urlencode($place);

  if (isset($geo['lat']) && isset($geo['lon']))
    return getApiUrlFromGeo($type, $geo['lat'], $geo['lon']);

  return "https://api.openweathermap.org/data/2.5/${type}?q=${encoded}&APPID=" . WEATHER_API_KEY;
}

function getHTTPStatusByHeader ($header) {
  return substr($header, 9, 3);
}

function getHTTPStatus ($url) {
  $context = stream_context_create(['http' => ['method' => 'HEAD']]);
  $headers = get_headers($url, false, $context);
  return getHTTPStatusByHeader($headers[0]);
}

function requestApi ($type, $place, $lat = false, $lon = false) {
  $url = $place ? getApiUrl($type, $place) : getApiUrlFromGeo($type, $lat, $lon);
  $data = requestWithRetry($url, true);

  if ($data === false && $place) {
    saveCache($place, null, 3600 * 24 * 365);
  }

  return $data;
}

function requestWithRetry ($url, $reinit = false) {
  global $retry;
  $reinit && ($retry = 0);
  $httpStatus = getHTTPStatus($url);

  if ($httpStatus === '200') {
    $data = file_get_contents($url);
    $httpStatus = getHTTPStatusByHeader($http_response_header[0]);

    if ($httpStatus === '200') {
      return json_decode($data, true);
    } else {
      $retry++;
      if ($retry < 3) return requestWithRetry($url);
    }
  } elseif ($httpStatus === '404') {
    return false;
  } else {
    $retry++;
    if ($retry < 3) return requestWithRetry($url);
  }
}

function formatForecast ($now, $data) {
    $ret = [formatWeatherData($now)];
    $ret[0]['date'] = formatDate(time(), $data['city']['timezone']);
    $ret[0]['place'] = $data['city']['name'];
    $ret[0]['timezone'] = $data['city']['timezone'];

    for ($i = 0; $i < count($data['list']); ++$i) {
      $item = formatWeatherData($data['list'][$i]);
      $item['date'] = formatDate($data['list'][$i]['dt'], $data['city']['timezone']);
      $item['day'] = dayOfWeek($data['list'][$i]['dt'], $data['city']['timezone']);
      $ret[] = $item;
    }

    return $ret;
}

function weather ($place) {
  getGeoData($place);
  $ret = loadCache($place);

  if ($ret !== false) return $ret;

  unset($ret);
  $data = requestApi('forecast', $place);

  if ($data) {
    $now = requestApi('weather', $place);
    $ret = formatForecast($now, $data);
    saveCache($place, $ret);
    return $ret;
  }
}

function geoWeather ($lat, $lon) {
  $data = requestApi('forecast', false, $lat, $lon);

  if ($data) {
    $now = requestApi('weather', false, $lat, $lon);
    $ret = formatForecast($now, $data);
    return $ret;
  }
}

function getCacheFile ($place) {
  return "./cache/$place";
}

function loadCache ($place) {
  if (file_exists(getCacheFile($place))) {
    $data = json_decode(file_get_contents(getCacheFile($place)), true);

    if ($data['ts'] && $data['ts'] + WEATHER_CACHE_ALIVE_TIME > time()) {
      return $data['data'];
    }

    return false;
  }

  return false;
}

function saveCache ($place, $data, $ttl = 0) {
  file_put_contents(getCacheFile($place), json_encode(['ts' => time() + $ttl, 'data' => $data]));
}

function formatDate ($timestamp, $timezone) {
  return date('Y-m-d H:i', $timestamp + $timezone);
}

function dayOfWeek ($timestamp, $timezone) {
  return date('N', $timestamp + $timezone);
}

function formatTemp ($temp) {
  return intval($temp - 273);
}

function getWindDirection ($deg) {
  if ($deg > 337.5 || $deg <= 22.5) {
    return 'north';
  } else if ($deg > 22.5 && $deg <= 67.5) {
    return 'northeast';
  } else if ($deg > 67.5 && $deg <= 112.5) {
    return 'east';
  } else if ($deg > 112.5 && $deg <= 157.5) {
    return 'southeast';
  } else if ($deg > 157.5 && $deg <= 202.5) {
    return 'south';
  } else if ($deg > 202.5 && $deg <= 247.5) {
    return 'southwest';
  } else if ($deg > 247.5 && $deg <= 292.5) {
    return 'west';
  } else {
    return 'northwest';
  }
}

function formatWeatherData ($data) {
  $pressure = isset($data['main']['grnd_level']) ? $data['main']['grnd_level']
              : (isset($data['main']['sea_level']) ? $data['main']['sea_level']
              : (isset($data['main']['pressure']) ? $data['main']['pressure'] : 0));
  $ret = [
    'temp' => formatTemp($data['main']['temp']),
    'description' => $data['weather'][0]['description'],
    'feels_like' => formatTemp($data['main']['feels_like']),
    'clouds' => $data['clouds']['all'],
    'humidity' => $data['main']['humidity'],
    'wind_speed' => round($data['wind']['speed'] * 10) / 10,
    'gust' => round($data['wind']['gust'] * 10) / 10,
    'wind_direction' => getWindDirection($data['wind']['deg']),
    'pressure' => intval($pressure * 0.75006)
  ];
  isset($data['rain']) && ($ret['rain'] = $data['rain']['3h'] ? $data['rain']['3h'] / 3 : $data['rain']['1h']);
  isset($data['snow']) && ($ret['snow'] = $data['snow']['3h'] ? $data['snow']['3h'] / 3 : $data['snow']['1h']);

  return $ret;
}

function preparePlace ($place) {
  return strtolower(preg_replace('/[^A-z ]/', '', $place));
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

    if ($date !== $prev_date  || $i === 0) {
      if ($i > 0) {
        asort($ret[$index]['wind_direction'], SORT_NUMERIC);
        $ret[$index]['wind_direction'] = array_keys($ret[$index]['wind_direction']);
        asort($ret[$index]['description'], SORT_NUMERIC);
        $ret[$index]['description'] = array_keys($ret[$index]['description']);
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
    $ret[$index]['description'][$data[$i]['description']] = isset($ret[$index]['description'][$data[$i]['description']]) ? $ret[$index]['description'][$data[$i]['description']] : 1;

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
  $wind_description = getWindSpeedDescription($data['now']['wind_speed']);
  $today_wind_description = getWindSpeedDescription($data['today']['wind']);
  $today_description_add = count($data['today']['description']) > 1 ? ' with ' . $data['today']['description'][1] : '';
  $today_pressure = intval($data['today']['pressure'] / $data['today']['numDays']);

  $reply = "The weather in {$data['now']['place']} now features {$data['now']['description']}. The current temperature is {$data['now']['temp']}°C and it feels like {$data['now']['feels_like']}°C, thanks to humidity {$data['now']['humidity']}%. The wind is {$wind_description} coming from the {$data['now']['wind_direction']} at {$data['now']['wind_speed']} m/s with occasional gusts up to {$data['now']['gust']} m/s. The atmospheric pressure is {$data['now']['pressure']} mm Hg.";

  $reply .= "\n\nLater today the temperature will swing between {$data['today']['min_temp']}°C and {$data['today']['max_temp']}°C. You will notice some {$data['today']['description'][0]}{$today_description_add} and a {$today_wind_description} blowing at {$data['today']['wind']} m/s with brief gusts up to {$data['today']['gust']} m/s. Pressure is around {$today_pressure} mm Hg.";

  return $reply;
}

function doLogic ($data) {
  $text = $data['message']['text'];
  $chatId = $data['message']['chat']['id'];

  if ($text == '/start') {
    return [
      'text' => START_MESSAGE,
      'chat_id' => $chatId
    ];
  }

  $place = preparePlace($text);

  if (! $place) {
    return [
      'text' => PLACE_ERROR_MESSAGE,
      'chat_id' => $chatId
    ];
  }

  $data = weather($place);
  $reply = makeSenseOfData($data);

  return [
    'text' => $reply,
    'chat_id' => $chatId
  ];
}
