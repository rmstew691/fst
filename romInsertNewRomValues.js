

$(document).ready(function () {
    var storedFields = {}; // Object to store the submitted data
    var checkedValues = {}; // Object to store the checkbox values
    var checkedCount = 0; // Counter for the number of checked checkboxes

    // Submit form event
    $('#rt_fields').submit(function (event) {
        event.preventDefault(); // Prevent default form submission

        // Create an object to hold the form data
        var formData = {
            customer: $('#customer').val(),
            address: $('#address').val(),
            rt_address: $('#rt_address').val(),
            rt_pocname: $('#rt_pocname').val(),
            rt_pocnumber: $('#rt_pocnumber').val(),
            rt_pocmail: $('#rt_pocmail').val(),
        };

        // Send AJAX request
        $.ajax({
            url: 'rt_insert2.php',
            type: 'POST',
            data: formData,
            success: function (response) {
                // Handle the response from the server
                console.log(response);

                // Store the form data in the object
                storedFields = formData;

                // Clear the form inputs
                $('#rt_fields')[0].reset();

                // Display the stored fields
                displayStoredFields();
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.log(error);
            },
        });
    });

    // Save and Continue button click event
    $('#saveButton').click(function () {
        var checkboxes = $('input[type="checkbox"]');

        checkboxes.each(function () {
            checkedValues[$(this).val()] = this.checked ? 1 : 0;
        });

        console.log(checkedValues);

        // Calculate the checked count
        checkedCount = Object.values(checkedValues).reduce((sum, value) => sum + value, 0);
        if (checkedCount === null || checkedCount === undefined) {
            checkedCount = 0;
        }
        console.log(checkedCount);

        // Send the values to the server-side script
        $.ajax({
            url: 'rt_checkboxes.php',
            type: 'POST',
            data: {
                values: checkedValues,
                checkedCount: checkedCount,
            },
            success: function (response) {
                if (response === null) {
                    // Handle null response
                    console.log('Null response received.');
                } else {
                    // Handle the response from the server
                    console.log('Data saved!');
                    console.log(response);

                    // Clear the checkboxes
                    checkboxes.prop('checked', false);
                }
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.log('Error:', error);
            },
        });
    });

    // Function to display the stored fields
    function displayStoredFields() {
        // Clear previous data
        $('#storedFields').empty();

        // Create a card element
        var card = $('<div>').addClass('card');

        // Add the form data to the card
        card.append('<p><strong>Customer:</strong> ' + storedFields.customer + '</p>');
        card.append('<p><strong>Address:</strong> ' + storedFields.address + '</p>');
        card.append('<p><strong>RT Address:</strong> ' + storedFields.rt_address + '</p>');
        card.append('<p><strong>RT Point of Contact Name:</strong> ' + storedFields.rt_pocname + '</p>');
        card.append('<p><strong>RT Point of Contact Number:</strong> ' + storedFields.rt_pocnumber + '</p>');
        card.append('<p><strong>RT Point of Contact Email:</strong> ' + storedFields.rt_pocmail + '</p>');

        // Append the card to the container
        $('#storedFields').append(card);
    }
});
