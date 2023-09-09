<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

//include php functions
include('phpFunctions.php');

//include ability to create part class
include('PHPClasses/Part.php');

//handles any adjustments to MO's
if ($_POST['tell'] == "update_mo"){
	
	//create query to update status
	$query = "UPDATE fst_allocations_mo SET status = '" . mysql_escape_mimic($_POST['status']) . "' WHERE id = " . mysql_escape_mimic($_POST['id']) . ";";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
	
}

//handles any adjustments to inventory
if ($_POST['tell'] == "update_inv"){

	// get user info
	$user_info = json_decode($_POST['user_info'], true);

	//add new part
	if($_POST['type'] == "add"){
		
		//get array from json
		$standard_info = json_decode($_POST['standard_info']);
		$attributes = json_decode($_POST['attributes'], true);
		
		//create query to save update for given part
		$query = "INSERT INTO invreport (";

		//create the rest of the query dynamically
		for ($i = 0; $i < sizeof($standard_info); $i++){

			//check if POST is set
			if (isset($_POST[$standard_info[$i]]))
				$query .= "`" . $standard_info[$i] . "`, ";
			
		}
				
		for ($i = 0; $i < sizeof($attributes); $i++){
			//check if POST is set
			if (isset($_POST[$attributes[$i]['key']]))
				$query .= "`" . $attributes[$i]['key'] . "`, ";
		}
		
		//add subs & close off ()
		$query.="`subPN`) VALUES (";
		
		//same loops but add values
		for ($i = 0; $i < sizeof($standard_info); $i++){

			//check if POST is set
			if (isset($_POST[$standard_info[$i]]))
				$query .= "'" . mysql_escape_mimic($_POST[$standard_info[$i]]) . "', ";
			
		}
		
		for ($i = 0; $i < sizeof($attributes); $i++){
			//check if POST is set
			if (isset($_POST[$attributes[$i]['key']]))
				$query .= "'" . mysql_escape_mimic($_POST[$attributes[$i]['key']]) . "', ";
		}
		
		//add subs + );
		$query.= "'" . mysql_escape_mimic($_POST['subPN']) . "');";

		//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
		//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
		if(custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
		
		//log part change (in phpFunctions)
		part_log($_POST['partNumber'], $user_info['id'], "New Part Creation");
		return;

	}
	
	//edit existing part
	if($_POST['type'] == "save"){
		
		//get array from json
		$update_parts = json_decode($_POST['update_parts'], true);
		$standard_info = json_decode($_POST['standard_info']);
		$attributes = json_decode($_POST['attributes'], true);
		$all_shops = json_decode($_POST['all_shops'], true);
		
		//loop through update_parts and update in invreport
		for ($i = 0; $i < sizeof($update_parts); $i++){

			//create new instance of part
			$part = new part($update_parts[$i]['partNumber'], $con);
			
			//create query to save update for given part
			$query = "UPDATE invreport SET ";

			//create the rest of the query dynamically
			for ($j = 0; $j < sizeof($standard_info); $j++){
				
				//check if part number
				if ($standard_info[$j] != "partNumber")
					$query .= $standard_info[$j] . " = '" . mysql_escape_mimic($update_parts[$i][$standard_info[$j]]) . "', ";
			}

			for ($j = 0; $j < sizeof($attributes); $j++){
				$query .= $attributes[$j]['key'] . " = '" . mysql_escape_mimic($update_parts[$i][$attributes[$j]['key']]) . "', ";
			}

			//add in subs list
			$query .= "subPN = '" . mysql_escape_mimic($update_parts[$i]['subs_list']) . "' ";

			//set where clause
			$query .= "WHERE partNumber = '" . mysql_escape_mimic($update_parts[$i]['partNumber']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			// loop through inv_locations and update any changes
			foreach ($all_shops as $shop){

				// if true, check if we have an entry already, otherwise add
				if ($update_parts[$i]['inv_locations'][$shop])
					$part->add_inv_location($shop, $user_info['id']);
				else 
					$part->remove_inv_location($shop, $user_info['id']);
			}

			//update description, manufacturer, category in inv_locations as well
			$query = "UPDATE inv_locations 
						SET category = '" . mysql_escape_mimic($update_parts[$i]['partCategory']) . "', manufacturer = '" . mysql_escape_mimic($update_parts[$i]['manufacturer']) . "', 
							description = '" . mysql_escape_mimic($update_parts[$i]['partDescription']) . "'
						WHERE partNumber = '" . mysql_escape_mimic($update_parts[$i]['partNumber']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//check if certain area's changed
			if ($part->info['price'] != $update_parts[$i]['price'])
				$part->log_part_update($user_info['id'], "Price Update (old: " . $part->info['price'] . " | new: " . $update_parts[$i]['price'] . ")", "PA");

			if ($part->info['partDescription'] != $update_parts[$i]['partDescription'])
				$part->log_part_update($user_info['id'], "Part Description Updated", "PA");

			//check other changes
			$other_changes = [];

			for ($j = 0; $j < sizeof($standard_info); $j++){
				
				//ignore previous 2
				if ($standard_info[$j] != "price" && $standard_info[$j] != "partDescription"){

					//check for change in value
					if ($update_parts[$i][$standard_info[$j]] != $part->info[$standard_info[$j]])
						array_push($other_changes, $standard_info[$j]);

				}
			}

			//same checks for actual attributes
			for ($j = 0; $j < sizeof($attributes); $j++){

				//check for change in value
				if ($update_parts[$i][$attributes[$j]['key']] != $part->info[$attributes[$j]['key']])
					array_push($other_changes, $attributes[$j]['key']);
			}

			//log if true
			if (sizeof($other_changes) > 0)
				$part->log_part_update($user_info['id'], "General Attribute Update (" . implode( ", ", $other_changes) . ")", "PA");
			
		}
		
		return;

	}
}

// Handles updating staging area for warehouses
if ($_POST['tell'] == "save_staging_area"){

	// Decode objects needed
	$user_info = json_decode($_POST['user_info'], true);
	$staging_area = json_decode($_POST['staging_area'], true);

	// Remove old entries for shop
	$query = "DELETE FROM inv_staging_areas WHERE shop = '" . $_POST['shop'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Loop through all staging area info and save
	foreach ($staging_area as $area){

		// Insert new row for each entry
		$query = "INSERT INTO inv_staging_areas 
					(shop, location_name, x, y) VALUES
					('" . $_POST['shop'] . "', '" . mysql_escape_mimic($area['location_name']) . "',
					'" . $area['x'] . "', '" . $area['y'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	}

	return;
}


//handles any changes to our shipping locations table
if ($_POST['tell'] == "update_shop"){

	//based on type we will either add, edit, or delete	
	//add new part
	if($_POST['type'] == "add"){
		
		//create query to add note
		$query = "INSERT INTO general_shippingadd (customer, name, abv, recipient, address, city, state, zip, phone, email) VALUES ('PW', '" . mysql_escape_mimic($_POST['name']) . "', '" . mysql_escape_mimic($_POST['abv']) . "', '" . mysql_escape_mimic($_POST['recipient']) . "', '" . mysql_escape_mimic($_POST['address']) . "', '" . mysql_escape_mimic($_POST['city']) . "', '" . mysql_escape_mimic($_POST['state']) . "', '" . mysql_escape_mimic($_POST['zip']) . "', '" . mysql_escape_mimic($_POST['phone']) . "', '" . mysql_escape_mimic($_POST['email']) . "')";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
	
	//edit existing part
	if($_POST['type'] == "edit"){

		//create query to add note
		$query = "UPDATE general_shippingadd SET 
					name = '" . mysql_escape_mimic($_POST['name']) . "',
					abv = '" . mysql_escape_mimic($_POST['abv']) . "',
					recipient = '" . mysql_escape_mimic($_POST['recipient']) . "', 
					address = '" . mysql_escape_mimic($_POST['address']) . "', 
					city = '" . mysql_escape_mimic($_POST['city']) . "', 
					state = '" . mysql_escape_mimic($_POST['state']) . "', 
					zip = '" . mysql_escape_mimic($_POST['zip']) . "',
					phone = '" . mysql_escape_mimic($_POST['phone']) . "', 
					email = '" . mysql_escape_mimic($_POST['email']) . "'
					WHERE name = '" . mysql_escape_mimic($_POST['old_name']) . "' AND customer = 'PW';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// update other instances were shipping address may be stored
		// fst_allocations_mo db table (ship_to)
		$query = "UPDATE fst_allocations_mo 
					SET ship_to = '" . mysql_escape_mimic($_POST['name']) . "'
					WHERE ship_to = '" . mysql_escape_mimic($_POST['old_name']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// fst_pq_overview db table (staging_loc)
		$query = "UPDATE fst_pq_overview 
					SET staging_loc = '" . mysql_escape_mimic($_POST['name']) . "'
					WHERE staging_loc = '" . mysql_escape_mimic($_POST['old_name']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// fst_pq_overview db table (shipping_loc)
		$query = "UPDATE fst_pq_overview 
					SET shipping_loc = '" . mysql_escape_mimic($_POST['name']) . "'
					WHERE shipping_loc = '" . mysql_escape_mimic($_POST['old_name']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// fst_grid_address db table (street1)
		$query = "UPDATE fst_grid_address 
					SET street1 = '" . mysql_escape_mimic($_POST['name']) . "'
					WHERE street1 = '" . mysql_escape_mimic($_POST['old_name']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// fst_pq_orders db table (po_ship_to)
		$query = "UPDATE fst_pq_orders 
					SET po_ship_to = '" . mysql_escape_mimic($_POST['name']) . "'
					WHERE po_ship_to = '" . mysql_escape_mimic($_POST['old_name']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		
		return;

	}
	
	//delete existing part
	if($_POST['type'] == "delete"){

		//create query to add note
		$query = "DELETE FROM general_shippingadd WHERE name = '" . mysql_escape_mimic($_POST['old_name']) . "' AND customer = 'PW';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
}
