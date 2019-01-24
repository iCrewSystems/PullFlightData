<?php
/**
 * This script gets all the entries in the database and places them in a csv file
 * The csv can be used to import into phpvms
 * If a tailnumber doesn't exist in your phpvms, that flight will not be imported
 */

//Create database connection


include ('../../phpvms/core/codon.config.php');
include ('../../phpvms/core/local.config.php');

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="ics_schedules.csv"');




$airlines = OperationsData::getAllAirlines($onlyenabled = true);
$fp = fopen('php://output', 'w');
$line = "code,flightnum,depicao,arricao,route,aircraft,flightlevel,distance,deptime,arrtime,flighttime,notes,price,flighttype,daysofweek,enabled, week1, week2, week3, week4";
fputcsv($fp, explode(',', $line));

foreach ($airlines as $airline) {
  $sql = "SELECT * FROM flights WHERE code = '$airline->code'";
  $flights = DB::get_results($sql);
  foreach ($flights as $s) {

    $flight_num = str_replace($airline->code, "", $s->flightnum);
    $line = "{$s->code},{$flight_num},{$s->depicao},{$s->arricao}," . "{$s->route},{$s->tailnum},{$s->flightlevel},{$s->distance}," .
        "{$s->deptime}, {$s->arrtime}, {$s->flighttime}, {$s->notes}, " . "{$s->price}, {$s->flighttype}, {$s->daysofweek}, {$s->enabled}, {$s->week1}, {$s->week2}, {$s->week3}, {$s->week4}";
        fputcsv($fp, explode(',', $line));
  }


fclose($fp);




}
