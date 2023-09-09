<?php

//allow session variables
session_start();

// create sub_folder to get dependencies
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

//define styles needed while creating each row
//add thin borders to outline quotes
$light_borders = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

//add thin borders to outline quotes
$thick_bottom_border = [
    'borders' => [
        'bottom' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
        ],
    ],
];

//function defined to add new entries to table
function add_new_group($worksheet, $group, $start_pos, $notes, $solution_types, $light_borders)
{

    //insert # of rows for size of unassigned
    $worksheet->insertNewRowBefore($start_pos, sizeof($group)); // (in between offset - 1 and offset)

    //cycle through unassigned
    for ($i = 0; $i < sizeof($group); $i++) {

        //set current position
        $curr_pos = $start_pos + $i;

        //fill desired information for backlog report
        $worksheet->setCellValueByColumnAndRow(2, $curr_pos, $group[$i]['quoteNumber']);
        $worksheet->getCell('B' . $curr_pos)->getHyperlink()->setUrl('https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=' . $group[$i]['quoteNumber']);
        $worksheet->setCellValueByColumnAndRow(3, $curr_pos, $group[$i]['location_name']);
        $worksheet->setCellValueByColumnAndRow(4, $curr_pos, $group[$i]['phaseName']);
        $worksheet->setCellValueByColumnAndRow(5, $curr_pos, $group[$i]['designer'] . "/" . $group[$i]['quoteCreator']);

        //set des task / des status based on type of project
        if ($group[$i]['personnel'] == "" || $group[$i]['personnel'] == null)
            $worksheet->setCellValueByColumnAndRow(6, $curr_pos, $group[$i]['task']);
        else
            $worksheet->setCellValueByColumnAndRow(6, $curr_pos, $group[$i]['status']);

        //format due dates
        if ($group[$i]['due_date'] == "" || $group[$i]['due_date'] == null)
            $due_date = "";
        else {
            $due_date = date_create($group[$i]['due_date']);
            $due_date =  date_format($due_date, "m/d/Y");
        }

        $worksheet->setCellValueByColumnAndRow(7, $curr_pos, $due_date);

        //read out the rest of the data
        $worksheet->setCellValueByColumnAndRow(8, $curr_pos, get_type_abv($group[$i]['projectType'], $solution_types));
        $worksheet->setCellValueByColumnAndRow(9, $curr_pos, substr($group[$i]['market'], 0, 4));
        $worksheet->setCellValueByColumnAndRow(10, $curr_pos, $group[$i]['customer']);
        $worksheet->setCellValueByColumnAndRow(11, $curr_pos, $group[$i]['fst_status']);
        $worksheet->setCellValueByColumnAndRow(12, $curr_pos, $group[$i]['quoteStatus']);
        $worksheet->setCellValueByColumnAndRow(13, $curr_pos, get_recent_note_entry($group[$i]['quoteNumber'], $notes));

        //apply desired styles to worksheet
        //$worksheet->mergeCells("C" . $curr_pos . ":D" . $curr_pos);
        $worksheet->getStyle("B" . $curr_pos . ":N" . $curr_pos)->applyFromArray($light_borders);
        $worksheet->getStyle("C" . $curr_pos . ":N" . $curr_pos)->getFont()->setItalic(false);
        $worksheet->getStyle("B" . $curr_pos . ":N" . $curr_pos)->getFont()->setBold(false);
        $worksheet->getStyle("C" . $curr_pos . ":N" . $curr_pos)->getFont()->setUnderline(false);
    }
}

//used to get most recent notes entry
//param 1 = quote, param 2 = list of all notes (array of object with note and fullName properties)
function get_recent_note_entry($quote, $notes)
{

    //get index
    $index = array_search(substr($quote, 0, strlen($quote) - 1), array_column($notes, 'quote'));

    //check if index returned anything
    if ($index == "")
        return "";

    return date("m-d-y", strtotime($notes[$index]['date'])) . " - " . $notes[$index]['notes'];
}

//used to check des_unassigned_timestamp (to see if it is over 1 week old)
//param 1 = unassigned_timestamp (found in fst_grid)
// return true if over a week, false if not
function one_week_old($unassigned_timestamp)
{

    //check if index returned anything
    if ($unassigned_timestamp == "")
        return false;

    //check if note was entered in the last 7 days (requires at least 1 week)
    if (strtotime($unassigned_timestamp) >= strtotime('-7 days'))
        return false;

    //pass all checks, return true
    return true;
}

//used to get solution type abbreviation
function get_type_abv($type_full, $solution_types)
{

    //if null, return blank string
    if ($type_full == null)
        return "";

    //find match in $solution_types array
    $index = array_search($type_full, array_column($solution_types, 'type'));

    //check if we found an index
    if ($index == "")
        return "";

    return $solution_types[$index]['abbreviation'];
}

