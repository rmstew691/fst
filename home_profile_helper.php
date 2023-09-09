<?php

// Load in dependencies
session_start();
require_once 'config.php';
include('phpFunctions.php');

// Unserialize data sent from js
$user_info = json_decode($_POST['user_info'], true);
$notification_keys = json_decode($_POST['notification_keys'], true);

// Write & Execute query to update filters
$query = "UPDATE fst_users 
			SET street1 = '".mysql_escape_mimic($_POST['street1'])."', street2 = '".mysql_escape_mimic($_POST['street2'])."',
				city = '".mysql_escape_mimic($_POST['city'])."', state = '".mysql_escape_mimic($_POST['state'])."',
				zip = '".mysql_escape_mimic($_POST['zip'])."',
				modified = NOW(), profileMarket = '".$_POST['market']."', 
				profileDes = '".$_POST['designer']."', profileQC = '".$_POST['quoteCreator']."' 
			WHERE email = '".$user_info['email']."'";
custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

// Loop through notification_types and write query to update this info
$_POST['user_id'] = $user_info['id'];
$query = create_custom_update_sql_query($_POST, array_column($notification_keys, 'id'), "fst_users_notifications", ["user_id"]);
custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);