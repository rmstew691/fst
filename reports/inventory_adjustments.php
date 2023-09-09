<?php

//allow session variables
session_start();

//$sub_folder = "FST";
$sub_folder = "FST";

// Load the database configuration file
require_once 'C:/xampp/htdocs/' . $sub_folder . '/config.php';

//include constants sheet
include('C:/xampp/htdocs/' . $sub_folder . '/constants.php');

//include php functions sheet
include('C:/xampp/htdocs/' . $sub_folder . '/phpFunctions.php');

//Step 3: Create Excel file to be attached in email
require 'C:/xampp/htdocs/' . $sub_folder . '/vendor/autoload.php';

//required to use PHPSpreadsheet
require 'C:/xampp/htdocs/' . $sub_folder . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'C:/xampp/htdocs/' . $sub_folder . '/PHPMailer/Exception.php';
require_once 'C:/xampp/htdocs/' . $sub_folder . '/PHPMailer/PHPMailer.php';
require_once 'C:/xampp/htdocs/' . $sub_folder . '/PHPMailer/SMTP.php';

//get invreprot
$inventory = [];
$query = "SELECT * FROM invreport";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
    array_push($inventory, $rows);
}

//get inv_locations (dump from VP)
$inv_locations = [];
$query = "select shop, partNumber, stock - allocated as 'true_stock' from inv_locations WHERE shop NOT IN ('OMA-WS', 'IN-TRANSIT', 'LMO', 'LPO', 'MIN-VZO') ORDER BY stock desc;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){

    //trim partNumber
    $rows['partNumber'] = trim($rows['partNumber']);
    array_push($inv_locations, $rows);
}

//array to hold any issues not finding a part
$issue_with_part = [];

//array to hold any issues not finding a shop
$issue_with_shop = [];

//array to hold any issues with stock
$issue_with_stock = [];

//loop through inv_locations. For every part, check invreport to see if the stock matches, if not, add to $issue_with_stock
//if there is no index found, add to $issue_with_shop
foreach ($inv_locations as $part){

    //look for index
    $index = array_search($part['partNumber'], array_column($inventory, 'partNumber'));

    //check if we found match, if not, add to issue with part
    if ($part['shop'] == "OMA-WS" || $part['shop'] == "IN-TRANSIT" || $part['shop'] == "LMO" || $part['shop'] == "LPO" || $part['shop'] == "MIN-VZO"){
        //ignore
    }
    elseif ($index == ""){
        array_push($issue_with_part, $part['partNumber']);
    }
    //check if shop exists or not (should be issue with IN-TRANSIT, OMA-WS, LPO, etc.)
    //elseif (!property_exists($inventory[$index], $part['shop'])){
    //   array_push($issue_with_part, $part['partNumber']);
    //}
    //check if stock matches what we have in invreport table
    elseif($inventory[$index][$part['shop']] != $part['true_stock']){
        array_push($issue_with_stock, [
            'part' => $part['partNumber'],
            'shop' => $part['shop'],
            'FST'  => $inventory[$index][$part['shop']],
            'VP'   => $part['true_stock']
        ]);
    }
}

//output results
/*
if (sizeof($issue_with_part) > 0){
    echo "<h3>Issues with parts</h3>";

    //start list
    echo "<ul>";

    //loop and add to list
    foreach ($issue_with_part as $part){
        echo "<li>" . $part . "</li>";
    }

    //end list
    echo "</ul>";
}
else{
    echo "<h3>No Issues with parts</h3>";
}

//output results
if (sizeof($issue_with_shop) > 0){
    echo "<h3>Issues with shops</h3>";

    //start list
    echo "<ul>";

    //loop and add to list
    foreach ($issue_with_shop as $part){
        echo "<li>" . $part . "</li>";
    }

    //end list
    echo "</ul>";
}
else{
    echo "<h3>No Issues with shops</h3>";
}*/

//output results
if (sizeof($issue_with_stock) > 0){
    echo "<h3>Issues with stock</h3>";

    //start list
    echo "<ul>";

    //loop and add to list
    foreach ($issue_with_stock as $part){
        echo "<li>";
        echo $part['part'] . " (" . $part['shop'] . ")";
        echo "<ul>";
        echo "<li>FST: " . $part['FST'] . "</li>";
        echo "<li>VP: " . $part['VP'] . "</li>";
        echo "</ul>";
        echo "</li>";
    }

    //end list
    echo "</ul>";
}
else{
    echo "<h3>No Issues with stock</h3>";
}