<?php

// Load in dependencies (including starting a session)
session_start();
include('phpFunctions.php');
include('phpFunctions_views.php');
include('phpFunctions_html.php');
include('constants.php');
include('PHPClasses/User.php');

// Load the database configuration file
require_once 'config.php';

//default time_zone to stored zone in session
if (isset($_SESSION['timezone']))
    date_default_timezone_set($_SESSION['timezone']);

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link & file name
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));
$filename = substr($actual_link, strpos($actual_link, "home"), strpos($actual_link, ".php") - strpos($actual_link, "home") + 4);

// Make sure user has privileges
// If employeeID is set in session, create new instance of user
if (isset($_SESSION['employeeID']))
    $user = new User($_SESSION['employeeID'], $con);
else
    $user = new User(-1, $con);

// Check session (phpFunctions.php)
sessionCheck($user->info['accessLevel']);

// Check if desktop override requested
$desktop_opt = "";
if (isset($_GET['desktop']))
    $desktop_opt = "on";

// redirect to home page if user does not have access
if ($user->info['role'] != "Ops" && $user->info['accessLevel'] != "Admin")
    header('Location: home.php');

//if admin, display admin button
$admin = "none";

if ($user->info['accessLevel'] == "Admin") {
    $admin = "";
}

//if user is deployment, hide $ values
$deployHide = "";
if ($user->info['accessLevel'] == "Deployment") {
    $deployHide = "none";
}

//if deployment, can only search through fst's, cannot create a new one
$protect_header = "";

if ($user->info['accessLevel'] == "Deployment") {
    $protect_header = "disabled";
}

//send user error message if one exists
if (isset($_SESSION['errorMessage']) && $_SESSION['errorMessage'] !== "")
    $session_error = $_SESSION['errorMessage'];
else
    $session_error = "";

//reset error message
unset($_SESSION['errorMessage']);

// call custom function to get operations needed info
$ops_views = get_operations_views($con, $user);



//init objects/arrays
$ops_tasks = [];
$query = "SELECT * FROM general_fst_status ORDER BY priority;";
//$query = "SELECT task as 'status', code, dashboard FROM general_ops_task ORDER BY priority;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
    array_push($ops_tasks, $rows);
}

?>


