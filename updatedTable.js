
// Tooltips
$(function () {
    $(document).tooltip();
});

$(".calculation").tooltip({
    open: function (event, ui) { }
});
$(".calculation").on("tooltipopen", function (event, ui) { });
$(".calculation").position({

});

var position = $(".calculation").tooltip("option", "position");


$(".calculation").tooltip("option", "position", { my: "left bottom", at: "center top-13" });

// Tooltips end

$(document).ready(function () {
    // Initial table population and calculations
    populateTable();
    updateTableValues();
    bdaKits();


    // Event listener for table update button
    $("#updateTable").on("click", function () {
        populateTable();
        updateTableValues();
        bdaKits();
    });

    // Event listener for dropdown change
    $("#pMarkup").on("change", function () {
        updateTableValues();
    });

    $(document).on("ajaxComplete", function (event, request, settings) {
        $("#msg").empty("<li>Data Request Complete.</li><li>Values are based off an Internal Pricing Model.</li>");
        $("#msg").append("<li>Data Request Complete.</li><li>Values are based off an Internal Pricing Model.</li>");
    });
});


function populateTable() {
    $.ajax({
        url: 'updatedTableServer2.php',
        type: 'get',
        dataType: 'JSON',
        cache: true,
        success: function (response) {
            var len = response.length;
            $("#partsTable tbody").empty();
            for (var i = 0; i < len; i++) {
                var id = response[i].id;
                var quantity = response[i].quantity;
                var description = response[i].description;
                var part_description = response[i].part_description;
                var cost = parseFloat(response[i].cost, 2);
                var total_cost = parseFloat(response[i].total_cost, 2);
                var price = parseFloat(response[i].price, 2);
                var partNumber = response[i].partNumber;

                var tr_str =

                    "<tr>" +

                    "<td align='center'>" + id + "</td>" +
                    "<td align='center'>" + parseInt(quantity) + "</td>" +
                    "<td align='center'>" + description + "</td>" +
                    "<td align='center'>" + part_description + "</td>" +
                    "<td align='center'>" + parseFloat(cost, 2) + "</td>" +
                    "<td class='valueRow' align='center'>$" + parseFloat(total_cost, 2) + "</td>" +
                    "<td align='center'>$" + parseFloat(price, 2) + "</td>" +
                    "<td align='center'>" + partNumber + "</td>" +
                    "</tr>";
                $("#partsTable tbody").append(tr_str);
            }
        }
    });
}





function updateTableValues(resetDropdown) {

    var dropdownValue = $("#pMarkup").val();
    //Iterate through each row with .valueRow class 
    $(".valueRow").each(function () {
        var totalCost = $(this);

        var priceCell = totalCost.next();

        var cost = parseFloat(totalCost.prev().text());
        var quantity = parseFloat(totalCost.prev().prev().prev().prev().text());


        var dropdownValue = $("#pMarkup").val();
        var total_cost = parseFloat(cost) * parseInt(quantity);
        var price;
        console.log(customer);
        if (dropdownValue === "1") {
            // Calculation for dropdown value 1
            price = (parseFloat(total_cost, 2) * .05) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "2") {
            // Calculation for dropdown value 2
            price = (parseFloat(total_cost, 2) * .10) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "3") {
            // Calculation for dropdown value 3
            price = (parseFloat(total_cost, 2) * .15) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "4") {
            // Calculation for dropdown value 4
            price = (parseFloat(total_cost, 2) * .20) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "5") {
            // Calculation for dropdown value 5
            price = (parseFloat(total_cost, 2) * .25) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "6") {
            // Calculation for dropdown value 6
            price = (parseFloat(total_cost, 2) * .30) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "7") {
            // Calculation for dropdown value 7
            price = (parseFloat(total_cost, 2) * .35) + parseFloat(total_cost, 2);
        } else if (dropdownValue === "8") {
            // Calculation for dropdown value 8
            price = (parseFloat(total_cost, 2) * .40) + parseFloat(total_cost, 2);
        } else {
            // Calculation for dropdown value 0
            price = parseFloat(total_cost, 2) * 0.00;

        }
        // Check if total_cost is NaN
        if (Number.isNaN(total_cost)) {
            total_cost = 0.00

        }

        totalCost.text(total_cost.toFixed(2)); // Update total_cost cell with calculated value
        priceCell.text(price.toFixed(2)); // Update price cell with calculated value
    });
}

function resetDropdown(dropdownValue) {
    if (dropdownValue.value !== "") {
        dropdownValue.value = "";
    }
}


//Bda Kits
function bdaKits() {
    $(document).ready(function () {
        $('#bda_kit-select').change(function () {
            var selectedOption = $(this).val();
            $.ajax({
                type: 'POST',
                dataType: 'JSON',
                url: 'bdaKitTableServer.php',
                data: { option: selectedOption },
                success: function (response) {
                    var len = response.length;
                    for (var i = 0; i < len; i++) {
                        var id = response[i].id;
                        var part_description = response[i].part_description;
                        var part_number = response[i].part_number;
                        var cost = parseFloat(response[i].cost, 1);
                        // var element_type = response[i].element_type;
                        // var rom_bda_number = response[i].rom_bda_number;

                        // Update table cells based on IDs or classes
                        $('#id_' + id).text(id);
                        $('#part_description_' + id).text(part_description);
                        $('#part_number_' + id).text(part_number);
                        $('#cost_' + id).text(parseFloat(cost, 1));
                        // $('.element_type_' + id).text(element_type);
                        // $('.rom_bda_number_' + id).text(rom_bda_number);
                    }
                }
            });
        });
    });
}


