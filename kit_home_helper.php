<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

//include php functions
include('phpFunctions.php');

//Initialize PHP Mailer (in case we need to send an email)
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//handles creating a new kit
if($_POST['tell'] == 'create_kit'){

    //get user from session variables
    $user = $_SESSION['firstName'] . " " . $_SESSION['lastName'];

    //create query into fst_bom_kits & execute
    $query = "INSERT INTO fst_bom_kits (kit_part_id, kit_description, who_created, last_update) 
    VALUES ('" . $_POST['kit_id'] . "', '" . $_POST['description'] . "', '" . $user . "', NOW())";

    custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    //create query to enter kit into inventory catalog
    $query = "INSERT INTO invreport (partDescription, partNumber, time_created, partCategory, manufacturer, uom, price, cost, status) 
    VALUES ('" . $_POST['description'] . "', '" . $_POST['kit_id'] . "', NOW(), 'PW-KITS', 'Pierson Wireless', 'EA', 0, 0, 'Active')";

    custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    return;
}

?>