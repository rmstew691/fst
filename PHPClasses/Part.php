<?php

// include dependencies
include_once('constants.php');

class part
{
    public $id;
    public $info;
    private $con;
    public $quoteNumber;
    public $manual_part;
    public $added;
    public $removed;

    function __construct($partNumber, $con, $id = null)
    {
        $this->con = $con;

        //set id if not null
        if ($id != null)
            $this->id = $id;
        else
            $this->id = -1;

        //set all attributes based on inventory/fst_boms 
        $query = "SELECT * FROM invreport WHERE partNumber = '" . mysql_escape_mimic($partNumber) . "';";
        $result = mysqli_query($this->con, $query);

        //if we find a match, set attributes based on invreport
        if (mysqli_num_rows($result) > 0) {
            $invreport = mysqli_fetch_array($result);
            $this->info = $invreport;
            $this->manual_part = false;

            //if this is a kit, update the kit cost
            if ($this->info['partCategory'] == "PW-KITS")
                $this->info['cost'] = $this->get_kit_cost();
        } else {
            $this->manual_part = true;
            $this->info['matL'] = -1;
            $this->info['partCategory'] = "";
            $this->info['manufacturer'] = "";
        }

        //if id not null, update some attributes to what is in inventory
        if ($id != null) {
            $query = "SELECT * FROM fst_boms WHERE id = '" . $id . "';";
            $result = mysqli_query($this->con, $query);

            if (mysqli_num_rows($result) > 0) {
                $fst_boms = mysqli_fetch_array($result);
                $this->info['cost'] = $fst_boms['cost'];
                $this->info['price'] = $fst_boms['price'];
                $this->info['partNumber'] = $fst_boms['partNumber'];
                $this->info['partCategory'] = $fst_boms['partCategory'];
                $this->info['manufacturer'] = $fst_boms['manufacturer'];
                $this->quoteNumber = $fst_boms['quoteNumber'];
            }
        }
    }

    //get material logistics cost for a part
    function get_material_logistics()
    {

        //check part category (if this is a kit, we need to calculate material logistics cost for each part and return)
        if ($this->info['partCategory'] == "PW-KITS") {

            //reset matL
            $this->info['matL'] = 0;

            //get list of parts in this kit
            $query = "SELECT * FROM fst_bom_kits_detail WHERE kit_id = '" . mysql_escape_mimic($this->info['partNumber']) . "';";
            $result = mysqli_query($this->con, $query);

            //cycle and add individual part matL to the total of the kit
            while ($rows = mysqli_fetch_assoc($result)) {
                $temp_part = new part($rows['partNumber'], $this->con);
                $this->info['matL'] += ($temp_part->get_material_logistics() * $rows['quantity']);
            }
        }

        //if we have a set matL cost, return
        if ($this->info['matL'] != -1)
            return  $this->info['matL'];

        //otherwise, calculate based on current matL 
        $query = "SELECT `value` FROM fst_intcalcs WHERE intCalcs = 'matLogisticPerc';";
        $result = mysqli_query($this->con, $query);
        $matL_perc = mysqli_fetch_array($result);

        return round($this->info['cost'] * $matL_perc['value'], 2);
    }

    //adds a log to invreport_logs
    function log_part_update($user_id, $description, $type, $status = "")
    {

        //include database config
        include('config.php');

        //step1: create error log and save to database
        $query = "INSERT INTO invreport_logs (id, partNumber, description, user, type, status, time_stamp) VALUES (null, '" . mysql_escape_mimic($this->info['partNumber']) . "', '" . mysql_escape_mimic($description) . "', '" . $user_id . "', '" . $type . "', '" . $status . "', NOW());";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        //step2: update last activity on part (only for certain types)
        //ME = material entry
        //MT = material transfer
        //WP = warehouse processing
        //REC = part received
        //PO = Purchase Order
        //LU = Location Update
        $log_types = ["ME", "MT", "WP", "REC", "PO"];

        if (in_array($type, $log_types)) {
            $query = "UPDATE inv_locations SET last_activity = NOW() WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "';";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
        }

        //check inventory and send out email to dustin & chad if below minimum amount
        //$this->check_minimum_inventory();
    }

