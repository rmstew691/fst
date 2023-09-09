<?php
session_start();
include("config.php");
//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

// include php built functions
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
$query = "SELECT * FROM fst_users where email = '" . $_SESSION['email'] . "'";
$result = $mysqli->query($query);

if ($result->num_rows > 0) {
    $fstUser = mysqli_fetch_array($result);
} else {
    $fstUser['accessLevel'] = "None";
}

sessionCheck($fstUser['accessLevel']);

if ($fstUser['accessLevel'] != "Admin") {
    header("Location: home.php");
}

//reset return address once we pass
unset($_SESSION['returnAddress']);

//load designer, market, solution types for dropdown (queries are ran later in the file)
$designerQ = "SElECT * FROM general_designer";
$marketQ = "SElECT * FROM general_market";
$typeQ = "SElECT * FROM general_type";
$typeQ = "SElECT * FROM general_subtype";
$intCalcsQ = "SELECT * FROM fst_intcalcs";
$laborRateQ = "SELECT * FROM fst_laborrates";
$contractMUQ = "SELECT * FROM fst_contractmu";
$remoteCheckQ = "SELECT * FROM analytics_remotecheck";
$usersQ = "SELECT * FROM fst_users WHERE status = 'Active' ORDER BY firstName, lastName";
$invStatePrefQ = "SELECT * FROM inv_statepref";
$invWEprefQ = "SELECT * FROM inv_wepref";

//load drop down menus
$approvalTiersQ = "SELECT tier from general_approvals";
$accessLevelQ = "SELECT accessLevel from general_accesslevels ORDER BY general_accesslevels.order";

$result2 = mysqli_query($con, $accessLevelQ);

$accessLevels = [];
$tierLevels = [];

//read into array
while ($rows2 = mysqli_fetch_assoc($result2)) {
    array_push($accessLevels, $rows2['accessLevel']);
}

$result2 = mysqli_query($con, $approvalTiersQ);

//read into array
while ($rows2 = mysqli_fetch_assoc($result2)) {
    array_push($tierLevels, $rows2['tier']);
}


//grab clarifications and their init values to show the user (these can be edited)
$clar_id = [];
$clar = [];

$query = 'SELECT * FROM fst_clarifications';
$result = mysqli_query($con, $query);

//read into array
while ($rows = mysqli_fetch_assoc($result)) {
    array_push($clar_id, $rows['id']);
    array_push($clar, $rows['clarification']);
}

//init values
$clar_init_id = [];
$clar_init_type = [];
$clar_init_tell = [];

$query = 'SELECT * FROM fst_clarifications_init';
$result = mysqli_query($con, $query);

//read into array
while ($rows = mysqli_fetch_assoc($result)) {
    array_push($clar_init_id, $rows['clar_id']);
    array_push($clar_init_type, $rows['type']);
    array_push($clar_init_tell, 'on');
}

//groupings
$clar_groups = [];


//init first line with general
$clar_subgroups = ['General'];
$clar_subgroups_key = ['General'];

//look through existing class names
$query = 'select * from fst_clarifications_class';
$result = mysqli_query($con, $query);

//read into array
while ($rows = mysqli_fetch_assoc($result)) {
    array_push($clar_subgroups, $rows['class']);
    array_push($clar_subgroups_key, "Classification");
}



$query = 'select * from fst_clarifications_groups order by group_order';
$result = mysqli_query($con, $query);

//read into array
while ($rows = mysqli_fetch_assoc($result)) {
    array_push($clar_groups, $rows['group']);
}

$query = 'select * from general_subtype';
$result = mysqli_query($con, $query);

$curr_type = "";

//read into array
while ($rows = mysqli_fetch_assoc($result)) {

    //if we find a change, create an "All [TYPE] column"
    if ($curr_type != $rows['projectType']) {

        if ($rows['projectType'] == "Private LTE") {
            array_push($clar_subgroups, "All (Other)");
            array_push($clar_subgroups_key, "Other");
            array_push($clar_subgroups, "All (Outdoor Coverage)");
            array_push($clar_subgroups_key, "Outdoor Coverage");
        }

        $curr_type = $rows['projectType'];
        array_push($clar_subgroups, "All (" . $rows['projectType'] . ")");
        array_push($clar_subgroups_key, $rows['projectType']);
    }

    array_push($clar_subgroups, $rows['subType']);
    array_push($clar_subgroups_key, $rows['projectType']);
}

