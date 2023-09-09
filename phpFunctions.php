<?php

//This file is used to store PHP functions for regular use

//check current session variables and make sure that session exists and the active session has not expired
function sessionCheck($accessLevel, $temporary_allowed = false)
{
	//Expire the session if user is inactive for 8 hours
	//minutes or more.
	$expireAfter = 60 * 8;

	//initialize return Address
	$return = "";

	if (isset($_SESSION['returnAddress'])) {
		$return = $_SESSION['returnAddress'];
	}

	// if user has timezone session set, update to default in php
	if (isset($_SESSION['timezone']))
		date_default_timezone_set($_SESSION['timezone']);

	// set redirect URL (based on temporary_allowed)
	if (!$temporary_allowed)
		$redirect = "index.php";
	else
		$redirect = "index_external.php";

	//Check to see if our "last action" session
	//variable has been set.
	if (isset($_SESSION['last_action'])) {

		//Figure out how many seconds have passed
		//since the user was last active.
		$secondsInactive = time() - $_SESSION['last_action'];

		//Convert our minutes into seconds.
		$expireAfterSeconds = $expireAfter * 60;

		//Check to see if they have been inactive for too long.
		if ($secondsInactive >= $expireAfterSeconds) {
			//User has been inactive for too long.
			//Kill their session.
			session_unset();
			session_destroy();
			session_start();
			$_SESSION['returnAddress'] = $return;
			header("Location: " . $redirect);
			exit();
		}

		//Check to see if they have access to the application
		if ($accessLevel == "None") {
			//User does not have access, Kill their session and take them to the log-in screen.
			session_unset();
			session_destroy();
			session_start();
			$_SESSION['returnAddress'] = $return;
			header("Location: " . $redirect);
			exit();
		}

		//Check to see if user has been granted temporary access and this page allows it
		/*if($accessLevel == "Temporary" && !$temporary_allowed){
			//User does not have access, Kill their session and take them to the log-in screen.
			session_unset();
			session_destroy();
			session_start();
			$_SESSION['returnAddress'] = $return;
			header("Location: " . $redirect);
			exit();
		}*/
	}
	//if it is not set, redirect to log-in so we can gather credentials
	else {
		session_unset();
		session_destroy();
		session_start();
		$_SESSION['returnAddress'] = $return;
		header("Location: " . $redirect);
		exit();
	}

	//Assign the current timestamp as the user's
	//latest activity (save to session and DB)
	$_SESSION['last_action'] = time();

	//include database config, write/execute
	if (isset($_SESSION['employeeID'])) {
		include('config.php');
		$query = "UPDATE fst_users SET last_access = NOW() WHERE id = '" . $_SESSION['employeeID'] . "';";
		$mysqli->query($query);
	}
}

/**
 * Logs a message to a log file with a timestamp.
 * @author Alex Borchers
 * @param mixed $message The message to be logged.
 * @return void
 */
function log_message($message)
{
	$log_file = 'Static/logs.txt'; // set the name of the log file
	$timestamp = date('Y-m-d H:i:s'); // get the current date and time in a format that's easy to read
	$message = "[$timestamp] $message\n"; // add the timestamp to the message

	// write the message to the log file
	file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX);
}

/** 
 * Handles converting UTC timestamp to local
 * @author Alex Borchers
 * @param datetime $timestamp UTC timestamp from sql
 * @return string
 */
function convert_timestamp_to_local($timestamp)
{

	//convert lastUpdate to local (https://stackoverflow.com/questions/3792066/convert-utc-dates-to-local-time-in-php)
	if ($timestamp == "0000-00-00 00:00:00" || $timestamp == null) {
		$local = "";
	} else {
		$local = new DateTime($timestamp, new DateTimeZone('UTC'));
		//$loc = ;
		$local->setTimezone((new DateTime)->getTimezone());
		$local = $local->format('n-j-Y') . " at " . $local->format('h:i A');
	}

	return $local;
}

/**
 * Formats a date string in yyyy-mm-dd format to mm/dd/yyyy format.
 * @author Alex Borchers
 * @param mixed $date_string The date string to format. Can be a string in yyyy-mm-dd format or a DateTime object.
 * @return string The formatted date string in mm/dd/yyyy format.
 */
function format_date($date_string)
{
	// If $date_string empty, return empty string
	if (empty($date_string))
		return "";

	// Create a DateTime object from the input string
	$date = new DateTime($date_string);

	// Format the date as mm/dd/yyyy
	$formatted_date = $date->format('m/d/Y');

	// Return the formatted date
	return $formatted_date;
}

//drops any 0 digits (unused) after the decimal
function tofloat($num)
{
	$dotPos = strrpos($num, '.');
	$commaPos = strrpos($num, ',');
	$sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

	if (!$sep) {
		return floatval(preg_replace("/[^0-9]/", "", $num));
	}

	return floatval(
		preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
			preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num)))
	);
}


//search and replace within a string
function str_replace_json($search, $replace, $subject)
{
	return json_decode(str_replace($search, $replace,  json_encode($subject)));
}


//format values from $000,000.00 format to 000000.00
function formatCurrency($money)
{
	//used to tell if currency is negative
	$sign = isNegative($money);

	//cleans string (removes '$', ',', '.' etc)
	$cleanString = preg_replace('/([^0-9\.,])/i', '', $money);
	$onlyNumbersString = preg_replace('/([^0-9])/i', '', $money);

	$separatorsCountToBeErased = strlen($cleanString) - strlen($onlyNumbersString) - 1;

	$stringWithCommaOrDot = preg_replace('/([,\.])/', '', $cleanString, $separatorsCountToBeErased);
	$removedThousandSeparator = preg_replace('/(\.|,)(?=[0-9]{3,}$)/', '',  $stringWithCommaOrDot);

	//return float (multiplied by the sign)
	return (float) str_replace(',', '.', $removedThousandSeparator) * $sign;
}

//turns money negative if it is negative
function isNegative($money)
{
	$negative = strpos($money, "-");

	if ($negative > 0) {
		return -1;
	} else {
		return 1;
	}
}

function mysql_escape_mimic($inp)
{
	if (is_array($inp))
		return array_map(__METHOD__, $inp);

	//replace instance in first array with second
	if (!empty($inp) && is_string($inp)) {
		return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
	}

	return $inp;
}


//format values from 000000.00 format to $000,000.00
function convert_money($format, $number)
{
	$regex  = '/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?' .
		'(?:#([0-9]+))?(?:\.([0-9]+))?([in%])/';
	if (setlocale(LC_MONETARY, 0) == 'C') {
		setlocale(LC_MONETARY, '');
	}
	$locale = localeconv();
	preg_match_all($regex, $format, $matches, PREG_SET_ORDER);
	foreach ($matches as $fmatch) {
		$value = floatval($number);
		$flags = array(
			'fillchar'  => preg_match('/\=(.)/', $fmatch[1], $match) ?
				$match[1] : ' ',
			'nogroup'   => preg_match('/\^/', $fmatch[1]) > 0,
			'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ?
				$match[0] : '+',
			'nosimbol'  => preg_match('/\!/', $fmatch[1]) > 0,
			'isleft'    => preg_match('/\-/', $fmatch[1]) > 0
		);
		$width      = trim($fmatch[2]) ? (int)$fmatch[2] : 0;
		$left       = trim($fmatch[3]) ? (int)$fmatch[3] : 0;
		$right      = trim($fmatch[4]) ? (int)$fmatch[4] : $locale['int_frac_digits'];
		$conversion = $fmatch[5];

		$positive = true;
		if ($value < 0) {
			$positive = false;
			$value  *= -1;
		}
		$letter = $positive ? 'p' : 'n';

		$prefix = $suffix = $cprefix = $csuffix = $signal = '';

		$signal = $positive ? $locale['positive_sign'] : $locale['negative_sign'];
		switch (true) {
			case $locale["{$letter}_sign_posn"] == 1 && $flags['usesignal'] == '+':
				$prefix = $signal;
				break;
			case $locale["{$letter}_sign_posn"] == 2 && $flags['usesignal'] == '+':
				$suffix = $signal;
				break;
			case $locale["{$letter}_sign_posn"] == 3 && $flags['usesignal'] == '+':
				$cprefix = $signal;
				break;
			case $locale["{$letter}_sign_posn"] == 4 && $flags['usesignal'] == '+':
				$csuffix = $signal;
				break;
			case $flags['usesignal'] == '(':
			case $locale["{$letter}_sign_posn"] == 0:
				$prefix = '-';
				$suffix = '';
				break;
		}
		if (!$flags['nosimbol']) {
			$currency = $cprefix .
				($conversion == 'i' ? $locale['int_curr_symbol'] : $locale['currency_symbol']) .
				$csuffix;
		} else {
			$currency = '';
		}
		$space  = $locale["{$letter}_sep_by_space"] ? ' ' : '';

		$value = number_format(
			$value,
			$right,
			$locale['mon_decimal_point'],
			$flags['nogroup'] ? '' : $locale['mon_thousands_sep']
		);
		$value = @explode($locale['mon_decimal_point'], $value);

		$n = strlen($prefix) + strlen($currency) + strlen($value[0]);
		if ($left > 0 && $left > $n) {
			$value[0] = str_repeat($flags['fillchar'], $left - $n) . $value[0];
		}
		$value = implode($locale['mon_decimal_point'], $value);
		if ($locale["{$letter}_cs_precedes"]) {
			$value =  $currency . $prefix . $space . $value . $suffix;
		} else {
			$value = $prefix . $value . $space . $currency . $suffix;
		}
		if ($width > 0) {
			$value = str_pad($value, $width, $flags['fillchar'], $flags['isleft'] ?
				STR_PAD_RIGHT : STR_PAD_LEFT);
		}

		$format = str_replace($fmatch[0], $value, $format);
	}
	return $format;
}

/**
 * Converts a string with underscores into a string with spaces
 * @param string $inputString 
 * @return string 
 */
function convert_underscores_to_spaces($input_string)
{
	$replaced_string = str_replace('_', ' ', $input_string);
	$capitalized_string = ucwords($replaced_string);
	return $capitalized_string;
}

//takes array and targ, returns the index
function search_array($array, $targ)
{

	for ($i = 0; $i < sizeof($array); $i++) {

		if ($targ == $array[$i]) {
			return $i;
		}
	}

	//if we don't find index, return -1
	return -1;
}

//logs all part edits
//param 1 = partnumber
//param 2 = user id
//param 3 = description
function part_log($part, $user_id, $description)
{

	//include database config
	include('config.php');

	//step1: create error log and save to database
	$query = "INSERT INTO invreport_logs (id, partNumber, description, user, type, time_stamp) VALUES (null, '" . mysql_escape_mimic($part) . "', '" . $description . "', '" . $user_id . "', 'PA', NOW());";
	$mysqli->query($query);
}

