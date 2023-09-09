<?php

/**
 * This class is responsible for logging regular activity inside of the FST.
 * It is also responsible for checking relevant users notification settings and sending email or notifying active users inside of the system.
 */

// include dependencies
include_once('constants.php');
include_once('PHPClasses/User.php');

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
 * @author Alex Borchers
 */
class Notifications
{
  private $con;
  private $type;
  private $full;
  private $group;
  public $cc_groups;
  private $detail;
  private $quote;
  private $use;
  private $market;
  private $possible_users;
  private $users_sent_email;
  public $user;
  public $grid;
  public $email_send;


  public function __construct($con, $type, $detail, $quote, $use, $cc_groups = null)
  {
    $this->con = $con;
    $this->type = $type;
    $this->detail = $detail;
    $this->quote = $quote;
    $this->use = $use;
    $this->possible_users = [];
    $this->users_sent_email = [];

    // Get full description of notification
    $query = "SELECT full, group_name, cc_groups, email_send FROM fst_users_notifications_key WHERE id = '" . $type . "';";
    $result = mysqli_query($con, $query);
    $obj = mysqli_fetch_array($result);
    $this->full = $obj['full'];
    $this->group = $obj['group_name'];
    $this->cc_groups = $obj['cc_groups'];
    $this->email_send = $obj['email_send'];

    // Overwrite CC Groups if passed in
    if ($cc_groups != null) {
      $this->cc_groups = $cc_groups;
    }
  }

  /**
   * Handles logging new system notifications
   * @param mixed $type     check fst_users_notifications tables (not user_id) for options
   * @param mixed $detail   the detail of the log (specific to the file being used)
   * @param mixed $quote    the quote # that the log is associated with
   * @return void 
   */
  public function log_notification($user_id, $extra_email_body = null, $send_together = false)
  {

    // Set user ID 
    $this->user = new User($user_id, $this->con);

    // Create & execute query
    $query = "INSERT INTO fst_logs (quoteNumber, employeeID, type, description, detail, date) 
                            VALUES ('" . $this->quote . "', '" . $user_id . "', '" . mysql_escape_mimic($this->type) . "', 
                                    '" . mysql_escape_mimic($this->group) . "', '" . mysql_escape_mimic($this->detail) . "', 
                                    NOW());";
    custom_query($this->con, $query, $_SERVER['REQUEST_URI'], __LINE__);

    // Check PW personnel on job, send emails to those that have requested this notification
    $this->setup_email_notifications($extra_email_body, $send_together);
  }

  private function setup_email_notifications($extra_email_body, $send_together)
  {

    // Call query to get basic information about a project
    $query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $this->quote . "';";
    $result = mysqli_query($this->con, $query);
    $grid = mysqli_fetch_array($result);
    $this->grid = $grid;
    $this->market = substr($grid['market'], 0, 4);

    // Check if we are sending this notification based on quote status (some are only sent after a quote is moved out of created)
    if ($grid['quoteStatus'] == "Created" && $this->email_send == "After-Created")
      return;

    // Take different route depending on if notifications are going out together or seperate
    if ($send_together) {

      // Collect the list of all potential employees, only send 1 notification for all
      $send_to_emails = [];
      $this->get_pw_personnel($grid);
      $this->get_techs();
      $this->get_managers();

      // Get id's related to personnel members
      $query = "SELECT id FROM fst_users WHERE CONCAT(firstName, ' ', lastName) IN ('" . implode("', '", $this->possible_users) . "');";
      $result = mysqli_query($this->con, $query);

      // Loop through IDs, check user's preference related to this type of update, send email if relevant
      while ($rows = mysqli_fetch_assoc($result)) {
        $user = new User($rows['id'], $this->con);
        $notification_pref = $user->get_notification_preferences();
        if (str_contains($notification_pref[$this->type], "2") || str_contains($notification_pref[$this->type], "4")) {
          array_push($send_to_emails, $user->info['email']);
        }
      }

      // Look to see if we have any cc_groups setup
      if ($this->cc_groups != "") {
        $cc_array = explode(";", $this->cc_groups);
        foreach ($cc_array as $cc) {
          array_push($send_to_emails, $cc);
        }
      }

      // Call method to send email to full group
      $this->send_email_notification($this->user, false, $extra_email_body, $send_to_emails);
    } else {

      // Get PW personnel related to $quote
      $this->get_pw_personnel($grid);
      $this->get_techs();

      // Get id's related to personnel members
      $query = "SELECT id FROM fst_users WHERE CONCAT(firstName, ' ', lastName) IN ('" . implode("', '", $this->possible_users) . "');";
      $result = mysqli_query($this->con, $query);

      // Loop through IDs, check user's preference related to this type of update, send email if relevant
      while ($rows = mysqli_fetch_assoc($result)) {
        $user = new User($rows['id'], $this->con);
        $notification_pref = $user->get_notification_preferences();
        if (str_contains($notification_pref[$this->type], "2")) {
          $this->send_email_notification($user, false, $extra_email_body);
          array_push($this->users_sent_email, $rows['id']);
        }
      }

      // Clear possible users, Get managers related to $quote
      $this->possible_users = [];
      $this->get_managers();

      // Get id's related to managers
      $query = "SELECT id FROM fst_users WHERE CONCAT(firstName, ' ', lastName) IN ('" . implode("', '", $this->possible_users) . "');";
      $result = mysqli_query($this->con, $query);

      // Loop through IDs, check user's preference related to this type of update, send email if relevant
      while ($rows = mysqli_fetch_assoc($result)) {
        $user = new User($rows['id'], $this->con);
        $notification_pref = $user->get_notification_preferences();
        if (str_contains($notification_pref[$this->type], "4") && !in_array($rows['id'], $this->users_sent_email)) {
          $this->send_email_notification($user, true, $extra_email_body);
          array_push($this->users_sent_email, $rows['id']);
        }
      }

      // Look to see if we have any cc_groups setup
      if ($this->cc_groups != "") {
        $cc_array = explode(";", $this->cc_groups);
        foreach ($cc_array as $cc) {
          $user = new User(-1, $this->con);
          $user->info['email'] = $cc;
          $user->info['firstName'] = $cc;
          $user->info['lastName'] = $cc;
          $this->send_email_notification($user, true, $extra_email_body);
        }
      }
    }
  }

