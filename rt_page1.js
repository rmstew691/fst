//Loads previous data from the database and populates the input fields


window.addEventListener('DOMContentLoaded', function () {
    checkForPreviousData();
});

function checkForPreviousData() {
    // Make an AJAX request to the server
    fetch('rt_page1_server.php')
        .then(response => response.json()) // Parse the response as JSON
        .then(data => {
            if (data !== null && Array.isArray(data) && data.length > 0) {
                // Get the latest entry
                const latestEntry = data.reduce((a, b) => a.id > b.id ? a : b);
                console.log(latestEntry);

                //map the properties to the input field IDs
                console.log(latestEntry.rt_customer);
                const propertyToFieldMap = {
                    'rt_customer': 'rt_customer',
                    'rt_address': 'rt_address',
                    'rt_pocname': 'rt_pocname',
                    'rt_pocnumber': 'rt_pocnumber',
                    'street': 'street'
                };
                for (const [property, value] of Object.entries(latestEntry)) {
                    if (propertyToFieldMap[property]) {
                        const fieldId = propertyToFieldMap[property];
                        const inputField = document.getElementById(fieldId);
                        if (inputField) {
                            inputField.value = value;
                        }
                    }
                }
            } else {
                console.log('No data found');
            }
        })
        .catch(error => {
            console.log('An error occurred:', error);
        });
}

function searchCustomer() {
    const searchValue = $("#searchInput").val();

    // Check if the search value has at least three characters
    if (searchValue.length >= 3) {
        // Make an AJAX request to the server
        $.ajax({
            url: "rt_page1_server.php",
            method: "GET",
            data: {
                customer: searchValue
            },
            dataType: "json",
            success: function (data) {
                if (data !== null && data.customer) {
                    $("#rt_customer").val(data.customer);
                } else {
                    console.log('No data found');
                }
            },
            error: function (error) {
                console.log('An error occurred:', error);
            }
        });
    } else {
        // Clear the customer input field and show the hint
        $("#rt_customer").val("");
        $("#rt_customer").attr("placeholder", "Enter at least 3 characters");
    }
}


