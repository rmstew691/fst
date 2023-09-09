<?php
// Establish a connection to your MySQL database
include "rt_config.php";

// Get the checkbox values, checked count, and checkbox value sum from the AJAX request
$checkboxValues = $_POST['values'];
$checkedCount = $_POST['checkedCount'];
$checkboxValueSum = isset($_POST['checkboxValueSum']) ? $_POST['checkboxValueSum'] : 0;

$checkboxValueSum = array_sum($checkboxValues);

// Check if the checkbox values are set and the checked count is greater than 0
if (isset($checkboxValues) && isset($checkedCount) && $checkedCount > 0) {
    // Prepare the SQL statement to insert the data into the table
    $stmt = $con->prepare("INSERT INTO rom_tool_checkbox_values (checkbox_value, checked_count_values, checkbox_value_sum) VALUES (?, ?, ?)");

    // Insert the checked checkbox values, their count, and checkbox value sum into the table
    foreach ($checkboxValues as $checkboxValue => $checked) {
        $checked = intval($checked); // Convert checked value to integer
        if ($checked === 1) {
            $stmt->bind_param("sii", $checkboxValue, $checkedCount, $checkboxValueSum);
            $stmt->execute();
        }
    }

    // Close the prepared statement
    $stmt->close();

    // Print a response message
    echo 'Checkbox values inserted successfully!';
} else {
    echo 'No checkbox values, checked count greater than 0, or checkbox value sum received.';
}
// Close the database connection
$con->close();
