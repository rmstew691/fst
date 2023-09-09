<?php

// include dependencies
include_once('constants.php');

// Initialize PHP Mailer (in case we need to send an email)
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

/**
 * Notification - Handles FST Notification & logging system
 *
 * @author Alex Borchers
 */
class User {
  private $id;
  public $info;
  private $con;
  public $supervising;

  public function __construct($id, $con) {
    $this->id = $id;
    $this->con = $con;

    // If $id passed is -1, this means user not recognized, set accessLevel to none
    if ($id != -1){
      $query = "SELECT * FROM fst_users WHERE id = '" . $id . "';";
      $result = mysqli_query($con, $query);
      $this->info = mysqli_fetch_array($result);
    }
    else{
      $this->info = [];
      $this->info['accessLevel'] = "None";
    }
  }

  public function get_notification_preferences() {
    $query = "SELECT * FROM fst_users_notifications WHERE user_id = '" . $this->info['id'] . "';";
    $result = mysqli_query($this->con, $query);

    // if we return results, send back array, otherwise, create array with 0 for all
    if (mysqli_num_rows($result) > 0)
      $pref =  mysqli_fetch_array($result, MYSQLI_ASSOC);
    else{
      $pref = [];
      $query = "SELECT id FROM fst_users_notifications_key;";
      $result = mysqli_query($this->con, $query);
      while($rows = mysqli_fetch_assoc($result)){
        $pref[$rows['id']] = "0";
      }
    }

    return $pref;
  }

  private function get_dashboard_notification_preferences() {
    
    // Get user preferences, initialize preference strings
    $user_pref = $this->get_notification_preferences();
    $assigned_string = "";
    $manager_string = "";

    // Go through each 
    foreach ($user_pref as $key => $value) {
      // Ignore user_id
      if ($key == "user_id")
        continue;
        
      // Check 1 & 3 to get assigned & manager preferences
      if (str_contains($value, "1"))
        $assigned_string.= "'" . $key . "',";
      if (str_contains($value, "3"))
        $manager_string.= "'" . $key . "',";
    }

    // Strip last char from each
    if ($assigned_string == "")
      $assigned_string = "''";
    else
     $assigned_string = substr($assigned_string, 0, strlen($assigned_string) - 1);
    if ($manager_string == "")
      $manager_string = "''";
    else
      $manager_string = substr($manager_string, 0, strlen($manager_string) - 1);

    // Create object with both to return
    $obj = [];
    $obj['assigned'] = $assigned_string;
    $obj['manager'] = $manager_string;
    return $obj;

  }

  /**
   * Generates log string to add to query for fst_logs
   * @author Alex Borchers
   * @return string 
   */
  private function get_required_log_string(){

    // Initialize log string, add to it based on results from query
    $req_log_string = "";
    $query = "SELECT * FROM fst_users_notification_defaults";
    $result = mysqli_query($this->con, $query);
    while($rows = mysqli_fetch_assoc($result)){
      $req_log_string.= "(a.type IN (";

      // Wrap string in quotations, replace commas with quotations
      $rows['types'] = str_replace(",", "','", $rows['types']);
      $req_log_string.= "'"  . $rows['types'] . "') AND c." . $rows['personnel'] . " = '" . $this->info['fullName'] . "') OR ";
    }

    // Remove last OR from string
    $req_log_string = substr($req_log_string, 0, strlen($req_log_string) - 4);
    return $req_log_string;

  }

  /**
   * Gets logs for user based on their notification preferences & required assignments
   * @param mixed $quotes 
   * @param mixed $market_string 
   * @return array 
   */
  public function get_user_specific_dashboard_logs($quotes, $market_string){

    // Initialize logs object, write & execute query, return logs
    $logs = [];
    $user_dashboard_preferences = $this->get_dashboard_notification_preferences();

    // Generate required logs based on user's personnel assignments
    $required_logs_string = $this->get_required_log_string();

    $query = "SELECT a.quoteNumber, a.description, a.detail, a.date, b.fullName, CONCAT(c.location_name, ' ' , c.phaseName) AS 'project_name' 
				FROM fst_logs a, fst_users b, fst_grid c 
				WHERE a.date >= (NOW() - INTERVAL 3 DAY) AND 
						a.employeeID = b.id AND a.quoteNumber = c.quoteNumber AND 
						a.quoteNumber IN (" . $quotes . ") 
						AND (
							a.type IN (" . $user_dashboard_preferences['assigned'] . ") OR
              (LEFT(c.market, 4) IN (" . $market_string . ") AND a.type IN (" . $user_dashboard_preferences['manager'] . ")) OR
              " . $required_logs_string . "
						)
            AND b.fullName <> '" . $this->info['fullName'] . "'
				ORDER BY a.id desc;";
    $result = mysqli_query($this->con, $query);
    while($rows = mysqli_fetch_assoc($result)){
      array_push($logs, $rows);
    }

    return $logs;

  }

  // get list of PW personnel that this user supervises
  public function set_supervising(){

    $supervising = [];
    $query = "select fullName from fst_users WHERE supervisor = '" . $this->info['firstName'] . " " . $this->info['lastName'] . "';";
    $result = mysqli_query($this->con, $query);

    // If we return something, loop through and push to array
    if (mysqli_num_rows($result) > 0){
      while($rows = mysqli_fetch_assoc($result)){
        array_push($supervising, $rows['fullName']);
      }
    }

    // Now we need to create a loop that will go through and further create a branch of supervisees
    for ($i = 0; $i < sizeof($supervising); $i++){
      
      $query = "select fullName from fst_users WHERE supervisor = '" . $supervising[$i] . "';";
      $result = mysqli_query($this->con, $query);

      // If we return a result, push to array (this should extend the array out to all users we need)
      if (mysqli_num_rows($result) > 0){
        while($rows = mysqli_fetch_assoc($result)){
          array_push($supervising, $rows['fullName']);
        }
      }
    }

    $this->supervising = $supervising;
  }
}