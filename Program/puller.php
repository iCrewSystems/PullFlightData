<?php
/**
 * Created by Rami Abou Zahra, A.K.A RAZERZ
 * Feel free to commit or distribute, it's FOSS!
 * Any issues can be raised on github or on the thread in the phpvms forum :)
 */
//Firstly, we define our API credentials

include ('../../phpvms/core/codon.config.php');
include ('../../phpvms/core/local.config.php');

$apiUserId = "Leonard1234";
$apiKey = "97a9db8ead21f0a3b5e60d95bfa5cb5129ffd562";
ini_set('max_execution_time', 300); //300 seconds = 5 minutes

//We create a class which takes care of all the authentication happening in FlightAware
class API {
    public function authenticate($apiUserId, $apiKey, $ICAO, $airline) {
        $query = array(
            'airport_code' => $ICAO,
            'filter' => 'airline:' . $airline
        ); //You need to replace 'ICAO' with the ICAO you wish to pull from and add your airline code

        //We will now create a variable that will store this info that will be sent via a cURL query
        $queryUrl = "https://flightxml.flightaware.com/json/FlightXML3/AirportBoards?" . http_build_query($query);

        //Initiate cURL!
        $ch = curl_init($queryUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $apiUserId . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //Gets the result from the query and places them in a variable
        return $result = curl_exec($ch);
        curl_close($ch);
    }
}


//Create a function that takes in the decoded json and sends it to the database
function flights($decodedResult, $position) { //Position is either 'arrival' or 'departures'

    //We need to get the flights from the multidimensional array
    $positionState = $decodedResult['AirportBoardsResult']["$position"]['flights'];

    if(!empty($positionState)) {
      //Foreach position State, we want to get the info and send them to the database
      foreach($positionState as $flight) {

          //We need to make a connection to the database, we use SQL
          // $mysql = new mysqli('localhost', 'phpvms_user', 'pwrd', 'flightawarepuller');


          //We should set some variables to make it easier
          $callsign = $flight['ident'];
          $depIcao = $flight['origin']['code'];
          $arrIcao = $flight['destination']['code'];
          $tailnumber = $flight['tailnumber'];
          $distance = $flight['distance_filed']; //We use the filed one to match the irl schedules, not irl events
          $deptime = date('H:i', strtotime($flight['filed_departure_time']['time'])); //It is recommended to use 24 hour format
          $arrtime = date('H:i', strtotime($flight['filed_arrival_time']['time']));
          $flighttime = gmdate('H:i', $flight['filed_ete']); //We convert it from Unix time stamp to human readable
          $route = $flight['route']; //Doesn't always exist, if it doesn't, we'll set it to nothing to avoid issues when sending to database
          $altitude = $flight['filed_altitude']; //Same as above
          if(empty($route)) $route = "";
          if(empty($altitude)) $altitude = "";
          $currDay = date("N"); //This is to get the current day of the week in numbers
          global $airline; //We'll also be using the '$airline' variable

          //Now we can start pushing to the database!
          //First of all, we need to check if the callsign already exits
          //If it does, we'll just update the data in the database rather than create two entries
          //We also need to increment the days of the week the flight is flown if it exits

          $sql1 = "SELECT * FROM flights WHERE flightnum = '$callsign'";
          $ret = DB::get_results($sql1);

          if($ret != '') {
            foreach($ret as $value){ }
              $sql1 = "UPDATE flights SET depicao = '$depIcao', arricao = '$arrIcao', route = '$route', tailnum = '$tailnumber', flightlevel = '$altitude', distance = '$distance', deptime = '$deptime', arrtime = '$arrtime', flighttime = '$flighttime', daysofweek = CONCAT(daysofweek, '$currDay') WHERE ID = '$value->id'";
              $ret = DB::query($sql1);
              echo "Success : updated $callsign ID : $value->id<br>";
          } else {
            $sql1 = "INSERT INTO flights VALUES('', '$airline', '$callsign', '$depIcao', '$arrIcao', '$route', '$tailnumber', '$altitude', '$distance', '$deptime', '$arrtime', '$flighttime', 'Route fetched by ICS Route fetcher v2', '160', 'P', '$currDay', '1')";
            $ret = DB::query($sql1);
            echo "Success : added $callsign <br>";
          }
      }
    } else {
      echo "Error : No $position found for Airline $airline from $ICAO Airport <br>";
    }



}



$airlines = OperationsData::getAllAirlines($onlyenabled = true);

foreach ($airlines as $phpvms_airline) {
  echo "Name : ".$phpvms_airline->name.", ICAO Code : ".$phpvms_airline->code."<br>";

  $airports = OperationsData::getAllAirports();
  if(count($airports) > 0) {
    echo count($airports).' Airports found on Database...Now proceeding to fetch data from FlightAware one by one (limited to 15 results per query)<br>';
    foreach ($airports as $airport) {

      echo "==================================================<br>
      Fetching details for : ".$airport->icao."...<br>";

      $ICAO = $airport->icao;
      $airline = $phpvms_airline->code;

      //Creates a new instance of the class, ready to be used
      $newSession = new API();

      //Gets the results from the function
      $returnedResult = $newSession->authenticate($apiUserId, $apiKey, $ICAO, $airline);

      //Now we can decode this json and place it into a nice array
      $decodedResult = json_decode($returnedResult, true); //Set it to true so we get a multidimensional array

      flights($decodedResult, "arrivals");
      flights($decodedResult, "departures");


    }
  } else {
    echo 'No Airports found on Database...you need to add Airports first!';
  }

}