  private function get_pw_personnel($grid)
  {

    // Setup array that matches fst_grid columns with personnel
    $pw_personnel = ["designer", "projectLead", "quoteCreator", "programCoordinator", "opsLead", "fs_engineer", "asd_member", "cop_member"];

    // Loop through personnel member & check user settings
    foreach ($pw_personnel as $role) {
      if ($grid[$role] != "" && !in_array($grid[$role], $this->possible_users))
        array_push($this->possible_users, $grid[$role]);
    }
  }

  private function get_techs()
  {

    // Look at tech's assigned to job
    $query = "SELECT * FROM fst_grid_tech_assignments WHERE quoteNumber = '" . $this->quote . "';";
    $result = mysqli_query($this->con, $query);

    while ($rows = mysqli_fetch_assoc($result)) {
      if ($rows['tech'] != "" && !in_array($rows['tech'], $this->possible_users))
        array_push($this->possible_users, $rows['tech']);
    }
  }

  private function get_managers()
  {

    // Look at possible managers based on the market
    $query = "SELECT CONCAT(firstName, ' ', lastName) as 'full_name' FROM fst_users WHERE manager = 'Checked' AND assigned_markets LIKE '%" . $this->market . "%';";
    $result = mysqli_query($this->con, $query);

    while ($rows = mysqli_fetch_assoc($result)) {
      if (!in_array($rows['full_name'], $this->possible_users))
        array_push($this->possible_users, $rows['full_name']);
    }
  }

  public function send_email_notification($user, $manager_tell, $extra_email_body, $group = [])
  {

    // Initialize PHP Mailer Object
    $mail = new PHPMailer();
    $mail = init_mail_settings($mail);

    // Set who email is from
    $mail->setFrom("projects@piersonwireless.com", "Web FST Automated Email System"); //set from (name is optional)

    // Set address this email is going to
    if ($this->use == "test")
      $mail->addAddress($_SESSION['email']);     // send to testing developer
    elseif (sizeof($group) == 0)
      $mail->addAddress($user->info['email']);   // user
    else {
      foreach ($group as $member) {
        $mail->addAddress($member);   // user
      }

      // Update "from" and "reply to"
      $mail->setFrom($user->info['email'], $user->info['firstName'] . " " . $user->info['lastName']);
      $mail->AddReplyTo($user->info['email'], $user->info['firstName'] . " " . $user->info['lastName']);
    }

    //Content
    $mail->isHTML(true);
    $mail->Subject =  "[FST:" . $this->full . "] " . $this->grid['location_name'] . " " . $this->grid['phaseName'] . " (" . $this->quote . ")";
    $mail->Body = "FST System Notification:<br><br>";
    $mail->Body .= "<b>Type:</b> " . $this->full . "<br>";

    // Only add detail if applicable
    if ($this->detail != "")
      $mail->Body .= "<b>Detail:</b> " . $this->detail . "<br>";

    $mail->Body .= "<b>User:</b> " . $this->user->info['firstName'] . " " . $this->user->info['lastName'] . "<br>";
    $mail->Body .= "<b>Link:</b> https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $this->quote . "<br><br>";

    if ($extra_email_body !== null)
      $mail->Body .= $extra_email_body . "<br><br>";

    if (!$manager_tell)
      $mail->Body .= "<i>You received this email because you are subscribed to receive emails for '" . $this->full . "' on quotes that you are assigned to. To modify these settings, go the your profile <a href = 'https://pw-fst.northcentralus.cloudapp.azure.com/FST/home_profile.php' target='_blank'>here</a>.</i><br><br>";
    else
      $mail->Body .= "<i>You received this email because you are subscribed to receive emails for '" . $this->full . "' on quotes that you manage. To modify these settings, go the your profile <a href = 'https://pw-fst.northcentralus.cloudapp.azure.com/FST/home_profile.php' target='_blank'>here</a>.</i><br><br>";

    $mail->Body .= "Thank you,";
    $mail->send();

    //close smtp connection
    $mail->smtpClose();
  }
}
