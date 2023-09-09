<?php

/**
 * This file is used to store complicated 'views' that need to be loaded repetitively on page load & during use of the application
 * these are mainly related to dashboard views which require loading large amounts of data related to each quote (notes, bom, logs, etc.)
 * @author Alex Borchers
 * 
 * Functions (views)
 * get_operations_views()	Handles grabbing views releated to ops dashboard (home_ops.php)
 * get_fse_views() 			Handles grabbing views releated to fse dashboard (home_fse.php)
 * get_design_views()		Handles grabbing views releated to design dashboard (home_des.php)
 * get_cop_views()			Handles grabbing views releated to cop dashboard (home_cop.php)
 */

// Set Global to determine which fst_grid columns to bring into grid applications
$columns = ['quoteNumber', 'location_name', 'location_id', 'vpProjectNumber', 'vpContractNumber', 'designer', 'phaseName', 'projectType', 'customer', 'customer_id', 'customer_pm', 'market', 'address', 'address2', 'city', 'state', 'zip', 'projectLead', 'quoteCreator', 'programCoordinator', 'opsLead', 'fs_engineer', 'asd_member', 'cop_member', 'quoteStatus', 'sub_date', 'po_number', 'fst_status', 'totalPrice', 'googleDriveLink', 'lastUpdate', 'sow', 'ops_status', 'ops_manual_start_date', 'ops_manual_end_date', 'ops_auto_start_date', 'ops_complete_timestamp', 'access_instructions', 'invoice_complete', 'cop_distribution', 'quote_type', 'submitBox'];

// Define constant from global to be used in functions
if (!defined("grid_columns")) {
	define("grid_columns", $columns);
	define("grid_columns_string", "a.`" . join("`,a.`", $columns) . "`");
}

/**
 * Handles grabbing views releated to ops dashboard
 * @param mixed $con SQL Connection
 * @param mixed $user object that matches row from fst_users db
 * @return mixed object of arrays of objects (views of multiple sql tables needed to run page)
 */