//handles filtering array based on designers
//param 1 = group to filter
//param 2 = designer to filter on 
//returns array of objects related to designer
/**
 * @author alex.borchers <email>
 * Handles filtering array based on designers
 * @param array $group
 * @param string $designer
 * @param string $type
 * @return array
 */
function filter_array($group, $designer, $type)
{

    //init array to be returned
    $new_group = [];

    //loop through group and push if criteria is met
    foreach ($group as $row) {

        //check if designer is in group for PW personnel
        if ($row[$type] == $designer)
            array_push($new_group, $row);
    }

    return $new_group;
}

//get solution types w/ abbreviation
$solution_types = [];
$query = "select * from general_type;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
    array_push($solution_types, $rows);
}

//get fst notes
$fst_notes = [];
$query = "select a.id, a.notes, LEFT(a.quoteNumber, length(a.quoteNumber)-1) as quote, CONCAT(b.firstName, ' ', b.lastName) AS fullName, a.date from fst_notes a, fst_users b WHERE a.user = b.id ORDER BY a.id desc;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
    array_push($fst_notes, $rows);
}

//query to get unassigned projects
$unassigned = [];
$unassigned_all = [];
$query = "SELECT a.*, b.*
            FROM fst_grid_service_request a
            LEFT JOIN fst_grid b
                ON a.quoteNumber = b.quoteNumber
            WHERE a.personnel = '' AND a.group = 'Design' AND a.status <> 'Complete';";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

    //only push if recent note is over a week.
    if (one_week_old($rows['timestamp_requested']))
        array_push($unassigned, $rows);

    //also push to seperate array that holds ALL unassigned projects
    array_push($unassigned_all, $rows);
}

//get count of unassigned
$unassigned_count = sizeof($unassigned);

//query to get anything just assigned (no action taken)
$assigned = [];
$assigned_all = [];
$query = "SELECT a.*, b.*
            FROM fst_grid_service_request a
            LEFT JOIN fst_grid b
                ON a.quoteNumber = b.quoteNumber
            WHERE a.personnel <> '' AND a.group = 'Design' AND a.status = 'Assigned';";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

    //only push if recent note is over a week.
    if (one_week_old($rows['timestamp_requested']))
        array_push($assigned, $rows);

    //also push to seperate array that holds ALL assigned projects
    array_push($assigned_all, $rows);
}

//get count of assigned
$assigned_count = sizeof($assigned);

//query to get anything just acknowledged (no action taken) - also add to "assigned"
$query = "SELECT a.*, b.*
            FROM fst_grid_service_request a
            LEFT JOIN fst_grid b
                ON a.quoteNumber = b.quoteNumber
            WHERE a.personnel <> '' AND a.group = 'Design' AND a.status = 'Acknowledged';";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

    //only push if recent note is over a week.
    if (one_week_old($rows['timestamp_requested']))
        array_push($assigned, $rows);
}

//get count of acknowledged
$acknowledged_count = sizeof($assigned) - $assigned_count;

//now that we have the info needed to create report, lets grab the template and insert new info
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('C:/xampp/htdocs/' . $sub_folder . '/ExcelTemplates/DTeam Backlog Template.xlsx');
$worksheet = $spreadsheet->getSheetByName("DTeam Backlog");

//fill header info
$worksheet->setCellValueByColumnAndRow(3, 6, $unassigned_count);
$worksheet->setCellValueByColumnAndRow(3, 7, $assigned_count);
$worksheet->setCellValueByColumnAndRow(3, 8, $acknowledged_count);

//save counts to reports_dteam_backlog
$query = "INSERT INTO reports_dteam_backlog (id, unassigned, assigned, acknowledged, date)
                                    VALUES (null, '" . $unassigned_count . "', '" . $assigned_count . "', 
                                            '" . $acknowledged_count . "', NOW());";
custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

//add unassigned (only if > 0)
if (sizeof($unassigned) > 0)
    add_new_group($worksheet, $unassigned, 16, $fst_notes, $solution_types, $light_borders);

//filter assigned array into 5 new sub-arrays to represent the 5 groups they may fall in
$init_des = [];
$des_update = [];
$as_built = [];
$est = [];
$est_update = [];

for ($i = 0; $i < sizeof($assigned); $i++) {

    if ($assigned[$i]['task'] == "Initial Design")
        array_push($init_des, $assigned[$i]);
    elseif ($assigned[$i]['task'] == "Design Update")
        array_push($des_update, $assigned[$i]);
    elseif ($assigned[$i]['task'] == "As Built Documentation")
        array_push($as_built, $assigned[$i]);
    elseif ($assigned[$i]['task'] == "Initial Estimation")
        array_push($est, $assigned[$i]);
    elseif ($assigned[$i]['task'] == "Estimation Update")
        array_push($est_update, $assigned[$i]);
}

