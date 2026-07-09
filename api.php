<?php

require_once('weather.php');

$allowedClients = [
  'kj;gadojknm,nvdw;[kjk;jsnm.gdsiotkkkk;spalksjndpit]]iw'
];
$isAnonymousAllowed = false;
$legitRequestTimeout = 30;

function getHash ($str) {
  return hash('sha256', $str);
}

function isClientAllowed ($hash, $time, $key) {
  global $isAnonymousAllowed;
  global $allowedClients;
  global $legitRequestTimeout;

  if ($isAnonymousAllowed) return true;
  if (time() - $time > $legitRequestTimeout) return false;

  for ($i = 0, $j = count($allowedClients); $i < $j; ++$i) {
    if ($hash === getHash($allowedClients[$i] . $time . $key)) return true;
  }

  return false;
}

function headers() {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Content-Type: application/json');
}

if (! isClientAllowed(isset($_GET['hash']) ? $_GET['hash'] : '', isset($_GET['time']) ? intval($_GET['time']) : 0, isset($_GET['key']) ? $_GET['key'] : '')) {
  headers();
  die('{"error": "Your app version is not supported, please update to a newest version"}');
}

if (isset($_GET['place'])) {
  $place = preg_replace('/[^A-z ]/', '', $_GET['place']);
  $place = strtolower($place);
  headers();
  die(json_encode(weather($place)));
} else if (isset($_GET['lat']) && isset($_GET['lon'])) {
  $lat = floatval($_GET['lat']);
  $lon = floatval($_GET['lon']);
  headers();
  die(json_encode(geoWeather($lat, $lon)));
}
