

$(document).ready(function () {
    var storedFields = []; // Array to store the submitted data

    $('#rt_fields').submit(function (event) {
        event.preventDefault(); // Prevent default form submission

        // Create an object to hold the form data
        var formData = {
            customer: $('#customer').val(),
            // rt_address: $('#rt_address').val(),
            // rt_pocname: $('#rt_pocname').val(),
            // rt_pocnumber: $('#rt_pocnumber').val(),
            // rt_pocmail: $('#rt_pocmail').val()
        };
        // Send AJAX request
        $.ajax({
            url: 'rt_insert.php', // Replace with the URL of your server-side script
            type: 'POST',
            data: formData,
            success: function (response) {
                // Handle the response from the server
                console.log(response);

                // Store the form data in the array
                storedFields.push(formData);

                // Clear the form inputs
                $('#rt_fields')[0].reset();

                // Display the stored fields
                displayStoredFields();
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.log(error);
            }
        });
    });

    // Function to display the stored fields
    function displayStoredFields() {
        // Clear previous data
        $('#storedFields').empty();

        // Iterate through the stored data
        $.each(storedFields, function (index, data) {
            // Create a card element
            var card = $('<div>').addClass('card');

            // Add the form data to the card
            card.append('<p><strong>Customer:</strong> ' + data.customer + '</p>');
            // card.append('<p><strong>Address:</strong> ' + data.rt_address + '</p>');
            // card.append('<p><strong>Point of Contact Name:</strong> ' + data.rt_pocname + '</p>');
            // card.append('<p><strong>Point of Contact Number:</strong> ' + data.rt_pocnumber + '</p>');
            // card.append('<p><strong>Point of Contact Email:</strong> ' + data.rt_pocmail + '</p>');

            // Append the card to the container
            $('#storedFields').append(card);
        });
    }
});