    //handles checking minimum stock levels and sending email if below certain threshold
    function check_minimum_inventory()
    {

        //to be updated

    }

    //updates log status
    function update_log_status($user_id, $log_id, $status)
    {

        //include database config
        include('config.php');

        //get user from user_id
        $query = "SELECT CONCAT(firstName, ' ', lastName) as user FROM fst_users WHERE id = '" . $user_id . "';";
        $result = mysqli_query($this->con, $query);
        $fst = mysqli_fetch_array($result);

        //step1: create error log and save to database
        $query = "UPDATE invreport_logs SET description = CONCAT(description, ' (', '" . $fst['user'] . "', ')'), status = '" . $status . "' WHERE id = '" . $log_id . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    //gets phase code based on category
    function get_phase_code()
    {

        //define active categories
        $active_cat = ["ACT-DASHE", "ACT-DASREM", "REPTR-BDA", "ASiR", "CBRS_CBSD", "ALU-BTS", "ERCSNDOT", "ERCSN-ENDB", "JMA-XRAN", "MODEMS", "NETW-EQUIP", "NOKIA-MM", "PS-REAPTER", "SFP-CARD", "SMCL-PICOC", "SPDRCLOUD", "WIFIAP&HDW", "PLTE-EPC", "PLTE-EUD", "PLTE-RAN", "PLTE-SAS", "PLTE-SIMS", "PS-REAPTER", "SAM-BTS"];

        if ($this->info['partCategory'] == "PW-KITS")
            return $this->get_kit_phase();
        elseif (in_array($this->info['partCategory'], $active_cat))
            return "03000"; //active phase code in viewpoint
        else
            return "06000"; //passive phase code in viewpoint

    }

    //used to get kit_phase (stored in fst_bom_kits)
    function get_kit_phase()
    {

        //query to get info about kit
        $query = "SELECT phase FROM fst_bom_kits WHERE kit_part_id = '" . $this->info['partNumber'] . "';";
        $result = mysqli_query($this->con, $query);
        $get = mysqli_fetch_array($result);

        if ($get['phase'] == "Active")
            return "03000";
        else
            return "06000";
    }

    //validates that the material category is able to support reel assignments (& assigns reel abv)
    function validate_reel_category()
    {

        //get list of categories approved
        $query = "SELECT * FROM inv_reel_categories WHERE category = '" . $this->info['partCategory'] . "';";
        $result = mysqli_query($this->con, $query);

        //if we return results, set info['id_abbv'] and return true
        if (mysqli_num_rows($result) > 0) {
            $get = mysqli_fetch_array($result);
            $this->info['id_abbv'] = $get['id_abbv'];
            return true;
        }

        //otherwise return false
        return false;
    }

    /**
     * assigns new reel and returns to user
     * @param int $type (0 = regular, 1 = bulk)
     * @return string $next_id (the newly assigned ID)
     */
    function assign_reel_id($type)
    {

        //get list of categories approved
        $query = "SELECT SUBSTRING(id, 3) AS id_num FROM inv_reel_assignments WHERE id LIKE '" . $this->info['id_abbv'] . "%' ORDER BY cast(id_num as unsigned) asc;";
        $result = mysqli_query($this->con, $query);

        //set ID (defaults for 0 results, incremented till we find a break otherwise)
        $id = 1;

        //if we return results, set info['id_abbv'] and return true
        if (mysqli_num_rows($result) > 0) {

            //loop through all results, break when find an inconsistency
            while ($rows = mysqli_fetch_assoc($result)) {

                //check if IDs match
                if ($id != intval($rows['id_num']))
                    break;

                //otherwise increment ID and continue search
                $id++;
            }
        }

        //set next ID to be saved & returned
        $next_id = $this->info['id_abbv'] . strval($id);

        //store id in database
        $query = "INSERT INTO inv_reel_assignments (id, bulk, partNumber, shop) 
                                            VALUES ('" . $next_id . "', '" . $type . "', '" . $this->info['partNumber'] . "', '" . $this->info['shop'] . "');";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        return $next_id;
    }

    //updates reel information based on current reel assignments
    //param 1 = array of objects with reel info (each array row has attributes id, quantity, loc which relate to rows in inv_reel_assignments)
    function update_reel_info($reel_detail)
    {

        //loop through reel detail and update each ID with current info
        for ($i = 0; $i < sizeof($reel_detail); $i++) {

            $query = "UPDATE inv_reel_assignments 
                        SET quantity = '" . $reel_detail[$i]['quantity'] . "', location = '" . $reel_detail[$i]['loc'] . "'
                        WHERE id = '" . $reel_detail[$i]['id'] . "';";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
        }

        return;
    }

    //get array of objects related to a kit id
    function get_kit_detail($extra_detail = false)
    {

        //get list of parts in this kit
        $kit_detail = [];

        // Set query depending on if we need extra detail for parts or not
        if ($extra_detail)
            $query = "SELECT a.*, b.partCategory, b.partDescription 
                        FROM fst_bom_kits_detail a
                        LEFT JOIN invreport b
                            ON a.partNumber = b.partNumber
                        WHERE a.kit_id = '" . mysql_escape_mimic($this->info['partNumber']) . "';";
        else
            $query = "SELECT * FROM fst_bom_kits_detail WHERE kit_id = '" . mysql_escape_mimic($this->info['partNumber']) . "';";

        $result = mysqli_query($this->con, $query);

        //cycle and add individual part matL to the total of the kit
        while ($rows = mysqli_fetch_assoc($result)) {
            array_push($kit_detail, $rows);
        }

        return $kit_detail;
    }

    //get cost of kit
    function get_kit_cost()
    {

        //get list of parts in this kit
        $kit_detail = [];
        $query = "SELECT * FROM fst_bom_kits_detail WHERE kit_id = '" . mysql_escape_mimic($this->info['partNumber']) . "';";
        $result = mysqli_query($this->con, $query);

        //init cost
        $cost = 0;

        //cycle and add individual part matL to the total of the kit
        while ($rows = mysqli_fetch_assoc($result)) {
            $temp_part = new Part($rows['partNumber'], $this->con);
            $cost += floatval($temp_part->info['cost']);
        }

        return $kit_detail;
    }

    // adds list of parts to a parts request
    // param 1 = list of kit parts (see fst_bom_kits_detail)
    // param 2 = parts request ID
    function add_kit_detail_to_parts_request($kit_detail, $quantity, $subs, $mmd, $note, $pq_id)
    {

        //loop through all kit detail and add to request
        for ($j = 0; $j < sizeof($kit_detail); $j++) {

            //create quantity based on part quantity & kit quantity
            $q_requested = intval($kit_detail[$j]['quantity'])  * intval($quantity);

            //create & execute query
            $query = "INSERT INTO fst_pq_detail (project_id, part_id, kit_id, quantity, q_allocated, subs, mmd, instructions) 
                                        VALUES ('" . $pq_id . "', '" . mysql_escape_mimic($kit_detail[$j]['partNumber']) . "', 
                                        '" . mysql_escape_mimic($this->info['partNumber']) . "', 
                                        '" . $q_requested . "', '" . $q_requested . "', 
                                        '" . $subs . "', '" . $mmd . "', 
                                        '" . $note . "');";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
            $get_id = mysqli_insert_id($this->con);

            //check if this is a kit (if so, we need to expand & add_kit_detail for this as well)
            $temp_part = new Part($kit_detail[$j]['partNumber'], $this->con);

            if ($temp_part->info['partCategory'] == "PW-KITS") {

                //update pq_detail kit_tell
                $query = "UPDATE fst_pq_detail SET kit_tell = 'Yes', send = 'false' WHERE id = '" . $get_id . "';";
                custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

                //get kit detail and add to request
                $temp_kit_detail = $temp_part->get_kit_detail();
                $temp_part->add_kit_detail_to_parts_request($temp_kit_detail, $q_requested, $subs, $mmd, $note, $pq_id);
            }
        }
    }

    /**
     * Adds a location into inv_locations db if not already available
     * @param mixed $shop
     * @return void
     */
    function add_inv_location($shop, $user_id)
    {

        // check if it exists in table
        $query = "SELECT * FROM inv_locations 
                    WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . mysql_escape_mimic($shop) . "';";
        $result = mysqli_query($this->con, $query);

        // if we don't return results, add new location
        if (mysqli_num_rows($result) == 0) {
            // get stock (if applicable)
            $stock = 0;
            if ($shop != "LMO" && $shop != "LPO" && $shop != "MIN-VZO" && $shop != "OMA-WS" && $shop != "IN-TRANSIT")
                $stock = $this->info[$shop];

            $query = "INSERT INTO inv_locations (`shop`, `partNumber`, `category`, `um`, `cost`, `last_cost`, `stock`, `allocated`, `lastCount`, `last_activity`, `min_stock`, `max_stock`, `min_primary`, `max_primary`)
                                        VALUES ('" . mysql_escape_mimic($shop) . "', '" . mysql_escape_mimic($this->info['partNumber']) . "', 
                                                '" . mysql_escape_mimic($this->info['partCategory']) . "', '" . mysql_escape_mimic($this->info['uom']) . "',
                                                '" . mysql_escape_mimic($this->info['cost']) . "', '" . mysql_escape_mimic($this->info['cost']) . "',
                                                '" . $stock . "', '0', '0000-00-00 00:00:00', NOW(), '0', '0', '0', '0');";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

            // log update
            $this->log_part_update($user_id, "Location Added (" . $shop . ")", "LU");
        }
    }

    /**
     * Removes a location from inv_locations db table if it exists
     * @param mixed $shop
     * @return void
     */
    function remove_inv_location($shop, $user_id)
    {

        // check if it exists in table
        $query = "SELECT * FROM inv_locations 
                    WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . mysql_escape_mimic($shop) . "';";
        $result = mysqli_query($this->con, $query);

        // if we return results, delete location
        if (mysqli_num_rows($result) > 0) {
            $query = "DELETE FROM inv_locations 
                        WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . mysql_escape_mimic($shop) . "';";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

            // log update
            $this->log_part_update($user_id, "Location Removed (" . $shop . ")", "LU");
        }
    }

    /***
     * 
     * 
     * THE NEXT FEW METHODS REQUIRE THAT info['shop'] has been set somewhere in code
     * PRIMARIY USED FOR terminal_warehouse_helper.php
     * list of functions:
     * 
     * update_on_hand()
     * update_physical_locations()
     * 
     */

    //handles updating on-hand for a given part # at a physical shop location
    //param 1 = new qty on hand
    //param 2 (optional) = log last count date
    function update_on_hand($new_on_hand)
    {

        //check shop is set
        if (!isset($this->info['shop'])) {
            echo "Error: Shop must be set to use this method.";
            return;
        }

        //write new on hand to database
        $query = "UPDATE invreport SET `" . $this->info['shop'] . "` = '" . $new_on_hand . "' WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        //update inv_locations as well
        $query = "UPDATE inv_locations SET stock = '" . $new_on_hand . "', `lastCount` = NOW() WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . $this->info['shop'] . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    //handles updating physical locations for a part # on hand
    //param 1 = array containing current list of physical locations
    function update_physical_locations($phys_locations)
    {

        //init arrays to hold removed/added items
        $added = [];
        $removed = [];

        //get current list of physical locations
        $curr_locations = [];
        $query = "SELECT location, quantity FROM invreport_physical_locations WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . $this->info['shop'] . "';";
        $result = mysqli_query($this->con, $query);

        //if we return results, loop and add to current list
        if (mysqli_num_rows($result) > 0) {
            while ($rows = mysqli_fetch_assoc($result)) {
                array_push($curr_locations, $rows);
            }
        }

        //check changes between old locations and new locations
        //first check for removed items
        for ($i = 0; $i < sizeof($curr_locations); $i++) {
            for ($j = 0; $j < sizeof($phys_locations); $j++) {
                //check match (location)
                if ($curr_locations[$i]['location'] == $phys_locations[$j]['loc'])
                    break;
            }

            //check $j, if equal to size, push to removed items
            if ($j == sizeof($phys_locations))
                array_push($removed, $curr_locations[$i]['location']);
        }

        //next check for added items
        for ($i = 0; $i < sizeof($phys_locations); $i++) {
            for ($j = 0; $j < sizeof($curr_locations); $j++) {
                //check match (location)
                if ($phys_locations[$i]['loc'] == $curr_locations[$j]['location'])
                    break;
            }

            //check $j, if equal to size, push to added items
            if ($j == sizeof($curr_locations))
                array_push($added, $phys_locations[$i]['loc']);
        }

        //update class variables
        $this->added = $added;
        $this->removed = $removed;

        //remove any prior locations from DB
        $query = "DELETE FROM invreport_physical_locations WHERE partNumber = '" . mysql_escape_mimic($this->info['partNumber']) . "' AND shop = '" . $this->info['shop'] . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        //loop through phys_locations and add to db table
        for ($i = 0; $i < sizeof($phys_locations); $i++) {
            $query = "INSERT INTO invreport_physical_locations (shop, partNumber, location, quantity, prime, last_update)
                                                        VALUES ('" . $this->info['shop'] . "', '" . mysql_escape_mimic($this->info['partNumber']) . "', 
                                                                '" . mysql_escape_mimic($phys_locations[$i]['loc']) . "', '" . $phys_locations[$i]['on_hand'] . "', 
                                                                '" . $phys_locations[$i]['primary'] . "', NOW());";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
        }
    }

    /** handles providing summary to user of any physical locations added/removed (array for each gathered in update_physical_locations) */
    // param 1 = type (added / removed) determines what type of summary we return
    function get_update_summary($type)
    {

        //init string to be returned
        $summary = "";

        //based on type, return string of summary
        if ($type == "added" && sizeof($this->added) > 0) {

            //add title
            $summary .= "<b>Added Physical Locations:</b> <br>";

            //loop through added array and push to string
            foreach ($this->added as $location) {
                $summary .= $location . "<br>";
            }

            //add 1 addition breakline
            $summary .= "<br>";
        } elseif ($type == "removed" && sizeof($this->removed) > 0) {
            //add title
            $summary .= "<b>Removed Physical Locations:</b> <br>";

            //loop through added array and push to string
            foreach ($this->removed as $location) {
                $summary .= $location . "<br>";
            }

            //add 1 addition breakline
            $summary .= "<br>";
        }

        //return final string
        return $summary;
    }

    /**
     * ENDING info['shop'] required functons
     */

    /*
    all methods from here out require an ID
    */

    //method to update cost & log cost (if applicable)
    function update_cost($new_cost, $part_quote, $part_quote_date)
    {

        //check ID (required for this)
        if ($this->id == -1)
            return;

        //log quote/date
        $query = "INSERT INTO fst_partlog (id, employeeID, quoteNumber, partQuote, quoteDate, partNumber, oldCost, newCost, dateEntered, type) 
                        VALUES (NULL, '" . $_SESSION['employeeID'] . "', '" . $this->quoteNumber . "', '" . $part_quote . "', '" . $part_quote_date . "', '" . mysql_escape_mimic($this->info['partNumber']) . "', '" . $this->info['cost'] . "', '" . $new_cost . "', NOW(), 'cost');";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        //update cost & get material logistics
        $this->info['cost'] = $new_cost;
        $matL = $this->get_material_logistics();

        //update query
        $query = "UPDATE fst_boms SET cost = '" . $new_cost . "', matL = '" . $matL . "' WHERE id = '" . $this->id . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    //method to update price & log price (if applicable)
    function update_price($new_price, $reason)
    {

        //check ID (required for this)
        if ($this->id == -1)
            return;

        //update query
        $query = "UPDATE fst_boms SET price = '" . $new_price . "' WHERE id = '" . $this->id . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        //log quote/date (if exists)
        $query = "INSERT INTO fst_partlog (id, employeeID, quoteNumber, partQuote, partNumber, oldCost, newCost, dateEntered, type)
                        VALUES (NULL, '" . $_SESSION['employeeID'] . "', '" . $this->quoteNumber . "', '" . $reason . "', '" . mysql_escape_mimic($this->info['partNumber']) . "', '" . $this->info['price'] . "', '" . $new_price . "', NOW(), 'price');";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    //method to set part to be ordered from allocations
    function set_to_order()
    {

        //check ID (required for this)
        if ($this->id == -1)
            return;

        //update query
        $query = "UPDATE fst_boms SET on_order = 1 WHERE id = '" . $this->id . "';";
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }
}