//add initial design
$offset = 4 + $unassigned_count;
if (sizeof($init_des) > 0)
    add_new_group($worksheet, $init_des, 16 + $offset, $fst_notes, $solution_types, $light_borders);

//add design update
$offset += 4 + sizeof($init_des);
if (sizeof($des_update) > 0)
    add_new_group($worksheet, $des_update, 16 + $offset, $fst_notes, $solution_types, $light_borders);

//add as built
$offset += 4 + sizeof($des_update);
if (sizeof($as_built) > 0)
    add_new_group($worksheet, $as_built, 16 + $offset, $fst_notes, $solution_types, $light_borders);

//add estimation
$offset += 4 + sizeof($as_built);
if (sizeof($est) > 0)
    add_new_group($worksheet, $est, 16 + $offset, $fst_notes, $solution_types, $light_borders);

//add estimation update
$offset += 4 + sizeof($est);
if (sizeof($est_update) > 0)
    add_new_group($worksheet, $est_update, 16 + $offset, $fst_notes, $solution_types, $light_borders);

//style unassigned
//$worksheet->getStyle("E2:E5")->applyFromArray($no_borders);

//autofit columns B - E
$worksheet->getColumnDimension('E')->setAutoSize(true);
$worksheet->getColumnDimension('F')->setAutoSize(true);
$worksheet->getColumnDimension('G')->setAutoSize(true);
$worksheet->getColumnDimension('H')->setAutoSize(true);
$worksheet->getColumnDimension('I')->setAutoSize(true);
$worksheet->getColumnDimension('J')->setAutoSize(true);

//reset arrays related to design team tasks.. do not require 7 day period
//filter assigned array into 5 new sub-arrays to represent the 5 groups they may fall in
$init_des = [];
$des_update = [];
$as_built = [];
$est = [];
$est_update = [];

for ($i = 0; $i < sizeof($assigned_all); $i++) {

    if ($assigned_all[$i]['task'] == "Initial Design")
        array_push($init_des, $assigned_all[$i]);
    elseif ($assigned_all[$i]['task'] == "Design Update")
        array_push($des_update, $assigned_all[$i]);
    elseif ($assigned_all[$i]['task'] == "As Built Documentation")
        array_push($as_built, $assigned_all[$i]);
    elseif ($assigned_all[$i]['task'] == "Initial Estimation")
        array_push($est, $assigned_all[$i]);
    elseif ($assigned_all[$i]['task'] == "Estimation Update")
        array_push($est_update, $assigned_all[$i]);
}

//get list of designers (not supervisors for now)
$designers = [];
$query = "SELECT CONCAT(firstName, ' ', lastName) as user_name FROM fst_users WHERE (des = 'checked' or qc = 'checked') ORDER BY firstName, lastName";
$result = mysqli_query($con, $query);

//loop through list and push to list
while ($rows = mysqli_fetch_assoc($result)) {

    //ignore managers
    if ($rows['user_name'] != "Roderick Maddox")
        array_push($designers, $rows['user_name']);
}

//get list of sheet names (to verify adding info about certain people)
$sheetNames = $spreadsheet->getSheetNames();

//loop through designers & create list of what is in each queue
for ($i = 0; $i < sizeof($designers); $i++) {

    //Go to individual designer sheet (if it exists)
    if (in_array($designers[$i], $sheetNames))
        $worksheet = $spreadsheet->getSheetByName($designers[$i]);
    else
        continue;   //skips to next iteration in for loop

    //update offset to new value
    $offset = 2;

    //Update header
    $worksheet->setCellValueByColumnAndRow(2, $offset, $designers[$i]);

    //increase offset to first row
    $offset += 4;

    //go through each category, filters quotes related to designer, add to queue
    //filter $init_des
    $ind_init_des = filter_array($init_des, $designers[$i], 'personnel');

    if (sizeof($ind_init_des) > 0)
        add_new_group($worksheet, $ind_init_des, $offset, $fst_notes, $solution_types, $light_borders);

    //add design update
    $offset += 4 + sizeof($ind_init_des);

    //filter $des_update
    $ind_des_update = filter_array($des_update, $designers[$i], 'personnel');

    if (sizeof($ind_des_update) > 0)
        add_new_group($worksheet, $ind_des_update, $offset, $fst_notes, $solution_types, $light_borders);

    //add as built
    $offset += 4 + sizeof($ind_des_update);

    //filter $as_built
    $ind_as_built = filter_array($as_built, $designers[$i], 'personnel');

    if (sizeof($ind_as_built) > 0)
        add_new_group($worksheet, $ind_as_built, $offset, $fst_notes, $solution_types, $light_borders);

    //add estimation
    $offset += 4 + sizeof($ind_as_built);

    //filter $est
    $ind_est = filter_array($est, $designers[$i], 'personnel');

    if (sizeof($ind_est) > 0)
        add_new_group($worksheet, $ind_est, $offset, $fst_notes, $solution_types, $light_borders);

    //add estimation update
    $offset += 4 + sizeof($ind_est);

    //filter $est_update
    $ind_est_update = filter_array($est_update, $designers[$i], 'personnel');

    if (sizeof($ind_est_update) > 0)
        add_new_group($worksheet, $ind_est_update, $offset, $fst_notes, $solution_types, $light_borders);
}