<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
    <link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
    <link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>">
    <link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
    <link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    <title>Ops Dashboard (v<?= $version ?>) - Pierson Wireless</title>

    <style>
        /**center notification checkboxes */
        .notify_cell {
            text-align: center;
        }

        /**styles related to legend */
        .legendTable td {
            font-weight: normal !important;
        }

        .tooltip:hover {
            cursor: help;
            position: relative;
        }

        .tooltip table {
            display: none;
        }

        .tooltip:hover table {
            display: block;
            z-index: 150;
            left: 0px;
            margin: 13px;
            margin-left: 75px;
            width: fit-content;
            position: absolute;
            top: 0px;
            font-weight: lighter;
            text-decoration: none;
            padding: 20px 20px;
            background: #eeeeee;
            color: black;
            border-radius: 20px;
            font: bold 15px "Helvetica Neue", Sans-Serif;
            box-shadow: 0px 0px 3px 0px black;
            width: 41em;
        }

        .related_job_header {
            font-style: italic;
            padding-top: 10px;
            font-weight: bold;
        }

        .homeTables {
            height: 10em;
        }

        /**style ship request tiny headers */
        .SR_tiny_header {
            font-weight: bold;
            padding-top: 5px;
        }

        /**float attachments so they are stacked */
        .upload_attachment {
            float: left;
        }

        /**style particular cell for fse request table */
        .fse_critical_content {
            width: 0em;
            font-weight: bold;
            background-color: yellow;
        }

        /**style shipping_request_container (mini table in expandable containers) */
        .shipping_request_container {
            margin: 2em;
        }

        .ship_request_head {
            border: 0;
            font-weight: bold;
            text-align: center;
        }

        /**set style for header labels in upload site survey dialog*/
        .dialog_header {
            font-weight: bold;
            padding: 6px 11px 6px 0px;
        }

        /**format TD info in material_breakdown_table */
        #material_breakdown_table td {
            /* float: left; */
            padding: 8px;
        }

        /**add a few styles to the trash-icon */
        .fa-trash-span {
            font-size: 20px;
            padding-bottom: 4px;
            padding-right: 4.5px;
            cursor: pointer;
        }

        /**set stick-header2 as search bars (stick to top on scroll) */
        .sticky-header2 {
            position: sticky;
            top: 19px;
            z-index: 100;
            background: #eeeeee;
        }

        /**define width styles for columns and inputs (need to be specific for each with how the tables are formatted) */
        /**col_ is given to a <td> element & input_ is given to an <input> element */
        .col_expand_button {
            width: 2em;
        }

        .col_quote_num,
        .col_market,
        .col_service_request,
        .col_fst_status,
        .col_cop_task,
        .col_invoice_complete,
        .col_assign_job,
        .col_request_reason,
        .col_status,
        .col_quoteStatus,
        .col_sub_date {
            width: 150px;
        }

        .col_staged_percent {
            width: 150px;
            cursor: pointer;
        }

        .col_project_name {
            width: 360px;
        }

        .col_opsLead {
            width: 140px;
        }

        .col_customer {
            width: 150px;
        }

        .col_parts_request {
            width: 200px;
        }

        .col_notes,
        .col_sow {
            width: 390px;
        }

        tbody .col_notes,
        tbody .col_sow {
            cursor: pointer;
        }

        /**style search inputs */
        .inputs {
            width: 100% !important;
        }

        input[type=checkbox] {

            text-align: center;
        }

        /**style inner page tabs */
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
        }

        /* Style the buttons that are used to open the tab content */
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            color: black;
            font-weight: bold;
        }

        /* Change background color of buttons on hover */
        .tab button:hover {
            background-color: #ddd;
        }

        /* Create an active/current tablink class */
        .tab button.active {
            background-color: #ccc;
        }

        /* Style the tab content */
        .inner_tabcontent {
            display: none;
        }

        .stored-field {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            width: 78.8%;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }

        body {
            margin: 5px;
        }

        .rt_checks {
            text-align: center;
        }

        input[type=text] {
            width: 195px;
        }

        #home {
            padding-top: 3em;
            padding-bottom: 100px;
        }
    </style>
</head>