$query = 'SELECT * FROM fst_users_roles;';
$result = mysqli_query($con, $query);

$role = [];

while ($rows = mysqli_fetch_assoc($result)) {
    array_push($role, $rows['role']);
}

$query = $usersQ;
$result = mysqli_query($con, $query);

$users = [];

while ($rows = mysqli_fetch_assoc($result)) {
    array_push($users, $rows['firstName'] . " " . $rows['lastName']);
}


//grab attachments taken from 
$name_locations = [];

$query = "select * from fst_locations order by description";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

    //push to array
    array_push($name_locations, $rows);
}

//grab attachments taken from 
$name_customers = [];

$query = 'SELECT * from fst_customers order by customer';
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

    //push to array
    array_push($name_customers, $rows);
}

?>


<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="shortcut icon" type="image/x-icon" href="images/PW_P Logo.png" />

    <meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
    <link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    <link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
    <link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
    <link rel="stylesheet" href="stylesheets/rt_bda_kits.css?<?= $version; ?>">







    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>

    <style>
        /**styles search divs */
        .search_div {
            padding: 1em;
            float: left;
        }

        .search_h4 {
            margin-bottom: 10px;
        }

        .search_table input[type=checkbox] {
            -ms-transform: scale(1.2);
            -webkit-transform: scale(1.2);
            transform: scale(1.2);
        }

        /**style note next to download button */
        #download_note {
            font-weight: normal;
            font-size: 16px;
            padding-left: 1em;
        }

        /**style autocomplete used in work order parts request menu */
        .ui-autocomplete {
            position: absolute;
            cursor: default;
            z-index: 4000 !important
        }

        .ui-autocomplete {
            max-height: 300px;
            overflow-y: auto;
            /* prevent horizontal scrollbar */
            overflow-x: hidden;
        }

        /**style stock class (used when selecting & requesting parts) */
        .stock {
            text-align: center;
            font-weight: bold;
        }

        /*set width based on group_class*/
        .all_class tbody {
            width: 2160px;
            height: 750px;
        }

        /** updating padding of profile tab */
        #profile {
            padding-top: 4em;
        }

        /**styles added to profile settings table on profile tab */
        #profileTable {
            border-collapse: collapse;
        }

        #profileTable td {
            border: 1px solid #000000;
            padding: 5px;
        }

        #profileTable th {

            padding: 5px;
        }

        /* style element in new part dialog box*/
        .newPart_input {
            width: 400px;
        }

        .newPart_th {
            text-align: left;
        }

        .new_project_button {
            font-size: 20px;
            width: 10em;
            height: 2em;
            text-align: center;
        }

        /** style widths of columns & input fields */
        .col_quote_num {
            width: 150px;
        }

        .col_value {
            width: 120px;
            display: <?= $deployHide ?>;
        }

        .input_value {
            display: <?= $deployHide ?>;
        }

        /**shows elements that hide for deployment */
        .hide_for_deployment {
            display: <?= $deployHide ?>;
        }

        .col_project_name {
            width: 360px;
        }

        .col_vp_num {
            width: 120px;
        }

        .col_vp_contract {
            width: 120px;
        }

        .col_designer {
            width: 140px;
        }

        .col_quote_creator {
            width: 141px;
        }

        .col_project_type {
            width: 230px;
        }

        .col_state {
            width: 60px;
        }

        .col_market {
            width: 100px;
        }

        .col_customer {
            width: 150px;
        }

        .col_quote_status {
            width: 150px;
        }

        .col_last_update {
            width: 185px;
        }

        #partsTable {
            width: 80%;
            border-collapse: separate;
            font-family: sans-serif;

        }

        #bda_kit {
            width: 60%;
            border-collapse: separate;

        }

        #msg {
            margin-left: 10%;
            padding-top: 10px;
        }

        label {
            font-family: sans-serif;
            font-size: 1rem;
            padding-right: 10px;
        }

        td {
            padding: 6px;
            border: 1px solid #000000;
        }
    </style>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="shortcut icon" type="image/x-icon" href="images/PW_P Logo.png" />

    <link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
    <link rel="stylesheet" href="stylesheets/rt_bda_kits.css?<?= $version; ?>">

</head>