//calculated urgency based on date
function urgency_calculator($due_date, $type = null)
{

	// If due_date is blank, return standard
	if ($due_date == "")
		return "[Standard]";

	//take the time of the request, compare it to the time of the request and assign urgency based on this
	$date1 = date_create_from_format('Y-m-d h:i:s', $due_date . ' 2:00:00');
	$date2 = date_create_from_format('Y-m-d h:i:s', date('Y-m-d h:i:s'));

	//calculates difference between two dates (in years, months, days, hs, etc.)
	$diff = (array) (date_diff($date1, $date2));

	$compNum = $diff['d'] . '.' . $diff['h'] . $diff['i'] . $diff['s'];

	$compNum = strval($compNum);

	//if $diff year or month > 1, add 30 days (default to standard)
	$compMonth = $diff['m'];
	$compYear = $diff['y'];

	$compMonth = strval($compMonth);
	$compYear = strval($compYear);

	if ($compMonth > 0 || $compYear > 0)
		$compNum += 30;

	//used for MMD
	if ($type == "mmd") {
		if ($compNum <= 1) {
			return "[Hot]";
		} elseif ($compNum <= 2) {
			return "[Priority]";
		} else {
			return "[Standard]";
		}
	}
	//used for parts requests
	else {
		if ($compNum <= 2) {
			return "[Overnight]";
		} elseif ($compNum <= 5) {
			return "[Urgent]";
		} else {
			return "[Standard]";
		}
	}
}

//performs a part substitution in our database
function part_sub_handler($old_part, $part_id, $new_part, $quote, $new_allocated, $user_id)
{

	//include database config
	include('config.php');

	//first grab cost of new part
	$query = "SELECT cost, partDescription, partCategory, manufacturer, `OMA-1` + `OMA-2` as 'OMA', `CHA-1` as 'CHA' FROM invreport WHERE partNumber = '" . $new_part . "' LIMIT 1;";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0) {
		$temp = mysqli_fetch_array($result);
	} else {
		$temp['cost'] = 0;
		$temp['partDescription'] = '';
		$temp['partCategory'] = '';
		$temp['manufacturer'] = '';
		$temp['OMA'] = 0;
		$temp['CHA'] = 0;
	}

	//query to update allocated amount & part number
	$query = "UPDATE fst_values SET manualOpt = 'M' WHERE quoteNumber = '" . $quote . "' and fst_values.key = 'matL';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//depending on value of part_id, use different query (if -1, this came from allocations screen)
	//query used to update basic info about part (description, cost, etc.)
	if ($part_id == -1)
		$query = "UPDATE fst_boms SET partNumber = '" . mysql_escape_mimic($new_part) . "', cost = '" . $temp['cost'] . "', description = '" . mysql_escape_mimic($temp['partDescription']) . "', partCategory = '" . $temp['partCategory'] . "', manufacturer = '" . mysql_escape_mimic($temp['manufacturer']) . "', subs_list = '' 
					WHERE partNumber = '" . mysql_escape_mimic($old_part) . "' AND quoteNumber = '" . $quote . "';";
	else
		$query = "UPDATE fst_boms SET allocated = " . $new_allocated . ", partNumber = '" . mysql_escape_mimic($new_part) . "', cost = '" . $temp['cost'] . "', description = '" . mysql_escape_mimic($temp['partDescription']) . "', partCategory = '" . $temp['partCategory'] . "', manufacturer = '" . mysql_escape_mimic($temp['manufacturer']) . "', subs_list = '' WHERE fst_boms.id = " . $part_id;

	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Log new project creation
	$notification = new Notifications($con, "material_change", "Part Substitution|" . $old_part . "=>" . $new_part, $quote, $use);
	$notification->log_notification($user_id);

	//return inventory values
	return $temp;
}

//handles generating viewpoint BOM row
function vp_bom_row($part, $project_id, $co_string)
{

	//load DB configurations
	include('config.php');

	//parse out substition brackets if applicable
	if (strpos($part['part_id'], "[") !== false) {
		$part['part_id'] = substr($part['part_id'], 0, strpos($part['part_id'], "[") - 1);
	}

	//get inventory attributes based on part
	$query = "SELECT * FROM invreport WHERE partNumber = '" . mysql_escape_mimic($part['part_id']) . "' LIMIT 1;";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0) {
		$attributes = mysqli_fetch_array($result);
	} else {
		$attributes['partNumber'] = $part['part_id'];
		$attributes['uom'] = "EA";
		$attributes['partCategory'] = "";
	}

	//initialize part_notes
	$note = "";

	//used to set purchase options
	if ($part['decision'] == "PO") {
		$po1 = "Y";
		$po2 = "P";
		$part['decision'] = "";
		$note = $part['instructions'];
	} else {
		$po1 = "";
		$po2 = "";
		$note = $part['mo_id'];
	}

	//init array to be returned
	$csv = [];

	//add data to row according to template in Viewpoint (PM Import Estmates > Detail > Materials)
	array_push($csv, '5');															//1-Template Tell (5=Materials) 
	array_push($csv, $project_id);													//2-Project #
	array_push($csv, '1');															//3-Always 1
	array_push($csv, material_phase($attributes['partCategory']) . $co_string);		//4-Phase
	array_push($csv, '2');															//5-Cost Type (always 2)
	array_push($csv, $part['q_allocated']);											//6-Quantity
	array_push($csv, $attributes['uom']);											//7-UOM
	array_push($csv, '');															//8-Always Blank
	array_push($csv, 'E');															//9-Always E
	array_push($csv, $attributes['partNumber']);									//10-Part Number
	array_push($csv, '');															//11-Always Blank
	array_push($csv, '');															//12-Always Blank
	array_push($csv, $note);														//13-Notes 
	array_push($csv, '');															//14-Always Blank
	array_push($csv, '1');															//15-InCo (always 1)
	array_push($csv, $part['decision']);											//16-Location
	array_push($csv, $po1);															//17-Purchase (Y if PO)
	array_push($csv, $po2);															//18-Purchase (P if PO)

	//return row
	return $csv;
}

//handes deciding if a part should be active or passive (returns code 03000 (active) or 06000 (passive))
//passes a part category
function material_phase($category)
{

	//list of actives
	$active_cat = ["ACT-DASHE", "ACT-DASREM", "REPTR-BDA", "ASiR", "CBRS_CBSD", "ALU-BTS", "ERCSNDOT", "ERCSN-ENDB", "JMA-XRAN", "MODEMS", "NETW-EQUIP", "NOKIA-MM", "PS-REAPTER", "SFP-CARD", "SMCL-PICOC", "SPDRCLOUD", "WIFIAP&HDW", "PLTE-EPC", "PLTE-EUD", "PLTE-RAN", "PLTE-SAS", "PLTE-SIMS", "PS-REAPTER", "SAM-BTS"];

	//cycle and look for a match (if yes, return 03000)
	for ($i = 0; $i < sizeof($active_cat); $i++) {
		if ($active_cat[$i] == $category)
			return "03000";
	}

	//if we don't find a match, return passive 06000
	return "06000";
}

//handles generating objects used in terminal_hub {object where key = pointer & options = array of options}
//passes query needed to get options, key name and options name
function get_pointer_options($query, $key, $option)
{

	//load DB configurations
	include('config.php');

	//init pointer_options (object to be returned)
	$pointer_options = [];

	//init current key (we'll use this to determine once we switch to new key)//strategy is to create object where cateogry => array of options
	$current_key = "";

	//execute passed query
	$result = mysqli_query($con, $query);

	//loop through query results
	while ($rows = mysqli_fetch_assoc($result)) {

		//check current category (if blank this is the first one)
		if ($current_key == "") {
			$current_key = $rows[$key];
			$options = [];
		} elseif ($current_key != $rows[$key]) {

			//create temp array and push to attribute assignments
			$temp = array(
				'key' => $current_key,
				'options' => $options
			);

			//push to attribute assignments
			array_push($pointer_options, $temp);

			//reset current category and category options
			$current_key = $rows[$key];
			$options = [];
		}

		//push to array
		array_push($options, $rows[$option]);
	}

	//add last category to object
	$temp = array(
		'key' => $current_key,
		'options' => $options
	);

	//push to attribute assignments
	array_push($pointer_options, $temp);

	//return assignments
	return $pointer_options;
}

//handles generating array of arrays to be passed to hub_check_csv later
//passes checkarrays (previous array to be added to) and header (db table header)
function hub_add_check_arrays($check_arrays, $header)
{

	//load DB configurations
	include('config.php');

	//init temp_check array
	$temp_check = [];

	//trim header (takes care of last line CSV issues)
	$header = trim($header);

	//based on header, run checks needed to verify
	if ($header == "partCategory" || $header == "manufacturer" || $header == "uom") {

		//add empty value
		array_push($temp_check, "");

		$query = "select `" . $header . "` from invreport GROUP BY `" . $header . "` ORDER BY `" . $header . "`;";
		$result = mysqli_query($con, $query);

		//if we returned a result, look for options and add to array
		if (mysqli_num_rows($result) > 0) {

			//loop through and push to array
			while ($rows = mysqli_fetch_assoc($result)) {
				array_push($temp_check, $rows[$header]);
			}
		}
	} elseif ($header == "pref_part" || $header == "hot_part") {
		$temp_check = ['', 'TRUE', 'FALSE'];
	}
	//else check for attributes
	else {
		$query = "select * from inv_attributes_options WHERE attribute_key = '" . $header . "';";
		$result = mysqli_query($con, $query);

		//if we returned a result, look for options and add to array
		if (mysqli_num_rows($result) > 0) {

			//add empty value
			array_push($temp_check, "");

			//loop through and push to array
			while ($rows = mysqli_fetch_assoc($result)) {
				array_push($temp_check, $rows['options']);
			}
		}
	}

	//push temp_check to check_arrays and return
	array_push($check_arrays, $temp_check);
	return $check_arrays;
}

