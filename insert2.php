<?php

//database configuration file
$dbHost     = "pw-sql-lab.mysql.database.azure.com";
$dbUsername = "pwadmin@pw-sql-lab";
$dbPassword = "h@rdhat-b0Rax-eas1ly-stony";
$dbName     = "fst_test_db";
$port       = 3306;

//establish connection to sql database
$con = mysqli_init();
mysqli_real_connect($con, $dbHost, $dbUsername, $dbPassword, $dbName, $port);
$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

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
    // Retrieve the input data from the form
    $customer = $_POST['customer'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $zip = $_POST['zip'];

    // Call the stored procedure
    $query = "CALL InsertCustomerAndLocation('$customer', '$address', '$city', '$state', '$zip')";
    $result = mysqli_query($con, $query);


//for response if needed

    $return_arr = array();
    while ($row = mysqli_fetch_array($result)) {
        $zip = $row['zip'];


        $return_arr[] = array(
            "zip" => $zip,

        );
    }

    // Encoding array in JSON format
    $json_response = json_encode($return_arr);

    // Send JSON response to AJAX call
    echo $json_response;
    // Execute the stored procedure
    if ($con->query($query) === TRUE) {
        // Get the last inserted customer ID
        echo ('Success');
    }
}


// Store the checkedCount value in a variable
$checkedCountValue = $checkedCount;

// Prepare and execute the query to insert the value into the table
$query = "INSERT INTO rom_tool_checkbox_values (checked_count_values) VALUES ('$checkedCountValue')";
$result = mysqli_query($connection, $query);

if ($result) {
    echo 'Checked count value stored successfully.';
} else {
    echo 'Error storing checked count value: ' . mysqli_error($connection);
}


// Get the checkbox values from the AJAX request
$checkboxValues = $_POST['values'];

// Prepare the SQL statement to call the stored procedure
$stmt = $con->prepare("CALL InsertCheckboxData(?, @checkboxValueSum)");
$stmt->bind_param("s", $checkboxValues);

// Execute the stored procedure
$stmt->execute();

// Get the value of the output parameter
$result = $con->query("SELECT @checkboxValueSum AS checkboxValueSum");
$row = $result->fetch_assoc();
$checkboxValueSum = $row['checkboxValueSum'];

// Check if the stored procedure call was successful
if ($checkboxValueSum !== null) {
    echo "Checkbox value sum: " . $checkboxValueSum;
} else {
    echo "Error executing stored procedure.";
}


$con->close();

?>





