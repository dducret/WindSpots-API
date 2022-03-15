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
  fwrite($wlHandle, Date("H:i:s").".$micro"." forecast.php: ".$message."\n");
  fclose($wlHandle);
}
// Global
$wind_min_speed     = 0;
$wind_average_speed = 0;
$wind_max_speed     = 0;
$wind_nb            = 0;
function windQuery($windId, $durationMinutes) {
  global $wind_min_speed;
  global $wind_average_speed;
  global $wind_max_speed;
  global $wind_nb;
  $wind_min_speed = 99.9;
  $wind_average_speed = 0;
  $wind_max_speed = 0;
  $wind_nb = 0;
  $point = array('points');
  $previous_time = 0;
  $sensorData = null;
  if($durationMinutes <= 60) {
    $sensorData = WindspotsDB::getSensorData($windId, $durationMinutes, false);
  } else {
    $sensorData = WindspotsDB::getSensorData($windId, $durationMinutes, true);
    $durationMinutes = round($durationMinutes / 10);
  }
  foreach($sensorData as $key => $data){
    if($previous_time != 0) {
      $diff = ceil(($previous_time - strtotime($data['sensor_time'])) / (60));
      if($diff > 80) {
        for($k=($diff/60);$k > 1;$k--) {
          $point[$wind_nb++]=array(
            'date' => "".strtotime($data['sensor_time'])+(($k-1)*60)."000",
            'direction' => "0", // .$station_time,
            'speed' => "0", // .$diff." - " . $k,
            'gust' => "-1"
          );
          if($wind_nb >= $durationMinutes)
            break;
        }
        $previous_time = 0;
      }
    }
    if($wind_nb >= $durationMinutes)
      break;
    $previous_time = strtotime($data['sensor_time']);
    if($data['gust'] > $wind_max_speed)
      $wind_max_speed = min($data['gust'], 99); // max 99 ms
    $wind_average_speed = $wind_average_speed + min($data['speed'], 99); // max 99 ms
    if($data['speed'] < $wind_min_speed)
      $wind_min_speed = $data['speed'];
    $point[$wind_nb++]=array(
      'date' => "".strtotime($data['sensor_time'])."000",
      'direction' => "".$data['direction'],
      'speed' => "".min($data['speed'], 99),
      'gust' => "".min($data['gust'], 99));
  }
  $windDirection = array('name' => "wind", 'points' => $point );
  $wind_average_speed = round(($wind_average_speed / $wind_nb), 2);
  return $windDirection;
}
function stationinfo($station=NULL) {
  // http://api.windspots.org/mobile/stationinfo
  global $windspotsData;
  $stationInfo = array('stationinfos');
  $i=0;
  $stations = NULL;
  //
  if($station != NULL) {
    $stations[0] = WindspotsDB::getStationByName($station);
  } else {
    $stations = WindspotsDB::getStations();
  }
  foreach($stations as $key => $stationData){
    $Temperature = 0;
    $TemperatureWater = 999;
    $Humidity = 0;
    $Barometer = 0;
    $Direction = 0;
    $Speed = 0.0;
    $Gust = 0.0;
    if(!empty($stationData['wind_id']) ){
      $SensorResultArray = WindspotsDB::getSensorData($stationData['wind_id']);
      $SensorResult = $SensorResultArray[0];
      if(is_array($SensorResult)) {
        $Direction = $SensorResult['direction'];
        $Speed = $SensorResult['speed'];
        $Gust = $SensorResult['gust'];
      }
    }
    if(!empty($stationData['barometer_id']) ){
      $SensorResultArray = WindspotsDB::getSensorData($stationData['barometer_id']);
      $SensorResult = $SensorResultArray[0];
      if(is_array($SensorResult)){
        $Barometer = $SensorResult['barometer'];
      }
    }
    if(!empty($stationData['temperature_id']) ){
      $SensorResultArray = WindspotsDB::getSensorData($stationData['temperature_id']);
      $SensorResult = $SensorResultArray[0];
      if(is_array($SensorResult)){
        $Temperature = $SensorResult['temperature'];
        $Humidity = $SensorResult['humidity'];
      }
    }
    if(!empty($stationData['water_id']) ){
      $SensorResultArray = WindspotsDB::getSensorData($stationData['water_id']);
      $SensorResult = $SensorResultArray[0];
      if(is_array($SensorResult)){
        $TemperatureWater = $SensorResult['temperature'];
      }
    }  
    $stationInfo[$i++]=array(
      'stationName' => $stationData['station_name'],
      'displayName' => $stationData['display_name'],
      'shortName' => $stationData['short_name'],
      'altitude' => $stationData['altitude'],
      'latitude' => $stationData['latitude'],
      'longitude' => $stationData['longitude'],
      'status' => WindspotsDB::maintenanceStatus(strtotime($stationData['data_time']), 0),
      'spotType' => "".$stationData['spot_type'],
      'maintenance' => "".$stationData['maintenance'],
      'reason' => $stationData['reason'],
      'GMT' => $stationData['GMT'],
      'dataTime' => $stationData['data_time'],
      'imageTime' => $stationData['image_time'],
      "direction"  => "".$Direction,
      "directionAlpha"  => "".WindspotsDB::directionAlpha($Direction),
      "speed"  => "".$Speed,
      "gust"  => "".$Gust,
      "barometer"  => "".$Barometer,
      "temperature"  => "".$Temperature,
      "humidity"  => "".$Humidity,
      "water"  => "".$TemperatureWater,
      "windId" => "".$stationData['wind_id'],
    );
  } //foreach($stations as $key => $stationData)
  if($station != NULL) {  // called from stationdata or stationforecast
    return $stationInfo[0];
  }
  echo "Stations - ".$i."\r\n";
  file_put_contents($windspotsData.'/'.'stationInfo.txt', serialize($stationInfo));
  return;
}
function stationdata($durationHours=1) {
  global $windspotsData;
  global $wind_min_speed;
  global $wind_average_speed;
  global $wind_max_speed; 
  global $wind_nb;
  $delay = 0;
  $stations = WindspotsDB::getStations();
  $ext = "";
  if($durationHours != 1)
    $ext = $durationHours;
  foreach($stations as $key => $station){
    $stationInfo = stationinfo($station['station_name']); 
    $stationData = array(
      'stationId' => $stationInfo['stationName'], 
      'lastUpdate' => $stationInfo['dataTime'], 
      'status' => WindspotsDB::maintenanceStatus(strtotime($stationInfo['dataTime']), $delay),
      'speed' => "".$stationInfo['speed'], 
      'gust' => "".$stationInfo['gust'], 
      'temperature' => "".$stationInfo['temperature'], 
      'humidity' => "".$stationInfo['humidity'], 
      'water' => "".$stationInfo['water'], 
      'shortName' => "".$stationInfo['shortName'], 
      'stationName' => "".$stationInfo['stationName'], 
      'dataTime' => $stationInfo['dataTime'],
      'update' => "".strtotime($stationInfo['dataTime'])."000",
      'direction' => "".$stationInfo['direction'],
      'directionAlpha' => "".WindspotsDB::directionAlpha($stationInfo['direction']),
      'spotType'=> "".$stationInfo['spotType'],
      'windChart' => array('duration' => "".($durationHours*60), 
        'serie' => windQuery($stationInfo['windId'], ($durationHours*60))),
      'windChartMin' => "".$wind_min_speed,
      'windChartAverage' => "".$wind_average_speed, 
      'windChartMax' => "".$wind_max_speed
    );
    // echo $stationData['stationId']." - ".$wind_nb." - max speed ".$wind_max_speed." - ".$stationData['windChartMax']."\r\n";
    file_put_contents($windspotsData.'/'.$stationInfo['stationName'].$ext.'.txt', serialize($stationData));
  } // foreach($stations as $key => $station)
  return;
}
function stationforecast(){
  global $windspotsData;
  $stations = WindspotsDB::getStations();
  foreach($stations as $key => $station){
    $now     = date('Y-m-d H:i:00');    
    $from    = date("Y-m-d H:i:00", strtotime('-6 hours',strtotime($now)));
    $to      = date("Y-m-d H:i:00", strtotime('+18 hours',strtotime($now)));
    $point   = array('points');
    $windmax = 0;
    $i       = 0;
    $previous_reference_time = 0;
    $forecastData = WindspotsDB::getStationForecast($station['station_name'], $from, $to);
    foreach($forecastData as $key => $forecast){
      if($previous_reference_time == $forecast["reference_time"])
        continue;
      $previous_reference_time = $forecast["reference_time"];
      $sensorData = WindspotsDB::getSensorDataAt($station['wind_id'], $forecast["reference_time"]);
      $direction = -1;
      $speed     = 0.0;
      $gust      = 0.0;
      if($sensorData!=NULL) {
        $direction = $sensorData["direction"];
        $speed = min(floatval(round(($sensorData["speed"]*3.6),1)), (99*3.6)); // maxi 99 ms
        $gust = min(floatval(round(($sensorData["gust"]*3.6),1)), (99*3.6));
        if($windmax < $gust) {
          $windmax = $gust;
        }
      }
      $forecastSpeed = min(floatval(round(($forecast["speed"]*3.6),1)), (99*3.6));
      if($windmax < $forecastSpeed) {
        $windmax = $forecastSpeed;
      }
      $point[$i++]=array(
        'date' => "".strtotime($forecast["reference_time"])."000",
        'direction' => "".round($forecast["direction"],0),
        'speed' => "".$forecastSpeed,
        'stationdirection' => "".$direction,
        'stationspeed' => "".$speed,
        'stationgust' => "".$gust,
      );
    }
    $windDirection = array(
      'name' => "forecast",
      'status' => maintenanceStatus(strtotime($station['data_time']), 0),
      'lastUpdate' => $station['data_time'],
      'update' => "".strtotime($station['data_time'])."000",
      'windMax' => "".$windmax,
      'nbPoints' => "".$i,
      'points' => $point,
     );
    echo $station['station_name']." - ".$i."\r\n";
    file_put_contents($windspotsData.'/'.$station['station_name'].'f.txt', serialize($windDirection));     
  } //  foreach($stations as $key => $station)
  return;      
}
$mt = microtime(true);
echo "generateArrays.php started.\r\n";
// Generate stationInfo
echo "Generate station Info.\r\n";
stationinfo();
// Generate Station Data
echo "Generate station Data 1h.\r\n";
stationdata(1);
echo "Generate station Data 12h.\r\n";
stationdata(12);
echo "Generate station Data 24h.\r\n";
stationdata(24);
echo "Generate station Forecast.\r\n";
stationforecast();
echo "generateArrays.php finished.\r\n";
$et = microtime(true) - $mt;
echo "Elapsed time: ". number_format($et,5)."\r\n;";
?>