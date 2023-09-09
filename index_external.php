<?php

/****************
 * 
 * THIS IS AN EXTERNAL FST LOG IN PAGE - THE INTENT IS FOR SUBCONTRACTRORS TO USE THIS PAGE TO RECEIVE ITEMS ON SITE
 * 
 * ALL INACTIVE SESSIONS FROM terminal_qr_receiving.php WILL BE RE-ROUTED TO THIS PAGE.
 * 
 *****************/

// Load in dependencies (including starting a session)
session_start();
include('phpFunctions_html.php');
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//check for return address, if not 1 present, throw error
if (!isset($_SESSION['returnAddress'])){
    header("Location: index.php");
}

// parse out sr_id and container from the return address
if (str_contains($_SESSION['returnAddress'], "terminal_qr_receiving")){
    $parse_url = parse_url($_SESSION['returnAddress'], PHP_URL_QUERY);
    parse_str($parse_url, $params);
    $sr_id = $params['sr_id'];      // will be used to validate PIN number
    $container = $params['container'];
    $fileName = basename($_SESSION['returnAddress']);
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 80px 20px;
    }

    /**hide certain nav items*/
    #open_side_nav_icon{
        display:none;
    }
    .tablink{
        opacity: 0;
        cursor: default !important; 
    }
    

    /**add style to main elements to make mobile friends */
    h1 {
        text-align: center;
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        margin-bottom: 5px;
        font-weight:bold;
    }
    
    input[type="text"],
    input[type="email"],
    input[type="number"] {
        width: 100%;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
        margin-bottom: 20px;
        box-sizing: border-box;
        font-size: 16px;
    }
    
    input[type="submit"] {
        width: 100%;
        background-color: #f0f0f0;
        color: black;
        border: solid 1px black;
        border-radius: 5px;
        padding: 15px 20px;
        cursor: pointer;
        font-size: 2rem !important;
        font-weight: bold;
    }

    /**adjust size of button */
    .mobile_button{
      width: 100%;
      margin-top: 1rem;
      font-size: 15px !important;
      font-weight: bold;
      height: 5rem;
      border-radius: 4px;
      border: none;
      color: black;
      border: 1px solid black;
      cursor: pointer
    }
    input[type="submit"]:hover, .mobile_button:hover {
      background-color: #ccc;
    }
    
    @media only screen and (max-width: 600px) {


    }

</style>
<!-- add any external style sheets here -->
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Log in Page (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['FST Sign-On'];           //what will appear on the tabs
$header_ids = ['div1'];             //must match a <div> element inside of body

//default $fstUser for index.php (otherwise pulled from fst_users db)
$fstUser = [];
$query = "DESCRIBE fst_users;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	$fstUser[$rows['Field']] = "";
}

// default accessLevel
$fstUser['accessLevel'] = "None";

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'>
    
    <div id = 'question_div'>
        <h2>Are you: </h2>
        <a href = 'index.php'><input type = 'button' class = 'mobile_button' value = 'Pierson Wireless Employee'></a>
        <input type = 'button' class = 'mobile_button' value = 'Other' onclick = 'show_next_option()'>
    </div>

    <div id = 'external_login' style = 'display:none;'>
        <form id = 'external_login_form'>
            <h1>Please fill in the following information: </h1>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required><br>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br>

            <label for="pin">PIN:</label>
            <input type="number" id="pin" name="pin" required><br>

            <input type="submit" class = 'mobile_button' value="Continue">
        </form> 
    </div>
</div>

<!-- external libraries used for particular functionallity (NOTE YOU MAKE NEED TO LOAD MORE EXTERNAL FILES FOR THINGS LIKE PDF RENDERINGS)-->
<!--load libraries used to make ajax calls-->
<!-- <script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script> -->

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>

<!-- jquery -->
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<!-- interally defined js files -->
<script src = "javascript/utils.js"></script>
<script type = "text/javascript" src="javascript/js_helper.js?<?= $version; ?>-1"></script>
<script src = "javascript/accounting.js"></script>

<script>

    // Set constant globals
    const actual_link = "<?= $actual_link; ?>";
    const returnAddress = '<?= $fileName; ?>';
    const sr_id = "<?= $sr_id; ?>";

    // Handles showing next set of options
    function show_next_option(){
        u.eid("question_div").style.display = "none";
        u.eid("external_login").style.display = "block";
    }

    /**
     * Handles form submission (validates PIN and redirects user to next page)
     * @author Alex Borchers
     */
    const form = document.getElementById('external_login_form');
    form.addEventListener('submit', function(event) {
        event.preventDefault();                             // prevent the default form submission behavior (redirect)

        // <form> will check for required fields and validate

        const formData = new FormData(form);                // get the form data
        formData.append('sr_id', sr_id);                    // append shipping request ID to form
        const xhr = new XMLHttpRequest();                   // create an AJAX request
        xhr.open('POST', 'index_external_helper.php');      // specify the URL to submit the form data to
        xhr.onload = function() {

            // check for error
            if (xhr.response == "Invalid Pin"){
                alert('[Error] The PIN is invalid.');
                return;
            }
            else if (xhr.response != ""){
                alert("[Error] There has been an issue. Response: " + xhr.response);
                console.log(xhr.response);
                return;   
            }

            console.log(returnAddress);
            debugger;

            // redirect to returnAddress
            window.location = returnAddress;

        };
        xhr.send(formData); // submit the form data
    });

    window.onload = function () {
        // functions to run after page load
    }

</script>

</body>
</html>

<?php
//perform any actions once page is entirely loaded

//reset return address once the page has loaded
//do not unset returnAddress here.. we will need it once again at our next redirect
//unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>