<?php 
class WindspotsDB{  
  // private
  private static function connectDb(){
    $dbHost = '127.0.0.1'; 
    $dbUser = 'windspots';
    $dbPassword = 'WS2022org!';
    $dbName = 'windspots';
    $dbLink = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    if($dbLink->connect_errno) {
      error_log("Connection error : %s\n", $dbLink->connect_error);
      return NULL;
    }
    return $dbLink;
  }
  // public
  // utils
  public static function maintenanceStatus($station_time, $delay) {
    if($delay == 1)
      $now = time() - 7200;
    else
      $now = time();
    $diff = ceil(($now - $station_time) / (60));
    $status = "red";
    if($diff <= 15)
      $status = "orange";
    if($diff <= 5)
      $status = "green";
    return $status;
  }
  public static function directionAlpha($direction) {
    $compass = array(
                "N", "NNE", "NE", "ENE",
                "E", "ESE", "SE", "SSE",
                "S", "SSW", "SW", "WSW",
                "W", "WNW", "NW", "NNW"
            );
    return $compass[round($direction / 22.5) % 16];
  }
  // get
  public static function getSensor($stationName, $sensorName, $channel){
    $sensorId = 0;
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `sensor`";
    $query .= " WHERE `station_name`='".$stationName."' AND `sensor_name`='".$sensorName."' AND `channel`='".$channel."' ";
    $data   = $dbLink->query($query);
    if($data==false){
      error_log("getSensor error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    if($data->num_rows >= 1) {
      while($sensorData=$data->fetch_assoc()){
        $result[] = $sensorData;
      }
      $sensorId = $result[0]['id'];
    } else { // sensor not existing so creating, need to be declared in windspots DB table station 
      $sensorId = self::setSensor($stationName, $sensorName, $channel);
      error_log("Sensor ".$sensorId." created for ".$stationName."\r\n");
    }
    $data->close();
    $dbLink->close();
    return $sensorId;
  }
  public static function getSensorData($sensorId, $durationMinutes = 1, $eachTenMinutes = false, $sortASC = false) {
    $dateTime = date('Y-m-d H:i:00', time() - ($durationMinutes * 60));
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `sensor_data`";
    $query .= " WHERE `sensor_id` = '".$dbLink->real_escape_string($sensorId)."'";
    $query .= " AND `sensor_time` >= '".$dbLink->real_escape_string($dateTime)."'";
    if($eachTenMinutes == true){
      $durationMinutes = max(1,round($durationMinutes / 10));
      $query .= " AND `ten` = 1 ";
    }else{
      $query .= " AND `ten` = 0 ";
    }
    $query .= " ORDER BY sensor_time DESC LIMIT ".$durationMinutes." ";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getSensorData error : ".$dbLink->error."\r\n");
      error_log("                    : ".$query."\r\n");
      $dbLink->close();
      return NULL; 
    }
    // echo "Query: ".$query." - ".$data->num_rows."\r\n";
    while($sensorData=$data->fetch_assoc()){
      $result[] = $sensorData;
    }
    if($sortASC){
      $result=array_reverse($result);
    }
    $data->close();
    $dbLink->close();
    return $result;
  }
  public static function getSensorDataAt($sensorId, $dateTime){
    if( empty($sensorId) ) {
      return NULL;
    }
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `sensor_data`";
    $query .= " WHERE `sensor_id` = '".$dbLink->real_escape_string($sensorId)."'";
    $query .= " AND `sensor_time` = '".$dbLink->real_escape_string($dateTime)."'";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getSensorDataAt error : ".$dbLink->error."\r\n");
      error_log("                    : ".$query."\r\n");
      $dbLink->close();
      return NULL; 
    }
    $sensorData=$data->fetch_assoc();
    $result = $sensorData;
    $data->close();
    $dbLink->close();
    return $result;
  }
  // for compatibility
  public static function getStationSensorData($sensorId, $last, $from, $to, $ten){
    if( empty($sensorId) || ((empty($from) || empty($to)) && empty($last))){
      return NULL;
    }
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `sensor_data`";
    $query .= " WHERE `sensor_id` = '".$dbLink->real_escape_string($sensorId)."' ";
    if(empty($last)){
      if($ten == false){
        $query .= " AND `ten` = '0'";
      }else{
        $query .= " AND `ten` = '1'";
      }
      $query .= " AND `sensor_time` >= '".$dbLink->real_escape_string($from)."' AND `sensor_time` <= '".$dbLink->real_escape_string($to)."'";
      $query .= " ORDER BY `sensor_time` ASC ;";
      $data = $dbLink->query($query);
      if($data==false){
        error_log("getStationSensorData error: ".$dbLink->error."\r\n");
        $dbLink->close();
        return NULL; 
      }
      // echo "<!-- Query: ".$query." - ".$data->num_rows."\r\n -->";
      while($sensorData=$data->fetch_assoc()){
        $result[] = $sensorData;
      }
      $data->close();
      $dbLink->close();
      return $result;
    }
    //order limit
    $query .= " ORDER BY `sensor_time` DESC LIMIT 1 ;";
    $data = $dbLink->query($query);
    if($data === null) {
      $dbLink->close();
      return NULL;
    }
    if($data==false){
      error_log("getStationSensorData limit 1 error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    $sensorData = $data->fetch_assoc();
    $data->close();
    $dbLink->close();
    $result[] = $sensorData;
    return $result;
  }
  public static function getStations($online = true){
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `station` WHERE ";
    if($online){
      $query .= "`online` = 1 ";
    }else{
      $query .= "`online` = 0 ";
    }
    //order alpha
    $query .= "ORDER BY `display_name` ASC ;";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getStations error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    while($station=$data->fetch_assoc()){
      $result[$station['station_name']] = $station;
    }
    $data->close();
    $dbLink->close();
    return $result;
  }
  public static function getStationByName($stationName){
    if ( empty( $stationName ) ) return NULL;
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `station` WHERE `station_name` = '".$dbLink->real_escape_string($stationName)."'";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getStationsByName error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    $result=$data->fetch_assoc();
    $data->close();
    $dbLink->close();
    return $result;
  }
  public static function getStationByMsName($msName) {
    if ( empty( $msName ) ) return NULL;
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `station` WHERE `ms_name` = '".$dbLink->real_escape_string($msName)."'";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getStationsByName error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    $result=$data->fetch_assoc();
    $data->close();
    $dbLink->close();
    return $result;
  }
  public static function getStationForecast($stationName, $from, $to){
    if(empty($stationName) || empty($from) || empty($to)){
      return NULL;
    }
    $dbLink = self::connectDb();
    $result = array();
    $query  = "SELECT * FROM `forecast` ";
    $query .= "WHERE `station_name` = '".$dbLink->real_escape_string($stationName)."' ";
    $query .= " AND `reference_time` >= '".$dbLink->real_escape_string($from)."' AND `reference_time` <= '".$dbLink->real_escape_string($to)."'";
    $query .= " ORDER BY `reference_time` ASC ;";
    $data = $dbLink->query($query);
    if($data==false){
      error_log("getStationForecast error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return NULL; 
    }
    while($forecastData=$data->fetch_assoc()){
      $result[] = $forecastData;
    }
    $data->close();
    $dbLink->close();
    return $result;
  }
  // set
  public static function setForecast($station_name,$reference_time,$speed,$direction) {
    $dbLink = self::connectDb();
    $result = array();
    $query =  'INSERT INTO `forecast` ';
    $query .= '( `station_name`, `reference_time`, `speed`, `direction` ) ';
    $query .= 'VALUES ';
    $query .= "( '".$station_name."', '".$reference_time."', '".$speed."', '".$direction."' );";
    if (!$dbLink->query($query)) {
      error_log("SetForecast error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return false; 
    }
    $dbLink->close();
    return true; 
  }
  public static function setSensor($stationName, $sensorName, $channel) {
    $dbLink = self::connectDb();
    $sensorId = 0;
    $query =  'INSERT INTO `sensor` (station_name, sensor_name, channel)';
    $query .= ' VALUES ';
    $query .= "( '".$stationName."', '".$sensorName."', '".$channel."' );";
    if(!$dbLink->query($query)) {
      error_log("setSensor error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return false; 
    }
    $sensorId = $dbLink->insert_id;
    $dbLink->close();
    return $sensorId; 
  }
  public static function setSensorData($sensorId, $sensorTime, $battery, $temperature, $humidity, $barometer, 
                                      $direction, $speed, $average, $gust, $UV, $rainRate, $rainTotal, $ten = '0') {
    $dbLink = self::connectDb();
    // remove seconds
    $sensorTime=strtotime($sensorTime);
    $sensorTime=date('Y-m-d H:i:00', $sensorTime);  
    $result = array();
    $query  = 'INSERT INTO `sensor_data`';
    $query .= ' (`sensor_id`, `sensor_time`, `battery`, `temperature`, `humidity`, `barometer`, `direction`, `speed`, `average`, `gust`, `uv`, `rain_rate`, `rain_total`, `ten`) ';
    $query .= ' VALUES';
    $query .= " ('".$sensorId."','".$sensorTime."','".$battery."','".$temperature."','".$humidity."','".$barometer."','".$direction."','".$speed."','".$average."','".$gust."','".$UV."','".$rainRate."','".$rainTotal."','".$ten."') ";
    if (!$dbLink->query($query)) {
      error_log("setSensorData error : ".$dbLink->error."\r\n");
      error_log("                    : ".$query."\r\n");
      $dbLink->close();
      return false; 
    }
    $sensorDataId = $dbLink->insert_id;
    $dbLink->close();
    return $sensorDataId; 
  }
  public static function setStation($stationName, $displayName, $shortName, $MSName, $information, $altitude,
                                    $latitude, $longitude, $spotType, $online, $maintenance, $reason, $GMT) {
    $dbLink = self::connectDb();
    $station= self::getStationByName($stationName);
    $displayName = $dbLink->real_escape_string($displayName);
    $shortName = $dbLink->real_escape_string($shortName);
    $information = $dbLink->real_escape_string($information);
    if($station==NULL){
      $query = "INSERT INTO station (station_name, display_name, short_name, ms_name, information,
                  altitude, latitude, longitude, spot_type, wind_id, barometer_id, temperature_id, water_id,
                  online, maintenance, reason, GMT)   
                VALUES('$stationName', '$displayName', '$shortName', '$MSName', '$information', 
                  '$altitude','$latitude', '$longitude', '$spotType', 0, 0 ,0, 0,
                  '$online', '$maintenance', '$reason', '$GMT')";
      if(!$dbLink->query($query)) {
        error_log("setStation Insert error : ".$dbLink->error."\r\n");
        $dbLink->close();
        return false; 
      }
    } else {
      $query = "UPDATE station SET display_name = '$displayName', short_name = '$shortName',  ms_name = '$MSName',  information = '$information',
               altitude = '$altitude',  latitude = '$latitude',  longitude = '$longitude',  spot_type = '$spotType',  
               online = '$online', maintenance = '$maintenance', reason = '$reason', GMT = '$GMT' WHERE station_name = '$stationName'";
      if(!$dbLink->query($query)) {
        error_log("setStation Update error : ".$dbLink->error."\r\n");
        $dbLink->close();
        return false; 
      }
    }
    $dbLink->close();
    return true;
  }
  public static function setStationDataTime($stationName) {
    $dbLink = self::connectDb();
    $dataTime=date('Y-m-d H:i:s', time());  
    $query = "UPDATE station SET data_time = '$dataTime' WHERE station_name = '$stationName'";
    if (!$dbLink->query($query)) {
      error_log("setStationDataTime error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return false; 
    }
    $dbLink->close();
    return true; 
  }
  public static function setStationImageTime($stationName) {
    $dbLink = self::connectDb();
    $imageTime=date('Y-m-d H:i:s', time());  
    $query = "UPDATE station SET image_time = '$imageTime' WHERE station_name = '$stationName'";
    if (!$dbLink->query($query)) {
      error_log("setStationDataTime error : ".$dbLink->error."\r\n");
      $dbLink->close();
      return false; 
    }
    $dbLink->close();
    return true; 
  }
  // wipe
  public static function wipeOutStation($stationName) {
    $dbLink = self::connectDb();
    // forecast
    $query = "DELETE FROM `forecast` WHERE `station_name` = '".$stationName."' ";
    if (!$dbLink->query($query)) {
      error_log("wipeOutStation error : ".$dbLink->error."\r\n");
    }
    // sensor_data
    $query  = "SELECT * FROM `sensor` WHERE `station_name`='".$stationName."'  ";
    $data   = $dbLink->query($query);
    if($data==false){
      error_log("getSensor error : ".$dbLink->error."\r\n");
    } else {
      while($sensor = $data->fetch_assoc()){
        $query = "DELETE FROM `sensor_data` WHERE `sensor_id` = '".$sensor["id"]."' ";
        if (!$dbLink->query($query)) {
          error_log("wipeOutStation error : ".$dbLink->error."\r\n");
        }
      }
      $data->close();
    }
    // sensor
    $query  = "DELETE FROM `sensor` WHERE `station_name`='".$stationName."'  ";
    if (!$dbLink->query($query)) {
      error_log("wipeOutStation error : ".$dbLink->error."\r\n");
    }
    // station
    $query  = "DELETE FROM `station` WHERE `station_name`='".$stationName."'  ";
    if (!$dbLink->query($query)) {
      error_log("wipeOutStation error : ".$dbLink->error."\r\n");
    }
    $dbLink->close();
    return true; 
  }
}