//now update "Current Jobs" sheet from template
$worksheet = $spreadsheet->getSheetByName("Current Jobs");

//set background colors to differentiate designers
$first['prime'] = "F2F2F2";
$first['alt'] = "D9D9D9";
$second['prime'] = "D9E1F2";
$second['alt'] = "B4C6E7";

//use (set $use) to $first to start
$use = $first;

//initialize array of columns that we want to read out on to this sheet (see fst_grid for reference)
$keys = [
    'quoteNumber', "location_name", "phaseName", "designer", "quoteCreator",
    "status", "task", "due_date", "projectType", "market",
    "customer", "vpStatus", "quoteStatus", "timestamp_requested", "timestamp_completed"
];

//set first row to keys
for ($i = 0; $i < sizeof($keys); $i++) {
    $worksheet->setCellValueByColumnAndRow($i + 1, 13, $keys[$i]);
}

//sort assigned projects by Designer
//usort($assigned_all, 'designer');
usort($assigned_all, fn ($a, $b) => strcmp($a['designer'], $b['designer']));

//default previous designer as first designer
//we set previous designer so we know when to flip colors ($first to $second)
$previous_designer = $assigned_all[0]['designer'];

//loop through all assigned objects, read key data out to excel sheet
for ($i = 0; $i < sizeof($assigned_all); $i++) {

    //update $use if previous_designer is different from current
    if ($assigned_all[$i]['designer'] != $previous_designer) {

        //check if we are current using first or second
        if ($first['prime'] == $use['prime'])
            $use = $second;
        else
            $use = $first;

        //update previous designer
        $previous_designer = $assigned_all[$i]['designer'];

        //set previous row bottom border to THICK
        $worksheet->getStyle('A' . ($i + 13) . ':P' . ($i + 13))->applyFromArray($thick_bottom_border);
    }

    //loop through keys
    for ($j = 0; $j < sizeof($keys); $j++) {
        $worksheet->setCellValueByColumnAndRow($j + 1, $i + 14, $assigned_all[$i][$keys[$j]]);
    }

    //use 'prime' or 'alt' based on odd/even
    if ($i % 2 == 0)
        $set_color = $use['prime'];
    else
        $set_color = $use['alt'];

    //set background color of row
    $worksheet->getStyle('A' . ($i + 14) . ':P' . ($i + 14))
        ->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB($set_color);

    //set border for row
    $worksheet->getStyle('A' . ($i + 14) . ':P' . ($i + 14))->applyFromArray($light_borders);
}

//set back to summary sheet
$worksheet = $spreadsheet->getSheetByName("DTeam Backlog");

//save as excel file
$date = date("n-d-Y");
$excel = "DTeam Backlog Report (" . $date . ").xlsx";

$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($excel);

//now we have the excel created, lets send an email to design team managers for review and attach the document
//Instantiation and passing `true` enables exceptions
$mail = new PHPMailer();
$mail = init_mail_settings($mail);

//Recipients
$mail->setFrom("projects@piersonwireless.com", 'Web FST Automated Email System'); //set from (name is optional)

//add to
//$mail->addAddress('alex.borchers@piersonwireless.com');

if ($use == "azure")
    $mail->addAddress('designers@piersonwireless.com');
else
    $mail->addAddress($_SESSION['email']);

//Content
$mail->isHTML(true);
$mail->Subject =  "D-Team Backlog Tracking Weekly Report (" . $date . ")";
$mail->Body = "Hello,<br><br>See attached the weekly D-Team Backlog Report.<br><br>Thank you,";

//attach excel file
$mail->addAttachment($excel);

//send mail
if ($mail->send()) {
    echo "<h1>Email successfully sent.</h1>";
} else {
    //echo 'Mailer Error: ' . $mail->ErrorInfo;
}

//close smtp connection
$mail->smtpClose();

//close SQL connection
$mysqli->close();

//delete excel report
unlink($excel);
