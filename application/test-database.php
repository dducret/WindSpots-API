<?php
$rootPath=__FILE__;
$scriptPath=baseName($rootPath);
$rootPath=str_replace($scriptPath,'',$rootPath);
$rootPath=realPath($rootPath.'../');
$rootPath=str_replace('\\','/',$rootPath);
date_default_timezone_set('Europe/Zurich');
$windspotsLog  =  $rootPath."/log";
$windspotsData =  $rootPath."/data";
// API
$windspotsAPI = $rootPath."/../api/library/windspots";
require_once $windspotsAPI.'/db.php';
function logIt($message) {
  global $windspotsLog;
  $logfile = "/error.log";
  $wlHandle = fopen($windspotsLog.$logfile, "a");
  $t = microtime(true);
  $micro = sprintf("%06d",($t - floor($t)) * 1000000);
  $micro = substr($micro,0,3);
  fwrite($wlHandle, Date("H:i:s").".$micro"." test-database.php: ".$message."\n");
  fclose($wlHandle);
}
// for testing all WindspotsDB functions
echo "\n\n\n\n";
echo "Starting Test Database.\r\n";
logIt("Starting Test Database.");
$stations  = WindspotsDB::getStations();    
$nbStation = count($stations);
// Create Station
$stationName = "TEST01";
$displayName = "Test for Database";
$shortName   = "TEST";
$MSName      = "WSTEST";
$information = "For testing purpose";
$altitude    = "370";
$latitude    = "46.20199";
$longitude   = "6.15921";
$spotType    = "63"; // KITE 1 - WINDSURF 2 - PADDLE 4 - RELAX 8 - PARA 16 - SWIM 32
$online      ="0";
$maintenance = "0";
$reason      = "N/A";
$GMT         = "1";
if(!WindspotsDB::setStation($stationName, $displayName, $shortName, $MSName, $information, $altitude,
                                    $latitude, $longitude, $spotType, $online, $maintenance, $reason, $GMT)) {
  echo "Error Insert WindspotsDB::setStation.\r\n";
}
$online = "1";
if(!WindspotsDB::setStation($stationName, $displayName, $shortName, $MSName, $information, $altitude,
                                    $latitude, $longitude, $spotType, $online, $maintenance, $reason, $GMT)) {
  echo "Error Update WindspotsDB::setStation.\r\n";
}
// Creste Sensor
$sensorName = "WT100";
$channel    = "1";
if(!WindspotsDB::setSensor($stationName, $sensorName, $channel)) {
  echo "Error WindspotsDB::setSensor.\r\n";
}
// Create Sensor data
$sensorId = WindspotsDB::getSensor($stationName, $sensorName, $channel);
if($sensorId == NULL) {
  echo "Error WindspotsDB::getSensor.\r\n";
}
$sensorTime   = date('Y-m-d H:i:00');    
$battery      = "100";
$temperature  = "21.0";
$humidity     ="62";
$barometer    = "1016";
$direction    = "193";
$speed        = "2.1";
$average      = "2.0";
$gust         = "2.5";
$UV           = "3";
$rainRate     = "4";
$rainTotal    = "10";
$ten          = "0";
if(!WindspotsDB::setSensorData($sensorId, $sensorTime, $battery, $temperature, $humidity, $barometer, 
                                      $direction, $speed, $average, $gust, $UV, $rainRate, $rainTotal, $ten)) {
  echo "Error WindspotsDB::setSensorData set 1.\r\n";
}
$ten          = "1";
if(!WindspotsDB::setSensorData($sensorId, $sensorTime, $battery, $temperature, $humidity, $barometer, 
                                      $direction, $speed, $average, $gust, $UV, $rainRate, $rainTotal, $ten)) {
  echo "Error WindspotsDB::setSensorData set 10.\r\n";
}
// Create forecast
$referenceTime = date('Y-m-d H:i:00');  
if(!WindspotsDB::setForecast($stationName,$referenceTime,$speed,$direction)) {
  echo "Error WindspotsDB::setForecast.\r\n";
}
// Set Data Time
if(!WindspotsDB::setStationDataTime($stationName)) {
  echo "Error WindspotsDB::setStationDataTime.\r\n";
}
// Set Image Time
if(!WindspotsDB::setStationImageTime($stationName)) {
  echo "Error WindspotsDB::setStationImageTime.\r\n";
}
// Get Station by MSName
$stationMS = WindspotsDB::getStationByMSName($MSName);
if(strcmp($stationMS["station_name"], $stationName) !== 0) {
  echo "Error WindspotsDB::getStationByMSName.".$MSName."\n";
  echo "     ".json_encode($stationMS)."\n";
}
echo "     ".$stationMS["station_name"]. "\r\n";
echo "     MS    : ".$stationMS["ms_name"]. "\r\n";
// Get Station by Station Name
$stationData = WindspotsDB::getStationByName($stationName);
if(strcmp($stationData["station_name"], $stationName) !== 0) {
  echo "Error WindspotsDB::getStationByName.\r\n";
}
echo "     Data  : ".$stationData["data_time"]. "\r\n";
echo "     Image : ".$stationData["image_time"]. "\r\n";
// Get Stations
$stations = WindspotsDB::getStations();    
if(count($stations) < 1) {
  echo "Error WindspotsDB::getStations.\r\n";
}
$check = (int)count($stations)-(int)$nbStation;
echo "     Check : ".$check."\r\n";
// Get Sensor Data
$sensorData = WindspotsDB::getSensorData($sensorId); 
if($sensorData[0]["sensor_id"] != $sensorId) {
  echo "Error WindspotsDB::getSensorData get 1.\r\n";
}
echo "     Sensor: ".$sensorData[0]["sensor_id"]. "\r\n";
// Get Sensor Data Ten
$sensorData = WindspotsDB::getSensorData($sensorId, 1, 1); 
if($sensorData[0]["ten"] != "1") {
  echo "Error WindspotsDB::getSensorData get 10.\r\n";
}
echo "     Ten   : ".$sensorData[0]["ten"]. "\r\n";
// Get Sensor Data
$sensorDataAt = WindspotsDB::getSensorDataAt($sensorId, $sensorTime); 
if($sensorDataAt["sensor_id"] != $sensorId) {
  echo "Error WindspotsDB::getSensorData.\r\n";
}
echo "     At    : ".$sensorDataAt["sensor_time"]. "\r\n";
// Get Forecast Data
$from = date("Y-m-d H:i:00", strtotime('-2 minutes',strtotime($sensorTime)));
$to   = date("Y-m-d H:i:00", strtotime('+2 minutes',strtotime($sensorTime)));
$forecastData = WindspotsDB::getStationForecast($stationName, $from, $to);
if(strcmp($forecastData[0]["reference_time"],$referenceTime) !== 0) {
  echo "Error WindspotsDB::getStationForecast.\r\n";
}
echo "     Time  : ".$forecastData[0]["reference_time"]. "\r\n";
// for compatibility not tested.
// getStationSensorData($sensorId, $last, $from, $to, $ten)
// Wipe outDate
if(!WindspotsDB::wipeOutStation($stationName)) {
  echo "Error WindspotsDB::wipeOut.\r\n";
}
logIt("Test Database Finished.");
echo "Test Database Finished.\r\n";
echo "\n\n\n\n";
?>