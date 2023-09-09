<?php

/******************************
 * 
This file is used to store PHP functions whose purpose is to render HTML
Current functions
1) create_navigation_bar (renders the header navigation bar, side nav bars, and PW logo for all pages in root directory)
2) create_select_options ()
 *
 ******************************/

/**
 * Renders the header navigation bar, side nav bars, and PW logo for all pages in root directory
 * @param array $header_names - array of header names (what appears on the tabs)
 * @param array $header_ids - header ids (the id's of the divs that each head opens on click)
 * @param string $save_function - save function (if there is info saved on the page, enter the save function here)
 * @param array $user - user (holds user specific info such as admin access or dashboard link)
 * @param string $default_open_id - default open id (optional parameter, used if user would like to specify a tab to be opened other than index 0)
 * @param array $header_redirect - holds redirect URLs (if the tabs are being used as links instead of tabs)
 * @return string
 */
function create_navigation_bar($header_names, $header_ids, $save_function = "", $user, $default_open_id = null, $header_redirect = null)
{

    //include any constants defined across multiple files
    include('constants.php');

    //init header format
    $header = "<ul class = 'nav-bar'>";

    //add hamburger icon
    $header .= "<li><a class   = 'tablink' onclick='open_nav()' style = 'padding-left: 1.2em;padding-right: 1em;' id = 'open_side_nav_icon'><i class='fa fa-bars'></i></a></li>\n";

    //if save_icon is true, add save icon
    if ($save_function != "")
        $header .= "<li><a class = 'tablink' id = 'save_button' onclick='" . $save_function . "' style = 'padding-left: 1.2em;padding-right: 1em;'><i class='fa fa-save'></i><sub id = 'save_confirmation'></sub></a></li>\n";

    //loop through arrays and add to header nav bar
    for ($i = 0; $i < sizeof($header_names); $i++) {

        // Check if user has passed redirect URLs ($header_redirect)
        if ($header_redirect !== null) {

            // Initialize action "onclick"
            $action = "onclick = 'change_tabs(\"" . $header_ids[$i] . "\", this, \"#114B95\")'";

            // if user has redirect set, change action to href on click
            if ($header_redirect[$i] != "")
                $action = "href = '" . $header_redirect[$i] . "'";

            //default first as "default open" (only if $default_open_id is null)
            if ($i == 0 && ($default_open_id == null || $default_open_id == ""))
                $header .= "<li><a " . $action . " class = 'tablink' id = 'defaultOpen'>" . $header_names[$i] . "</a></li>\n";
            elseif ($header_ids[$i] == $default_open_id)
                $header .= "<li><a " . $action . " class = 'tablink' id = 'defaultOpen'>" . $header_names[$i] . "</a></li>\n";
            else
                $header .= "<li><a " . $action . " class = 'tablink'>" . $header_names[$i] . "</a></li>\n";
        }
        // Otherwise, treat as regular tabs
        else {

            //default first as "default open" (only if $default_open_id is null)
            if ($i == 0 && ($default_open_id == null || $default_open_id == ""))
                $header .= "<li><a onclick = 'change_tabs(\"" . $header_ids[$i] . "\", this, \"#114B95\")' class = 'tablink' id = 'defaultOpen'>" . $header_names[$i] . "</a></li>\n";
            elseif ($header_ids[$i] == $default_open_id)
                $header .= "<li><a onclick = 'change_tabs(\"" . $header_ids[$i] . "\", this, \"#114B95\")' class = 'tablink' id = 'defaultOpen'>" . $header_names[$i] . "</a></li>\n";
            else
                $header .= "<li><a onclick = 'change_tabs(\"" . $header_ids[$i] . "\", this, \"#114B95\")' class = 'tablink'>" . $header_names[$i] . "</a></li>\n";
        }
    }

    //add user image and notification icon (only if applicable)
    if ($user['picture'] != "") {
        $header .= "<li style='float:right; width: 57px;' onclick = 'navbar_dropdown(\"user_options\")'>
                <a class='dropbtn'><img class = 'user_profile_image' src='" . $user['picture'] . "' referrerpolicy='no-referrer'/></a>
                <div class='dropdown-content' id='user_options'>
                    <a href='home_profile.php' class = 'notification_item'>Your Profile</a>
                    <a href='index.php' class = 'notification_item'>Sign out</a>
                </div>
            </li>";
        /*
        $header .= "<li class='dropdown' style='float:right' onclick = 'navbar_dropdown(\"myNotifications\")'>
                <a class='tablink dropbtn' id = 'notification_button'><i class='fa fa-bell'> <sub id = 'notification_count'>0</sub></i></a>
                <div class='dropdown-content' id='myNotifications'>
                    <a href='#' class = 'notification_item'>[Placeholder for notification]</a>
                    <a href='#' class = 'notification_item'>[Placeholder for notification]</a>
                    <a href='#' class = 'notification_item'>[Placeholder for notification]</a>
                </div>
            </li>";
        */
    }

    //if this is a fake user, add place to sign out of user
    if (isset($_SESSION['fake_user']) && $_SESSION['fake_user'])
        $header .= "<li style='float:right'><a class = 'tablink fake_user' href = 'index.php'>[WARNING] You are currently logged in as another user. Click here to sign back in.</a></li>";

    //close off list
    $header .= "</ul>";

    //add side-navigation bar to header
    $header .= "<div id='mySidenav' class='sidenav'>
                    <a href='javascript:void(0)' class='closebtn' onclick='close_nav()'>&times;</a>
                    <a href='" . $user['dashboard_link'] . "'>Go To Dashboard</a>
                    <a href='home.php'>View All Quotes</a>
                    <a href='newProject.php'>Create New Quote</a>
                    <a href='romNewProject.php'>ROM Tool</a>";


    //add admin to side bar if applicable
    if ($user['accessLevel'] == "Admin")
        $header .=  "<a href='admin.php'>Admin Dashboard</a>";

    //add kit_home to side bar if applicable
    if ($user['inventory_admin'] == "checked")
        $header .=  "<a href='kit_home.php'>Kit Home</a>";

    //if admin, add in all available dashboards
    if ($user['accessLevel'] == "Admin") {
        $header .=  "<a href='#' style = 'cursor: auto; margin-top: 3em; font-style: italic;'>Dashboards</a>";
        $header .=  "<a href='romNewProject.php'>ROM Tool</a>";
        $header .=  "<a href='home_des.php'>Design Dashboard</a>";
        $header .=  "<a href='home_ops.php'>Operations Dashboard</a>";
        $header .=  "<a href='home_cop.php'>COP Dashboard</a>";
        $header .=  "<a href='home_fse.php'>FSE Dashboard</a>";
        $header .=  "<a href='terminal_allocations_new.php'>Allocations Dashboard</a>";
        $header .=  "<a href='terminal_orders.php'>Orders Dashboard</a>";
        $header .=  "<a href='terminal_warehouse_main.php?shop=OMA'>OMA Warehouse Dashboard</a>";
        $header .=  "<a href='terminal_warehouse_main.php?shop=CHA'>CHA Warehouse Dashboard</a>";
    }

    // if user meets criteria for maintenance items, add in side nav
    if ($user['vendor_maintenance'] == "checked" || $user['allocations_admin'] == "checked" || $user['accessLevel'] == "Admin" || $user['assets_admin'] == "checked") {

        $header .= "<div class = 'extra_space'>";
        $header .=  "<a href='#' style = 'cursor: auto; margin-top: 3em; font-style: italic;'>Maintenance</a>";

        // add individual maintenance items
        if ($user['vendor_maintenance'] == "checked" || $user['accessLevel'] == "Admin")
            $header .=  "<a href='terminal_orders_vendors.php?'>Vendor Maintenance</a>";

        if ($user['allocations_admin'] == "checked" || $user['accessLevel'] == "Admin")
            $header .=  "<a href='terminal_hub.php?'>Inventory Admin</a>";

        if ($user['assets_admin'] == "checked" || $user['accessLevel'] == "Admin")
            $header .=  "<a href='terminal_assets.php?'>Assets Admin</a>";

        $header .= "</div>";
    }

    //close off sidenav
    $header .= "</div>";

    //add PW logo and link to feedback sheet
    $header .= "<a class = 'pw_logo_wrapper' href = '" . feedback_form_link . "' target='_blank'>";
    $header .= "<img src='images/PW_Std Logo_medium.png' alt='PW Corporate Logo' id = 'pw_logo'><br>";
    $header .= "<span id = 'pw_version'>FST Version " . $version . " </span>";
    $header .= "</a>";

    //<br><br><br>

    //<img src='images/PW_Std Logo.png' alt='PW Corporate Logo' id = 'pw_logo' width="6%" height="6%" style = "float:right;" >

    return $header;
}