<body>

    <?php

    //define array of names & Id's to generate headers
    $header_names = ['Dashboard'];
    $header_ids = ['home'];

    //default save_function 
    $save_function = "";

    //pass to php function to create navigation bars
    echo create_navigation_bar($header_names, $header_ids, "save_changes(false, true)", $user->info);



    ?>

    <div id='home' class='tabcontent'>

        <h1> Step 2: Building Information and Solution Configuration - <?= $_SESSION['firstName'] ?> <?= $_SESSION['lastName'] ?> </h1>

        <?php

        ?>

        <!-- start of ROM CDN's , Site Information Table , and checkbox's -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css">
        <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
        <h2>Site Information</h2>

        <table id='rt_siteInformation'>
            <!-- inserted by serverData -->
            <form action="romBdaDropdowns.php" method="POST" id="rt_fields">
                <table>
                    <tbody>
                        <tr>
                            <td>
                                <label for="rt_date">Date</label>
                            </td>

                            <td>
                                <input style="width: 195px" type="date" id="rt_date" name="rt_date" value="<?php echo date("Y-m-d")  ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="customer">Customer Name</label>
                            </td>
                            <td>
                                <input type="text" id="customer" name="customer">
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <label for="address">Project Address</label>
                            </td>
                            <td>
                                <input type="text" id="address" name="address">
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <label for="city">Project City</label>
                            </td>
                            <td>
                                <input type="text" id="city" name="city">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="state">Project State</label>
                            </td>
                            <td>
                                <input type="text" id="state" name="state">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="zip">Project Zip</label>
                            </td>
                            <td>
                                <input type="text" id="zip" name="zip">
                            </td>
                        </tr>
                        <?php

                        ?>
                        <tr>
                        <tr>
                            <td>
                                <label for="rt_pocname">POC Name</label>
                            </td>
                            <td>
                                <input type="text" id="rt_pocname" name="rt_pocname" class="inputs">
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <label for="rt_pocnumber">Location Number</label>
                            </td>
                            <td>
                                <input type="text" id="rt_pocnumber" name="rt_pocnumber" class="inputs">
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <label for="rt_sqft">Total Building SQ FT</label>
                            </td>
                            <td>
                                <input type="text" id="rt_sqft" name="rt_sqft" class="inputs">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="rt_floorNum">Number of Floors</label>
                            </td>
                            <td>
                                <input type="text" id="rt_floorNum" name="rt_floorNum" class="inputs">
                            </td>
                        </tr>
                        <tr>
                        <tr>
                        <tr>
                            <td>
                                <label for="facility_type">Facility Type</label>
                            </td>
                            <td>
                                <select id="facility_type">
                                    <option value="">Facility Type</option>
                                    <option value="1">Office Building</option>
                                    <option value="2">Hospital</option>
                                    <option value="3">Airport</option>
                                    <option value="3">Stadium/Arena</option>
                                    <option value="3">Education Building</option>
                                    <option value="3">Harden Facility/Data Center</option>
                                    <option value="3">Manufactoring / Warehouse</option>
                                    <option value="3">Outdoor</option>

                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="building_density">Building Density</label>
                            </td>
                            <td>
                                <select id="building_density">
                                    <option value="">Building Density</option>
                                    <option value="1">Low</option>
                                    <option value="2">Medium</option>
                                    <option value="3">Dense</option>

                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>


                <br>
                <div class="container" style="height: 50px;">

                </div>

                <table id="checkboxes" border="1" style="border-collapse: collapse; width: 80%">

                    <th colspan="7">
                        <h1>Commercial Frequencies (Remember Priority Bands vs Non-Priority Bands)</h1>
                    </th>

                    <tr>
                        <th>
                            <h3>AT&T</h3>
                        </th>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_700MHz"> 700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_850MHz"> 850 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_1900MHz"> 1900 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_2100MHz"> 2100 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_2300MHz"> 2300 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="a_3700MHz"> 3700 MHz<br>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <h3>T-Mobile</h3>
                        </th>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_600MHz"> 600 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_700MHz"> 700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_1900MHz"> 1900 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_2100MHz"> 2100 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_2500MHz"> 2500 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="t_disabled" disabled> <br>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <h3>Verizon</h3>
                        </th>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_700MHz"> 700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_850MHz"> 850 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_1900MHz"> 1900 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_2100MHz"> 2100 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_3700MHz"> 3700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="v_disabled" disabled> <br>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <h3>U.S. Cellular</h3>
                        </th>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_600MHz"> 600 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_700MHz"> 700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_850MHz"> 850 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_1900MHz"> 1900 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_2100MHz">2100 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="u_3700MHz"> 3700 MHz<br>
                        </td>

                    </tr>
                    <tr>
                        <th>
                            <h3>Dish</h3>
                        </th>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_600"> 600 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_700"> 700 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_800"> 800 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_2100"> 2100 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_3500"> 3500 MHz<br>
                        </td>
                        <td>
                            <input type="checkbox" name="frequencyChecks" value="d_disabled" disabled> <br>
                        </td>
                    </tr>

                </table>
                <div class="widget" style="padding-top: 15px">

                    <!-- saves the checkboxes but doesnt move to next page -->
                    <button type="button" id="saveButton">Save and Exit</button>


                    <!-- Saves current state of checkboxes, inputs and moves to next page -->

                    <button onclick='window.location.href = "romBdaDropdowns.php"'>Save and Continue</button>

                    <!-- Clear the form values -->
                    <button id="clearButton" type="reset">Reset Form</button>



                </div>

            </form>




            <!-- Navigation only . not save or submit -->
            <button onclick='window.location.href = "home_asd.php"'>Previous Page</button>
            <!-- <button onclick='window.location.href = "romBdaSelectionTable.php"'>Next Page</button> -->
            <button onclick='window.location.href = "romBdaDropdowns.php"'>Next Page</button>


            <script src="clearForm.js"></script>



            <!-- Use for inserting Bool / INT values into DB -->
            <!-- <script src="rt_insertChecksStoredProc.js"></script> -->

            <!-- Used to insert value as a varchar/string -->
            <script src="rt_checkboxes.js"></script>
            <script src="insert.js"></script>
            <!-- <script src="romInsertNewRomValues.js"></script> -->

            <!-- <script src="rt_page1.js"></script> Validation Scripts -->


</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>

</html>