function get_operations_views($con, $user)
{

	// set object to be returned
	$return_obj = [];

	// get list of supervisees
	$user->set_supervising();
	$supervisees_string = "('" . implode("','", $user->supervising) . "')";

	// load in grid
	$return_obj['grid'] = [];

	// if this user is a manager, create an array based on assigned markets
	if ($user->info['manager'] == "checked")
		$market_string = "'" . str_replace(",", "','", $user->info['assigned_markets']) . "'";
	else
		$market_string = "''";

	// set user['fullName']
	$user->info['fullName'] = $user->info['firstName'] . " " . $user->info['lastName'];

	// put query together
	$query = "SELECT b.poc_name, b.poc_number, b.poc_email, c.number, c.email, d.jha_revision, " . constant('grid_columns_string') . " 
				FROM fst_grid a
				LEFT JOIN fst_locations b
					ON a.location_id = b.id
				LEFT JOIN fst_contacts c
					ON a.customer_pm = c.project_lead 
				LEFT JOIN (SELECT quote_number, MAX(revision) as 'jha_revision' FROM jha_form GROUP BY quote_number) d
					ON a.quoteNumber = d.quote_number
				WHERE (a.quoteStatus LIKE 'Award%' OR (a.projectLead = '" . $user->info['fullName'] . "' AND a.quoteStatus NOT LIKE 'Dead%' AND a.quoteStatus NOT IN ('Forecast', 'Archived')))
				AND (
						(
						LEFT(a.market, 4) IN (" . $market_string . ") 
						OR a.quoteNumber IN (SELECT quoteNumber FROM fst_grid_tech_assignments WHERE tech = '" . $user->info['fullName'] . "')
						OR a.projectLead = '" . $user->info['fullName'] . "'
						OR a.opsLead = '" . $user->info['fullName'] . "'
						OR a.programCoordinator = '" . $user->info['fullName'] . "'
						)
					OR (
						a.quoteNumber IN (SELECT quoteNumber FROM fst_grid_tech_assignments WHERE tech IN " . $supervisees_string . ")
						OR a.projectLead IN " . $supervisees_string . "
						OR a.opsLead IN " . $supervisees_string . "
						OR a.programCoordinator IN " . $supervisees_string . "
						)
					)
				AND (a.ops_complete_timestamp >= (NOW() - INTERVAL 30 DAY) OR a.fst_status <> 'Complete')
				GROUP BY a.quoteNumber
				ORDER BY a.lastUpdate DESC;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		$rows['assigned'] = 0;
		array_push($return_obj['grid'], $rows);
	}

	//get comma seperated list of potential quotes
	$quotes = "'" . join("','", array_column($return_obj['grid'], 'quoteNumber')) . "'";
	$project_nums = "'" . join("','", array_column($return_obj['grid'], 'vpProjectNumber')) . "'";

	//load in related quotes
	$return_obj['related_projects'] = [];

	$query = "SELECT quoteNumber, vpProjectNumber, phaseName, fst_status, quoteStatus FROM fst_grid WHERE vpProjectNumber IN (" . $project_nums . ") AND quoteStatus <> 'Archived';";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['related_projects'], $rows);
	}

	//load in service requests (task list)
	$return_obj['service_requests'] = [];

	$query = "SELECT b.*, a.* FROM fst_grid_service_request b
				LEFT JOIN fst_grid a
					ON b.quoteNumber = a.quoteNumber
				WHERE b.group = 'Ops' AND b.status <> 'Complete' AND
				(
					(
					LEFT(a.market, 4) IN (" . $market_string . ") 
					OR a.quoteNumber IN (SELECT quoteNumber FROM fst_grid_tech_assignments WHERE tech = '" . $user->info['fullName'] . "')
					OR a.projectLead = '" . $user->info['fullName'] . "'
					OR a.opsLead = '" . $user->info['fullName'] . "'
					OR a.programCoordinator = '" . $user->info['fullName'] . "'
					)
				OR (
					a.quoteNumber IN (SELECT quoteNumber FROM fst_grid_tech_assignments WHERE tech IN " . $supervisees_string . ")
					OR a.projectLead IN " . $supervisees_string . "
					OR a.opsLead IN " . $supervisees_string . "
					OR a.programCoordinator IN " . $supervisees_string . "
					)
				);";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['service_requests'], $rows);
	}

	//load in stored shipping addresses based on quotes loaded in from grid
	$return_obj['shipping_addresses'] = [];

	$query = "SELECT * FROM fst_grid_address WHERE quoteNumber IN (" . $quotes . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['shipping_addresses'], $rows);
	}

	//load in BOM based on quotes loaded in from grid
	$return_obj['boms'] = [];

	$query = "SELECT id, type, quoteNumber, partNumber, manufacturer, quantity, allocated FROM fst_boms WHERE quoteNumber IN (" . $quotes . ") ORDER BY mmd desc, partCategory;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['boms'], $rows);
	}

	//load in tech's assigned to quotes
	$return_obj['tech_assignments'] = [];

	$query = "SELECT * FROM fst_grid_tech_assignments WHERE quoteNumber IN (" . $quotes . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['tech_assignments'], $rows);
	}

	//load in pq_overview based on quotes
	$return_obj['pq_ids'] = [];

	$query = "SELECT id FROM fst_pq_overview WHERE quoteNumber IN (" . $quotes . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['pq_ids'], $rows['id']);
	}

	//get comma seperated list of pq_ids
	$pq_ids = "'" . join("','", $return_obj['pq_ids']) . "'";

	//load in pq_detail based on pq_ids
	$return_obj['pq_detail'] = [];

	$query = "SELECT b.quoteNumber, a.id, a.project_id, a.part_id, a.quantity, a.status, a.decision, a.mo_id, a.po_number, a.shipment_id, a.ship_request_id, a.received_qty, a.received_staged_loc, a.shop_staged, a.wh_container
				FROM fst_pq_detail a
				LEFT JOIN fst_pq_overview b 
					ON a.project_id = b.id 
				WHERE a.project_id IN (" . $pq_ids . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['pq_detail'], $rows);
	}

	//load tracking info from fst_allocations_mo
	$return_obj['allocations_mo'] = [];

	$query = "select id, mo_id, tracking, ship_to from fst_allocations_mo WHERE pq_id IN (" . $pq_ids . ") AND mo_id <> 'PO';";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['allocations_mo'], $rows);
	}

	//load tracking info from fst_pq_orders_shipments
	$return_obj['pq_shipments'] = [];

	$query = "select a.shipment_id, a.tracking, b.po_ship_to from fst_pq_orders_shipments a, fst_pq_orders b WHERE a.po_number IN (select po_number from fst_pq_orders_assignments WHERE pq_id IN(" . $pq_ids . ")) AND a.po_number = b.po_number;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['pq_shipments'], $rows);
	}

	//load tracking info from fst_pq_ship_requests
	$return_obj['ship_requests'] = [];

	$query = "SELECT id, tracking, ship_location FROM fst_pq_ship_request WHERE id IN (SELECT ship_request_id FROM fst_pq_detail WHERE project_id IN(" . $pq_ids . ") AND ship_request_id IS NOT NULL);";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['ship_requests'], $rows);
	}

	// Load logs from user class
	$return_obj['logs'] = $user->get_user_specific_dashboard_logs($quotes, $market_string);

	//load in notes (order by id most recent)
	$return_obj['notes'] = [];

	$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) AS name, a.* FROM fst_notes a, fst_users b WHERE a.user = b.id AND a.quoteNumber IN (" . $quotes . ") ORDER BY a.id DESC;";

	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['notes'], $rows);
	}

	// Load in material notification preferences
	$return_obj['material_notifications'] = [];
	$query = "SELECT * FROM fst_users_notifications_parts WHERE quoteNumber IN (" . $quotes . ") AND user_id = '" . $user->info['id'] . "';";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['material_notifications'], $rows);
	}

	return $return_obj;
}

