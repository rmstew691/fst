<?php

//allow session variables
session_start();

//update max execute time for file (in seconds)
ini_set('max_execution_time', 120);
//set_time_limit ( 300 );

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

//include php functions sheet
include('phpFunctions.php');

//Step 3: Create Excel file to be attached in email
require 'vendor/autoload.php';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//init list of parts to be ignored
$ignore_list = array('ADRF-CHC-U-LP-D', 'ADRF-CHC-U-LP-M', 'ADX-BPF-7FUL', 'ADX-BPF-7PUL');

//run query to look for missing parts
$query = 'select TRIM(a.partNumber) as part from inv_hq a where TRIM(a.partNumber) NOT IN (select TRIM(b.partNumber) from invreport b);';
$result = mysqli_query($con, $query);

//run through results and add to email body as a table
//init list

$body = "<ol>";

while($rows = mysqli_fetch_assoc($result)){
		
	//init decision var
	$ignore = false;
	
	//check for parts that can be ignored
	foreach ($ignore_list as $part){
		if (is_numeric(strpos($rows['part'], $part))){
			$ignore = true;
			break;
		}
	}
	
	//if ignore is false, add to table
	if (!$ignore){

		//get id from new_parts (if applicable)
		$query = 'select id from fst_newparts WHERE partNumber = "' . mysql_escape_mimic($rows['part']) . '" ORDER BY id desc LIMIT 1;';
		$result = mysqli_query($con, $query);
		
		//check num rows (if > 0, add link to part creation)
		if (mysqli_num_rows($result) > 0){
			$get_index = mysqli_fetch_array($result);
			$body.= "<li>";
			$body.= $rows['part'] . " (https://pw-fst.northcentralus.cloudapp.azure.com/FST/terminal_hub.php?newPart=" . $get_index['id'] . ")";
			$body.= "</li>";
		}
		else{
			$body.= "<li>";
			$body.= $rows['part'];
			$body.= "</li>";
		}

		
		
	}
	
}

//if we have no parts (equal to <ol>) let group no there is no action needed
if ($body == "<ol>")
	$body = "<br><br>No action needed.<br><br>";
else
	$body = "<br><br>The following parts are listed in Viewpoint and not in the Web FST: <br>" . $body . "</ol>";

//Instantiation and passing `true` enables exceptions
$mail = new PHPMailer();
$mail = init_mail_settings($mail);

//Recipients
$mail->setFrom($_SESSION['email'], 'Web FST Automated Email System'); //set from (name is optional)

//check to see if a semicolon is present, if so, parse through email line & add all emails to group
//add to
$mail->addAddress('alex.borchers@piersonwireless.com');

//Content
$mail->isHTML(true);
$mail->Subject =  "Parts for review (HQ materials not in FST)";
$mail->Body = "Hello," . $body . "Thank you,";

//send mail
if ($mail->send()){
	echo "<h1>Email successfully sent.</h1>";
}

//close smtp connection
$mail->smtpClose();

//close SQL connection
$mysqli -> close();
