<?php

/****************
 * 
 * THIS FILE IS INTENDED TO BE A TEMPLATE FOR FUTURE USE
 * 
 * PLEASE UPDATE THIS SECTION WITH THE PURPOSE OF THE FILE
 * 
 *****************/

// load in dependencies
session_start();
include('phpFunctions_html.php');
include('phpFunctions.php');
include('constants.php');

// load db configuration
require_once 'config.php';

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//init fstUser array
$fstUser = [];

//Make sure user has privileges
//check session variable first
if (isset($_SESSION['email'])){
	$query = "SELECT * from fst_users where email = '".$_SESSION['email']."';";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0){
		$fstUser = mysqli_fetch_array($result);
	}
	else{
		$fstUser['accessLevel'] = "None";
	}
}
else{
	$fstUser['accessLevel'] = "None";
	
}

//verify user
sessionCheck($fstUser['accessLevel']);

?>

<!DOCTYPE html>
<html>
<head>
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 70px 20px;
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>FST TEMPLATE (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Tab 1', 'Tab 2', 'Tab 3', 'Tab 4'];   //what will appear on the tabs
$header_ids = ['div1', 'div2', 'div3', 'div4'];                                                       //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "save_function()", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'>Show/Hide contents using $header_names[0]</div>
<div id = 'div2' class = 'tabcontent'>Show/Hide contents using $header_names[1]</div>
<div id = 'div3' class = 'tabcontent'>Show/Hide contents using $header_names[2]</div>
<div id = 'div4' class = 'tabcontent'>Show/Hide contents using $header_names[3]</div>

<!-- external libraries used for particular functionallity (NOTE YOU MAKE NEED TO LOAD MORE EXTERNAL FILES FOR THINGS LIKE PDF RENDERINGS)-->
<!--load libraries used to make ajax calls-->
<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

<!-- jquery -->
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<!-- interally defined js files -->
<script src = "javascript/utils.js"></script>
<script type = "text/javascript" src="javascript/js_helper.js?<?= $version; ?>-1"></script>
<script src = "javascript/accounting.js"></script>

<script>

    //handles tabs up top that toggle between divs
    function change_tabs (pageName, elmnt, color) {

      //check to see if clicking the same tab (do nothing if so)
      if (elmnt.style.backgroundColor == color)
        return;

      // Hide all elements with class="tabcontent" by default */
      var i, tabcontent, tablinks;
      tabcontent = u.class("tabcontent");
      for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
      }

      // Remove the background color of all tablinks/buttons
      tablinks = u.class("tablink");
      for (i = 0; i < tablinks.length; i++) {
        tablinks[i].style.backgroundColor = "";
      }

      // Show the specific tab content
      u.eid(pageName).style.display = "block";

      // Add the specific color to the button used to open the tab content
      elmnt.style.backgroundColor = color;		  
    }
    
    //handles saving any information (remove if not using)
    function save_function(){

      alert("Saving!");

    }

    //windows onload
		window.onload = function () {
      //place any functions you would like to run on page load inside of here
      u.eid("defaultOpen").click();
		}

</script>

</body>
</html>

<?php
//perform any actions once page is entirely loaded

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>