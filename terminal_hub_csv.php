<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//load in php functions
include('phpFunctions.php');

//load in constants
include('constants.php');

//save file
$target_dir = getcwd();
$target_file = $target_dir . "\\" . basename($_FILES["theFile"]["name"]);
$errorMessage = ""; //used to send message back to user if there is an error

$uploadOk = 1;
$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// grab file type to check if xlsx

// Check if file already exists
if (file_exists($target_file)) {
	unlink($_FILES["theFile"]["name"]);
}

// Check file size
if ($_FILES["theFile"]["size"] > 5000000) {
  $errorMessage = "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Allow certain file formats
if($fileType == "csv") {
  

}
else{
	$errorMessage = "Sorry, only csv files are allowed.";
	$uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  echo "Sorry, your file was not uploaded. ". $errorMessage;
	goto rejected;
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["theFile"]["tmp_name"], $target_file)) {

  } else {
	echo "Sorry, there was an error uploading your file.";
	  goto rejected;
  }
}

//$file = file("BOM - [TEST] Mission Health Hosp - VP206-005601 - Q55230 (8).csv");
// = file($_FILES["theFile"]["name"]);
$csv = [];

$file = fopen($_FILES["theFile"]["name"], 'r');
while (($line = fgetcsv($file)) !== FALSE) {
  //$line is an array of the csv elements
  array_push($csv, ($line));
}
//print_r($csv);

fclose($file);

//go through each line and turn into table
/*foreach($file as $k)
	$csv[] = explode(',', $k);

print_r($csv);
return;
*/

//init array to be used
$db_keys = [];			//used to grab column heads (row 1) assume column 1 (index 0) is id
$db_values = [];		//used to grab values to be updated
$check_arrays = [];		//used to hold check_arrays for db_keys
$error_rows = [];		//used to hold error rows
$error_messages = [];	//used to hold error messaages

//init query pre-sets
$query_preset = "UPDATE invreport SET ";
$inv_locations_query_preset = "UPDATE inv_locations SET ";

//loop through csv contents
for ($i = 0; $i < sizeof($csv); $i++){
	
	//reset error array
	$temp_errors = [];
	
	//first row, grab the keys
	if ($i == 0){
		
		//loop through size of $csv and grab keys (also grab check arrays if applicable)
		for ($j = 0; $j < sizeof($csv[$i]); $j++){
			array_push($db_keys, $csv[$i][$j]);
			$check_arrays = hub_add_check_arrays($check_arrays, $csv[$i][$j]);
		}		
	}
	//otherwise save to db based on keys
	else{
		
		//init query string
		$query = $query_preset;
		$inv_locations_query = $inv_locations_query_preset;
				
		//add to query on a loop based on keys
		for ($j = 1; $j < sizeof($csv[$i]); $j++){

			//validate that the data is correct
			//passes database header & value
			//returns array ([0]=>true/false, [1]=>value (if applicable)
			$check = hub_check_csv($db_keys[$j], $csv[$i][$j], $check_arrays[$j]);
									
			//if false, skip this one and do not execute
			if ($check[0] == 'true'){
			
				//if description, may need to remove "" from wrap of string
				if ($j == 1){
					$first = substr($check[1], 0, 1);
					$last = substr($check[1], strlen($check[1])-1);

					if ($first == '"' && $last == '"'){
					   $check[1] = substr($check[1], 1, strlen($check[1])-2);
					   $check[1] = str_replace('""', '"', $check[1]);
					}
				}
				
				//escape string
				$check[1] = mysqli_real_escape_string($con, $check[1]);
				
				//treat last entry differently
				if ($j == sizeof($csv[$i]) - 1)
					$query .= $db_keys[$j] . " = '" . $check[1] . "' ";
				else
					$query .= $db_keys[$j] . " = '" . $check[1] . "', ";
				
			}
			//if error, we want to return the error to the user
			else{
				//push the value to an error array
				array_push($temp_errors, "C" . ($j+1) . ": " . $check[1]);					
				
			}

			//if key is equal to a key attribute, add it to inv_locations query
			//check for key attributes
			if ($db_keys[$j] == "partDescription")
				$inv_locations_query .= "`description` = '" . $check[1] . "', "; 
			if ($db_keys[$j] == "manufacturer")
				$inv_locations_query .= "`manufacturer` = '" . $check[1] . "', ";
			if ($db_keys[$j] == "partCategory")
				$inv_locations_query .= "`category` = '" . $check[1] . "', ";
			
		}
		
		//only write to DB if we pass all checks
		if (sizeof($temp_errors) == 0){
			
			//finish off query using key
			$query .= "WHERE " . $db_keys[0] . " = '" . mysql_escape_mimic($csv[$i][0]) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			
			//log part change (in phpFunctions)
			part_log($csv[$i][0], $_SESSION['employeeID'], "Part Edit (CSV)");
			
		}
		else{
			array_push($error_rows, $i + 1);
			array_push($error_messages, $temp_errors);
		}

		//check if we need to write inv_location updates to db
		if ($inv_locations_query != $inv_locations_query_preset){
			//remove last 2 characters (will be a space and comma)
			$inv_locations_query = substr($inv_locations_query, 0, strlen($inv_locations_query) - 2);
			$inv_locations_query .= " WHERE `partNumber` = '" . mysql_escape_mimic($csv[$i][0]) . "';";
			custom_query($con, $inv_locations_query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
}
	
//remove from directory
unlink($_FILES["theFile"]["name"]);

//return error rows (if applicable)
$return_array = [];
array_push($return_array, $error_rows);
array_push($return_array, $error_messages);
echo json_encode($return_array);

return;

rejected: 

echo "ERROR|" . $errorMessage;

return;