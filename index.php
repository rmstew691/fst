<?php

/****************
 * 
 * THIS IS THE FST LOG IN PAGE (index.php is the file rendered when there is no file in the URL ex. (localhost/FST = localhost/FST/index.php)
 * 
 * ALL INACTIVE SESSIONS WILL BE RE-ROUTED TO THIS PAGE. 
 * THIS PAGE USES GOOGLE-API TO RENDER SIGN-ON BUTTON AND GOOGLE SECURITY TO VALIDATE USER IS SIGNING ON WITH piersonwireless.com DOMAIN
 * USER INFORMATION IS VALIDATED AND $_SESSION INFORMATION IS STORED IN userData.php
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

$check = 0;
$check = strpos($actual_link, "index.php");

if ($check > 0){
	$actual_link = substr($actual_link, 0, $check);
}

//init return
$return = "";

if (isset($_GET['return']))
	$return = $_GET['return'];
elseif(isset($_SESSION['returnAddress']))
    $return = $_SESSION['returnAddress'];
else
	$return = "";

?>

<!DOCTYPE html>
<html>
<head>
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 40px 20px;
      margin: 0 auto;
    }

    /**hide certain nav items*/
    #open_side_nav_icon{
        display:none;
    }
    .tablink{
        opacity: 0;
        cursor: default !important; 
    }

    #buttonDiv{
        margin-left: 1em;
        margin-top: 2em;
        width:288px;
    }

    @media screen and (max-width: 481px) {
      /* Styles for screens smaller than 768px */
      #buttonDiv {
        margin-top: 1.6em;
        margin-left: 0em;
      }      
    }
    @media screen and (max-width: 400px) {
      /* Styles for screens smaller than 768px */
      #buttonDiv {
        margin-top: 1.6em;
        margin-left: 0em;
      }      
    }

</style>
<!-- add any external style sheets here -->
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    
    <div id="buttonDiv"></div> 

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

    var desc = 0;
    var actual_link = "<?= $actual_link; ?>";
    var returnAddress = '<?= $return; ?>'; 

    //handles response from google sign on
    function handleCredentialResponse(response) {
        
        //use JSON to parse JWT and get rid of bad characters
        const responsePayload = parseJwt(response.credential);

        console.log("ID: " + responsePayload.sub);
        console.log('Full Name: ' + responsePayload.name);
        console.log('Given Name: ' + responsePayload.given_name);
        console.log('Family Name: ' + responsePayload.family_name);
        console.log("Image URL: " + responsePayload.picture);
        console.log("Email: " + responsePayload.email);

        saveUserData(responsePayload);
    }

    function parseJwt (token) {
        var base64Url = token.split('.')[1];
        var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));

        return JSON.parse(jsonPayload);
    };

    // Save user data to the database
    function saveUserData(userData){

        var email = userData.email, 
            name = userData.name, 
            authID = userData.sub, 
            imageURL = userData.picture,
            timezone_offset_minutes = new Date().getTimezoneOffset();

            //https://usefulangle.com/post/31/detect-user-browser-timezone-name-in-php
            //It should be noted that getTimezoneOffset returns an offset which is positive if the local timezone is behind UTC and negative if it is ahead. So we must add an opposite sign (+ or -) to the offset.
            timezone_offset_minutes = timezone_offset_minutes == 0 ? 0 : -timezone_offset_minutes;
            timezone_offset_minutes += 60;  //adjust by 1 hour

        $.ajax({
            type : "POST",  //type of method
                    url  : "userData.php",  //your page
                    data : { nameVal : name, emailVal : email, picVal : imageURL, authVal : authID, timezone_offset_minutes : timezone_offset_minutes},// passing the values
            success : function (response) {

                //response will be an object encoded, we need to convert to js object
                // response['message'] will hold which avenue we take
                // response['dashboard'] will hold where the user needs to go (check returnAddress, only use dashboard if returnAddress = home.php)
                response = $.parseJSON(response);

                //message used during maintenace (just uncomment during upload)
                //alert("FST is currently under scheduled maintenace. Please contact fst@piersonwireless.com if this is urgent.");
                //return;

                if (response['message'] == "success"){

                    //if return address == "" use dashboard link
                    if (returnAddress == "")
                        window.location.replace(actual_link + response['dashboard']);
                    else
                        window.location.replace(returnAddress);

                }
                
                else if(response['message'] == "newUser"){
                    alert("Welcome new user. Your account has not been set up yet and you currently do not have access to this application. Please contact fst@piersonwireless.com to request access.");
                }
                
                else if(response['message'] == "none"){
                    alert("Sorry, you do not have access to this application. Please contact fst@piersonwireless.com to request access.");
                }
                
                else{
                    alert("There may have been an error: " + response['message']);
                }
            }
        });
    }

    window.onload = function () {
        google.accounts.id.initialize({
        client_id: "573761357198-hin7ae7q19qgvoab7t0781b41530546g",
        callback: handleCredentialResponse
        });
        google.accounts.id.renderButton(
        document.getElementById("buttonDiv"),
        { theme: "filled_blue", size: "large" }  // customization attributes https://developers.google.com/identity/gsi/web/reference/js-reference
        );
        google.accounts.id.prompt(); // also display the One Tap dialog

        //disable notification drop-down
        //u.eid("notification_button").remove();
    }

</script>

</body>
</html>

<?php
//perform any actions once page is entirely loaded

//reset return address once the page has loaded
//unset($_SESSION['returnAddress']);
$_SESSION['last_action'] = 0;

//close SQL connection
$mysqli -> close();

?>