//used to check if hub values are correct
//passes header (db table header), value (value to check), and check_array (the array that the value may need to be checked on)
function hub_check_csv($header, $value, $check_array)
{

	//init return array
	$return_array = [];

	//trim value
	$value = trim($value);

	//if we have an array, use it to run checks
	if (sizeof($check_array) > 0) {

		//loop through check array and compare values
		for ($i = 0; $i < sizeof($check_array); $i++) {
			//if we find a match, return true
			if (strtolower(trim($check_array[$i])) == strtolower(trim($value))) {
				array_push($return_array, 'true');
				array_push($return_array, $check_array[$i]);
				return $return_array;
			}
		}
	}
	//if related to a number, make sure it is numeric
	elseif ($header == "price" || $header == "cost") {
		if (is_numeric($value)) {
			array_push($return_array, 'true');
			array_push($return_array, $value);
			return $return_array;
		}
	}
	//if date, re-format datestring to match desired format "yyyy-mm-dd"
	elseif ($header == "quoteDate") {

		//update value if not blank
		if ($value != "" && $value != null) {
			$date = new DateTime($value);
			$value = $date->format("Y-m-d");
		}

		array_push($return_array, 'true');
		array_push($return_array, $value);
		return $return_array;
	}
	//else we do not have any restrictions, return true
	else {
		array_push($return_array, 'true');
		array_push($return_array, $value);
		return $return_array;
	}

	//if we reach the end, return an error
	array_push($return_array, 'false');
	array_push($return_array, trim($value));
	return $return_array;
}

//function used to generate unique name for files
function new_name($name, $type)
{

	//find type extension
	$ext_pos = strpos($name, $type) - 1;

	//cut off extension
	$name = substr($name, 0, $ext_pos);

	//take first 20 letters
	$name = substr($name, 0, 25);

	//generate random sequence of characters
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < 6; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}

	//concat new name, random id, and type, and return
	$name = $name . $randomString . "." . $type;

	return $name;
}

/**
 * Handles formatting $_POST from home_ops & applications.php to pass to initiate_service_request function
 * @param mixed $con SQL Connection
 * @param mixed $post Whatever is passed in $post
 */
function initiate_service_request_handler($con, $post)
{

	// convert user info
	$user_info = json_decode($_POST['user_info'], true);

	// depending on type, pass relevant info to initiate_service_request
	if ($post['type'] == "des")
		$request_id = initiate_service_request($con, $post['type'], $post['quote'], $post['request_des_services'], $post['request_design_due_date'], $post['note'], $user_info, $post['request_estimation_due_date']);
	elseif ($post['type'] == "fse")
		$request_id = initiate_service_request($con, $post['type'], $post['quote'], $post['request_fse_services'], $post['request_fse_date_from'], $post['note'], $user_info, $post['request_fse_date_to']);
	elseif ($post['type'] == "ops")
		$request_id = initiate_service_request($con, $post['type'], $post['quote'], $post['request_ops_services'], $post['request_ops_due_date'], $post['note'], $user_info);
	elseif ($post['type'] == "cop")
		initiate_cop_service_request($con, $post, $user_info);
}

/**
 * Handles calling service request
 * @param mixed $con SQL connecton
 * @return mixed $request_id ID of service request
 */