/**
 * Handles grabbing views releated to fse dashboard
 * @param mixed $con SQL Connection
 * @param mixed $user object that matches row from fst_users db
 * @return mixed object of arrays of objects (views of multiple sql tables needed to run page)
 */
function get_fse_views($con, $user)
{

	// set object to be returned
	$return_obj = [];

	// load in grid
	$return_obj['grid'] = [];

	// if this user is a manager, create an array based on assigned markets
	if ($user->info['manager'] == "checked")
		$market_string = "'" . str_replace(",", "','", $user->info['assigned_markets']) . "'";
	else
		$market_string = "''";

	//only accept tasks that are not blank or denoted with no service request & if a status is complete, only include if within the last 30 days
	$query = "SELECT d.*, b.poc_name, b.poc_number, b.poc_email, c.number, c.email, " . constant('grid_columns_string') . "
				FROM fst_grid_service_request d
				LEFT JOIN fst_grid a
					ON d.quoteNumber = a.quoteNumber
				LEFT JOIN fst_locations b
					ON a.location_id = b.id
				LEFT JOIN fst_contacts c
					ON a.customer_pm = c.project_lead 
				WHERE d.group = 'FSE' AND ((d.task = 'Complete' AND d.timestamp_completed >= (NOW() - INTERVAL 30 DAY)) OR (d.task <> 'Complete'))
				GROUP BY d.id
				ORDER BY a.lastUpdate DESC;";

	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		$rows['assigned'] = 0;
		array_push($return_obj['grid'], $rows);
	}

	//get comma seperated list of potential quotes / location IDs / customers
	$quotes = "'" . join("','", array_column($return_obj['grid'], 'quoteNumber')) . "'";
	$location_ids = "'" . join("','", array_column($return_obj['grid'], 'location_id')) . "'";
	$customers = custom_join(array_column($return_obj['grid'], 'customer'));

	//load in fst_locations
	$return_obj['fst_locations'] = [];
	$query = "SELECT id, description, poc_name, poc_number, poc_email FROM fst_locations WHERE id IN (" . $location_ids . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['fst_locations'], $rows);
	}

	//load in customer contact info
	$return_obj['fst_contacts'] = [];
	$query = "SELECT * FROM fst_contacts WHERE customer IN (" . $customers . ");";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['fst_contacts'], $rows);
	}

	// Load logs from user class
	$return_obj['logs'] = $user->get_user_specific_dashboard_logs($quotes, $market_string);

	//load in notes (order by id most recent)
	$return_obj['notes'] = [];
	$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) AS name, a.* FROM fst_notes a, fst_users b WHERE a.user = b.id AND a.quoteNumber IN (" . $quotes . ") ORDER BY a.id DESC;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['notes'], $rows);
	}

	//grab fse requests
	$return_obj['remote_support_requests'] = [];
	$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) as 'requestor', a.* FROM fst_cop_engineering_data_submission a
				LEFT JOIN fst_users b
					ON a.user = b.id
				WHERE a.quoteNumber IN (" . $quotes . ");";
	$result = mysqli_query($con, $query);

	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['remote_support_requests'], $rows);
	}

	return $return_obj;
}

/**
 * Handles grabbing views releated to fse dashboard
 * @param mixed $con SQL Connection
 * @param mixed $user object that matches row from fst_users db
 * @return mixed object of arrays of objects (views of multiple sql tables needed to run page)
 */
