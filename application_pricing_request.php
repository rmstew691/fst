<?php

//*******************************************
//Last Update (8.18.22)
//Handles sending email orders team to request part pricing. Includes Excel Document with full BOM and info provided by Designer.
//Step 1: Check non_mmd_request_bom, if non-empty, do step 3 & 4
//Step 2: Check mmd_request_bom, if non-empty, do step 3 & 4
//Step 3: Create excel sheet from template (Request-Template)
//Step 4: Generate Email & Save info
//******************************************

// Load Dependencies
session_start();
include('constants.php');
include('phpFunctions.php');
include('PHPClasses/Part.php');

// Load the database configuration file
require_once 'config.php';

//required to use PHPSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//styling to remove borders
$no_borders = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
        ],
    ],
];

//convert incoming arrays from json
$request_info = json_decode($_POST['request_info'], true);
$non_mmd_request_bom = json_decode($_POST['non_mmd_request_bom'], true);
$mmd_request_bom = json_decode($_POST['mmd_request_bom'], true);

//run loop twice, first run check for mmd, second run check for non-mmd (originally had as function but could not get warnings to go away while accessing constants sheet)
for ($i = 0; $i < 2; $i++){

    //change bom based on $i
    //$i = 0 -> mmd
    if ($i == 0){

        //check size of mmd, if 0, increment i
        if (sizeof($mmd_request_bom) > 0)
            $bom = $mmd_request_bom;
        else
            $i++;

    }

    //$i = 1 -> non-mmd
    if ($i == 1){

        //check size of mmd, if 0, break from loop
        if (sizeof($non_mmd_request_bom) > 0)
            $bom = $non_mmd_request_bom;
        else
            break;

    }

    //Step 3: Create Excel file to be attached in email (start with template "Request-Template")
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/Request-Template.xlsx');

    $worksheet = $spreadsheet->getActiveSheet();

    //fill header info
    $worksheet->setCellValueByColumnAndRow(3, 2, $request_info['project_name']); 
    $worksheet->setCellValueByColumnAndRow(3, 3, $request_info['project_num']); 
    $worksheet->setCellValueByColumnAndRow(3, 4, $request_info['quote_num']); 
    $worksheet->setCellValueByColumnAndRow(3, 5, $request_info['due_date']); 

    //only add certain info for mmd
    if ($i == 0){
        $worksheet->setCellValueByColumnAndRow(5, 2, $request_info['vz_id']); 
        $worksheet->setCellValueByColumnAndRow(5, 3, $request_info['bus_unit']); 
        $worksheet->setCellValueByColumnAndRow(5, 4, $request_info['gc_loc']);
    }
    else{
        //remove header info
        $worksheet->setCellValueByColumnAndRow(4, 2, ""); 
        $worksheet->setCellValueByColumnAndRow(4, 3, ""); 
        $worksheet->setCellValueByColumnAndRow(4, 4, "");

        //remove borders from cells that don't need it
        $worksheet->getStyle("E2:E5")->applyFromArray($no_borders);
    }

    //cycle through bom
    for ($j = 0; $j < sizeof($bom); $j ++){

        //new instance of part, set flag for on order
        $part = new Part($bom[$j]['part'], $con, $bom[$j]['id']);
        $part->set_to_order();
        
        //fill part # and quantity regardless of what type of project it is
        $worksheet->setCellValueByColumnAndRow(2, $j + 8, $bom[$j]['description']); //Description
        $worksheet->setCellValueByColumnAndRow(3, $j + 8, $bom[$j]['manufacturer']); //Manufacturer
        $worksheet->setCellValueByColumnAndRow(4, $j + 8, $bom[$j]['part']); //Part #
        $worksheet->setCellValueByColumnAndRow(5, $j + 8, $bom[$j]['quantity']); //Quantity Requested
        
    }

    //autofit columns B - E
    $worksheet->getColumnDimension('B')->setAutoSize(true);
    $worksheet->getColumnDimension('C')->setAutoSize(true);
    $worksheet->getColumnDimension('D')->setAutoSize(true);
    $worksheet->getColumnDimension('E')->setAutoSize(true);

    //save as excel file
    //MMD = 0
    if ($i == 0)
        $excel = "MMD Request - " . $request_info['project_name'] . " - " . $request_info['quote_num'] . ".xlsx";
    //Standard = 1    
    elseif ($i == 1)
        $excel = "Standard Request - " . $request_info['project_name'] . " - " . $request_info['quote_num'] . ".xlsx";
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($excel);

    //Instantiation and passing `true` enables exceptions
    $mail = new PHPMailer();
    $mail = init_mail_settings($mail);

    //Recipients
    $mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
    $mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

    //if testing, send to yourself
    if ($use == "test"){
        $mail->addAddress($_SESSION['email']); 			//send to test email
    }
    else{
        $mail->addAddress("orders@piersonwireless.com"); //send to material creation group

        //query fst_grid to get the email of the estimator
        $query = "SELECT b.email FROM fst_grid a, fst_users b WHERE a.quoteNumber = '" . $request_info['quote_num'] . "' AND a.quoteCreator = CONCAT(b.firstName, ' ', b.lastName) LIMIT 1;";
        $result = mysqli_query($con, $query);

        //if we return a result, grab the array and add to CC
        if (mysqli_num_rows($result) > 0){
            $qc = mysqli_fetch_array($result);
            $mail->addCC($qc['email']); //cc yourself
        }
    }

    //cc user & bcc alex for testing
    $mail->addCC($_SESSION['email']); //cc yourself
    //$mail->addBCC('alex.borchers@piersonwireless.com'); //bcc alex

    //mailer excel file
    $mail->addAttachment($excel);

    //get urgency based on due date
    $urgency = urgency_calculator($request_info['due_date'], "mmd");

    //hard code sub_link for time being
	$sub_link = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/";

    //set subject & body based on type of request
    //0 = MMD
    if ($i == 0){
        $subject = $urgency . " MMD Request - " . $request_info['project_name'] . " - " . $request_info['quote_num'];
        $body = "Orders Team,<br><br>";
        $body .= "Please use the attached document to request MMD pricing.<br><br>Referenced FST: " . $sub_link . "application.php?quote=" . $request_info['quote_num'] . "<br><br>";
        $body .= "Thank you,";
    }
    //1 = Regular    
    elseif ($i == 1){
        $subject = $urgency . " Pricing Request - " . $request_info['project_name'] . " - " . $request_info['quote_num'];
        $body = "Orders Team,<br><br>";
        $body .= "Please use the attached document to request pricing.<br><br>Referenced FST: " . $sub_link . "application.php?quote=" . $request_info['quote_num'] . "<br><br>";
        $body .= "Thank you,";
    }
        
    //Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    //send email
    $mail->send();

    //close smtp connection
    $mail->smtpClose();

    //Delete the file
    unlink($excel);

}

//close SQL connection
$mysqli -> close();