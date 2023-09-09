$(document).ready(function () {
    var checkedValues = {};
    var checkedCount = 0;

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
            url: 'rt_insertChecksStoredProc.php',
            type: 'POST',
            data: {
                values: checkedValues,
                checkedCount: checkedCount
            },
            success: function (response) {
                if (response === null) {
                    // Handle null response
                    console.log('Null response received.');
                } else {
                    // Handle the response from the server
                    console.log('Data saved!');
                    console.log(response);
                }
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.log('Error:', error);
            }
        });
    });
});



