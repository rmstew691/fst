<?php

// include dependencies
include_once('constants.php');

class Asset
{
    public $equipment_code;
    public $info;
    private $con;

    function __construct($con, $equipment_code = null)
    {
        // Set DB Connection
        $this->con = $con;

        // Set equipment_code if not null
        if ($equipment_code != null)
            $this->equipment_code = $equipment_code;
        else
            return;

        // Get attributes for equipment code
        $query = "SELECT * FROM equipment_code WHERE equipment_code = '" . mysql_escape_mimic($equipment_code) . "';";
        $result = mysqli_query($this->con, $query);

        //if we find a match, set attributes based on invreport
        if (mysqli_num_rows($result) > 0) {
            $this->info = mysqli_fetch_array($result);
        } else {
            echo "[Error] This equipment code does not exist.";
        }
    }

    //adds a log to invreport_logs
    function log_asset_update($user_id, $description, $type)
    {

        //include database config
        include('config.php');

        //step1: create error log and save to database
        $query = "INSERT INTO asset_logs (id, equipment_code, description, user, type, time_stamp) VALUES (null, '" . mysql_escape_mimic($this->info['partNumber']) . "', '" . mysql_escape_mimic($description) . "', '" . $user_id . "', '" . $type . "', NOW());";
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
    }

    /**
     * Handles creating new asset
     * @author Alex Borchers
     * @param mixed $user 
     * @param mixed $keys 
     * @param mixed $values 
     * @return void 
     */
    function create_new_asset($user, $keys, $values)
    {
        // Get equipment code based on asset category and subtype
        $values['equipment_code'] = $this->get_new_equipment_code($values['category'], $values['category_description']);

        // Add equipment_code to keys
        array_push($keys, "equipment_code");

        // Create insert query using keys and values
        $query = create_custom_insert_sql_query($values, $keys, "assets", false);
        custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

        // Set asset info
        $this->equipment_code = $values['equipment_code'];
        $query = "SELECT * FROM assets WHERE equipment_code = '" . mysql_escape_mimic($values['equipment_code']) . "';";
        $result = mysqli_query($this->con, $query);
        $this->info = mysqli_fetch_array($result);

        // Update created_by and created_timestamp
        //$query = "UPDATE assets SET created_by = '" . $user->id . "', created_timestamp = NOW() WHERE equipment_code = '" . $values['equipment_code'] . "';";
        //custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);
    }

    private function get_new_equipment_code($category, $subtype)
    {
        try {
            // Get first set of abbreviations based on category
            $query = "SELECT * FROM asset_categories WHERE category = '" . mysql_escape_mimic($category) . "';";
            $result = mysqli_query($this->con, $query);
            $category_info = mysqli_fetch_array($result);

            // Get subtype abbreviation based on subtype
            $query = "SELECT * FROM asset_category_subtypes 
                    WHERE subtype = '" . mysql_escape_mimic($subtype) . "' AND category_code = '" . $category_info['code'] . "';";
            $result = mysqli_query($this->con, $query);
            $subtype_info = mysqli_fetch_array($result);

            //echo $query;

            // Increase next id # by 1
            $query = "UPDATE asset_categories SET next_id = next_id + 1 
                    WHERE code = '" . mysql_escape_mimic($category_info['code']) . "';";
            custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

            // Adjust next_id to 4 digits
            $category_info['next_id'] = str_pad($category_info['next_id'], 4, "0", STR_PAD_LEFT);

            //echo $subtype_info['code'];

            // Return new equipment code
            $equipment_code = $category_info['code'] . "-" . $subtype_info['code'] . "-" . $category_info['next_id'];
            return $equipment_code;
        } catch (Exception $e) {
            echo $e->getMessage();
            return;
        }
    }
}
