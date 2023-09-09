<?php

/**
 * HELPER FILE
 * ANYTHING ENDING IN _helper.php IS A HELPER FILE 
 * It's role is to help read & write information to the database
 * 
 * Any calls to a helper file should include a 'tell' variable so the helper knows what to do
 * 
 */

// init session variables
session_start();

// load in potentially used files
require_once 'config.php';
include('constants.php');
include('phpFunctions.php');

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//handles removing a part from the request
if ($_POST['tell'] == 'update_vendor'){

	// manually create a query to update the information
    // the $_POST[] variables is what we defined in the form object (fd.append()) they can be used here
    // use mysql_escape_mimic on ALL free-form input fields. This escapes problematic characters like ' or " that may cause the query to fail 
    // mysql_escape_mimic() can be found in phpFunctions.php file. This file must be included before using (see line 18)
	$query = "UPDATE fst_vendor_list 
                SET vendor_search = '" . mysql_escape_mimic($_POST['search_name']) . "',
                    poc = '" . mysql_escape_mimic($_POST['poc']) . "',
                    phone = '" . mysql_escape_mimic($_POST['phone']) . "',
                    street = '" . mysql_escape_mimic($_POST['street']) . "',
                    city = '" . mysql_escape_mimic($_POST['city']) . "',
                    state = '" . mysql_escape_mimic($_POST['state']) . "',
                    zip = '" . mysql_escape_mimic($_POST['zip']) . "'                
                WHERE id = '" . $_POST['id'] . "';";

    // custom_query is also in phpFunctions.php
    // custom_query should be used for all WRITE functions (update, delete, etc.)
    // any errors that custom_query runs into, will kick an email to fst@pw.com with the error message, line, and file name
    // all 4 params should remain the same. $query should be updated prior to calling custom_query
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    // return exits the file once hit. Make sure to put this at the end of all if($_POST['tell']) conditions to avoid doing something we don't want to do
	return;

}

//handles createing a new vendor
if ($_POST['tell'] == 'create_vendor'){

    // decode user_info encoded from js
    $user_info = json_decode($_POST['user_info'], true);

	// manually create a query to update the information
    // the $_POST[] variables is what we defined in the form object (fd.append()) they can be used here
    // use mysql_escape_mimic on ALL free-form input fields. This escapes problematic characters like ' or " that may cause the query to fail 
    // mysql_escape_mimic() can be found in phpFunctions.php file. This file must be included before using (see line 18)
	$query = "INSERT INTO fst_vendor_list (`vendor`, `poc`, `phone`, `street`, `city`, `state`, `zip`)
                                    VALUES ('" . mysql_escape_mimic($_POST['name']) . "', '" . mysql_escape_mimic($_POST['poc']) . "',
                                            '" . mysql_escape_mimic($_POST['phone']) . "', '" . mysql_escape_mimic($_POST['street']) . "',
                                            '" . mysql_escape_mimic($_POST['city']) . "', '" . mysql_escape_mimic($_POST['state']) . "', 
                                            '" . mysql_escape_mimic($_POST['zip']) . "');";

    // custom_query is also in phpFunctions.php
    // custom_query should be used for all WRITE functions (update, delete, etc.)
    // any errors that custom_query runs into, will kick an email to fst@pw.com with the error message, line, and file name
    // all 4 params should remain the same. $query should be updated prior to calling custom_query
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    // send newly created ID back to user
    $id = mysqli_insert_id($con);
    echo $id;

    // kick out email with vendor info
    $mail = new PHPMailer();
    $mail = init_mail_settings($mail, $user_info);

    // send to user requesting
    if ($use == "test"){
        $mail->addAddress($user_info['email']);
    }
    else{
        $mail->addAddress($user_info['email']);
        $mail->addAddress("AccountsPayable@piersonwireless.com ");	//send to AP
    }

    // create body
    $body  = "Hello,<br><br>";
    $body .= "The following vendor has been created in the FST:<br><br>";
    $body .= "ID: " . $id . "<br>";
    $body .= "Vendor Name: " . $_POST['name'] . "<br>";
    $body .= "POC: " . $_POST['poc'] . "<br>";
    $body .= "POC Phone: " . $_POST['phone'] . "<br>";
    $body .= "Address: " . $_POST['street'] . "<br>";
    $body .= "City: " . $_POST['city'] . "<br>";
    $body .= "State: " . $_POST['state'] . "<br>";
    $body .= "Zip: " . $_POST['zip'] . "<br><br>";
    $body .= "Thank you,";

    // Content
    $mail->isHTML(true);
    $mail->Subject =  "New Vendor Creation Email: " . $_POST['name'];
    $mail->Body = $body;
    $mail->send();

    // close smtp connection
    $mail->smtpClose();    

    // return exits the file once hit. Make sure to put this at the end of all if($_POST['tell']) conditions to avoid doing something we don't want to do
	return;

}

// handles removing vendor poc
if ($_POST['tell'] == 'delete_poc'){

	// manually create a query to update the information
    // the $_POST[] variables is what we defined in the form object (fd.append()) they can be used here
    // use mysql_escape_mimic on ALL free-form input fields. This escapes problematic characters like ' or " that may cause the query to fail 
    // mysql_escape_mimic() can be found in phpFunctions.php file. This file must be included before using (see line 18)
	$query = "DELETE FROM fst_vendor_list_poc WHERE id = '" . $_POST['id'] . "';";

    // custom_query is also in phpFunctions.php
    // custom_query should be used for all WRITE functions (update, delete, etc.)
    // any errors that custom_query runs into, will kick an email to fst@pw.com with the error message, line, and file name
    // all 4 params should remain the same. $query should be updated prior to calling custom_query
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    // return exits the file once hit. Make sure to put this at the end of all if($_POST['tell']) conditions to avoid doing something we don't want to do
	return;

}

// handles updating POC info
if ($_POST['tell'] == 'update_poc'){

    // get new poc_info passed from js
    $poc_info = json_decode($_POST['poc_info'], true);

    // loop through info & save to db
    foreach($poc_info as $poc){
        $query = "UPDATE fst_vendor_list_poc 
                    SET name = '" . mysql_escape_mimic($poc['name']) . "',
                        phone = '" . mysql_escape_mimic($poc['phone']) . "',
                        email = '" . mysql_escape_mimic($poc['email']) . "'
                    WHERE id = '" . mysql_escape_mimic($poc['id']) . "';";
        custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    // return exits the file once hit. Make sure to put this at the end of all if($_POST['tell']) conditions to avoid doing something we don't want to do
	return;

}