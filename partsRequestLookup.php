<?php

/****************
 * 
 * This file can be used to look up parts requests for any quote #
 * $_GET['quote'] is optional, if present, pre-set filter and show all parts requests related to the given quote
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

//check for quote #
$quote = "";
if (isset($_GET['quote']))
    $quote = $_GET['quote'];

//will hold arrays on load to transfer to javascript
$pq_overview = [];
$pq_detail = [];

//get pq_overview info
$query = "select * from fst_pq_overview order by quoteNumber;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
    array_push($pq_overview, $rows);	
}

//transfer parts request info
$query = "select * from fst_pq_detail order by project_id;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($pq_detail, $rows);
}

//get list of unique quotes
$quotes = [];
$query = "SELECT quoteNumber FROM fst_pq_overview GROUP BY quoteNumber ORDER BY quoteNumber;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($quotes, array(
		'label'=>$rows['quoteNumber']
	));
}

?>

<!DOCTYPE html>
<html>
<head>
<style>
    .ui-menu { 
		width: 150px; 
	}

    .quantity{
        width: 75px;
    }

    .homeTables {
        border-collapse: collapse;
        margin-bottom: 20px;
        margin-right: 20px;
    }	

    .homeTables th {
        padding: 4px;
    }

    .homeTables td {
        border: 1px solid #000000;
        padding: 4px;
    }

    .ui-widget{
        padding-bottom: 10px;
    }

    .toggle-wrap, .shape {
		display: inline-block;
		vertical-align: top;
    }
    .shape {
		margin-left: 4em;
		margin-top: 2.5em;
		height: 8em;
		width: 8em;
		box-shadow: 4px 4px 8px;
		color: #ccc;
		background-repeat: no-repeat;
		background-size: 90%;
		background-position: 50%;
    }

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
<title>Parts Request Lookup (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Parts Request Lookup'];   //what will appear on the tabs
$header_ids = ['pq_lookup'];                                                       //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'pq_lookup' class = 'tabcontent'>

    <h1>Parts Request Lookup</h1>

    Enter Quote #: <input id = 'quote_number' onKeyUp="search_handler(this)" onChange="search_handler(this)">

    <br><br>

    <div style = 'float:left; margin-right: 4em;' class = 'hidePrint'>
        <div class="pq-wrap" id = 'pq-sel-div' style = 'display: none'>
        <h3>Select a Parts Request</h3>
            <div class="pq">
                <label for="pq-0" id = 'pq-label-0'>PQ0</label>
                <input type="radio" name="pq" id="pq-0" onclick = 'show_info(0)'>
                <label for="pq-1" id = 'pq-label-1'>PQ1</label>
                <input type="radio" name="pq" id="pq-1" onclick = 'show_info(1)'>
                <label for="pq-2" id = 'pq-label-2'>PQ2</label>
                <input type="radio" name="pq" id="pq-2" onclick = 'show_info(2)' >
                <label for="pq-3" id = 'pq-label-3'>PQ3</label>
                <input type="radio" name="pq" id="pq-3" onclick = 'show_info(3)' >
                <label for="pq-4" id = 'pq-label-4'>PQ4</label>
                <input type="radio" name="pq" id="pq-4" onclick = 'show_info(4)' >
                <label for="pq-5" id = 'pq-label-5'>PQ5</label>
                <input type="radio" name="pq" id="pq-5" onclick = 'show_info(5)' >
                <label for="pq-6" id = 'pq-label-6'>PQ6</label>
                <input type="radio" name="pq" id="pq-6" onclick = 'show_info(6)' >
                <label for="pq-7" id = 'pq-label-7'>PQ7</label>
                <input type="radio" name="pq" id="pq-7" onclick = 'show_info(7)' >
                <label for="pq-8" id = 'pq-label-8'>PQ8</label>
                <input type="radio" name="pq" id="pq-8" onclick = 'show_info(8)' >
                <label for="pq-9" id = 'pq-label-9'>PQ9</label>
                <input type="radio" name="pq" id="pq-9" onclick = 'show_info(9)' >
                <label for="pq-10" id = 'pq-label-10'>PQ10</label>
                <input type="radio" name="pq" id="pq-10" onclick = 'show_info(10)' >
                <label for="pq-11" id = 'pq-label-11'>PQ11</label>
                <input type="radio" name="pq" id="pq-11" onclick = 'show_info(11)' >
                <label for="pq-12" id = 'pq-label-12'>PQ12</label>
                <input type="radio" name="pq" id="pq-12" onclick = 'show_info(12)' >
            </div>
        </div>
    </div>

    <div style = 'align-content:center '>
	
		<table id = 'pq_table' style = 'display:none; float: left; margin-right: 5em'>

				<tr>
					<td style = 'text-align: left' colspan='2'><b>Initiated By: </b><span id = 'created_by'></span></td>
				</tr>

			<tr style = 'height: 1em;'></tr>

				<tr>
					<th style = 'text-align: left'><i>Contact Info / Shipping Location</i></th>
					<td></td>
				</tr>
				<tr>
					<td id = 'shipping_name'></td>
				</tr>
				<tr>
					<td id = 'contact_info'></td>
				</tr>
				<tr>
					<td id = 'shipping_street'></td>
				</tr>
				<tr>
					<td id = 'shipping_csz'></td> 
				</tr>

			<tr style = 'height: 1em;'></tr>

				<tr>
					<th style = 'text-align: left'><i>Delivery Requirements</i></th>
					<td></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Required Date: </th>
					<td id = 'dueDate'></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Liftgate Required? </th>
					<td id = 'liftgateOpt'></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Scheduled Delivery? </th>
					<td id = 'delivOpt'></td>
				</tr>
				<tr id = 'schedRow'>
					<th style = 'text-align: left'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Delivery Date/Time: </th>
					<td id = 'schedDate'></td>
				</tr>

			<tr style = 'height: 1em;'></tr>

				<tr>
					<th style = 'text-align: left'><i>Project Info</i></th>
					<td></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Is MMD Required?</th>
					<td id = 'mmd'></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Customer Registration #: </th>
					<td id = 'custNum'></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;OEM Registration #: </th>
					<td id = 'oemNum'></td>
				</tr>
				<tr>
					<th style = 'text-align: left'>&nbsp;&nbsp;Justification: </th>
					<td id = 'justification'></td>
				</tr>

			<tr style = 'height: 1em;'></tr>

				<tr>
					<td><button onclick="exportHandler()" class = 'hidePrint'>Export to CSV</button></td>
				</tr>

		</table>

		<table id = 'pq-bom-table' class = 'homeTables' style = 'display: none;'>
			<tr>
				<th>Part Number</th>
				<th>Quantity</th>
				<th>Subs?</th>
				<th>MMD</th>
			</tr>
		</table>
	</div>
	
	<a id = 'hold_bom'></a>

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
    
    //get quote passed from $_GET (if available)
    const target_quote = "<?= $quote ?>";

    //global that holds information about current search or selected item
    var requests = []; 
    var current_request;
        
    //pass php arrays to js
    var pq_overview = <?php echo json_encode($pq_overview); ?>,
        pq_detail = <?php echo json_encode($pq_detail); ?>,
		quotes = <?php echo json_encode($quotes); ?>;

	//set options to parts array (relevant for parts request new parts)
	var options = {
		source: quotes,
		minLength: 2
	};
	
	//choose selector (input with part as class)
	var selector = '#quote_number';
	
	//on keydown, show autocomplete after 2 characters
	$(document).on('keydown.autocomplete', selector, function() {
		$(this).autocomplete(options);
	});

    //handles change of location input field
    function search_handler(quote){

        //look for a match
        requests = [];
        
        //filter all matches to quote
        requests = pq_overview.filter(function (req) {
            return req.quoteNumber == quote.value;
        });
		        
        //if -1 (no match), if not (show table and read in values)
        if (requests.length == 0){
            u.eid("pq_table").style.display = "none";
            u.eid("pq-sel-div").style.display = "none";
            u.eid("pq-bom-table").style.display = "none";
        }
        else{
            
            //go through requests and render items to choose from
            for (var i = 0; i < requests.length; i++){
                u.eid("pq-label-" + i).innerHTML = "Initiated by " + requests[i].requested_by + " on " + sql_date_convert(requests[i].requested, 'datetime_adj');
                u.eid("pq-" + i).style.display = "block";
                u.eid("pq-label-" + i).style.display = "block";
            }
            for (var j = i; j < 13; j++){
                u.eid("pq-" + j).style.display = "none";
                u.eid("pq-label-" + j).style.display = "none";
            }
            
            //show div
            u.eid("pq-sel-div").style.display = "block";
            
        }
    }
    
    //handles radio clicks
    function show_info(index){
        
        //set current index
        current_request = requests[index];
        
        //set new values, display table
        u.eid("created_by").innerHTML = current_request.requested_by + " on " + sql_date_convert(current_request.requested, 'datetime_adj');

        u.eid("shipping_name").innerHTML = current_request.shipping_loc;
        u.eid("contact_info").innerHTML = "Attn: " + current_request.poc_name + " " + current_request.poc_number;
        u.eid("shipping_street").innerHTML = current_request.shipping_street;
        u.eid("shipping_csz").innerHTML = current_request.shipping_city + ", " + current_request.shipping_state + " " + current_request.shipping_zip;
        u.eid("dueDate").innerHTML = sql_date_convert(current_request.due_date, 'date');
        u.eid("liftgateOpt").innerHTML = current_request.liftgate;
        u.eid("delivOpt").innerHTML = current_request.sched_opt;

        //check sched opt
        if (current_request.sched_opt == 'N')
            u.eid("schedRow").style.visibility = 'collapse';
        else{
            u.eid("schedDate").innerHTML = sql_date_convert(current_request.sched_time, 'datetime');
            u.eid("schedRow").style.visibility = 'visible';
        }

        u.eid("mmd").innerHTML = "NA"; //needs updated
        u.eid("custNum").innerHTML = "NA";
        u.eid("oemNum").innerHTML = "NA";
        u.eid("justification").innerHTML = "Kickoff";

        //show table
        u.eid("pq_table").style.display = "block";
        
        //create bom table
        show_bom_handler();
        
    }
    
    //handles adding BOM 
    function show_bom_handler(){
        
        //remove previous rows
        document.querySelectorAll('.pq-bom-row').forEach(function(a){
            a.remove()
        })
        
        //filter all pq_detail items for request
        parts = pq_detail.filter(function (part) {
            return part.project_id == current_request.id;
        });

        //cycle through existing parts requested and add any relevant parts
        for (var i = 0; i < parts.length; i++){
            show_bom_row(parts[i]);
        }
        
        //show table
        u.eid("pq-bom-table").style.display = "block";
        
    }
    
    //handles adding new row to BOM
    function show_bom_row(part){

        //grab table
        var table = u.eid("pq-bom-table");

        //insert new row and add classname to it
        var row = table.insertRow(-1);
        row.classList.add("pq-bom-row");

        //part number
        var cell = row.insertCell(0);
        cell.innerHTML = part.part_id;

        //quantity
        var cell = row.insertCell(1);
        cell.innerHTML = part.quantity;
        
        //subs
        var cell = row.insertCell(2);
        cell.innerHTML = part.subs;
        
        //mmd
        var cell = row.insertCell(3);
        cell.innerHTML = part.mmd;
        
    }
    
    //converts sql date to better format
    function sql_date_convert(date, type){
        
        var date_local = new Date(date + ' UTC');
        var date_utc = new Date(date);
        
        
        //if just date
        if (type == 'date'){
            var y = date_utc.getFullYear(),
                m = date_utc.getMonth() + 1,
                d = date_utc.getDate();
            
            return m + "/" + d + "/" + y
            
        }
        
        //date & time, convert to central time zone
        if (type == 'datetime_adj'){
            var y = date_local.getFullYear(),
                m = date_local.getMonth() + 1,
                d = date_local.getDate(),
                hours = date_local.getHours(),
                minutes = date_local.getMinutes();
            
            var time = time_format(hours, minutes);
                            
            return m + "/" + d + "/" + y + " at " + time;
        }
        
        //date & time, no conversion
        if (type == 'datetime'){
            var y = date_utc.getFullYear(),
                m = date_utc.getMonth() + 1,
                d = date_utc.getDate(), 
                hours = date_utc.getHours(), 
                minutes = date_utc.getMinutes();
            
            var time = time_format(hours, minutes);
            
            return m + "/" + d + "/" + y + " at " + time;
        }
        
    }
        
    //changes military time to standard
    function time_format(hours, minutes){
        
        //init time to be returned
        var timeValue;
        
        //use hours to check if this needs to be am or pm
        if (hours > 0 && hours <= 12) {
            timeValue= "" + hours;
        } 
        else if (hours > 12) {
            timeValue= "" + (hours - 12);
        } 
        else if (hours == 0) {
            timeValue= "12";
        }
        
        timeValue += (minutes < 10) ? ":0" + minutes : ":" + minutes;  // get minutes
        timeValue += (hours >= 12) ? " P.M." : " A.M.";  // get AM/PM

        // return value
        return timeValue;
    }
    
    //handles export to csv
    function exportHandler(){
        //initialize csvContent to export csv
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // add headers to CSV
        csvContent += "Part Number, Quantity, Subs?, MMD\r\n";
                    
        //filter all pq_detail items for request
        parts = pq_detail.filter(function (part) {
            return part.project_id == current_request.id;
        });

        //cycle through existing parts requested and add any relevant parts
        for (var i = 0; i < parts.length; i++){
            csvContent += parts.part_id + ",";
            csvContent += parts.quantity + ",";
            csvContent += parts.subs + ",";
            csvContent += parts.mmd;
            csvContent += "\n" //newline
        }
    
        //create name for file
        var csv_name = "Parts Request BOM - " + current_request.type + " - " + current_request.quoteNumber + ".csv";
        
        //embed csv content into <a> tag hidden on the screen							
        var encodedUri = encodeURI(csvContent);
        var link = u.eid("hold_bom");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", csv_name);
        
        //force download to user
        link.click();
        
    }
		
    //turns 'wait' on and off during ajax requests
    $(document).ajaxStart(function () {
        waiting('on');
    });
    
    $(document).ajaxStop(function () {
        waiting('off');
    });
		
	// Initalize widgets
    $( ".shape-bar, .pq" ).controlgroup();
    $( ".pq" ).controlgroup( {
        direction: "vertical"
    } );
		
	$( ".toggle" ).on( "change", handleToggle );
	
    function handleToggle( e ) {
        var target = $( e.target );
        var checked = target.is( ":checked" ),
        value = $( "[name='pq']" )
        .filter( ":checked" )
        .attr( "data-" + target[ 0 ].id )
        $( ".shape" ).css( target[ 0 ].id, checked ? value : "" );
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

    //windows onload
    window.onload = function() {
        //place any functions you would like to run on page load inside of here
        u.eid("defaultOpen").click();

        //if quote is not blank, set as input field and search
        if (target_quote != ""){
            u.eid("quote_number").value = target_quote;
            search_handler(u.eid("quote_number"));
        }
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