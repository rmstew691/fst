<?php

// Establish a connection to the MySQL database
include("rt_config.php");

// Check if the necessary fields are set
if (isset(
    $_POST['customer'],
    $_POST['address'],
    $_POST['city'],
    $_POST['state'],
    $_POST['zip'],
    // $_POST['pocname'],
    // $_POST['pocnumber'],
    // $_POST['sqft'],
    // $_POST['floorNum'],
    // $_POST['facility_type'],
    // $_POST['building_density']
)) {
    // Retrieve the input field values
    $customer = $_POST['customer'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $zip = $_POST['zip'];
    // $pocname = $_POST['pocname'];
    // $pocnumber = $_POST['pocnumber'];
    // $sqft = $_POST['sqft'];
    // $floorNum = $_POST['floorNum'];
    // $facility_type = $_POST['facility_type'];
    // $building_density = $_POST['building_density'];

    // Prepare the SQL statement to insert the data into the table
    // $sql = "INSERT INTO rt_fst_grid (customer, address, city, state, zip, pocname, pocnumber, sqft, floorNum, facility_type, building_density)
    //         VALUES ('$customer', '$address', '$city', '$state', '$zip', '$pocname', '$pocnumber', '$sqft', '$floorNum', '$facility_type', '$building_density')";

    $query = "INSERT INTO rt_fst_locations (city, state, zip) VALUES ('$city', '$state', '$zip')";
    //$query = "INSERT INTO rt_fst_grid (customer,address) VALUES ('$customer', '$address')";

    // Execute the SQL statement
    if ($con->query($query) === TRUE) {
        // Fetch the inserted data from the table
        $selectQuery = "SELECT * FROM rt_fst_grid " . $con->insert_id;
        $result = $con->query($selectQuery);

        if ($result->num_rows > 0) {
            // Convert the fetched data to an associative array
            $row = $result->fetch_assoc();

            // Send the response back as JSON
            echo json_encode($row);
        } else {
            echo "No data found.";
        }
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}


// Close the database connection
$con->close();
