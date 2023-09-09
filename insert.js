
$('#submitButtonA').on('click', function () {

    // Assuming you have the input field values stored in variables
    var customerValue = $('#customer').val();
    var addressValue = $('#address').val();
    var cityValue = $('#city').val();
    var stateValue = $('#state').val();
    var zipValue = $('#zip').val();
    // var pocnameValue = $('#rt_pocname').val();
    // var pocnumberValue = $('#rt_pocnumber').val();
    // var sqftValue = $('#rt_sqft').val();
    // var floorNumValue = $('#rt_floorNum').val();
    // var facilityTypeValue = $('#facility_type').val();
    // var buildingDensityValue = $('#building_density').val();
    // Create an object with the input field values
    var formData = {
        customer: customerValue,
        address: addressValue,
        city: cityValue,
        state: stateValue,
        zip: zipValue,
        // pocname: pocnameValue,
        // pocnumber: pocnumberValue,
        // sqft: sqftValue,
        // floorNum: floorNumValue,
        // facility_type: facilityTypeValue,
        // building_density: buildingDensityValue
    };

    // Make the AJAX request
    $.ajax({
        url: "insert.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function (response) {
            // Handle the response from the server
            console.log(response); // Assuming the response is a JSON object

            // Store the response data in an array for calculations and other uses
            var dataArray = [];
            dataArray.push(response.customer);
            dataArray.push(response.address);
            dataArray.push(response.city);
            dataArray.push(response.state);
            dataArray.push(response.zip);
            // Add other fields as needed
            console.log(dataArray);
            // Perform calculations or use the data in the array as required
            // Example: Accessing the customer name from the array
            var customerName = dataArray[0];
            // console.log("Customer Name: " + customerName);
        },
        error: function (xhr, status, error) {
            // Handle any errors that occur during the AJAX request
            // console.log(error);
        }
    });

});
