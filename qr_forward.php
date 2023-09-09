<?php
// Example URL: https://pw-fst.northcentralus.cloudapp.azure.com/FST/qr_forward.php?equipment_code=CE-AB-1106
// Load in dependencies
session_start();
require_once 'config.php';
include('phpFunctions.php');

// Check to make sure $_GET['equipment_code is set]
if (!isset($_GET['equipment_code'])){
	echo 'Error: equipment_code is required.';
	return;
}

try {
    // Write & Execute query to update filters
	$query = "SELECT qr_code FROM inv_assets WHERE equipment_code = '" . $_GET['equipment_code'] . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) > 0){
		$asset = mysqli_fetch_array($result);
		header( "Location: https://" . $asset['qr_code'] );
	}
	else{
		echo 'Error: No match in our database.';
	}

} catch (Exception $e) {
    echo 'Error: ',  $e->getMessage(), "\n";
}

