<?php

// Load dependences
session_start();
include('phpFunctions.php');
include('PHPClasses/Part.php');

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

//save file
$target_dir = getcwd();
$target_file = $target_dir . "\\" . basename($_FILES["file"]["name"]);
$errorMessage = ""; //used to send message back to user if there is an error

$uploadOk = 1;
$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// grab file type to check if xlsx

// Check if file already exists
if (file_exists($target_file)) {
	unlink($_FILES["file"]["name"]);
}

// Check file size
if ($_FILES["file"]["size"] > 5000000) {
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
  if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {

  } else {
	echo "Sorry, there was an error uploading your file.";
	  goto rejected;
  }
}

//init csv array
$csv = [];

$file = fopen($_FILES["file"]["name"], 'r');
while (($line = fgetcsv($file)) !== FALSE) {
  //$line is an array of the csv elements
  array_push($csv, ($line));
}

//close the file after reading its contents
fclose($file);

//get user info from POST
$user_info = json_decode($_POST['user_info'], true);

//loop until we hit header row
$start = 0; 
while ($csv[$start][0] != "Shop"){
    $start++;
}

//loop through csv contents
for ($i = $start + 1; $i < sizeof($csv); $i++){
	
    //create new instance of a part & update shop
    $part = new Part($csv[$i][1], $con);
    $part->info['shop'] = $csv[$i][0];
	
    //if on-hand is blank, update to 0
    if ($csv[$i][2] == "")
        $csv[$i][2] = 0;

    //call function to update on hand
    $part->update_on_hand($csv[$i][2], true);
    
    //log requested update
    //type "CC" = "Cycle Count"
    $part->log_part_update($user_info['id'], 'Old (' . $part->info[$part->info['shop']] . ') - New (' . $csv[$i][2] . ') (' . $user_info['firstName'] . ' ' . $user_info['lastName'] . ')', 'CC', 'Approved');

    //loop through physical locations & add to array for processing
    //init physical locations 
    $phys_locations = [];

    //init starting location & offset (offset is how far we need to go to get to the next location)
    $PL_start = 3;
    $curr_offset = 0;
    $offset = 2;

    //while we still see a physical location entered
    while ($csv[$i][$PL_start + $curr_offset] != ""){

        //update count if blank
        if ($csv[$i][$PL_start + $curr_offset + 1] == "")
            $csv[$i][$PL_start + $curr_offset + 1] = 0;
        
        //check if location was saved as primary before
        $query = "SELECT prime FROM invreport_physical_locations 
                    WHERE shop = '" . $part->info['shop'] . "' AND partNumber = '" . mysql_escape_mimic($part->info['partNumber']) . "' AND location = '" . mysql_escape_mimic($csv[$i][$PL_start + $curr_offset]) . "';";
        $result = mysqli_query($con, $query);
        if (mysqli_num_rows($result) > 0)
            $is_prime = mysqli_fetch_array($result);
        else
            $is_prime['prime'] = 0;

        //add to physical locations array
        array_push($phys_locations, array(
            'loc' => $csv[$i][$PL_start + $curr_offset],
            'on_hand' => $csv[$i][$PL_start + $curr_offset + 1],
            'primary' => $is_prime['prime']
          )
        );

        //update current offset
        $curr_offset += $offset;
    }

	//update physical locations
	$part->update_physical_locations($phys_locations);

  //if new <> old, send email to inventory@pw.com
  if ($part->info[$part->info['shop']] != $csv[$i][2] || (sizeof($part->added) + sizeof($part->removed)) > 0){

      //Instantiation and passing `true` enables exceptions
      $mail = new PHPMailer();

      //init $mail settings
      $mail = init_mail_settings($mail, $user_info);
      
      if ($use == "test")
        $mail->addAddress($_SESSION['email']);
      else
        $mail->addAddress("inventory@piersonwireless.com"); //send to allocations

      //if this is a -3 shop, default cost to 0
      if (str_contains($part->info['shop'], "-3"))
        $part->info['cost'] = 0;

      //calculate affect on cost (adjusted $)
      $adjusted = (intval($csv[$i][2]) - intval($part->info[$part->info['shop']])) * floatval($part->info['cost']);

      // depending on the adjusted $ value, send email for approval or not
      if (abs($adjusted) >= 200 && $user_info['allocations_admin'] != "checked"){

        //log requested update
        //type "IA" = "Inventory Adjustment"
        $part->log_part_update($user_info['id'], 'Old (' . $part->info[$part->info['shop']] . ') - New (' . $csv[$i][2] . ') (' . $user_info['firstName'] . ' ' . $user_info['lastName'] . ')', 'CC', 'Pending');

        //get log id
        $log_id = $con->insert_id;

        // create link for user to click to approve adjustment
        // replace any potentially bad characters in URL 
        $part_fix = str_replace("+","%2B", $part->info['partNumber']);

        $sublink = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/";

        $approve_link = $sublink . 'terminal_warehouse_confirmation.php?type=Approved&part=' . $part_fix . "&shop=" . $part->info['shop'] . "&new=" . $csv[$i][2] . "&old=" . $part->info[$part->info['shop']] . "&log_id=" . $log_id;
        $approve_html = "<a href = '" . $approve_link . "'>Approve Adjustment</a>";

        $reject_link = $sublink . 'terminal_warehouse_confirmation.php?type=Rejected&part=' . $part_fix . "&shop=" . $part->info['shop'] . "&new=" . $csv[$i][2] . "&old=" . $part->info[$part->info['shop']] . "&log_id=" . $log_id;
        $reject_html = "<a href = '" . $reject_link . "'>Reject Adjustment</a>"; 

        //create body of email
        $body = "Hello,<br><br>";
        $body.= "Please click one of the following links to confirm adjustment:<br><br>";
        $body.= $approve_html . "<br>";
        $body.= $reject_html . "<br><br>";
        $body.= "An adjustment for part #" . $part->info['partNumber'] . " has been requested that is over the threshold ($200).<br><br>";
        $body.= "<b>Adjustment Summary</b><br>";
        $body.= "Part #: " . $part->info['partNumber'] . "<br>";
        $body.= "Shop: " . $part->info['shop'] . "<br>";
        $body.= "Current Stock: " . $part->info[$part->info['shop']] . "<br>";
        $body.= "Adjusted Stock: " . $csv[$i][2] . "<br>";
        $body.= "Affect on Cost: $" . strval($adjusted) . "<br><br>";
        $body.= $part->get_update_summary("added");
        $body.= $part->get_update_summary("removed");
        $body.= "Thank you,";
            
        //Content
        $mail->isHTML(true);
        $mail->Subject = "[Cycle Count Adjustment] Part #" . $part->info['partNumber'];
        $mail->Body = $body;
        $mail->send();

        //close smtp connection
        $mail->smtpClose();

    }
    else{

      //log requested update
      //type "IA" = "Inventory Adjustment"
      $part->log_part_update($user_info['id'], 'Old (' . $part->info[$part->info['shop']] . ') - New (' . $csv[$i][2] . ') (' . $user_info['firstName'] . ' ' . $user_info['lastName'] . ')', 'CC', 'Approved');

      //create body of email
      $body = "Hello,<br><br>";
      $body.= "An adjustment for part #" . $part->info['partNumber'] . " as a part of a cycle count that is not over the threshold OR made my an administrator.<br><br>";
      $body.= "<b>Adjustment Summary</b><br>";
      $body.= "Part #: " . $part->info['partNumber'] . "<br>";
      $body.= "Shop: " . $part->info['shop'] . "<br>";
      $body.= "Old Stock: " . $part->info[$part->info['shop']] . "<br>";
      $body.= "Adjusted Stock: " . $csv[$i][2] . "<br>";
      $body.= "Affect on Cost: $" . strval($adjusted) . "<br><br>";
      $body.= $part->get_update_summary("added");
      $body.= $part->get_update_summary("removed");
      $body.= "Thank you,";

      //Content
      $mail->isHTML(true);
      $mail->Subject = "[Cycle Count Adjustment] Part #" . $part->info['partNumber'];
      $mail->Body = $body;
      $mail->send();

      //close smtp connection
      $mail->smtpClose();

    }    
  }
}
	
//remove from directory
unlink($_FILES["file"]["name"]);

return;

rejected: 

//remove from directory
unlink($_FILES["file"]["name"]);

//return error message
echo "ERROR|" . $errorMessage;

return;