function get_cop_views($con, $user)
{

	// set object to be returned
	$return_obj = [];

	// load in grid
	$return_obj['grid'] = [];

	// if this user is a manager, create an array based on assigned markets
	if ($user->info['manager'] == "checked")
		$market_string = "'" . str_replace(",", "','", $user->info['assigned_markets']) . "'";
	else
		$market_string = "''";

	//only accept tasks that are not blank or denoted with no service request & if a status is complete, only include if within the last 30 days
	$query = "SELECT e.*, b.poc_name, b.poc_number, b.poc_email, c.number, c.email, " . constant('grid_columns_string') . "
				FROM fst_grid_service_request e
				LEFT JOIN fst_grid a
					ON e.quoteNumber = a.quoteNumber
				LEFT JOIN fst_locations b
					ON a.location_id = b.id
				LEFT JOIN fst_contacts c
					ON a.customer_pm = c.project_lead
				WHERE e.group = 'COP' AND ((e.task = 'Complete' AND e.timestamp_completed >= (NOW() - INTERVAL 30 DAY)) OR (e.task <> 'Complete'))
				GROUP BY e.id
				ORDER BY a.lastUpdate DESC;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		$rows['assigned'] = 0;
		array_push($return_obj['grid'], $rows);
	}

	//get comma seperated list of potential quotes / location IDs / customers
	$quotes = "'" . join("','", array_column($return_obj['grid'], 'quoteNumber')) . "'";
	$location_ids = "'" . join("','", array_column($return_obj['grid'], 'location_id')) . "'";
	$customers = custom_join(array_column($return_obj['grid'], 'customer'));

	// load in projects currently in install
	$return_obj['install_grid'] = [];

	//only accept tasks that are not blank or denoted with no service request & if a status is complete, only include if within the last 30 days
	$query = "SELECT * FROM fst_grid WHERE quoteNumber NOT IN (" . $quotes . ") AND fst_status = 'Installation';";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['install_grid'], $rows);
	}

	// Load logs from user class
	$return_obj['logs'] = $user->get_user_specific_dashboard_logs($quotes, $market_string);

	//load in notes (order by id most recent)
	$return_obj['notes'] = [];
	$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) AS name, a.* FROM fst_notes a, fst_users b WHERE a.user = b.id AND a.quoteNumber IN (" . $quotes . ") ORDER BY a.id DESC;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['notes'], $rows);
	}

	return $return_obj;
}

/**
 * Handles grabbing views releated to design dashboard
 * @param mixed $con SQL Connection
 * @param mixed $user object that matches row from fst_users db
 * @return mixed object of arrays of objects (views of multiple sql tables needed to run page)
 */
function get_design_views($con, $user)
{

	// set object to be returned
	$return_obj = [];

	// load in grid
	$return_obj['grid'] = [];

	// get list of supervisees
	$user->set_supervising();
	$supervisees_string = "('" . implode("','", $user->supervising) . "')";

	// if this user is a manager, create an array based on assigned markets
	if ($user->info['manager'] == "checked")
		$market_string = "'" . str_replace(",", "','", $user->info['assigned_markets']) . "'";
	else
		$market_string = "''";

	//only accept tasks that are not blank or denoted with no service request & if a status is complete, only include if within the last 30 days
	$query = "SELECT b.*, " . constant('grid_columns_string') . "
				FROM fst_grid_service_request b
				LEFT JOIN fst_grid a
					ON b.quoteNumber = a.quoteNumber
				WHERE b.group = 'Design' AND 
						(b.status <> 'Complete' OR b.timestamp_completed > now() - INTERVAL 30 day) AND 
						(
							LEFT(a.market, 4) IN (" . $market_string . ") OR
							(b.personnel IN " . $supervisees_string . " OR b.personnel = '') OR
							(a.designer = '" . $user->info['fullName'] . "' OR a.quoteCreator = '" . $user->info['fullName'] . "')
						)
				ORDER BY b.due_date DESC;";

	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {

		// Update market if null
		if (is_null($rows['market']))
			$rows['market'] = "";

		$rows['assigned'] = 0;
		array_push($return_obj['grid'], $rows);
	}

	//get comma seperated list of potential quotes / location IDs / customers
	$quotes = "'" . join("','", array_column($return_obj['grid'], 'quoteNumber')) . "'";

	//load in notes (order by id most recent)
	$return_obj['notes'] = [];
	$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) AS name, a.* FROM fst_notes a, fst_users b WHERE a.user = b.id AND a.quoteNumber IN (" . $quotes . ") ORDER BY a.id DESC;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($return_obj['notes'], $rows);
	}

	// Load logs from user class
	$return_obj['logs'] = $user->get_user_specific_dashboard_logs($quotes, $market_string);

	return $return_obj;
}

/**
 * Handles custom joining array & surrounding with quotations (used to get search string for sql query from array)
 * @param array $array
 */
function custom_join($array)
{

	// initialize string to be returned
	$joined_string = "";

	// return if array is empty
	if (sizeof($array) == 0)
		return "''";

	// reduce array to only unique values
	$array = array_unique($array);

	// loop through array & 'join'
	foreach ($array as $element) {

		// escape element
		$element = mysql_escape_mimic($element);

		// wrap element in single quotations
		$element = "'" . $element . "'";

		// push to $joined_string
		$joined_string .= $element . ", ";
	}

	// string off last 2 characters
	$joined_string = substr($joined_string, 0, strlen($joined_string) - 2);

	return $joined_string;
}
