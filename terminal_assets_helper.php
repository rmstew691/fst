<?php

// Load in dependencies
session_start();
include('constants.php');
include('phpFunctions.php');
include('PHPClasses/Asset.php');

// Load the database configuration file
require_once 'config.php';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//handles reading and writing pq_detail/overview to and from server (updates queue)
if ($_POST['tell'] == "save_asset_changes") {

	//Converting JSON to php
	$update_assets = json_decode($_POST["update_assets"], true);
	$user_info = json_decode($_POST['user_info'], true);

	//Loop through update_assets
	foreach ($update_assets as $asset) {

		$query = "UPDATE `assets` 
			SET 
				`description` = '" . mysql_escape_mimic($asset['description']) . "',
				`vin_serial` = '" . mysql_escape_mimic($asset['vin_serial']) . "',
				`make` = '" . mysql_escape_mimic($asset['make']) . "',
				`model` = '" . mysql_escape_mimic($asset['model']) . "',
				`year` = '" . mysql_escape_mimic($asset['year']) . "',
				`type` = '" . mysql_escape_mimic($asset['type']) . "',
				`status` = '" . mysql_escape_mimic($asset['status']) . "',
				`category` = '" . mysql_escape_mimic($asset['category']) . "',
				`category_description` = '" . mysql_escape_mimic($asset['category_description']) . "',
				`department` = '" . mysql_escape_mimic($asset['department']) . "',
				`dept_description` = '" . mysql_escape_mimic($asset['dept_description']) . "',
				`location` = '" . mysql_escape_mimic($asset['location']) . "',
				`location_description` = '" . mysql_escape_mimic($asset['location_description']) . "',
				`rev_code_description` = '" . mysql_escape_mimic($asset['rev_code_description']) . "',
				`pr_co` = '" . mysql_escape_mimic($asset['pr_co']) . "',
				`pr_co_description` = '" . mysql_escape_mimic($asset['pr_co_description']) . "',
				`operator` = '" . mysql_escape_mimic($asset['operator']) . "',
				`operator_name` = '" . mysql_escape_mimic($asset['operator_name']) . "',
				`jc_co_description` = '" . mysql_escape_mimic($asset['jc_co_description']) . "',
				`jc_job_#` = '" . mysql_escape_mimic($asset['jc_job_#']) . "',
				`jb_description` = '" . mysql_escape_mimic($asset['jb_description']) . "',
				`usage_ct` = '" . mysql_escape_mimic($asset['usage_ct']) . "',
				`usage_ct_description` = '" . mysql_escape_mimic($asset['usage_ct_description']) . "',
				`last_used_date` = '" . mysql_escape_mimic($asset['last_used_date']) . "',
				`license_#` = '" . mysql_escape_mimic($asset['license_#']) . "',
				`state` = '" . mysql_escape_mimic($asset['state']) . "',
				`expiration_date` = '" . mysql_escape_mimic($asset['expiration_date']) . "',
				`last_location` = '" . mysql_escape_mimic($asset['last_location']) . "',
				`last_location_description` = '" . mysql_escape_mimic($asset['last_location_description']) . "',
				`last_jcco` = '" . mysql_escape_mimic($asset['last_jcco']) . "',
				`last_job_#` = '" . mysql_escape_mimic($asset['last_job_#']) . "',
				`last_job_description` = '" . mysql_escape_mimic($asset['last_job_description']) . "',
				`job_date` = '" . mysql_escape_mimic($asset['job_date']) . "',
				`fuel_type` = '" . mysql_escape_mimic($asset['fuel_type']) . "',
				`fuel_capacity` = '" . mysql_escape_mimic($asset['fuel_capacity']) . "',
				`capacity_uom` = '" . mysql_escape_mimic($asset['capacity_uom']) . "',
				`asset_weight` = '" . mysql_escape_mimic($asset['asset_weight']) . "',
				`height` = '" . mysql_escape_mimic($asset['height']) . "',
				`#_of_axles` = '" . mysql_escape_mimic($asset['#_of_axles']) . "',
				`overall_width` = '" . mysql_escape_mimic($asset['overall_width']) . "',
				`overall_length` = '" . mysql_escape_mimic($asset['overall_length']) . "',
				`tire_type` = '" . mysql_escape_mimic($asset['tire_type']) . "',
				`ownership_status` = '" . mysql_escape_mimic($asset['ownership_status']) . "',
				`dealer` = '" . mysql_escape_mimic($asset['dealer']) . "',
				`purchase_date` = '" . mysql_escape_mimic($asset['purchase_date']) . "',
				`purchase_cost` = '" . mysql_escape_mimic($asset['purchase_cost']) . "',
				`lease_start` = '" . mysql_escape_mimic($asset['lease_start']) . "',
				`lease_end` = '" . mysql_escape_mimic($asset['lease_end']) . "',
				`lease_payment` = '" . mysql_escape_mimic($asset['lease_payment']) . "',
				`in_service_date` = '" . mysql_escape_mimic($asset['in_service_date']) . "',
				`expected_lifespan` = '" . mysql_escape_mimic($asset['expected_lifespan']) . "',
				`expected_life_tf` = '" . mysql_escape_mimic($asset['expected_life_tf']) . "',
				`sold_date` = '" . mysql_escape_mimic($asset['sold_date']) . "',
				`sale_price` = '" . mysql_escape_mimic($asset['sale_price']) . "',
				`capitalized` = '" . mysql_escape_mimic($asset['capitalized']) . "',
				`attach_to_equipment` = '" . mysql_escape_mimic($asset['attach_to_equipment']) . "',
				`attachment_description` = '" . mysql_escape_mimic($asset['attachment_description']) . "',
				`component_of_equipment` = '" . mysql_escape_mimic($asset['component_of_equipment']) . "',
				`component_description` = '" . mysql_escape_mimic($asset['component_description']) . "',
				`hours_reading` = '" . mysql_escape_mimic($asset['hours_reading']) . "',
				`hour_reading_date` = '" . mysql_escape_mimic($asset['hour_reading_date']) . "',
				`odometer_reading` = '" . mysql_escape_mimic($asset['odometer_reading']) . "',
				`mechanic_notes` = '" . mysql_escape_mimic($asset['mechanic_notes']) . "',
				`misc_notes` = '" . mysql_escape_mimic($asset['misc_notes']) . "',
				`equipment_color` = '" . mysql_escape_mimic($asset['equipment_color']) . "',
				`qr_code` = '" . mysql_escape_mimic($asset['qr_code']) . "',
				`assign_to` = '" . mysql_escape_mimic($asset['assign_to']) . "'
			WHERE
				(`equipment_code` = '" . mysql_escape_mimic($asset['equipment_code']) . "');";

		//echo $query;
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	return;
}

// Handles creating new assets
if ($_POST['tell'] == "create_new_asset") {

	// Decode JSON objects from JS
	$user_info = json_decode($_POST['user_info'], true);
	$keys = json_decode($_POST['keys'], true);

	// Create new instance of asset
	$asset = new Asset($con);

	// Call function to create asset
	$asset->create_new_asset($user_info, $keys, $_POST);

	//Step 1: Send email to cc'd individuals
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom($user_info['email'], 'Web FST Automated Email System');
	$mail->AddReplyTo($user_info['email'], $user_info['fullName']);

	//Break here for two type of emails

	if ((floatval($_POST['purchase_cost']) > 5000) || ($_POST['category'] == 'Vehicles')) {

		$mail->addAddress("dtvp-processing@piersonwireless.com", "Viewpoint Processing");

		$message = "This asset meets the requirement to be entered in the financial system.  
						Please enter this asset in Vista.";
	} else {
		$message = "";
	}

	// CC tester
	$mail->addCC($user_info['email'], $user_info['fullName']); 	//bcc myself for the time being

	// create email body 
	$body = "Hello,<br><br>";
	$body .= "Asset " . $asset->info['equipment_code'] .  " has been added to the system. <br>";
	$body .= $message . "<br><br>";
	$body .= "<b>Purchase Price:</b> " . convert_money('%.2n', $asset->info['purchase_cost']) . "<br>";
	$body .= "<b>Equipment:</b> " . $asset->info['equipment_code'] . " <br>";
	$body .= "<b>Equipment Description:</b> " . $asset->info['description'] . "<br>";
	$body .= "<b>Asset:</b> " . substr($asset->info['equipment_code'], 6) . "<br>";
	$body .= "<b>Asset Description:</b> " . $asset->info['description'] . "<br>";
	$body .= "<b>First Month:</b> " . $currentDate = date('m/d/Y') . "<br>";
	$body .= "<b>Depr Method</b><br> ";
	$body .= "<b>Residual Value:</b> "  . convert_money('%.2n', $asset->info['purchase_cost']) . "<br>";
	$body .= "<b>#Months to Depr:</b><br> ";
	$body .= "<b>DB Factor:</b><br> ";
	$body .= "<b>Sales Price:</b> " . convert_money('%.2n', 0) . "<br>";
	$body .= "<b>Total To Depr:</b> " . convert_money('%.2n', $asset->info['purchase_cost']) . "<br>";
	$body .= "<b>Accum Depr Acct:</b><br>";
	$body .= "<b>Depr Expense Acct:</b><br>";
	$body .= "<b>Depr Asset Acct:</b><br><br>";
	//$body .= "<b>Link:</b> https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $request['quoteNumber'] . "<br><br>";
	$body .= "Thank you,";

	// Content
	$mail->isHTML(true);
	$mail->Subject = "New asset " . $asset->info['equipment_code'] .  " created";
	$mail->Body = ($body);
	$mail->send();

	// close smtp connection
	$mail->smtpClose();
}
