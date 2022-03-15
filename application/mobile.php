<?php
$rootPath=__FILE__;
$scriptPath=baseName($rootPath);
$rootPath=str_replace($scriptPath,'',$rootPath);
$rootPath=realPath($rootPath.'../');
$rootPath=str_replace('\\','/',$rootPath);
date_default_timezone_set('Europe/Zurich');
$windspotsLog =  $rootPath."/log";
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
  fwrite($wlHandle, Date("H:i:s").".$micro"." forecast.php: ".$message."\n");
  fclose($wlHandle);
}
function validateMysqlDate($date){ 
  if (preg_match("/^(\d{4})-(\d{2})-(\d{2}) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/", $date, $matches)) { 
    if (checkdate($matches[2], $matches[3], $matches[1])) { 
      return true; 
    } 
  } 
  return false; 
} 
class mobile {
  // https://api.windspots.org/mobile/hello
  // https://api.windspots.org/mobile/hello.json
  // https://api.windspots.org/mobile/hello?to=john
  // https://api.windspots.org/mobile/hello.json?to=john
  function hello($to='world') {
    return "Welcome $to in WindSpots API!";
  }
  function stationinfo() {
    // https://api.windspots.org/mobile/stationinfo
    // https://api.windspots.org/mobile/stationinfo.json
    global $windspotsData;
    $value = unserialize( file_get_contents($windspotsData.'/stationInfo.txt') );
    return array('stationInfo' => $value);
  }
  function stationdata($station=NULL, $duration=1) {
    // https://api.windspots.org/mobile/stationdata?station=CHGE99
    // https://api.windspots.org/mobile/stationdata.json?station=CHGE99
    // https://api.windspots.org/mobile/stationdata?station=CHGE99&duration=12
    global $windspotsData;
    if($station==NULL)
      return "Station is NULL";
    if($duration == 1) {
      $value = unserialize( file_get_contents($windspotsData.'/'.strtoupper($station).'.txt') );
      return array('stationData' => $value);
    }
    if($duration == 12) {
      $value = unserialize( file_get_contents($windspotsData.'/'.strtoupper($station).'12.txt') );
      return array('stationData' => $value);
    }
    if($duration == 24) {
      $value = unserialize( file_get_contents($windspotsData.'/'.strtoupper($station).'24.txt') );
      return array('stationData' => $value);
    }
  }
  function stationdataext($station, $last=TRUE, $from=NULL, $to=NULL, $ten=FALSE){
    global $windspotsData;
    if($station==NULL)
      return "Station is NULL";
    if($last == false) {
      if(!validateMysqlDate($from)) {
        return "Invalid date from ".$from." - ie 2022-03-14 16:30:00";
      }
      if(!validateMysqlDate($to)) {
        return "Invalid date to ".$to." - ie 2022-03-14 16:40:00";
      }
      if(strtotime($to) - strtotime($from) > (60*60*24)) {
        return "From ".$from." - To ".$to." will give too many results - limited to 24hours";
      }
    }
    $duration = round((strtotime($to) - strtotime($from)) / 60);
    if($ten == true)
      $duration = round($duration / 10);
    $stationInfo = WindspotsDB::getStationByName($station); 
    // logIt(json_encode($stationInfo));
    $wind_min_speed = 99.9;
    $wind_average_speed = 0;
    $wind_max_speed = 0;
    $wind_nb = 0;
    $point = array('serie');
    $sensorData = WindspotsDB::getStationSensorData($stationInfo['wind_id'], $last, $from, $to, $ten); 
    if($last == true) {
        $point[$wind_nb++]=array(
          'date' => "".strtotime($sensorData['sensor_time'])."000",
          'direction' => "".$sensorData['direction'],
          'speed' => "".min($sensorData['speed'], 99),
          'gust' => "".min($sensorData['gust'], 99));
        if($sensorData['gust'] > $wind_max_speed)
          $wind_max_speed = min($sensorData['gust'], 99); // max 99 ms
        $wind_average_speed = $wind_average_speed + min($sensorData['speed'], 99); // max 99 ms
        if($sensorData['speed'] < $wind_min_speed)
          $wind_min_speed = $sensorData['speed'];
    } else {
      foreach($sensorData as $key => $data){
        $point[$wind_nb++]=array(
          'date' => "".strtotime($data['sensor_time'])."000",
          'direction' => "".$data['direction'],
          'speed' => "".min($data['speed'], 99),
          'gust' => "".min($data['gust'], 99));
        if($data['gust'] > $wind_max_speed)
          $wind_max_speed = min($data['gust'], 99); // max 99 ms
        $wind_average_speed = $wind_average_speed + min($data['speed'], 99); // max 99 ms
        if($data['speed'] < $wind_min_speed)
          $wind_min_speed = $data['speed'];
      }
    }
    $wind_average_speed = round(($wind_average_speed / $wind_nb), 2);
    // logIt(json_encode($stationData));
    $delay = 1;
    $stationData = array(
      'stationId' => $stationInfo['station_name'], 
      'lastUpdate' => $stationInfo['data_time'], 
      'status' =>  WindspotsDB::maintenanceStatus(strtotime($stationInfo['data_time']), $delay),
      'shortName' => "".$stationInfo['short_name'], 
      'stationName' => "".$stationInfo['station_name'], 
      'dataTime' => $stationInfo['data_time'],
      'update' => "".strtotime($stationInfo['data_time'])."000",
      'spotType'=> "".$stationInfo['spot_type'],
      'windChart' => array('duration' => "".$duration, 'from' => "".$from, 'to' => "".$to,
        'serie' => $point),
      'windChartMin' => "".$wind_min_speed,
      'windChartAverage' => "".$wind_average_speed, 
      'windChartMax' => "".$wind_max_speed
    );
    return array('stationData' => $stationData);
  }
  function stationforecast($station=NULL){
    global $windspotsData;
    // https://api.windspots.org/mobile/forecast?station=CHGE08
    if($station==NULL)
      return "Station is NULL";
    $value = unserialize( file_get_contents($windspotsData.'/'.strtoupper($station).'f.txt') );
    return array('forecast' => $value);
  }
}
?>