/**
 * Renders list of options given an array and pointer (optional)
 * @param array $options - array of <options>
 * @param string $select - header ids (the id's of the divs that each head opens on click)
 * @param string $pointer (optional) - used if the array is an array of object
 * 
 * @return string $option_string - string of all options to be rendered as HTML list
 */
function create_select_options($options, $select = "", $pointer = null)
{

    // init string that holds options to be returned
    $option_string = "";

    // loop through array of options sent over
    if (is_null($pointer)) {
        foreach ($options as $option) {
            $option_string .= "<option ";

            // check if this option is to be selected
            if ($option == $select)
                $option_string .= "selected";

            // complete option string
            $option_string .= ">" . $option . "</option>";
        }
    } else {
        foreach ($options as $option) {
            $option_string .= "<option ";

            // check if this option is to be selected
            if ($option[$pointer] == $select)
                $option_string .= "selected";

            // complete option string
            $option_string .= "> " . $option[$pointer] . "</option>";
        }
    }

    return $option_string;
}

/**
 * Renders applicable <link> tags, <script> tags applicable for all pages
 * @author Alex Borchers
 * @return string 
 */
function get_html_head_data()
{
    $head = '<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />';
    $head .= '<link rel="stylesheet" href="stylesheets/element-styles.css?1" />';
    $head .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">';
    $head .= '<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">';
    $head .= '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">';
    $head .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>';

    return $head;
}
