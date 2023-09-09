<?php

/**
 * FILE EXPLANATION
 * 
 * This file is used to maintain vendors information. Users can edit vendor information & vendor POC information for use in termianl_orders.php
 * 
 */

//initialize session
session_start();

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "terminal"));

//include php functions sheet
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

//include constants sheet
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
//check session variable first
if (isset($_SESSION['email'])){
	$query = "SELECT * from fst_users where email = '".$_SESSION['email']."';";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0)
		$fstUser = mysqli_fetch_array($result);
	else
		$fstUser['accessLevel'] = "None";
}
else
	$fstUser['accessLevel'] = "None";
	
//check if session is expired/if user has access
sessionCheck($fstUser['accessLevel']);

//initialize array for previous vendor list
$previous_orders = [];

//query to grab any subs available
$query = "SELECT part_id, sum(q_allocated) as 'q_allocated', vendor, sum(vendor_qty) as 'vendor_qty' 
			FROM fst_pq_detail 
			WHERE vendor is not null AND vendor <> '' GROUP BY part_id, vendor ORDER BY part_id, vendor_qty desc;";
$result =  mysqli_query($con, $query);

// this while statement syntax can be used to loop through all results returned from a query
// specifically, this will loop and create a table called $previous_orders, that looks exactly like the $query from above.
// the same workflow follows for the next 2 queries
while($rows = mysqli_fetch_assoc($result)){
	array_push($previous_orders, $rows);
}

//get vendor list
$vendors = [];
$query = "select * from fst_vendor_list ORDER BY vendor;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
    array_push($vendors, $rows);    
}

//same thing for vendor poc's
$vendor_poc = [];
$query = "select * from fst_vendor_list_poc order by vendor_id;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
    array_push($vendor_poc, $rows);
}

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>">
	
<title>Vendor List (v<?= $version ?>) - Pierson Wireless</title>
	
<style>

	/**force header to be 'sticky' (while scrolling, stick to top of screen)*/
	.sticky_order_header{
		position: sticky;
		top: 46px;
		z-index: 100;
		background: white;
	}

    /**force header to be 'sticky', notice "top" is higher value, this is so both headers stay (search bar and header) */
    .sticky_order_header2{
		position: sticky;
		top: 70px;
		z-index: 100;
		background: white;
	}

	/**style delete element */
	.delete_contact{
		color: red;
		cursor: pointer;
	}

	/**style the elements inside of the expanded row */
	.expanded_row table {
		margin:2em;
	}

	.expanded_row button {
		margin-top:2em;
		margin-left:2em;
	}
	
	.contacts_header td{
		border: 0;
		text-align: center;
		font-weight: bold;
	}

	/**style create vendor labels */
	.create_vendor_label{
		font-weight: bold;
		padding: 3px 20px 10px 0px;
	}

</style>
</head>