function initiate_service_request(
	$con,
	$type,
	$quote,
	$service_request,
	$due_date,
	$user_note,
	$user_info,
	$secondary_due_date = null,
	$prelim_sow = null,
	$site_walk_date = null
) {

	// Load DB configurations & dependencies
	include('config.php');

	// initialize note to be added to fst_notes (will be modified throughout the function)
	$note = "";

	// Load in Grid for quote
	$grid = get_grid($con, $quote);

	// Check 'type' (will be related to specific department)
	if ($type == 'des') {

		// if no request, exit here
		if ($service_request == "No Services Requested")
			return;

		// If triggering for design & estimation from new project creation, trigger a second request so both are on dashboard
		$status = "";
		if ($service_request == "Initial Design & Estimation") {

			// Switch this request to estimation
			$service_request = "Initial Estimation";
			$status = "Waiting On Others";

			// Call service request for design
			$request_id = initiate_service_request($con, "des", $quote, "Initial Design", $due_date, $user_note, $user_info, $secondary_due_date, $prelim_sow, $site_walk_date);
		}

		// Get personnel for job
		$query = 'SELECT personnel FROM general_des_task WHERE task = "' . $service_request . '";';
		$result = mysqli_query($con, $query);
		$personnel = mysqli_fetch_array($result);

		// Log service request
		$notification = new Notifications($con, "des_est_request", "Design Request (" . $service_request . ")", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Insert new task to service request to fst_grid_service_request
		// Query built differently if design vs. estimation
		if ($personnel['personnel'] == "designer") {
			$query = "INSERT INTO `fst_grid_service_request`
					(`quoteNumber`, `group`, `task`, `status`, `due_date`, `timestamp_requested`) 
					VALUES ('" . $quote . "', 'Design', '" . mysql_escape_mimic($service_request) . "',
							'" . mysql_escape_mimic($status) . "', '" . mysql_escape_mimic($due_date) . "', 
								NOW());";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			$request_id = mysqli_insert_id($con);

			// Check personnel required for task
			if ($grid['designer'] != "") {
				$query = "UPDATE `fst_grid_service_request` SET `personnel` = '" . $grid['designer'] . "', status = 'Assigned', timestamp_assigned = NOW() WHERE `id` = '" . $request_id . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				notify_user_assignment($request_id, $user_info);
			}
		} elseif ($personnel['personnel'] == "estimator") {
			$query = "INSERT INTO `fst_grid_service_request`
					(`quoteNumber`, `group`, `task`, `status`, `due_date`, `timestamp_requested`) 
					VALUES ('" . $quote . "', 'Design', '" . mysql_escape_mimic($service_request) . "',
							'" . mysql_escape_mimic($status) . "', '" . mysql_escape_mimic($secondary_due_date) . "', 
								NOW());";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			$request_id = mysqli_insert_id($con);

			if ($grid['quoteCreator'] != "") {
				$query = "UPDATE `fst_grid_service_request` SET `personnel` = '" . $grid['quoteCreator'] . "', status = 'Assigned', timestamp_assigned = NOW() WHERE `id` = '" . $request_id . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				notify_user_assignment($request_id, $user_info);
			}
		}
	} elseif ($type == 'est') {

		// Log service request
		$notification = new Notifications($con, "des_est_request", "Estimation Request (" . $service_request . ")", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Insert new task to service request to fst_grid_service_request
		$query = "INSERT INTO `fst_grid_service_request`
					(`quoteNumber`, `group`, `task`, `due_date`, `timestamp_requested`) 
					VALUES ('" . $quote . "', 'Design', '" . mysql_escape_mimic($service_request) . "', 
							'" . mysql_escape_mimic($due_date) . "', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		$request_id = mysqli_insert_id($con);

		// Update task with personnel if there is one listed for this job already
		if ($grid['quoteCreator'] != "") {
			$query = "UPDATE `fst_grid_service_request` SET `personnel` = '" . $grid['quoteCreator'] . "', status = 'Assigned', timestamp_assigned = NOW() WHERE `id` = '" . $request_id . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			notify_user_assignment($request_id, $user_info);
		}
	} elseif ($type == 'fse') {

		// Log service request
		$notification = new Notifications($con, "fse_request", "FSE Request (" . $service_request . ")", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Insert new task to service request to fst_grid_service_request
		$query = "INSERT INTO `fst_grid_service_request`
					(`quoteNumber`, `group`, `task`, `date_from`, `date_to`, `timestamp_requested`) 
					VALUES ('" . $quote . "', 'FSE', '" . mysql_escape_mimic($service_request) . "', 
							'" . mysql_escape_mimic($due_date) . "', '" . mysql_escape_mimic($secondary_due_date) . "', 
							NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		$request_id = mysqli_insert_id($con);

		// Update task with personnel if there is one listed for this job already
		if ($grid['fs_engineer'] != "") {
			$query = "UPDATE `fst_grid_service_request` SET `personnel` = '" . $grid['fs_engineer'] . "', status = 'Assigned', timestamp_assigned = NOW() WHERE `id` = '" . $request_id . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			notify_user_assignment($request_id, $user_info);
		}
	} elseif ($type == 'ops') {

		// Log service request
		$notification = new Notifications($con, "ops_request", "Ops Request (" . $service_request . ")", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Insert new task to service request to fst_grid_service_request
		$query = "INSERT INTO `fst_grid_service_request`
					(`quoteNumber`, `group`, `task`, `timestamp_requested`) 
					VALUES ('" . $quote . "', 'Ops', '" . mysql_escape_mimic($service_request) . "', 
							NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		$request_id = mysqli_insert_id($con);
	}

	//update fst_notes based on the type of service requested
	if ($service_request == "Initial Design & Estimation")
		$note = "Design and Estimation request. Please route to estimator following completion of design.";
	elseif ($service_request == "No Services Requested")
		$note = "No Design or Estimation Services Requested.";
	else
		$note = $service_request . " Request.";

	// add user specified info to note
	if ($note != "" && $user_note != "")
		$note .= " | Note: " . $user_note;

	// check other optional critera
	if ($prelim_sow != null && $prelim_sow != "")
		$note .= " | SOW: " . $prelim_sow;
	if ($site_walk_date != null && $site_walk_date != "")
		$note .= " | Site Walk Date: " . date_format(date_create($site_walk_date), "m/d/y");
	if (($due_date != null && $due_date != "") && $type != 'fse')
		$note .= " | Requested " . get_description_code($type, True) . " Due Date: " . date_format(date_create($due_date), "m/d/y");
	if (($due_date != null && $due_date != "") && $type == 'fse')
		$note .= " | Requested " . get_description_code($type, True) . " Date From: " . date_format(date_create($due_date), "m/d/y");
	if (($secondary_due_date != null && $secondary_due_date != "") && $type != 'fse')
		$note .= " | Requested " . get_description_code($type, False) . " Due Date: " . date_format(date_create($secondary_due_date), "m/d/y");
	if (($secondary_due_date != null && $secondary_due_date != "") && $type == 'fse')
		$note .= " To: " . date_format(date_create($secondary_due_date), "m/d/y");

	// only write query if we have a note
	if ($note != "") {

		//create query to add note
		$query = "INSERT INTO fst_notes (quoteNumber, notes, user, date) 
								VALUES ('" . $quote . "', '" . mysql_escape_mimic($note) . "', '" . $user_info['id'] . "', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	return $request_id;
}

/**
 * Handles calling COP service request
 * @param mixed $con SQL connecton
 * @return void
 */
function initiate_cop_service_request($con, $post, $user_info)
{

	// Load DB configurations & dependencies
	include('config.php');

	// Get grid for job
	$grid = get_grid($con, $post['quote']);

	// If Design As-Built is required, issue service request to design team
	if ($post['request_cop_as_built'] == "Yes") {
		$request_id = initiate_service_request($con, "des", $post['quote'], "As Built Documentation", $post['request_cop_due_date'], "COP Requested - As Built Documentation Required.", $user_info);
	}

	// initialize note to be added to fst_notes (will be modified throughout the function)
	$note = "";

	// write query to update cop information
	$query = "INSERT INTO `fst_grid_service_request`
				(`quoteNumber`, `group`, `task`, `due_date`, `timestamp_requested`) 
				VALUES ('" . $post['quote'] . "', 'COP', '" . mysql_escape_mimic($post['request_cop_services']) . "', 
						'" . mysql_escape_mimic($post['request_cop_due_date']) . "', 
						NOW());";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// get request id
	$request_id = mysqli_insert_id($con);

	// If we have COP member already assigned to job, make them the responsible party
	if ($grid['cop_member'] != "") {
		$query = "UPDATE `fst_grid_service_request` SET `personnel` = '" . $grid['cop_member'] . "', status = 'Assigned', timestamp_assigned = NOW() WHERE `id` = '" . $request_id . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// update fst_notes based on the type of service requested
	$note = "COP Request.";

	// add user specified info to note
	if ($note != "" && $post['note'] != "")
		$note .= " | Note: " . $post['note'];

	// check other optional critera
	if ($post['request_cop_due_date'] != null && $post['request_cop_due_date'] != "")
		$note .= " | Requested COP Due Date: " . date_format(date_create($post['request_cop_due_date']), "m/d/y");

	// only write query if we have a note
	if ($note != "") {

		//create query to add note
		$query = "INSERT INTO fst_notes (quoteNumber, notes, user, date) 
								VALUES ('" . $post['quote'] . "', '" . mysql_escape_mimic($note) . "', '" . $user_info['id'] . "', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// Create specialized email for COP requests that go out
	$email_body = "<b>Summary of work performed:</b> " . $post['sow'] . "<br>";
	$email_body .= "<b>COP will be submitted to:</b> " . $post['request_cop_distribution'] . "<br>";
	$email_body .= "<b>Are there and updates required on the design to reflect the system as-built?</b> " . $post['request_cop_as_built'] . "<br>";
	$email_body .= "<b>Requested completion/submittal date:</b> " . format_date($post['request_cop_due_date']);

	// Add notes if applicable
	if ($post['note'] != "")
		$email_body .= "<br><b>Notes:</b> " . $post['note'];

	// Log service request
	$notification = new Notifications($con, "cop_request", "COP Request (" . $post['request_cop_services'] . ")", $post['quote'], $use, $post['request_cc']);
	$notification->log_notification($user_info['id'], $email_body, true);
}

function initiate_fse_service_request($con, $post)
{

	// Load DB configurations & dependencies
	include('config.php');

	// convert json objects sent over from js
	$user_info = json_decode($post['user_info'], true);
	$fse_keys = json_decode($post['fse_remote_keys'], true);

	// Insert new task to service request to fst_grid_service_request
	$query = "INSERT INTO `fst_grid_service_request`
				(`quoteNumber`, `group`, `task`, `timestamp_requested`) 
				VALUES ('" . $post['quoteNumber'] . "', 'FSE', '" . mysql_escape_mimic($post['fse_service_requested']) . "', 
						NOW());";

	// loop through keys to create entry into fst_cop_engineering_data_submission
	$query = "INSERT INTO fst_cop_engineering_data_submission (";

	foreach ($fse_keys as $key) {
		$query .= "`" . $key . "`, ";
	}

	// trim last 2 characters
	$query = substr($query, 0, strlen($query) - 2) . ") VALUES (";

	// loop through keys 1 more time, insert $post values into query
	foreach ($fse_keys as $key) {

		// treat certain keys differently
		if ($key == "id")
			$query .= "NULL, ";
		elseif ($key == "user")
			$query .= "'" . $user_info['id'] . "', ";
		elseif ($key == "time_created")
			$query .= "NOW(), ";
		else
			$query .= "'" . mysql_escape_mimic($post[$key]) . "', ";
	}

	// close query & execute
	$query = substr($query, 0, strlen($query) - 2) . ");";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// initialize note to be added to fst_notes
	$note = "FSE Service Request: " . $post['fse_service_requested'];

	// add note if applicable
	if ($post['fse_service_notes'] != "")
		$note .= " | Notes: " . $post['fse_service_notes'];

	//create query to add note
	$query = "INSERT INTO fst_notes (quoteNumber, notes, user, date) 
							VALUES ('" . $post['quoteNumber'] . "', '" . mysql_escape_mimic($note) . "', '" . $user_info['id'] . "', NOW());";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Log service request
	$notification = new Notifications($con, "fse_request", "FSE Remote Support Request (" . $post['fse_service_requested'] . ")", $post['quoteNumber'], $use);
	$notification->log_notification($user_info['id']);
}

/**
 * Handles getting project info
 * @param mixed $con 
 * @param mixed $quote 
 * @return array|false|null 
 */
function get_grid($con, $quote)
{
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	return mysqli_fetch_array($result);
}

/**
 * Handles translating $code (des, ops, fse, cop) to description
 * @param string $code 	(des, ops, fse, cop)
 * @param bool $primary (T/F) False assumes secondary
 * @return string 		The full description of the code
 */
function get_description_code($code, $primary)
{

	// check code & primary tells
	if ($primary) {
		if ($code == "des")
			return "Design";
		elseif ($code == "fse")
			return "FSE";
		elseif ($code == "ops")
			return "Operations";
		elseif ($code == "cop")
			return "COP";
	} else {
		if ($code == "des")
			return "Estimation";
	}

	// if we find no match, return code
	return $code;
}

//function used to log any changes in due dates
function due_date_change_log($id, $new_date, $user_id)
{

	// Load DB configurations & dependencies
	include('config.php');

	//init due date note
	$due_date_note = "";

	//check if there is a change in date
	$query = "SELECT * FROM fst_grid_service_request WHERE id = '" . $id . "';";
	$result = mysqli_query($con, $query);

	//if we returned a result, look for options and add to array
	if (mysqli_num_rows($result) > 0) {

		//push dates into object
		$request = mysqli_fetch_array($result);

		// Check if due date has changed
		if ($request['due_date'] != $new_date && $request['due_date'] != "") {
			$due_date_note = $request['group'] . " due date changed from " . date('m/d/Y', strtotime($request['due_date'])) . " to " . date('m/d/Y', strtotime($new_date));

			// Get notification type based on group
			$notification_type = "";
			if ($request['group'] == "Design" || $request['group'] == "Estimation")
				$notification_type = "des_due_date_change";
			elseif ($request['group'] == "Ops")
				$notification_type = "ops_due_date_change";
			elseif ($request['group'] == "FSE")
				$notification_type = "fse_due_date_change";
			elseif ($request['group'] == "COP")
				$notification_type = "cop_due_date_change";

			if ($notification_type != "") {
				$notification = new Notifications($con, $notification_type, $due_date_note, $request['quoteNumber'], $use);
				$notification->log_notification($user_id);
			}
		}
	}
}

/**
 * Summary of adjust_inventory
 * Handles adjusting inventory after a part has been processed by the warehouse
 * @param string $mo (mo_id) matches column in fst_allocations_mo db table
 * @param mixed $user_info (object) holds user info, matches entry in fst_users db table
 * @param string $status ('Pending' OR 'Requested') use the status that needs to be used for the search query
 * 
 * @return void
 */
function adjust_inventory($mo, $user_info, $status)
{

	//load DB configurations
	include('config.php');

	//loop through parts processed and adjust inventory
	$query = "select * from fst_pq_detail WHERE mo_id = '" . $mo . "' AND status = '" . $status . "';";
	$result = mysqli_query($con, $query);

	//if we returned a result, look for options and add to array
	if (mysqli_num_rows($result) > 0) {

		while ($rows = mysqli_fetch_assoc($result)) {
			//create query based on results returned
			$query = "update invreport SET `" . $rows['decision'] . "` = `" . $rows['decision'] . "` - " . $rows['q_allocated'] . " WHERE partNumber = '" . mysql_escape_mimic($rows['part_id']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//also update inv_locations db table
			$query = "update inv_locations SET stock = stock - " . $rows['q_allocated'] . " WHERE partNumber = '" . mysql_escape_mimic($rows['part_id']) . "' AND shop = '" . $rows['decision'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//create instance of part to log change
			//type "WP" = "Warehouse Processing"
			$part = new Part($rows['part_id'], $con);
			$part->log_part_update($user_info['id'], 'Part staged/shipped (MO: ' . $mo . ') (Shop: ' . $rows['decision'] . ' | QTY: ' . $rows['q_allocated'] . ')', 'WP');
		}
	}
}

/**
 * handles remove material order OR purchase order from queue if all parts have been removed
 * @param int $id (material order # to check)
 * @param string $type (determines what kind of request this is)
 * @return void
 */
function remove_from_queue_if_empty($id, $type)
{

	//load DB configurations
	include('config.php');

	//if this is from the warehouse, we need to check the id against the MO in pq_detail 
	if ($type == "warehouse") {

		//check for existance of parts under this MO in pq_detail
		$query = "select * from fst_pq_detail WHERE mo_id = '" . $id . "' AND status = 'Pending';";
		$result = mysqli_query($con, $query);

		//if we returned no results, remove MO from allocations queue
		if (mysqli_num_rows($result) == 0) {

			$query = "UPDATE fst_allocations_mo SET status = 'Closed' WHERE mo_id = '" . $id . "';";
			$result = mysqli_query($con, $query);
		}
	}
	//if this if from purchasing, we need to check ID against pq_id in pq_detail
	elseif ($type == "purchasing") {

		//check for existance of parts under this MO in pq_detail
		$query = "select * from fst_pq_detail WHERE project_id = '" . $id . "' AND status = 'Pending' AND decision = 'PO';";
		$result = mysqli_query($con, $query);

		//if we returned no results, remove MO from allocations queue
		if (mysqli_num_rows($result) == 0) {
			$query = "UPDATE fst_allocations_mo SET status = 'Submitted' WHERE pq_id = '" . $id . "' AND mo_id = 'PO';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
}

/**
 * Summary of update_shipped_status
 * handles updating fst_pq_detail status for part that has been assigned a shipment
 * @param mixed $con (sql connection)
 * @param string $po_ship_to (location shipment is going to)
 * @param string $id (fst_pq_orders_shipment ID, also assigned to parts in fst_pq_detail)
 * @return void
 */
function update_shipped_status($con, $po_ship_to, $id)
{

	//update fst_pq_detail based on where part is being shipped to
	//check if po_ship_to is from a pierson wireless location
	$query = "SELECT name FROM general_shippingadd WHERE name = '" . mysql_escape_mimic($po_ship_to) . "' AND customer = 'PW';";
	$result = mysqli_query($con, $query);

	//if we return results, set to In-Transit, otherwise, set to Shipped
	if (mysqli_num_rows($result) > 0)
		$query = "UPDATE fst_pq_detail SET status = 'In-Transit' WHERE shipment_id = '" . $id . "';";
	else
		$query = "UPDATE fst_pq_detail SET status = 'Shipped' WHERE shipment_id = '" . $id . "';";

	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
}

//handles copying all values from one quote to another (used in revision process and duplicating existing quote process)
//param 1 = old quote
//param 2 = new quote
//param 3 = type (revision or duplicate)
//param 4 = sql connection variable 1
function copy_all_quote_information($old_quote, $new_quote, $type, $con)
{

	//Need to go through the following tables and have insert statement coupled with select statement
	//1) fst_values, 2)fst_boms, 3)fst_travelcosts, 4)fst_laborrates, 5)fst_quote_clarifications

	//check to see if values exist
	$query = "SELECT quoteNumber FROM fst_values WHERE quoteNumber = '" . $old_quote . "';";
	$result = mysqli_query($con, $query);

	//fst_values
	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//insert statement
		$query = "INSERT INTO fst_values(quoteNumber, fst_values.key, mainCat, subCat, phaseCostType, role, people, days, laborHrs, laborRate, travelOpt, localPeople, grdUber, travelLabor, air_perc, lodge_perc, food_perc, grnd_perc, markupPerc, cost, margin, price, startDate, manualOpt, checkBox, indiTrav, list_seperate, lastUpdate) ";

		//coupled with select statement
		$query .= "SELECT '" . $new_quote . "', fst_values.key, mainCat, subCat, phaseCostType, role, people, days, laborHrs, laborRate, travelOpt, localPeople, grdUber, travelLabor, air_perc, lodge_perc, food_perc, grnd_perc, markupPerc, cost, margin, price, startDate, manualOpt, checkBox, indiTrav, list_seperate, lastUpdate FROM fst_values WHERE quoteNumber = '" . $old_quote . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//fst_boms
	//check to see if values exist
	$query = "SELECT quoteNumber FROM fst_boms WHERE quoteNumber = '" . $old_quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//insert statement
		$query = "INSERT INTO fst_boms(id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, matL, uom, phase, mmd, manual, subs, subs_list) ";

		//coupled with select statement
		$query .= "SELECT null, type, '" . $new_quote . "', description, partCategory, manufacturer, partNumber, quantity, cost, price, matL, uom, phase, mmd, manual, subs, subs_list from fst_boms WHERE quoteNumber = '" . $old_quote . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//fst_travelcosts
	//check to see if values exist
	$query = "SELECT quoteNumber FROM fst_travelcosts WHERE quoteNumber = '" . $old_quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//insert statement
		$query = "INSERT INTO fst_travelcosts(quoteNumber, airfare, lodging, food, grdRental) ";

		//coupled with select statement
		$query .= "SELECT '" . $new_quote . "', airfare, lodging, food, grdRental from fst_travelcosts WHERE quoteNumber = '" . $old_quote . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//fst_laborrates
	//check to see if values exist
	$query = "SELECT quoteNumber FROM fst_laborrates WHERE quoteNumber = '" . $old_quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//insert statement
		$query = "INSERT INTO fst_laborrates(quoteNumber, instRate, supRate, engRate, desRate, projCRate) ";

		//coupled with select statement
		$query .= "SELECT '" . $new_quote . "', instRate, supRate, engRate, desRate, projCRate from fst_laborrates WHERE quoteNumber = '" . $old_quote . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//fst_quote_clarifications
	//check to see if values exist
	$query = "SELECT quoteNumber FROM fst_quote_clarifications WHERE quoteNumber = '" . $old_quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//insert statement
		$query = "INSERT INTO fst_quote_clarifications(quoteNumber, type, clar_order, clar_id, clar_full) ";

		//coupled with select statement
		$query .= "SELECT '" . $new_quote . "', type, clar_order, clar_id, clar_full from fst_quote_clarifications WHERE quoteNumber = '" . $old_quote . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//Anything after this point is specified either for a revision or a duplicate
	//if this is a duplicate, we need to update BOM pricing.
	if ($type == "duplicate") {

		//include phpClasses
		include('PHPClasses/Part.php');

		//query what we have saved for fst_boms
		$query = "SELECT * FROM fst_boms WHERE quoteNumber = '" . $new_quote . "';";
		$result = mysqli_query($con, $query);

		//loop through all parts
		while ($rows = mysqli_fetch_assoc($result)) {

			//create new instance of part
			$part = new Part($rows['partNumber'], $con);

			//check phase (if active, save cost as 0)
			if ($part->get_phase_code() == "03000")
				$cost = 0;
			else
				$cost = $part->info['cost'];

			//write query to update cost and execute
			$query = "UPDATE fst_boms SET cost = '" . $cost . "', matL = '" . $part->get_material_logistics() . "' WHERE id = '" . $rows['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		//also, remove any additional parts from fst_boms
		$query = "DELETE FROM fst_boms WHERE quoteNumber = '" . $new_quote . "' AND type = 'A';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}
}

/**
 * Used to initialize mail settings in other php files
 * @param mixed $mail 		instance of PHPMailer object
 * @param mixed $user_info 	(optional) object containing user info 
 * @return mixed 
 */
function init_mail_settings($mail, $user_info = null)
{

	//include constants (get email & password)
	include('constants.php');

	//Server settings
	$mail->isSMTP();                                            //Send using SMTP
	$mail->Host = 'smtp.gmail.com';                     		//Set the SMTP server to send through
	$mail->SMTPAuth = true; 									//Enable SMTP authentication
	$mail->SMTPSecure = 'ssl';									//Enable TLS encryption
	$mail->Port = 465;			 								//TCP port to connect to, use 465 or 587
	$mail->Username = $FST_EMAIL;		    					//SMTP username
	$mail->Password = $FST_EMAIL_PASSWORD;                      //SMTP password

	//update ssl settings to allow self signed certificate
	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
	);

	// if $user_info not null, and email/first/last is set, set from, reply to, & cc
	if (!is_null($user_info) && isset($user_info['email']) && isset($user_info['firstName']) && isset($user_info['lastName'])) {
		$mail->setFrom($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);
		$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);
		$mail->addCustomHeader('Return-Path', $user_info['email']);
		$mail->Sender = $user_info['email'];
	}

	//(optional) bcc user for oversight
	//$mail->addBCC('alex.borchers@piersonwireless.com');

	//return configured $mail instance
	return $mail;
}

/**
 * Used to initialize mail settings in other php files
 * @param mixed $mail 		instance of PHPMailer object
 * @param mixed $user_info 	(optional) object containing user info 
 * @return mixed 
 */
function init_mail_settings_oauth($mail, $user_info)
{

	//include constants (get email & password)
	include('constants.php');

	// Configure the SMTP settings for Gmail
	$mail->isSMTP();
	$mail->Host = 'smtp.gmail.com';
	$mail->SMTPAuth = true;
	$mail->SMTPSecure = 'tls';
	$mail->Port = 587;
	//$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_LOWLEVEL;
	$mail->Username = $user_info['email'];
	$mail->Password = $user_info['oauth_password'];

	// Get refresh token
	/*$tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
    }
	$mail->setOAuth(
		new PHPMailer\PHPMailer\OAuth(
			array(
				'clientId' => '573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com',
				'clientSecret' => 'vPfsNDx0Syd_sRrDKy03nZHh',
				'refreshToken' => $accessToken['refresh_token'],
				'accessToken' => $access_token['access_token'],
				'expires' => $access_token['expires_in']
			)
		)
	);*/

	//update ssl settings to allow self signed certificate
	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
	);

	// Set From, reply to, etc. to handle bounce back emails
	$mail->setFrom($user_info['email'], $user_info['firstName'] . ' ' . $user_info['lastName'], FALSE);
	$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . ' ' . $user_info['lastName']);
	$mail->addCustomHeader('Return-Path', $user_info['email']);
	$mail->Sender = $user_info['email'];

	//return configured $mail instance
	return $mail;
}

//
//param 1 = user (first last)
/**
 * Used to create signature for emails sent out of FST system
 * @author Alex Borchers
 * @param mixed $mail PHPMailer object
 * @param mixed $user FST User object (matches row from fst_users db table)
 * @return mixed $signature (completed signature for user)
 */
function create_signature($mail, $user)
{

	//init signature to be returned
	$signature = "<span style = 'font-family: sans-serif; color: #666666;'>";

	//add name / team / phone / email / company URL
	$signature .= "<span style = 'font-weight: bold;'>" . $user['firstName'] . " " . $user['lastName'] . "</span><br>";

	// check if we have job title for user
	if ($user['job_title'] != "" && $user['job_title'] != null)
		$signature .= $user['job_title'] . "<br>";

	// check if we have phone for user
	if ($user['phone'] != "" && $user['phone'] != null)
		$signature .= $user['phone'] . " | " . $user['email'] . "<br>";
	else
		$signature .= $user['email'] . "<br>";

	$signature .= "www.piersonwireless.com <br><br>";
	$signature .= "</span>";

	//add PW logo
	$mail->AddEmbeddedImage('images/your_wireless_logo.jpg', 'pw_logo');
	$signature .= '<img alt="PW Logo" src="cid:pw_logo" style = "width: 400px; height: auto;">';

	return $signature;
}

// handles creating notification for work orders team on certain status changes
// param 1 = $mail is pre-initialzed PHP Mailer instance
// param 2 = $quote # referenced
// param 3 = $status (Complete, Dead, or Revision)
// param 4 = $use (global type variable stored in config.php)
// param 5 = $price (current price of the project)
function alert_work_orders($mail, $quote, $status, $use, $price)
{

	//Recipients
	$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
	$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test") {
		$mail->addAddress($_SESSION['email']);
		//$mail->addAddress('workorders@piersonwireless.com');
	} else {
		$mail->addAddress('workorders@piersonwireless.com');
	}

	//CC user
	$mail->addCC($_SESSION['email']);
	$mail->addBCC('alex.borchers@piersonwireless.com'); 	//bcc me for now

	//set temp sublink
	$sub_link = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/";

	//set subject & body of email
	$subject_line = "[Work Orders] Project #" . substr($quote, 0, 7) . " - Quote #" . $quote . " " . $status . " Notification";
	$body = "Hello Work Orders Team,<br><br>";

	//create difference line depending on status
	if ($status == "Revision")
		$body .= "A revision for WO #" . substr($quote, 0, 7) . " has been created and the final total to invoice is " . convert_money('%.2n', $price) . ". Please use the link below to update this work order in Viewpoint.<br><br>";
	elseif ($status == "SOW")
		$body .= "A SOW for WO #" . substr($quote, 0, 7) . " has been created. Please use the link below to update this work order in Viewpoint.<br><br>";
	elseif ($status == "Complete")
		$body .= "WO #" . substr($quote, 0, 7) . " has been moved to " . $status . " and the final total to invoice is " . convert_money('%.2n', $price) . ". Please use the link below to update this work order in Viewpoint.<br><br>";
	else
		$body .= "WO #" . substr($quote, 0, 7) . " has been moved to " . $status . ". Please use the link below to update this work order in Viewpoint.<br><br>";

	$body .= $sub_link . "application.php?quote=" . $quote . "<br><br>";
	$body .= "Thank you,";

	//Content
	$mail->isHTML(true);
	$mail->Subject =  $subject_line;
	$mail->Body = $body;
	$mail->send();

	//close smtp connection
	$mail->smtpClose();
}

//used to add to, cc, and bcc from semi-colon seperated strings
//param 1 = $mail class instance (must already by initialized)
//param 2 = semi-colon delimited string (ex. 'alex.borchers@piersonwireless.com; roderick@piersonwireless.com')
//param 3 = type (To, CC, BCC) determines what line the addresses get added to 
function add_custom_recipients($mail, $email_string, $type)
{

	//xplode string (turns string into an array. use semi-colon as delimeter)
	$emails = explode(";", $email_string);

	//loop through array of emails
	foreach ($emails as $email) {

		//if we reach weird string, ignore
		if (trim($email) == "<br>")
			continue;

		//depending on option, add to $mail object
		if ($type == "To")
			$mail->addAddress(trim($email));
		elseif ($type == "CC")
			$mail->addCC(trim($email));
		elseif ($type == "BCC")
			$mail->addBCC(trim($email));
	}

	return $mail;
}

/**
 * Loads an array from a CSV file.
 * @author Alex Borchers
 * @param string $filename The name of the CSV file to load.
 * @return array|false An array of associative arrays representing the data in the CSV file, or false if the file could not be opened.
 */
function load_csv_array($filename)
{
	if (($handle = fopen($filename, 'r')) !== false) {
		$headers = fgetcsv($handle);  // read the header row
		$rows = array();

		while (($data = fgetcsv($handle)) !== false) {
			$row = array();

			// map the values in the current row to their corresponding headers
			foreach ($headers as $i => $header) {
				$row[$header] = $data[$i];
			}

			$rows[] = $row;  // add the row to the result array
		}

		fclose($handle);

		return $rows;  // return the resulting array
	}

	return false;  // return false if the file could not be opened
}

/**
 * Generates next FST PO # in sequence
 * @param mixed $con SQL connecton
 * @return mixed New PO Number (FST-xxxxx)
 */
function get_new_fst_po_number($con)
{

	// Get last entered PO #, increment by 1, and return
	$query = "SELECT po_number FROM fst_pq_orders WHERE po_number LIKE 'FST-%' ORDER BY po_number DESC LIMIT 1;";
	$result = mysqli_query($con, $query);
	$previous = mysqli_fetch_array($result);
	$new = intval(substr($previous['po_number'], 4));
	$new++;
	return "FST-" . $new;
}



/** ANY FUNCTIONS AFTER THIS POINT WILL SEND EMAILS **/
//Initialize PHP Mailer (in case we need to send an email)
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//handles issuing material creation requests
function mat_creation_email($parts, $quote)
{

	//load DB configurations
	include('config.php');

	//include constants sheet
	include('constants.php');

	//used to grab actual link for the current address
	$link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	//$link = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/addBOM_new.php"
	//OR "https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php" (depending on where it comes from)

	//hard code sub_link for time being
	$sub_link = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/";

	//init string to carry message
	$new_parts = "";

	//opening of email body
	$new_parts .= "<br><br>"; // extra space
	$new_parts .= "Please created the following material(s) in Viewpoint:"; //header info

	//init cut_sheet array
	$cutsheet = [];

	//loop through array and grab info for 
	for ($i = 0; $i < sizeof($parts); $i++) {

		//push blank cutsheet path
		array_push($cutsheet, "");

		//add blank space between parts
		$new_parts .= "<br><br>";

		//query fst_newparts for most recent info
		$query = "select * from fst_newparts where partNumber = '" . $parts[$i] . "' order by date desc LIMIT 1;";
		$result = mysqli_query($con, $query);

		//check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {

			//grab array
			$part_info = mysqli_fetch_array($result);

			//add to material creation body
			$new_parts .= "<b>PW Part ID / OEM Part #: </b><span style = 'color:red'>" . $part_info['partNumber'] . "</span><br>";
			$new_parts .= "<b>Part Description: </b><span style = 'color:red'>" . $part_info['description'] . "</span><br>";
			$new_parts .= "<b>Manufacturer: </b><span style = 'color:red'>" . $part_info['manufacturer'] . "</span><br>";
			$new_parts .= "<b>Vendor (if known): </b><span style = 'color:red'>" . $part_info['vendor'] . "</span><br>";
			$new_parts .= "<b>Cost to PW: </b><span style = 'color:red'>" . $part_info['cost'] . "</span>";
			//$new_parts.= "<b>Proposed Labor Rate: </b><span style = 'color:red'></span>";

			//check for cutsheet link
			//check for attachment
			if ($part_info['cutsheet_link'] != null && $part_info['cutsheet_link'] != "")
				$new_parts .= "<br><b>Cut Sheet Link: </b><span style = 'color:red'>" . $part_info['cutsheet_link'] . "</span>";

			$new_parts .= "<br><br>Material Creation Link: " . $sub_link . "terminal_hub.php?newPart=" . $part_info['id'];

			//check for attachment
			if ($part_info['cutsheet'] != null && $part_info['cutsheet'] != "")
				$cutsheet[$i] = $part_info['cutsheet'];

			//update status of part to requested
			$query = "UPDATE fst_newparts SET requested = 'yes' WHERE id = '" . $part_info['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		} else {
			$new_parts .= "Sorry there is no information available for this part. Please reach out to the designer on the referenced FST.";
		}
	}

	//add referenced FST at the bottom
	$new_parts .= "<br><br>Referenced FST: https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $quote;

	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom($_SESSION['email'], "Web FST Automated Email System"); //set from (name is optional)
	//$mail->addAddress("amb035@morningside.edu"); //send to material creation group

	//if testing, send to yourself
	if ($use == "test") {
		$mail->addAddress("alex.borchers@piersonwireless.com"); //send to material creation group
	} else {
		$mail->addAddress("MaterialsCreation@piersonwireless.com"); //send to material creation group
	}

	$mail->addCC($_SESSION['email']); //cc yourself

	//get target directory for any cutsheets
	$target_dir = getcwd();

	//link any cutsheets if applicable
	for ($i = 0; $i < sizeof($cutsheet); $i++) {

		//file attachment logic
		if ($cutsheet[$i] != "") {
			//grab target file, add to email
			$target_file = $target_dir . "\\cutsheets\\" . $cutsheet[$i];
			$mail->addAttachment($target_file, $cutsheet[$i]);
		}
	}

	//Content
	$mail->isHTML(true);
	$mail->Subject =  "Material Creation Request";
	$mail->Body = "Material Creation Team," . $new_parts . "<br><br>Thank you,";
	$mail->send();

	//close smtp connection
	$mail->smtpClose();

	return;
}

/**
 * Handles saving JHA document
 * @param mixed $con 
 * @param mixed $post 
 * @return void 
 */
function save_jha($con, $post)
{

	// decode user_info & jha_keys
	$jha_key = json_decode($post['jha_key'], true);

	// check if this JHA has been created already
	$query = "SELECT * FROM jha_form WHERE quote_number = '" . $post['quote_number'] . "' AND revision = '" . $post['revision'] . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) == 0) {
		create_jha($con, $post);
		return;
	}

	// otherwise update
	// begin query
	$query = "UPDATE jha_form SET ";

	// loop through keys to create query
	foreach ($jha_key as $key) {

		// ignore certain columns
		if (in_array($key, ["created_by", "created", "quote_number", "revision"]))
			continue;

		// for datetime, use NOW()
		if ($key == "last_update")
			$query .= "`" . $key . "` = NOW(),";
		else
			$query .= "`" . $key . "` = '" . mysql_escape_mimic($post[$key]) . "',";
	}

	// remove last character
	$query = substr($query, 0, strlen($query) - 1);

	// add where clause
	$query .= " WHERE quote_number = '" . $post['quote_number'] . "' AND revision = '" . $post['revision'] . "';";

	// run update w/error handler
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

/**
 * Handles creating JHA entry
 * @param mixed $con 
 * @param mixed $post 
 * @return void 
 */
function create_jha($con, $post)
{

	// Load db config
	include('config.php');

	// decode user_info & jha_keys
	$jha_key = json_decode($post['jha_key'], true);
	$user_info = json_decode($post['user_info'], true);

	// set submitted to false
	$post['submitted'] = 0;

	// loop through keys & create query to insert values into db table
	// begin query
	$query = "INSERT INTO jha_form (";

	// loop through keys to create initial VALUES portion of $query
	foreach ($jha_key as $key) {
		$query .= "`" . $key . "`,";
	}

	// remove last character, add closing ) and beginning of next statement
	$query = substr($query, 0, strlen($query) - 1);
	$query .= ") VALUES (";

	// loop through keys to create query
	foreach ($jha_key as $key) {

		// for datetime columns, use NOW()
		if (in_array($key, ["created", "last_update"]))
			$query .= "NOW(),";
		// use users info for created_by
		elseif ($key == "created_by")
			$query .= "'" . mysql_escape_mimic($user_info['firstName'] . " " . $user_info['lastName']) . "',";
		else
			$query .= "'" . mysql_escape_mimic($post[$key]) . "',";
	}

	// remove last character & close query
	$query = substr($query, 0, strlen($query) - 1);
	$query .= ");";

	// run update w/error handler
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Log new JHA creation
	$notification = new Notifications($con, "jha_creation", "", $post['quote_number'], $use);
	$notification->log_notification($user_info['id']);

	return;
}

/**
 * Handles submitting JHA document (lock & email techs/ops lead)
 * @param mixed $con 
 * @param mixed $post 
 * @return void 
 */
function submit_jha($con, $post)
{

	// check if this JHA has been created already
	$query = "SELECT * FROM jha_form WHERE quote_number = '" . $post['quote_number'] . "' AND revision = '" . $post['revision'] . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) == 0)
		create_jha($con, $post);

	// update form to submitted & send email to users that need to approve it
	$query = "UPDATE jha_form SET submitted = 1 WHERE quote_number = '" . $post['quote_number'] . "' AND revision = '" . $post['revision'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// decode json objects
	$user_info = json_decode($post['user_info'], true);

	// initialize mailer object & settings
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail, $user_info);

	// look at tech's assigned to quote, send out email asking them to review the contents of the JHA w/link
	$query = "SELECT b.email FROM fst_grid_tech_assignments a
				LEFT JOIN fst_users b
					ON a.tech = CONCAT(b.firstName, ' ', b.lastName)
				WHERE a.quoteNumber = '" . $post['quote_number'] . "';";
	$result = mysqli_query($con, $query);

	// loop through results
	while ($rows = mysqli_fetch_assoc($result)) {

		// add tech email to $mail object
		$mail->addAddress($rows['email']);
	}

	// create body
	$body  = "Hello,<br><br>";
	$body .= "You have a JHA to review. Please review & acknowledge using the link below:<br>";
	$body .= "https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $post['quote_number'] . "&jha=1<br><br>";
	$body .= "Thank you";

	// set subject & body & send to users
	$mail->isHTML(true);
	$mail->Subject = "Please Review JHA Agreement for " . $post['project_name'];
	$mail->Body = $body;
	$mail->send();

	// close smtp connection
	$mail->smtpClose();

	return;
}

/**
 * Handles revising JHA document (create copy & unlock)
 * @param mixed $con 
 * @param mixed $post 
 * @return void 
 */
function revise_jha($con, $post)
{

	// increment revision by 1, create new jha
	$post['revision'] = intval($post['revision']) + 1;
	create_jha($con, $post);
}

/**
 * Handles acknowledging JHA document (save's acknowledgement & logs it)
 * @param mixed $con 
 * @param mixed $post 
 * @return void 
 */
function acknowledge_jha($con, $post)
{

	// decode user info from json object
	$user_info = json_decode($post['user_info'], true);

	// create query to update technician table
	$query = "UPDATE fst_grid_tech_assignments 
				SET jha_acknowledged = '" . $post['revision'] . "' 
				WHERE tech = '" . mysql_escape_mimic($user_info['firstName'] . " " . $user_info['lastName']) . "' 
					AND quoteNumber = '" . $post['quote_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Log acknowledgement
	$notification = new Notifications($con, "jha_acknowledgement", 'Acknowledgement (revision ' . $post['revision'] . ')', $post['quote_number'], $use);
	$notification->log_notification($user_info['id']);
}

/**
 * Creates a custom query from custom syntax typically used in _helper.php files
 * @param mixed $row 		object related to an SQL table row
 * @param mixed $columns 	array that matches the columns that we want to update in the table
 * @param mixed $table 		table name
 * @param mixed $keys 		ID (or what needs to be in the WHERE clause, passed as an array)
 * @return string 			complete SQL query
 * example input = create_custom_update_sql_query ({quoteNumber: '12345', value1: 'test', value2: 'example'}, ['value1', 'value2'], 'fst_grid', ['quoteNumber'])
 * expected output = UPDATE `fst_grid` SET `value1` = 'test', `value1` = 'example' WHERE quoteNumber = '12345';
 */
function create_custom_update_sql_query($row, $columns, $table, $keys)
{

	//initialize query
	$query = "UPDATE `" . $table . "` SET ";

	//loop through $columns that need updated. apply new $row fields
	for ($i = 0; $i < sizeof($columns); $i++) {

		//treat last column differnetly (don't include comma)
		if ($columns[$i] == "")
			continue;
		elseif ($i == sizeof($columns) - 1)
			$query .= "`" . $columns[$i] . "` = '" . mysql_escape_mimic($row[$columns[$i]]) . "' ";
		else
			$query .= "`" . $columns[$i] . "` = '" . mysql_escape_mimic($row[$columns[$i]]) . "', ";
	}

	//init WHERE clause
	$query .= "WHERE ";

	//loop through keys and add to where clause
	for ($i = 0; $i < sizeof($keys); $i++) {

		//treat last column differnetly (don't include comma)
		if ($i == sizeof($keys) - 1)
			$query .= "`" . $keys[$i] . "` = '" . mysql_escape_mimic($row[$keys[$i]]) . "';";
		else
			$query .= "`" . $keys[$i] . "` = '" . mysql_escape_mimic($row[$keys[$i]]) . "' AND ";
	}

	//return completed query
	return $query;
}

/**
 * Creates a custom query from custom syntax typically used in _helper.php files
 * @param mixed $row 		object related to an SQL table row
 * @param mixed $columns 	array that matches the columns that we want to update in the table
 * @param mixed $table 		table name
 * @param boolean $replace 	is this REPLACE INTO or INSERT
 * @return string 			complete SQL query
 * example input = create_custom_insert_sql_query ({quoteNumber: '12345', value1: 'test', value2: 'example'}, ['value1', 'value2'], 'fst_grid', false)
 * expected output = INSERT INTO `fst_grid` (`value1`, `value2`) VALUES ('test', 'example');
 */
function create_custom_insert_sql_query($row, $columns, $table, $replace)
{

	//initialize query
	if ($replace)
		$query = "REPLACE INTO `" . $table . "` (";
	else
		$query = "INSERT INTO `" . $table . "` (";

	//loop through $columns you're adding, only add column name first time around
	for ($i = 0; $i < sizeof($columns); $i++) {

		//treat last column differnetly (don't include comma)
		if ($i == sizeof($columns) - 1)
			$query .= "`" . $columns[$i] . "`";
		else
			$query .= "`" . $columns[$i] . "`, ";
	}

	//add VALUES and open for actual values to insert
	$query .= ") VALUES (";

	//loop through $columns again, this time use $row to reference actual values passed
	for ($i = 0; $i < sizeof($columns); $i++) {

		//treat last column differnetly (don't include comma)
		if ($i == sizeof($columns) - 1)
			$query .= "'" . mysql_escape_mimic($row[$columns[$i]]) . "'";
		else
			$query .= "'" . mysql_escape_mimic($row[$columns[$i]]) . "', ";
	}

	// Close query & return
	$query .= ");";
	return $query;
}

/** 
 * Custom query functions (should only be used for inserting/updating records, not reading records from DB)
 * takes as parameters an sql connection, query, file name, and line number. If we hit an error, record it and return error message
 * SHOULD ALWAYS BE IN THE FORM custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__) (update $query with your desired query)
 * @param mixed $con SQL Connection
 * @param mixed $query Query to be executed
 * @param mixed $file_name Use "$_SERVER['REQUEST_URI']"
 * @param mixed $line_number Use "[underscore][underscore]LINE[underscore][underscore]"
 * @return boolean True = Error, False = Success
 */
function custom_query($con, $query, $file_name, $line_number)
{

	// execute query (call error_handler if we encouter a problem)
	if (!mysqli_query($con, $query)) {
		error_handler_new(mysqli_error($con), $query, $file_name, $line_number, $con);
		return true;
	}

	return false;
}

//improved error handler (accounts for line #, file name, and alerts user from function (not from script it was called))
function error_handler_new($error, $problem_query, $file_name, $line_number, $con)
{

	//include constants sheet
	include('constants.php');

	//step1: create error log and save to database
	$error_log = "INSERT INTO fst_errorlog (id, user_id, message, query, file, line_number, time_stamp) 
									VALUES (null, '" . $_SESSION['employeeID'] . "', '" . mysql_escape_mimic($error) . "', 
											'" . mysql_escape_mimic($problem_query) . "', '" . mysql_escape_mimic($file_name) . "', 
											'" . mysql_escape_mimic($line_number) . "', NOW());";
	mysqli_query($con, $error_log);

	//step2: send email to admin to fix bug
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom($_SESSION['email'], "Web FST Automated Email System"); //set from (name is optional)

	//for testing env just send to user and bcc alex
	if ($use == "test") {
		$mail->addAddress($_SESSION['email']); 				//user
		$mail->addBCC("alex.borchers@piersonwireless.com"); //myself for now
	} else {
		$mail->addAddress("fst@piersonwireless.com"); //send to FST admin group
	}

	//Content
	$mail->isHTML(true);
	$mail->Subject =  "SQL Error Report";
	$mail->Body = "<b>Error Message:</b> " . $error . "<br><br><b>Query:</b> " . $problem_query . "<br><br><b>File Name:</b> " . $file_name . "<br><br><b>Line Number:</b> " . $line_number;
	$mail->send();

	//close smtp connection
	$mail->smtpClose();

	//finally, return error message to user
	echo "Error description: " . $error;
}

/**
 * Handles sending onboarding package to subcontractor
 * @param mixed $use 
 * @param mixed $con 
 * @param mixed $id 
 * @param mixed $user_info 
 * @param mixed $quote 
 * @return void 
 */
function send_subcontractor_onboarding($use, $con, $id, $user_info, $quote)
{
	// Get subcontractor info
	$query = "SELECT * FROM fst_vendor_list WHERE id = '" . $id . "';";
	$result = mysqli_query($con, $query);
	$sub = mysqli_fetch_array($result);

	// If already onboarded, quit now
	if ($sub['onboarded'] == 1) {
		echo "[Error] Subcontractor already onboarded. Please try again or reach out to fst@piersonwireless.com for assistance.";
		return;
	}

	// Set up email package to send out
	//send out email based on information provided
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom("projects@piersonwireless.com", "Pierson Wireless Automated Email System");
	$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test")
		$mail->addAddress($user_info['email']);
	else {
		$mail->addAddress($sub['email'], $sub['poc']);
		$mail->addCC('sub-maint@piersonwireless.com');

		// get pw sub point of contact & add to CC
		$query = "SELECT email, fullName FROM fst_users 
					WHERE CONCAT(firstName, ' ', lastName) = '" . mysql_escape_mimic($sub['pw_contact']) . "' LIMIT 1;";
		$result = mysqli_query($con, $query);

		//if we returned a result, add to email
		if (mysqli_num_rows($result) > 0) {
			$poc = mysqli_fetch_array($result);
			$mail->addCC($poc['email'], $poc['fullName']);
		}
	}

	//CC user
	$mail->addCC($user_info['email']);
	$mail->addBCC('alex.borchers@piersonwireless.com'); //bcc me for now

	// call function in phpFunctions_drive.php to get onboarding documents
	$search_files = ["Subcontractor Package.pdf"];	//init name of files we are looking to grab
	$mail = get_subcontractor_onboarding_package($mail, $search_files);

	// create email body 
	$body  = "Hello,<br><br>";
	$body .= "We are reaching out to set up your company as a subcontractor in preparation for upcoming work.  We have attached a packet of information that requires your input, and also request a signed W-9 and current COI.<br><br>";

	// subcontractor package & bullets
	$body .= "<b>Subcontractor Package</b>";
	$body .= "<ul>";
	$body .= "<li>Vendor Information Form</li>";
	$body .= "<li>ACH Information Form</li>";
	$body .= "<li>Subcontractor Qualification Form</li>";
	$body .= "<li>COI Requirements </li>";

	// open new sub list
	$body .= "<ul>";
	$body .= "<li>Certificate Holder: Pierson Wireless Corp., 11414 S 145th Street, Omaha, NE 68138</li>";
	$body .= "</ul>";	//close sub list
	$body .= "</ul>";	//close full list

	// W-9 section
	$body .= "<b>W-9</b><br>";
	$body .= "Please provide a signed W-9, or W-9 form available here: https://www.irs.gov/pub/irs-pdf/fw9.pdf<br><br>";
	$body .= "Please review these needed documents and email them completed to sub-maint@piersonwireless.com so that we may complete your Subcontractor enrollment before the work is scheduled to begin.  We ask that you only email them to the email provided so that we can ensure your information is kept as confidential as possible.<br><br>";
	$body .= "If you have any questions please reach out to this group and we will assist however is needed.<br><br>";
	$body .= "We want to thank you for completing this work promptly and hope that you enjoy your day.";

	// Content
	$mail->isHTML(true);
	$mail->Subject =  "Subcontractor Information Request: " . $_POST['subcontractor_name'];
	$mail->Body = $body;

	// On success, remove files we attached from our server
	if ($mail->send()) {
		foreach ($search_files as $file) {
			// set up target path (reference download_file in phpFunctions_drive.php)
			$file_path = getcwd() . "//uploads//" . $file;
			unlink($file_path);
		}
	}

	// close smtp connection
	$mail->smtpClose();

	// Update onboarding flag for subcontractor
	$query = "UPDATE fst_vendor_list SET onboarding = 1 WHERE id = '" . $id . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
}

function send_subcontractor_package($use, $con, $subcontractor, $poc, $user_info, $quote, $type)
{

	// check when the last time we initiated this request was (set threshold at 5 days)
	$query = "SELECT vendor FROM fst_vendor_list WHERE vendor = '" . mysql_escape_mimic($subcontractor) . "' AND last_compliance_request > now() - INTERVAL 5 day;";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) > 0) {
		send_subcontractor_reminder($use, $con, $subcontractor, $user_info, $quote, $type);
		return;
	}

	// send out email based on information provided
	// Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom("projects@piersonwireless.com", "Pierson Wireless Automated Email System");
	$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);

	// if this is in a testing environment, just send to user making request
	if ($use == "test")
		$mail->addAddress($user_info['email']);
	// otherwise send to required groups or individuals
	else {
		$mail->addAddress($poc);
		$mail->addCC('sub-maint@piersonwireless.com');

		// get Project Owner & add to CC
		$query = "SELECT email FROM fst_users WHERE CONCAT(firstName, ' ', lastName) = (SELECT projectLead FROM fst_grid WHERE quoteNumber = '" . $quote . "');";
		$result = mysqli_query($con, $query);

		//if we returned a result, add to email
		if (mysqli_num_rows($result) > 0) {
			$pl = mysqli_fetch_array($result);
			$mail->addCC($pl['email']);
		}

		// get pw sub point of contact & add to CC
		$query = "SELECT email FROM fst_users 
					WHERE CONCAT(firstName, ' ', lastName) = (SELECT pw_contact FROM fst_vendor_list WHERE vendor = '" . mysql_escape_mimic($subcontractor) . "') LIMIT 1;";
		$result = mysqli_query($con, $query);

		//if we returned a result, add to email
		if (mysqli_num_rows($result) > 0) {
			$poc = mysqli_fetch_array($result);
			$mail->addCC($poc['email']);
		}
	}

	//bcc me for now
	$mail->addBCC('alex.borchers@piersonwireless.com');

	// call function in phpFunctions_drive.php to get onboarding documents
	$search_files = ["Subcontractor Package.pdf"];	//init name of files we are looking to grab
	$mail = get_subcontractor_onboarding_package($mail, $search_files);

	// create email body 
	$body  = "Hello,<br><br>";
	$body .= "We are reaching out to update our records for your company in preparation for upcoming work.  We have attached a packet of information, and also request a signed W-9 and current COI.<br><br>";

	// subcontractor package & bullets
	$body .= "<b>Subcontractor Package</b>";
	$body .= "<ul>";
	$body .= "<li>Vendor Information Form</li>";
	$body .= "<li>ACH Information Form</li>";
	$body .= "<li>Subcontractor Qualification Form</li>";
	$body .= "<li>COI Requirements </li>";

	// open new sub list
	$body .= "<ul>";
	$body .= "<li>Certificate Holder: Pierson Wireless Corp., 11414 S 145th Street, Omaha, NE 68138</li>";
	$body .= "</ul>";	//close sub list
	$body .= "</ul>";	//close full list

	// W-9 section
	$body .= "<b>W-9</b><br>";
	$body .= "Please provide a signed W-9, or W-9 form available here: https://www.irs.gov/pub/irs-pdf/fw9.pdf<br><br>";
	$body .= "Please review these needed documents and email them completed to sub-maint@piersonwireless.com so that we may complete your Subcontractor enrollment before the work is scheduled to begin.  We ask that you only email them to the email provided so that we can ensure your information is kept as confidential as possible.<br><br>";
	$body .= "If you have any questions please reach out to this group and we will assist however is needed.<br><br>";
	$body .= "We want to thank you for completing this work promptly and hope that you enjoy your day.";

	// Content
	$mail->isHTML(true);
	$mail->Subject =  "Subcontractor Information Request: " . $subcontractor;
	$mail->Body = $body;

	// On success, remove files we attached from our server
	if ($mail->send()) {
		foreach ($search_files as $file) {
			// set up target path (reference download_file in phpFunctions_drive.php)
			$file_path = getcwd() . "//uploads//" . $file;
			unlink($file_path);
		}
	}

	// close smtp connection
	$mail->smtpClose();

	// save last time compliance was requested
	$query = "UPDATE fst_vendor_list 
				SET last_compliance_request = NOW() 
				WHERE vendor = '" . $subcontractor . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
}

function send_subcontractor_reminder($use, $con, $subcontractor, $user_info, $quote, $type)
{

	// send out email based on information provided
	// Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom("projects@piersonwireless.com", "Pierson Wireless Automated Email System");
	$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);

	// if this is in a testing environment, just send to user making request
	if ($use == "test")
		$mail->addAddress($user_info['email']);
	// otherwise send to required groups or individuals
	else {
		$mail->addAddress('sub-maint@piersonwireless.com', "Subcontractor Maintenance");

		// get Project Owner & add to CC
		$query = "SELECT email FROM fst_users WHERE CONCAT(firstName, ' ', lastName) = (SELECT projectLead FROM fst_grid WHERE quoteNumber = '" . $quote . "');";
		$result = mysqli_query($con, $query);

		//if we returned a result, add to email
		if (mysqli_num_rows($result) > 0) {
			$pl = mysqli_fetch_array($result);
			$mail->addCC($pl['email']);
		}

		// pw sub point of contact (maybe?)
	}

	//bcc me for now
	$mail->addBCC('alex.borchers@piersonwireless.com');

	// create email body 
	$body  = "Hello,<br><br>";

	if ($type == "add_to_quote")
		$body .= "Subcontractor <b>" . $subcontractor . "</b> has been added to quote " . $quote . " but is non-compliant. Please assist the subcontractor in addressing any outstanding requirements.<br><br>";
	elseif ($type == "po_request")
		$body .= "A PO needs to be issued to subcontractor <b>" . $subcontractor . "</b> for quote " . $quote . " but is non-compliant. We have issued a reminder email within the last 5 days. Please assist the subcontractor in addressing any outstanding requirements if this persists.<br><br>";
	elseif ($type == "awarded")
		$body .= "Quote #" . $quote . " has been awarded. Subcontractor <b>" . $subcontractor . "</b> is listed on the quote but is non-compliant. Please assist the subcontractor in addressing any outstanding requirements if this persists.<br><br>";

	// Content
	$mail->isHTML(true);
	$mail->Subject =  "Subcontractor Non-Compliant Reminder: " . $subcontractor;
	$mail->Body = $body;

	// close smtp connection
	$mail->smtpClose();
	return;
}

/**
 * Handles notifying user when they are assigned to a job
 * @param mixed $con MySQL connection
 * @param mixed $request_id Request ID (from fst_grid_service_request)
 * @param mixed $user_info User row (from fst_users)
 * 
 * @return void 
 */
function notify_user_assignment($request_id, $user_info, $previous_task_completed = false)
{
	// Get db $con and $use
	include('config.php');

	// Get user & quote # from request
	$query = "SELECT a.*, CONCAT(b.location_name, ' ', b.phaseName) as 'project_name' 
				FROM fst_grid_service_request a
				LEFT JOIN fst_grid b
					ON a.quoteNumber = b.quoteNumber
				WHERE a.id = '" . $request_id . "';";
	$result = mysqli_query($con, $query);
	$request = mysqli_fetch_assoc($result);

	// Get user to notify
	$query = "SELECT email, fullName FROM fst_users WHERE fullName = '" . $request['personnel'] . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	// If no results returned, exit (may not be user assigned for job)
	if (mysqli_num_rows($result) == 0)
		return;
	$user = mysqli_fetch_assoc($result);

	// send out email based on information provided
	// Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom('projects@piersonwireless.com', 'Web FST Automated Email System');

	// if this is in a testing environment, just send to user making request
	if ($use == "test")
		$mail->addAddress($user_info['email']);
	// otherwise send to required groups or individuals
	else
		$mail->addAddress($user['email'], $user['fullName']);

	//bcc me for now
	$mail->addBCC('alex.borchers@piersonwireless.com');

	// create email body 
	if ($previous_task_completed) {
		$body = "Hello,<br><br>";
		$body .= "A team member has finised their task, and your task is ready to be started. See the details below:<br><br>";
		$body .= "<b>Quote #:</b> " . $request['quoteNumber'] . "</a><br>";
		$body .= "<b>Service:</b> " . $request['task'] . "<br>";
		$body .= "<b>Due Date:</b> " . format_date($request['due_date']) . "<br>";
		$body .= "<b>Link:</b> https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $request['quoteNumber'] . "<br><br>";
		$body .= "Thank you,";
	} else {
		$body = "Hello,<br><br>";
		$body .= "You have been assigned to a job in FST. See the details below:<br><br>";
		$body .= "<b>Quote #:</b> " . $request['quoteNumber'] . "</a><br>";
		$body .= "<b>Service:</b> " . $request['task'] . "<br>";
		$body .= "<b>Due Date:</b> " . format_date($request['due_date']) . "<br>";
		$body .= "<b>Link:</b> https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $request['quoteNumber'] . "<br><br>";
		$body .= "Thank you,";
	}

	// Content
	$mail->isHTML(true);
	$mail->Subject =  "[FST:Job Assignment]: " . $request['project_name'] . " (" . $request['quoteNumber'] . ")";
	$mail->Body = $body;
	$mail->send();

	// close smtp connection
	$mail->smtpClose();
}
