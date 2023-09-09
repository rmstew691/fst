<?php

include "rt_config.php";

// Get the checkbox values from the AJAX request
$checkboxValues = $_POST['values'];

// Convert the checkbox values array to a comma-separated string
$checkboxValuesString = implode(',', $checkboxValues);

// Prepare the SQL statement to call the stored procedure
$stmt = $con->prepare("CALL InsertCheckboxData(?, @checkboxValueSum)");
$stmt->bind_param("s", $checkboxValuesString);

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