<body>

    <?php

    //define array of names & Id's to generate headers
    $header_names = ['User Settings', 'Clarifications', 'Default Settings', 'Locations', 'Customers'];
    $header_ids = ['users', 'clarifications', 'default', 'locations', 'Customers'];

    //pass to php function to create navigation bars
    echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

    ?>

    <h1 style='padding-top:2em; padding-left: 0.6em;'>Step 3: Select BDA Kit </h1>

    <!-- <h1 style='padding-top:2em; padding-left: 0.6em;'><?= $_SESSION['firstName'] ?> <?= $_SESSION['lastName'] ?> Step 3: Select BDA Kit </h1> -->

    <div id='users' class='tabcontent'>
        <br>
        <table id="partsTable" style="border-collapse: collapse; font-family: sans-serif">

            <tr>

                <select id="bda_kit-select" style="margin-left: 10%; width:7%" class="custom-select">
                    <option value="0">Select Value</option>
                    <option value="1">ADRF SDR (24dBm / .25W) </option>
                    <option value="2">ADRF SDR (30dBm / 1W)</option>
                    <option value="3">ADRF SDR (33dBm / 2W)</option>
                    <option value="4">ADRF SDR (43dBm / 20W)</option>
                    <option value="5">Commscope NodeA (27dBm / .5W)</option>
                    <option value="6">Commscope NodeA (37dBm / 5W)</option>
                    <option value="7">JMA Teko</option>
                    <option value="8">SOLiD Alliance</option>
                    <option value="9">ADRF ADXV</option>
                    <option value="10">2W - JMA</option>
                    <option value="11">10W - JMA</option>
                    <option value="12">20W - JMA</option>
                    <option value="13">30W - JMA</option>
                    <option value="14">BLANK</option>
                    <option value="15">5W - SOLiD (4 Bands)</option>
                    <option value="16">20W - SOLiD (5 Bands)</option>
                    <option value="17">2W - ADRF (2 Bands)</option>
                    <option value="18">5W - ADRF (2 Bands)</option>
                    <option value="19">20W - ADRF (4 Bands)</option>
                </select>
            </tr>
            <tr>
                <select id="pMarkup" class="custom-select">
                    <option value="">Select Value</option>
                    <option value="1">5%</option>
                    <option value="2">10%</option>
                    <option value="3">20%</option>
                    <option value="4">30%</option>
                    <option value="5">40%</option>
                    <option value="6">50%</option>
                    <option value="7">60%</option>
                    <option value="8">70%</option>
                    <option value="9">80%</option>
                </select>
            <tr>
            <tr>
                <select id="aMarkup" class="custom-select">
                    <option value="-1">Select Value</option>
                    <option value="1">5%</option>
                    <option value="2">10%</option>
                    <option value="3">20%</option>
                    <option value="4">30%</option>
                    <option value="5">40%</option>
                    <option value="6">50%</option>
                    <option value="7">60%</option>
                    <option value="8">70%</option>
                    <option value="9">80%</option>
                </select>
            <tr>
                <thead>
                    <tr>

                        <th width="3%">ID</th>
                        <th width="3%">Quantity</th>
                        <th width="20%">Part Description</th>
                        <th width="10%">Description</th>
                        <th width="5%">Cost</th>
                        <th width="5%" class="calculation" title="Total Cost = Cost x Quantity">Total Cost</th>
                        <th width="5%" class="calculation" title="Price = Total Cost x P-Markup">Price</th>
                        <th width="15%">Part Number</th>
                    </tr>
                </thead>

            </tr>
            <button onclick="populateTable()">Reset Table</button>
            <button onclick="updateTable()">Update</button>

        </table>
        <div id="msg"></div>
        <br>
        <div>
            <input type="text" id="bda_kit-input" name="search" placeholder="Search BDA Kit" style="margin-left: 10%">
        </div>

        <table id="bda_kit" border="2" style="border-collapse: collapse">
            <thead>
                <tr>
                    <th width="3%">ID</th>
                    <th width="20%">Part Description</th>
                    <th width="10%">Part Number</th>
                    <th width="5%">Cost</th>
                    <!-- <th width="10%">Element Type</th>
                <th width="10%">Bda Number</th> -->
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>
        <br>
        <div>
            <table id="bda_kit_search">
        </div>

        <br>


















        <script src="romBdaSelectionTable.js"></script>
        <!-- <script src="rt_updatedTable.js"></script> -->
        <script src="bdaKitTable.js"></script>






</body>

</html>