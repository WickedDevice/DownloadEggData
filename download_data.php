<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//

require_once('vendors/com.rapiddigitalllc/xively/api.php');

//print_r($_POST);
define("XIVELY_API_KEY", "3dEjaKNfZpmKBxNHVZuJWxwrVRFfL1VwaQdaNBendJFpRsq2");

if( (   array_key_exists("feed_id", $_POST) 
    && array_key_exists("datastream_id", $_POST) 
    && array_key_exists("time_unit", $_POST) )){          
    
$feed_id = $_POST["feed_id"];
$datastream_id = $_POST["datastream_id"];

$two_digit_keys = array("start_date_day", "start_date_month", "start_time_hour", "start_time_minute", "start_time_second","end_date_day", "end_date_month", "end_time_hour", "end_time_minute", "end_time_second");
$four_digit_keys = array("start_date_year", "end_date_year");

foreach($two_digit_keys as $k){
  $int_k = (int) $_POST[$k];
  if($int_k < 10){
    $_POST[$k] = "0" . $int_k;
  }
}

foreach($four_digit_keys as $k){
  $int_k = (int) $_POST[$k];
  if($int_k < 10){
    $_POST[$k] = "000" . $int_k;
  }
  else if($int_k < 100){
    $_POST[$k] = "00" . $int_k;
  }
  else if($int_k < 1000){
    $_POST[$k] = "0" . $int_k;
  }  
}


$start_time_string = $_POST["start_date_day"]."/".$_POST["start_date_month"]."/".$_POST["start_date_year"]." ";
$start_time_string .= $_POST["start_time_hour"].":".$_POST["start_time_minute"].":".$_POST["start_time_second"]." ";
$start_time_string .= $_POST["start_time_ampm"]." +00:00";
$start_time  = strtotime($start_time_string);
$start_date = date('c',$start_time);

$end_time_string = $_POST["end_date_day"]."/".$_POST["end_date_month"]."/".$_POST["end_date_year"]." ";
$end_time_string .= $_POST["end_time_hour"].":".$_POST["end_time_minute"].":".$_POST["end_time_second"]." ";
$end_time_string .= $_POST["end_time_ampm"]." +00:00";
$end_time = strtotime($end_time_string);
$end_date = date('c',$end_time);

$duration = (int) $_POST["duration"];
$time_unit = trim($_POST["time_unit"]);
$interval = (int) $_POST["interval"];       

$params = array('start' => $start_date, 'interval' => $interval);

if($duration == 0){
  $params['end'] = $end_date;

  $requestedRangeInSeconds = $end_time - $start_time;  
}
else{
  $params['duration'] =  $duration.$time_unit;
  $timeUnitToSeconds = array(
      "seconds" => 1,
      "minutes" => 60,
      "hours"   => 60*60,
      "days"    => 60*60*24,
      "weeks"   => 60*60*24*7,
      "months"  => 60*60*24*7*4,
      "years"   => 60*60*24*7*52
  );
  
  $requestedRangeInSeconds = $duration * $timeUnitToSeconds[$time_unit];
}

$numberOfIntervalsInRange = $requestedRangeInSeconds / $interval;


header('Content-Description: File Transfer');
header("Content-Type: application/csv") ;
header("Content-Disposition: attachment; filename=".$feed_id."-".$datastream_id.".csv");
header("Expires: 0");

  
// only 1000 data points per query are allowed
if($numberOfIntervalsInRange <= 900){ 
  $xi = \Xively\Api::forge(XIVELY_API_KEY);    
  $r = $xi->csv()->feeds($feed_id)->datastreams($datastream_id)->range($params)->read()->get();
  echo $r."\n"; 
}
else{
  // need to chunk it up into the relevant intervals
  // starting at start date ... add 1000 intervals until you've done enough
  
  $this_start_time = $start_time;
  $this_start_date = $start_date;
  $this_end_time = $this_start_time + 900 * $interval;
  $this_end_date = date('c',$this_end_time);
  $ii = 0;
  $params = array('start' => $this_start_date, 'end' => $this_end_date, 'interval' => $interval);
  
  //while($numberOfIntervalsInRange > 0){       
  $xi = \Xively\Api::forge(XIVELY_API_KEY);    
  while($ii < 5 && $this_start_time < $end_time){
    //$ii++;
    //echo "<br/>[[ $numberOfIntervalsInRange > 0 ]]<br/>";
    //echo $this_start_date.",".$this_end_date.",".$last_timestamp.",".$this_start_time."<br/>";
    
    //echo "array('start' => $this_start_date, 'end' => $this_end_date, 'interval' => $interval)<br/>";  
    
    $xi->reset();
    $params['start'] = $this_start_date;
    $params['end']   = $this_end_date;
    $params['interval'] = $interval;
    
    $r = $xi->csv()->feeds($feed_id)->datastreams($datastream_id)->range($params)->read()->get();
    
    $lines = explode("\n", $r);
    $last_line = end($lines);
    $columns = explode(",", $last_line);
    $last_timestamp = $columns[0];    
    //echo $last_timestamp."<br/>";
    $last_timestamp = explode(".", $last_timestamp);
    $last_timestamp = $last_timestamp[0];
    $last_timestamp = explode("T", $last_timestamp);
    $last_timestamp_date = explode("-", $last_timestamp[0]);
    $last_timestamp_time = explode(":", $last_timestamp[1]);
    $last_timestamp_ampm = "AM";
    if((int) $last_timestamp_time[0] == 0){
      $last_timestamp_time[0] = "12";      
    }
    else if((int) $last_timestamp_time[0] == 12){
      $last_timestamp_ampm = "PM";
      
    }
    else if((int) $last_timestamp_time[0] > 12){
      $last_timestamp_time[0] = (((int) $last_timestamp_time[0]) - 12);
      if($last_timestamp_time[0] < 10){
        $last_timestamp_time[0] = "0".$last_timestamp_time[0];
      }
      else{
        $last_timestamp_time[0] = $last_timestamp_time[0]."";
      }
      $last_timestamp_ampm = "PM";
    }
    
    $last_timestamp = $last_timestamp_date[1]."/".$last_timestamp_date[2]."/".$last_timestamp_date[0]." ".$last_timestamp_time[0].":".$last_timestamp_time[1].":".$last_timestamp_time[2]." ".$last_timestamp_ampm." +00:00";     
    //echo $last_timestamp."<br/>";           
    //echo "BREAK\n";
    echo $r."\n";
    //flush();
    
    //advance start time and end time by 100 intervals each
    //$numberOfIntervalsInRange -= 100;    
    
    $this_start_time  = strtotime($last_timestamp) + 1;
    $this_start_date = date('c', $this_start_time);      
    $this_end_time = $this_start_time + $interval * 900;
    if($this_end_time > $end_time) $this_end_time = $this_end_time;
    $this_end_date = date('c',$this_end_time);            
    
    //$this_start_time = $this_start_time + 100 * $interval;
    //$this_start_date = date('c',$this_start_time);      
    //$this_end_time = $this_start_time + $interval * min(100, $numberOfIntervalsInRange);
    //$this_end_date = date('c',$this_end_time);        
    
  }
  
}

//echo "# Datapoints Requested: ".$numberOfIntervalsInRange."<br/>";

//$r = $xi->csv()->feeds($feed_id)->datastreams($datastream_id)->range($params)->read()->get();
/*
$r = $xi->csv()->feeds(115602)->datastreams('CO_00-04-a3-37-cc-7f_1')->range(
        array(
                'start' => date('c',strtotime('-10 days')),
                'end' => date('c',strtotime('now')),
                'time_unit' => '',
        )
     )->read()->get();  
*/    
//echo $r;
     
}
else{

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Download Air Quality Egg Data</title>
<link rel="stylesheet" type="text/css" href="/download_form/view.css" media="all">
<script type="text/javascript" src="/download_form/view.js"></script>
<script type="text/javascript" src="/download_form/calendar.js"></script>
</head>
<body id="main_body" >
	
	<img id="top" src="/download_form/top.png" alt="">
	<div id="form_container">
		<form id="form_750224" class="appnitro"  method="post" action="">
					<div class="form_description">
			<h2>Air Quality Egg Data Download</h2>
			<p>Enter values into the fields below and click Submit to get CSV data. Mouse-over fields for guidance.</p>
		</div>						
			<ul >
			
					<li id="li_1" >
		<label class="description" for="feed_id">Feed ID </label>
		<div>
			<input id="element_1" name="feed_id" class="element text medium" type="text" maxlength="255" value=""/> 
		</div><p class="guidelines" id="guide_1"><small>e.g. <b>115601</b>, as in airqualityegg.com/eggs/<b>115601</b></small></p> 
		</li>		<li id="li_2" >
		<label class="description" for="datastream_id">Datastream ID </label>
		<div>
			<input id="element_2" name="datastream_id" class="element text medium" type="text" maxlength="255" value=""/> 
		</div><p class="guidelines" id="guide_2"><small>e.g. CO_00-04-a3-37-cc-7f_1, as seen at xively.com/feeds/<b>115601</b></small></p> 
		</li>		<li class="section_break">
			<h3></h3>
			<p></p>
		</li>		<li id="li_3" >
		<label class="description" for="start_date">Start Date </label>
		<span>
			<input id="element_3_1" name="start_date_day" class="element text" size="2" maxlength="2" value="" type="text"> /
			<label for="element_3_1">MM</label>
		</span>
		<span>
			<input id="element_3_2" name="start_date_month" class="element text" size="2" maxlength="2" value="" type="text"> /
			<label for="element_3_2">DD</label>
		</span>
		<span>
	 		<input id="element_3_3" name="start_date_year" class="element text" size="4" maxlength="4" value="" type="text">
			<label for="element_3_3">YYYY</label>
		</span>
	
		<span id="calendar_3">
			<img id="cal_img_3" class="datepicker" src="/download_form/calendar.gif" alt="Pick a date.">	
		</span>
		<script type="text/javascript">
			Calendar.setup({
			inputField	 : "element_3_3",
			baseField    : "element_3",
			displayArea  : "calendar_3",
			button		 : "cal_img_3",
			ifFormat	 : "%B %e, %Y",
			onSelect	 : selectDate
			});
		</script>
		 
		</li>		<li id="li_4" >
		<label class="description" for="start_time">Start Time </label>
		<span>
			<input id="element_4_1" name="start_time_hour" class="element text " size="2" type="text" maxlength="2" value=""/> : 
			<label>HH</label>
		</span>
		<span>
			<input id="element_4_2" name="start_time_minute" class="element text " size="2" type="text" maxlength="2" value=""/> : 
			<label>MM</label>
		</span>
		<span>
			<input id="element_4_3" name="start_time_second" class="element text " size="2" type="text" maxlength="2" value=""/>
			<label>SS</label>
		</span>
		<span>
			<select class="element select" style="width:4em" id="element_4_4" name="start_time_ampm">
				<option value="AM" >AM</option>
				<option value="PM" >PM</option>
			</select>
			<label>AM/PM</label>
		</span> 
		</li>		<li id="li_10" >
		<label class="description" for="time_unit">Duration </label>
		<div>
		<input id="sdfkjksdkljdsf" name="duration" class="element text " size="3" type="text" maxlength="3" value=""/> 
		<select class="element select medium" id="element_10" name="time_unit"> 
      <option value="seconds" >seconds</option>
      <option value="minutes" >minutes</option>
      <option value="hours" selected="selected">hours</option>
      <option value="days" >days</option>
      <option value="weeks" >weeks</option>
      <option value="months" >months</option>
      <option value="years" >years</option>
		</select>
		<p class="guidelines" id="guide_1"><small>e.g. 8 hours, provide duration *or* end date, if you provide both, duration wins</small></p> 
		</div> 
		</li>		
		</li>		<li class="section_break">
			<h3></h3>
			<p></p>
		</li>		<li id="li_5" >
		<label class="description" for="end_date">End Date </label>
		<span>
			<input id="element_5_1" name="end_date_day" class="element text" size="2" maxlength="2" value="" type="text"> /
			<label for="element_5_1">MM</label>
		</span>
		<span>
			<input id="element_5_2" name="end_date_month" class="element text" size="2" maxlength="2" value="" type="text"> /
			<label for="element_5_2">DD</label>
		</span>
		<span>
	 		<input id="element_5_3" name="end_date_year" class="element text" size="4" maxlength="4" value="" type="text">
			<label for="element_5_3">YYYY</label>
		</span>
	
		<span id="calendar_5">
			<img id="cal_img_5" class="datepicker" src="/download_form/calendar.gif" alt="Pick a date.">	
		</span>
		<script type="text/javascript">
			Calendar.setup({
			inputField	 : "element_5_3",
			baseField    : "element_5",
			displayArea  : "calendar_5",
			button		 : "cal_img_5",
			ifFormat	 : "%B %e, %Y",
			onSelect	 : selectDate
			});
		</script>
		 
		</li>		<li id="li_6" >
		<label class="description" for="end_time">End Time </label>
		<span>
			<input id="element_6_1" name="end_time_hour" class="element text " size="2" type="text" maxlength="2" value=""/> : 
			<label>HH</label>
		</span>
		<span>
			<input id="element_6_2" name="end_time_minute" class="element text " size="2" type="text" maxlength="2" value=""/> : 
			<label>MM</label>
		</span>
		<span>
			<input id="element_6_3" name="end_time_second" class="element text " size="2" type="text" maxlength="2" value=""/>
			<label>SS</label>
		</span>
		<span>
			<select class="element select" style="width:4em" id="element_6_4" name="end_time_ampm">
				<option value="AM" >AM</option>
				<option value="PM" >PM</option>
			</select>
			<label>AM/PM</label>
		</span> 
		</li>		<li id="li_1dsafsdfas" >
		<label class="description" for="interval">Interval </label>
		<div>
		<select class="element select large" id="element_10asdfadsfsd" name="interval"> 
			<option value="0" selected="selected">All Samples</option>
      <option value="30" >One datapoint every 30 seconds</option>
      <option value="60" >One datapoint every minute</option>
      <option value="300" selected="selected">One datapoint every 5 minutes</option>
      <option value="900" >One datapoint every 15 minutes</option>
      <option value="1800" >One datapoint per 30 minutes</option>
      <option value="3600" >One datapoint per hour</option>
      <option value="10800" >One datapoint per three hours</option>
      <option value="21600" >One datapoint per six hours</option>      
      <option value="43200" >One datapoint per twelve hours</option>   
      <option value="86400" >One datapoint per day</option>
		</select>
		<p class="guidelines" id="guide_2"><small>This doesn't do interpolation / averaging, but rather down-samples the data as requested</small></p> 
		</div> 
		</li>		
		</li>	<li class="section_break">
			<h3></h3>
			<p></p>			
					<li class="buttons">
			    <input type="hidden" name="form_id" value="750224" />
			    
				<input id="saveForm" class="button_text" type="submit" name="submit" value="Submit" />
		</li>
			</ul>
		</form>	
		<div id="footer">
			Generated by <a href="http://www.phpform.org">pForm</a>
		</div>
	</div>
	<img id="bottom" src="/download_form/bottom.png" alt="">
	</body>
</html>
<?php } ?>
