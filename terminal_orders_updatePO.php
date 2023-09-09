<?php

/****************
 * 
 * THIS FILE IS INTENDED TO BE A TEMPLATE FOR FUTURE USE
 * 
 * PLEASE UPDATE THIS SECTION WITH THE PURPOSE OF THE FILE
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

//get existing POs
$pos = [];
$query = "SELECT z.pq_id, b.quoteNumber, b.project_id as 'vp_id', c.googleDriveLink, CONCAT(c.location_name, ' ', c.phaseName) as 'project_name', count(d.id) as 'unassigned_items', a.* 
            FROM fst_pq_orders_assignments z
              LEFT JOIN fst_pq_orders a
                ON z.po_number = a.po_number
              LEFT JOIN fst_pq_overview b
                ON z.pq_id = b.id
              LEFT JOIN fst_grid c
                ON b.quoteNumber = c.quoteNumber
              LEFT JOIN fst_pq_detail d
                ON a.po_number = d.po_number AND (d.shipment_id is null or d.shipment_id = '')
              GROUP BY a.po_number, z.pq_id
              ORDER BY a.po_number ASC;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)){
  array_push($pos, $rows);
}

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
<title>TEMPLATE (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Update PO'];   //what will appear on the tabs
$header_ids = ['div1'];          //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'>

    <h3>Current PO # <input id = 'current_po'></h3>
    <h3>New PO # <input id = 'new_po'></h3>
    <button onclick = 'update_po()'>Update PO</button>

</div>

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

    //move pos to global from php
    const pos = <?= json_encode($pos); ?>;

    //handles updating PO #
    function update_po(){

        //get po#'s
        var curr_po = u.eid("current_po").value,
            new_po = u.eid("new_po").value;

        //check for match
        var index = pos.findIndex(object => {
          return object.po_number == new_po;
        });

        //check if we already have PO
        if (index != -1){

          var error_message = "Error: The new PO # that you entered is already assigned.\n\n";
          error_message += "Vendor: " + pos[index].vendor_name + "\n";
          error_message += "Project Name: " + pos[index].project_name + "\n";
          error_message += "Project ID: " + pos[index].vp_id + "\n";

          alert(error_message);
          return;
        }

        //initalize form data (will carry all form data over to server side)
        var fd = new FormData();
                    
        //add to form data
        fd.append('curr_po', curr_po);
        fd.append('new_po', new_po);

        //add type (PM / SM)
        fd.append('tell', 'temp_new_po');
                    
        //send info to ajax, set up handler for response
        $.ajax({
            url: 'terminal_orders_helper.php',
            type: 'POST',
            processData: false,
            contentType: false,
            data: fd,
            success: function (response) {
                
                //output error if we have a response
                if (response != ""){
                    alert (response);
                    return;
                }

                alert("This PO # has been successfully updated.");

            }	
        });
    }

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