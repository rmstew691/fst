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


if (isset($_GET['kit'])){
	$kit_id = $_GET["kit"];

	//check if kit id exists in fst_boms_kits
	//$query = "select * from fst_bom_kits WHERE kit_part_id = 'pwk-example1';"; => represents example of query below
	$query = "select * from fst_bom_kits WHERE kit_part_id = '" . $kit_id . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) == 0)
		header("Location: home.php");	//replace home.php with destination like kit_admin.php or kitCreation.php
	//if we find a match, search for parts within this kit
	else{

		$query = "select * from fst_bom_kits_detail WHERE kit_id = '" . $kit_id . "';";
		$result = mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){
			//store an array of objects
			//$kit_parts[0] => {kit_id : pwk-example1, partNumber : 12/WP, quantity: 100}...
			array_push($kit_parts, $rows);
		}
	}
}

//initialize array to hold parts
$parts = [];

//write query to grab parts from database
$query = "SELECT partNumber FROM invreport WHERE active = 'True';";

//execute query
$result = mysqli_query($con, $query);

//loop through results and push to array
while($rows = mysqli_fetch_assoc($result)){
	array_push($parts, $rows['partNumber']);
	//EXAMPLE of array push
	//$parts = [];
	//array_push($parts, 'test part')
	//$parts = ['test part']
	//array_push($parts, 'next part')
	//$parts = ['test part', 'next part']
}

//print results
//print_r($parts);

?>

<!DOCTYPE html>
<html>
<head>
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 70px 20px;
    }

    /**gets rid of borders in td of table */
    .no_borders td{
      border: none !important;
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Kit Administration (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Kits'];   //what will appear on the tabs
$header_ids = ['div1'];                                                       //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'>

<h1 style="color:Black;">
	Kit Administration
    </h1>

<button id="KitBtn" class = 'large_button'>Create New Kit</button>

		<form action="materialEntry.php" method="get">  <!-- post stores everything where user can't see -- get shows all info in url -->

			<table class = "standardTables" style = 'margin-top: 2em;'>
					<tr>
						<th> Kit_id </th> 
						<th> Description </th>
						<th> Created By </th>
						<th> Last Updated </th>


					</tr>

      <?php
        $query = "select * from fst_bom_kits";
        $result = mysqli_query($con, $query);
        while($rows = mysqli_fetch_assoc($result)){ 
          
          //convert last_update to local (https://stackoverflow.com/questions/3792066/convert-utc-dates-to-local-time-in-php)
          if ($rows['last_update'] == "0000-00-00 00:00:00" || $rows['last_update'] == null){
            $local = "";
          }
          else{
            $local = new DateTime($rows['last_update'], new DateTimeZone('UTC'));
            //$loc = ;
            $local->setTimezone((new DateTime)->getTimezone());
            $local = $local->format('n-j-Y') . " at " . $local->format('h:i A');
          }
          
      ?>
        <tr>
						<!-- <td> <input name="kit" value=""  type="submit"> </td>  -->
            <td><a href = 'materialEntry.php?kit=<?= $rows['kit_part_id'];?>' target='_blank'><?= $rows['kit_part_id'];?></a></td>
						<td> <input value="<?= $rows['kit_description'];?>" type="text" readonly> </td>
						<td> <input value="<?= $rows['who_created'];?>" readonly > </td>
						<td> <input value="<?= $local;?>" readonly> </td>


					</tr>
				<?php
				
			}	
		?>

			</table>

			
		</form>

		<div 
      style="display:none"
      id="kit-dialog"
      title="Enter New Kit Information"
    >
			<table class = "standardTables no_borders" style = 'margin-top:2em;'>
					<tr>
						<th> Part Number <i>(Max: 30 characters)</i></th> 
						<td><input type = "text" id="part_no" value = 'PWK-' onchange = "pwk()" maxlength="30" style = 'width: 18em;'></td>
					</tr>
          <tr>
            <th> Description </th>
            <td><textarea id="descr" style = 'width: 18em;'></textarea></td>
          </tr>
		  </table>
      <button onclick = 'create_kit()'>Create Kit</button> 
		</div>
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

    //pass php arrays to javascript
    var parts = <?= json_encode($parts); ?>;
                    
      //used to create new kit
    $("#KitBtn").on('click', function(){
      $( "#kit-dialog" ).dialog({
        width: "auto",
        height: "auto",
        dialogClass: "fixedDialog",
      })
    });
    

    //handles checking to make sure that part # is formatted correctly and not in our inventory
    function pwk(){

      //get user entered part # (only first 4 characters)
      var full_part = document.getElementById("part_no").value;
      first_four = full_part.substring(0,4)

      //check to see if user has entered any info
      if (full_part == "PWK-"){
        //do something
        alert("Please enter something more than 'PWK-'.");
        return true;
      }

      //create conditional statement to check first 4 characters
      if (first_four !== "PWK-"){
        //do something
        alert("All part numbers should begin with 'PWK-'. Please include 'PWK-' at the start of your part number id.");
        return true;
      }

      //check if part number is unique in catalog (parts array)
      //option 1
      for (var i = 0; i < parts.length; i++){

        //why do we want to move each to lower case?
        //javascript does not recognize 'Hello' and 'hello' as the same thing.
        if (full_part.toLowerCase() == parts[i].toLowerCase()){
          alert("This part number exists in our catalog, please use a different kit id.");
          return true;
        }
      }

      /* option 2
      if (parts.includes(full_part)){
        //do something
        alert("This part number exists in our catalog, please use a different kit id.");
        return true;
      }
      */

      //if we pass all checks, return false (no error)
      return false;

    }

    //handles creating a kit
    function create_kit(){

      //run error checks
      if (pwk())
        return;	//kill function

      //also check part description
      if (u.eid("descr").value == ""){
        alert ("Please enter a kit description.");
        return;
      }

      //grab kit & description
      var kit_id = document.getElementById("part_no").value;
      var description = document.getElementById("descr").value;

      //create form elements and add kit id and description
      //initalize form data (will carry all form data over to server side)
      var fd = new FormData();
      
      //add other applicable info
      fd.append('kit_id', kit_id);
      fd.append('description', description);

      fd.append('tell', 'create_kit');
      
      //call ajax with form elements
      //ajax request to communicate with database
      $.ajax({
        url: 'kit_home_helper.php',
        type : "POST",  //type of method
        processData: false,
        contentType: false,
        data: fd,
        success: function (response) {

          //check for error
          if (response != ""){
            alert ("ERROR " + response);
          }

          //if no error, redirect to material entry with kit_id
          alert("This kit has been successfully created.");
          location = "materialEntry.php?kit=" + kit_id;
          
        }
      });	
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