<body>
	<?php

		// define array of names & Id's to generate headers
		$header_names = ['Vendor List'];
		$header_ids = ['vendor_list'];

		// pass to php function to create navigation bars
		echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

	?>
		
	<div style = 'padding-left:1em;padding-top:4em;'>

		<!--CONSTANT IN constants.php-->
		<?= constant('terminal_navigation') ?>

	</div>
	
	<div id = 'vendor_list' class = 'tabcontent'>

		<button class = 'large_button' onclick = 'open_dialog("create_vendor_dialog")' style = 'margin-left:2em; margin-top:1em;'>Create New Vendor</button>

		<table id = 'vendor_table' class = 'standardTables' style = 'margin: 2em;'>
			<thead>
				<tr class = 'sticky_order_header'>
					<th></th>
					<th>Vendor ID</th>
					<th>Vendor Name</th>
					<th>Vendor Search</th>
					<th>Vendor POC</th>
					<th>Vendor Phone #</th>
					<th>Vendor Address</th>
					<th>Vendor City</th>
					<th>Vendor State</th>
					<th>Vendor Zip Code</th>				
				</tr>
                <tr class = 'sticky_order_header2'>
					<td></td>
					<td><input type = 'text' id = 'search_vendor_id' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_name' onkeyup="init_vendor_list()"></td>
					<td><input type = 'text' id = 'search_vendor_search_name' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_poc' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_phone' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_street' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_city' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_state' onkeyup="init_vendor_list()"></td>
                    <td><input type = 'text' id = 'search_vendor_zip' onkeyup="init_vendor_list()"></td>
                </tr>
			</thead>
			<tbody>
				<!--to be entered by init_vendor_list() !-->
			</tbody>
		</table>
	</div>

	<div id = 'create_vendor_dialog' title = 'Create New Vendor' style = "display: none">
		<table id = 'create_vendor_table' style = 'margin: 1em;'>
			<tr>
				<td class = 'create_vendor_label'>*Vendor Name</td>
				<td><input id = 'vendor_name'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Vendor POC</td>
				<td><input id = 'vendor_poc'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Vendor Phone</td>
				<td><input id = 'vendor_phone'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Address Line 1</td>
				<td><input id = 'vendor_street'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Address Line 2</td>
				<td><input id = 'vendor_street2'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>City</td>
				<td><input id = 'vendor_city'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Vendor State</td>
				<td><input id = 'vendor_state'></td>		
			</tr>
			<tr>
				<td class = 'create_vendor_label'>Vendor Zip</td>
				<td><input id = 'vendor_zip'></td>		
			</tr>
			<tr>
				<td colspan = '2'><button onclick = 'create_new_vendor()'>Create Vendor</button></td>
			</tr>
		</table>
	</div>

	<!-- external js libraries -->
	<!--jquery capabilities-->
	<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!--google APIs-->
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!--load pdf renderer-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	
	<!--local scripts-->
	<script src = "javascript/accounting.js"></script>
	<script src="javascript/js_helper.js?<?= $version ?>1"></script>
	<script src = "javascript/utils.js"></script>
	<script src="javascript/fst_js_functions.js"></script>

	<script>
		
		//Namespace
		var z = {}
		
		//load in active user (first + last)
		const user_info = <?= json_encode($fstUser); ?>;

        //pass php array to js
        var vendors = <?= json_encode($vendors); ?>,
            vendor_poc = <?= json_encode($vendor_poc); ?>,
            previous_orders = <?= json_encode($previous_orders); ?>;

        // handles filtering list of vendors based on user entered search criteria and adding to table
        function init_vendor_list(){

            //get user entered search bars
            // NOTE: u.eid is short for document.getElementByID() (see javascript/utils.js for other short-hands)
            var id = u.eid("search_vendor_id").value.toLowerCase(),
				name = u.eid("search_vendor_name").value.toLowerCase(),
				search_name = u.eid("search_vendor_search_name").value.toLowerCase(),
                poc = u.eid("search_vendor_poc").value.toLowerCase(),
                phone = u.eid("search_vendor_phone").value.toLowerCase(),
                street = u.eid("search_vendor_street").value.toLowerCase(),
                city = u.eid("search_vendor_city").value.toLowerCase(),
                state = u.eid("search_vendor_state").value.toLowerCase(),
                zip = u.eid("search_vendor_zip").value.toLowerCase();

            //filter vendor_list based on search criteria
			var filtered_vendors = vendors.filter(function (v) {
				return (id == "" || v.id.toLowerCase().includes(id)) &&							//checks id
						(name == "" || v.vendor.toLowerCase().includes(name)) &&						//checks name
						(search_name == "" || v.vendor_search.toLowerCase().includes(search_name)) &&	//checks name
						(poc == "" || v.poc.toLowerCase().includes(poc)) &&				    			//checks poc
						(phone == "" || v.phone.toLowerCase().includes(phone)) &&	        			//checks phone
						(street == "" || v.street.toLowerCase().includes(street)) &&					//checks street
						(city == "" || v.city.toLowerCase().includes(city)) &&			    			//checks city
						(state == "" || v.state.toLowerCase().includes(state)) &&						//checks state
						(zip == "" || v.zip.toLowerCase().includes(zip))                    			//checks zip
			});

			//remove previous entries (if they exist)
            //vendor_table_row is a class added to all rows in add_vendor_row() function
			document.querySelectorAll('.vendor_table_row').forEach(function(a){
				a.remove()
			})

			//set max limit of results to 50 (force user to search further + decrease time for table rendering)
			var limit = Math.min(50, filtered_vendors.length);

            //get table that we want to add to (specifically, get <tbody>)
            //differentiating between <tbody> and <thead> can be useful when using filter bars
            var table = u.eid("vendor_table").getElementsByTagName('tbody')[0];

			//loop through filtered_grid till we reach our limit
			for (var i = 0; i < limit; i++){
				add_vendor_row(filtered_vendors[i], table);
			}
        }

        /**
		 * handles generating a row to add to vendor_table
		 * 
         * input:
         * param 1: vendor (object) matches row from fst_vendor_list table
         * param 2: <table> to be added to 
         * 
         * no output: end result is a new row created and added to vendor_table
         * 
         */
        function add_vendor_row(vendor, table){

            //create new row
			var row = table.insertRow(-1);
			row.classList.add("vendor_table_row");  //add class so we can remove this for future searches

            //init array of cells to be entered (should match columns in fst_vendor_list sql table)
            var keys = ['expand_button', 'id', 'vendor', 'vendor_search', 'poc', 'phone', 'street', 'city', 'state', 'zip'];

			//loop through keys and add a cell for each key to the table
			for (var i = 0; i < keys.length; i++){

                //create new cell on given row
                var cell = row.insertCell(i);
                
                //create input field to append to cell
                var input = document.createElement("input");
                
                //set value of input (use vendor and keys to do so)
                //vendor[keys[i]] is equivalent to vendor['vendor'] or vendor['poc'], etc.
                input.value = vendor[keys[i]];

                //create specific rules for certain keys
                //if this is the vendor name, we do not want this changing, set this to readonly
                if (keys[i] == "vendor" || keys[i] == "id")
                    input.readOnly = true;

				// if key is expand_button, overwrite <input> set to <button>
				if (keys[i] == "expand_button"){
					input = document.createElement("button");
					input.classList.add(keys[i]);
					input.id = "expand_" + vendor.id;
					input.addEventListener("click", expand_row);
					input.innerHTML = "+";
				}

                //append input to cell
                cell.appendChild(input);
			}

            //add <button> used to update vendor info as last cell
            var cell = row.insertCell(i);
            
            //you can also render plain HTML rather than "creating" an input element
            cell.innerHTML = "<button onclick = 'update_vendor_info(" + vendor.id + ", this)'>Update Vendor Info</button>";
        }

		/**
		 * Handles expanding a Vendor row to show all contacts & allow user to edit
		 * @author Alex Borchers
		 * 
		 * Use 'this' to determine what button was clicked, work back to vendor name
		 * 
		 * @returns void
		 */
		function expand_row(){

			// get vendor ID from <button> id attribute (structured like "expand_[id]")
			var vendor_id = this.id.substr(7);

			// depending on innerHTML of button, show or remove row
			if (this.innerHTML == "+"){

				// update to '-'
				this.innerHTML = "-";

				// work back to <tr>
				var td = this.parentNode;
				var tr = td.parentNode;

				// call function to expand row
				add_expanded_section(vendor_id, tr.rowIndex);

			}
			else{

				// update to '-'
				this.innerHTML = "+";

				// remove existing row
				u.eid("expanded_row_" + vendor_id).remove();

			}
		}

		/**
		 * Handles adding an expanded row to show vendor contacts and allow user to edit
		 * @author Alex Borchers
		 * @param int {vendor_id} matches vendor_id in fst_vendor_list db table
		 * @param int {row_index} row that user clicked
		 * @returns void
		 */
		function add_expanded_section(vendor_id, row_index){

			//get orders table (used throughout function)
			var vendor_t = u.eid("vendor_table").getElementsByTagName('tbody')[0];0

			//get list of parts assigned po number
			var contacts = vendor_poc.filter(function (object) {
				return object.vendor_id == vendor_id;
			});

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("contacts_table")

			//create headers
			var row = table.insertRow(-1);
			row.classList.add("contacts_header");	

			// add column for name, phone, email
			var cell = row.insertCell(0);
			cell.innerHTML = "Name";
			var cell = row.insertCell(1);
			cell.innerHTML = "Phone";
			var cell = row.insertCell(2);
			cell.innerHTML = "Email";

			// loop through contacts and call function to create row for given contact
			for (var i = 0; i < contacts.length; i++){
				add_expanded_row(table, contacts[i]);
			}

			// create button to send create new shipments
			var button = document.createElement("button");
			button.innerHTML = "Update Contacts";
			button.id = "update_poc_" + vendor_id;
			button.addEventListener("click", update_contacts);

			//add row to orders table and push table to new row
			var row = vendor_t.insertRow(row_index - 1);
			row.id = "expanded_row_" + vendor_id;
			row.classList.add("expanded_row");
			row.classList.add("vendor_table_row");
			var cell = row.insertCell(0);
			cell.colSpan = vendor_t.rows[0].cells.length;
			cell.append(button);

			//add newly created table to row
			cell.append(table);
			
		}

		/**
		 * Handles adding a row to the expanded section
		 * @author Alex Borchers
		 * @param {HTMLEntity} 	table 	<table> element that we are adding to
		 * @param {Object} 		contact vendor POC we are adding (matches row from fst_vendor_list_poc)
		 * @returns void
		 */
		function add_expanded_row(table, contact){

			//create new row from given table
			var row = table.insertRow(-1);

			// set content
			var content = ["name", "phone", "email"];

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < content.length; i++){

				//create new cell
				var cell = row.insertCell(i);

				//create input element & add classlist
				var input = document.createElement("input");
				input.classList.add(content[i] + "_" + contact.vendor_id);
				input.value = contact[content[i]];
				cell.appendChild(input);

				//if "name", add ID to input
				if (content[i] == "name")
					input.id = "poc_" + contact.id;

			}

			//add button to remove shipment at end of each row
			var cell = row.insertCell(content.length);
			cell.innerHTML = "&#10006";
			cell.classList.add("delete_contact");
			cell.id = "delete_" + contact.id;

		}

		//globals that define sql id's for a given part
		var open_order_shipping_content = ['shipped', 'shipment_id', 'tracking', 'carrier', 'cost', 'ship_date', 'arrival', 'received_by', 'notes'];

		//adds open order row for shipment items
		//param 1 = the table that we are adding
		//param 2 = the shipment we are adding
		function add_order_shipment_row(table, part){

			//create new row from given table
			var row = table.insertRow(-1);

			//insert expansion button as first element of row
			//create new cell
			var cell = row.insertCell(0);

			//create input element & add classlist
			var button = document.createElement("button");
			button.classList.add("expand_shipment");
			button.innerHTML = "+";

			//append to cell
			cell.appendChild(button);

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < open_order_shipping_content.length; i++){

				//create new cell
				var cell = row.insertCell(i + 1);

				//create input element & add classlist
				var input = document.createElement("input");
				input.classList.add(open_order_shipping_content[i]);
				input.classList.add("refresh_shipping")

				//define input type depending on content
				if (open_order_shipping_content[i] == "shipped"){
					input.type = "checkbox";
					cell.style.textAlign = "center";

					//default checked to saved value.. check if we have a number saved (from sql) and overwrite if we do
					input.checked = part[open_order_shipping_content[i]];

					//db saves values as 0 == false, 1 == true
					if (part[open_order_shipping_content[i]] == "0")
						input.checked = false
					else if (part[open_order_shipping_content[i]] == "1")
						input.checked = true;	

				}
				else if (open_order_shipping_content[i] == "ship_date" || open_order_shipping_content[i] == "arrival"){
					input.type = "date";
				}
				else if(open_order_shipping_content[i] == "cost"){
					input.type = "number";
				}

				//update input value based on saved parts info
				if (open_order_shipping_content[i] == "notes")
					input.value = get_recent_note(part.shipment_id, 'shipment');
				else
					input.value = part[open_order_shipping_content[i]];


				//append to cell (treat delete_shipment different)
				cell.appendChild(input);

				//set to readonly depending on type of content
				if (open_order_shipping_content[i] == "shipment_id" || open_order_shipping_content[i] == "notes")
					input.readOnly = true;

			}

			//add button to remove shipment at end of each row
			var cell = row.insertCell(open_order_shipping_content.length + 1);
			cell.innerHTML = "&#10006";
			cell.classList.add("delete_shipment");
		}

		/**
		 * Handles deleting a contact
		 */
		$(document).on('click', '.delete_contact', function(){
			
			// get db row id from <td>.id (structured "delete_[id]")
			var id = this.id.substr(7);

			//ask user if they are sure
			var message = "Are you sure you would like to delete contact?";

			//if confirmed, send to function to process deletion
			if (confirm(message)){
				delete_contact(id);

				// get row to remove
				var tr = this.parentNode;
				tr.remove();
			}
		});

		/**
		 * Handles preparing form to pass through AJAX to PHP helper file
		 * @author Alex Borchers
		 * @param {int} id matches ID from fst_vendor_list_poc db table
		 * @returns void
		 */
		function delete_contact(id){

			// initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// pass variables to form element
            // the first parameter will show up in a $_POST element
			fd.append('id', id);

			// add tell variable (we always need to add a 'tell' to form data so the helper function knows what to do)
			fd.append('tell', 'delete_poc');

			// send to helper php file via ajax
			$.ajax({
				url: 'terminal_orders_vendors_helper.php',  //change to your helper file name
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					// check for error response
                    // we sometimes will pass information back from the 'response'
                    // in this case, we are not passing info back, so anything that comes back would be considered an error
					if (response != ""){
						alert("Error: Please screenshot and send to fst@piersonwireless.com: " + response);
						return;
					}

					// remove poc from our global list
					var index = vendor_poc.findIndex(object => {
						return object.id == id;
					});
					vendor_poc.splice(index, 1);
					
					// if no error, let user know they were successful
					alert("The POC has been deleted successfully.");											
				}
			});
		}

		/**
		 * Handles updating list of contacts for a given vendor
		 * @author Alex Borchers
		 * 
		 * Use 'this' (the <button> clicked) to extra ID and make decisions
		 * 
		 * @returns void
		 */
		function update_contacts(){

			// use this.id to extra correct class name
			// class name is in the form content[i] + "_" + contact.id
			// <button> ID is in the form update_poc_[id]
			var vendor_id = this.id.substr(11);

			// initialize object to be passed to php
			var poc_info = [];

			// get related classes
			var poc_name = u.class("name_" + vendor_id);
			var poc_phone = u.class("phone_" + vendor_id);
			var poc_email = u.class("email_" + vendor_id);

			// loop and add to array of objects
			for (var i = 0; i < poc_name.length; i++){
				poc_info.push({
					id: poc_name[i].id.substr(4),
					name: poc_name[i].value,
					phone: poc_phone[i].value,
					email: poc_email[i].value					
				})
			}

			// initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// pass variables to form element
            // the first parameter will show up in a $_POST element
            // ex if name = 'ADRF' then $_POST['name'] => 'ADRF in the helper function
			fd.append('poc_info', JSON.stringify(poc_info));

			// add tell variable (we always need to add a 'tell' to form data so the helper function knows what to do)
			fd.append('tell', 'update_poc');

			// send to helper php file via ajax
			$.ajax({
				url: 'terminal_orders_vendors_helper.php',  //change to your helper file name
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					// check for error response
                    // we sometimes will pass information back from the 'response'
                    // in this case, we are not passing info back, so anything that comes back would be considered an error
					if (response != ""){
						alert("Error: Please screenshot and send to fst@piersonwireless.com: " + response);
						return;
					}

					// loop through new POC info and update with new info
					for (var i = 0; i < poc_info.length; i++){
						var index = vendor_poc.findIndex(object => {
							return object.id == poc_info[i].id;
						});

						vendor_poc[index].name = poc_info[i].name;
						vendor_poc[index].email = poc_info[i].email;
						vendor_poc[index].phone = poc_info[i].phone;
					}
					
					// if no error, let user know they were successful
					alert("The POC information has been updated for this vendor.");
											
				}
			});
		}

        //handles updating vendor information
        /**
         * input:
         * param 1 = vendor ID (matches id in fst_vendor_list db table)
         * param 2 = object that holds <button> -> we can use this to work backwards into the rest of the table to grab other info
         * 
         * output:
         * message to user of successful update
         * 
         */
        function update_vendor_info(id, button){

            //use 'button' object, work backwards into table to grab vendor name, poc, etc.
            var td = button.parentNode;
            var tr = td.parentNode;

            // we're now at the <tr> tag, we can work down to other rows and grab their info
            // example:
            // name =  <tr>.<td>.<input (vendor name)>.value
            var name = tr.childNodes[2].childNodes[0].value,
				search_name = tr.childNodes[3].childNodes[0].value,
                poc = tr.childNodes[4].childNodes[0].value,
                phone = tr.childNodes[5].childNodes[0].value,
                street = tr.childNodes[6].childNodes[0].value,
                city = tr.childNodes[7].childNodes[0].value,
                state = tr.childNodes[8].childNodes[0].value,
                zip = tr.childNodes[9].childNodes[0].value;

			// initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// pass variables to form element
            // the first parameter will show up in a $_POST element
            // ex if name = 'ADRF' then $_POST['name'] => 'ADRF in the helper function
			fd.append('id', id);
            fd.append('name', name);
			fd.append('search_name', search_name);
            fd.append('poc', poc);
            fd.append('phone', phone);
            fd.append('street', street);
            fd.append('city', city);
            fd.append('state', state);
            fd.append('zip', zip);

			// add tell variable (we always need to add a 'tell' to form data so the helper function knows what to do)
			fd.append('tell', 'update_vendor');

			// send to helper php file via ajax
			$.ajax({
				url: 'terminal_orders_vendors_helper.php',  //change to your helper file name
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					// check for error response
                    // we sometimes will pass information back from the 'response'
                    // in this case, we are not passing info back, so anything that comes back would be considered an error
					if (response != ""){
						alert("Error: Please screenshot and send to fst@piersonwireless.com: " + response);
						return;
					}
					
					// if no error, let user know they were successful
					alert("The vendor information has been successfully updated.");

                    // this can be used to force reload the page
					window.location.reload();
											
				}
			});
        }

		//handles updating vendor information
        /**
         * input:
         * param 1 = vendor ID (matches id in fst_vendor_list db table)
         * param 2 = object that holds <button> -> we can use this to work backwards into the rest of the table to grab other info
         * 
         * output:
         * message to user of successful update
         * 
         */
        function create_new_vendor(){

			// initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// pass variables to form element
            // the first parameter will show up in a $_POST element
            // ex if name = 'ADRF' then $_POST['name'] => 'ADRF in the helper function
            fd.append('name', u.eid("vendor_name").value);
            fd.append('poc', u.eid("vendor_poc").value);
            fd.append('phone', u.eid("vendor_phone").value);
            fd.append('street', u.eid("vendor_street").value);
			fd.append('street2', u.eid("vendor_street2").value);
            fd.append('city', u.eid("vendor_city").value);
            fd.append('state', u.eid("vendor_state").value);
            fd.append('zip', u.eid("vendor_zip").value);

			// add tell variable (we always need to add a 'tell' to form data so the helper function knows what to do)
			fd.append('tell', 'create_vendor');

			// add user_info so we can see who is interacting with this function
			fd.append('user_info', JSON.stringify(user_info));

			// send to helper php file via ajax
			$.ajax({
				url: 'terminal_orders_vendors_helper.php',  //change to your helper file name
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					// check for error response
                    // we sometimes will pass information back from the 'response'
                    // in this case, we are not passing info back, so anything that comes back would be considered an error
					if (response.substr(0, 5) == "Error"){
						alert("Error: Please screenshot and send to fst@piersonwireless.com: " + response);
						return;
					}
					
					// if no error, let user know they were successful
					alert("The vendor has been created. The ID is " + response + ". An email has also been sent out to Accounts Payable with this information.");

                    // this can be used to force reload the page
					window.location.reload();
											
				}
			});
        }

		/* AJAX EXAMPLE
		$.ajax({
			url: 'your_php_file.php',  	//change to your helper file name
			type: 'POST',				//do not change
			processData: false,			//do not change
			contentType: false,			//do not change
			data: fd,					//do not change (append data to fd earlier in the function)
			done: function (response) {
				//check response for errors, alert user when successful						
			}
		});
		*/

		//on ajax call, turn mouse waiting ON
		$(document).ajaxStart(function () {
			waiting('on');
		});

		//on ajax stop, turn mouse waiting OFF
		$(document).ajaxStop(function () {
			waiting('off');
		});

        //handles tabs up top that toggle between divs
		function change_tabs (pageName, elmnt, color) {

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
		window.onload = function(){

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

            // render table on page load
            init_vendor_list();
		
		}

	</script>
	
</body>
	
<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>
	
</html>