<?php

/****************
 * 
 * This file is intended to be used for automatic confirmation for inventory adjustements.
 * 
 *****************/

//get access to session variables
session_start();

//get access to php html renderings
include('phpFunctions_html.php');

//get access to other php functions used throughout many applications
include('phpFunctions.php');

//load in database configuration
require_once 'config.php';

//include constants sheet
include('constants.php');

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "terminal"));

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

//init php var's to be passed to js
$part = "";
$shop = "";
$new = "";
$old = "";
$type = "";
$log_id = "";

//check for set info in header of URL
if (isset($_GET['part']))
    $part = $_GET['part'];
if (isset($_GET['shop']))
    $shop = $_GET['shop'];
if (isset($_GET['new']))
    $new = $_GET['new'];
if (isset($_GET['old']))
    $old = $_GET['old'];
if (isset($_GET['type']))
    $type = $_GET['type'];
if (isset($_GET['log_id']))
    $log_id = $_GET['log_id'];

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
<title>FST Adjustment Confirmation (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = [''];   //what will appear on the tabs
$header_ids = ['div1'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'></div>

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

    //pass from php to js
    var part = "<?= $part; ?>",
        shop = "<?= $shop; ?>",
        new_on_hand = "<?= $new; ?>",
        old_on_hand = "<?= $old; ?>",
        type = "<?= $type; ?>",
        sub_link = "<?= $sub_link; ?>",
        log_id = "<?= $log_id; ?>",
        user_info = <?= json_encode($fstUser); ?>;

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

    //handles approving part Adjustment
    function approve_part_adjustment(){

        //make sure type falls under categories allowed
        if (type != "Approved" && type != "Rejected"){
            alert("Error: Must be approval or rejection.");
            return;
        }

        //get shop substr (used to go to shop after running function)
        var shop_sub = shop.substr(0, shop.indexOf("-"));

        //init form data
		var fd = new FormData();

        //push any info required to form 
        fd.append('part', part);
        fd.append('shop', shop);
        fd.append('on_hand', old_on_hand);
        fd.append('new_on_hand', new_on_hand);
        fd.append('type', type);
        fd.append('log_id', log_id);

        //serialize any arrays and add to form
        fd.append('user_info', JSON.stringify(user_info));

        //add tell variable
        fd.append('tell', 'adjust_on_hand');
        fd.append('type', type);

        $.ajax({
            url: 'terminal_warehouse_helper.php',
            type: 'POST',
            processData: false,
            contentType: false,
            data: fd,
            success: function (response) {
                
                //check for error response
                var check = response.substr(0, 5);
                if (check == "Error"){
                    alert(response);
                    window.location = sub_link + "terminal_warehouse_main.php?shop=" + shop_sub;
                    return;
                }

                //alert users of successful changes
                alert("The inventory has been adjusted.");		
                window.location = sub_link + "terminal_warehouse_main.php?shop=" + shop_sub;
            }
        });
    }

    //windows onload
    window.onload = function () {

        //place any functions you would like to run on page load inside of here
        u.eid("defaultOpen").click();

        //run ajax to update part
        approve_part_adjustment();
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