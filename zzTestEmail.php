<?php

// load dependencies
session_start();
include('phpFunctions_drive.php');
include('constants.php');
include('phpFunctions.php');

// load db configuration
require_once 'config.php';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//Get user info
$query = "SELECT * FROM fst_users WHERE id = '0';";
$result = mysqli_query($con, $query);
$user_info = mysqli_fetch_array($result);

//Step 1: Send email to cc'd individuals
//Instantiation and passing `true` enables exceptions
$mail = new PHPMailer(true);
$mail = init_mail_settings_oauth($mail, $user_info);
$mail = init_mail_settings($mail, $user_info);

// add failing address
$mail->addAddress("not.an.email@piersonwireless.com");

// add vacation email
//$mail->addAddress("alexmborchers@gmail.com");

// CC tester
$mail->addCC("alex.borchers@piersonwireless.com"); 	//bcc myself for the time being

// Content
$mail->isHTML(true);
$mail->Subject =  "Test Subject";
$mail->Body = "Test Body<br><br>";
$mail->Body.= create_signature($mail, $user_info);

// Set the Return-Path header to the sender's email address
$mail->addCustomHeader('Return-Path', 'alex.borchers@piersonwireless.com');
$mail->Sender = "alex.borchers@piersonwireless.com";

try {
    // Send the message
    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo 'Message could not be sent. Error: ', $mail->ErrorInfo;
}

// close smtp connection
$mail->smtpClose();