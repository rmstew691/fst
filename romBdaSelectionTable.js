
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
        url: 'romServer.php',
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

                var formattedPrice = price.toLocaleString('en-US', { style: 'currency', currency: 'USD' });

                var tr_str =
                    "<tr>" +
                    "<td align='center'><input type='text' value='" + id + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + parseInt(quantity) + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + description + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + part_description + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + parseFloat(cost, 2) + "' readonly></td>" +
                    "<td class='valueRow' align='center'><input type='text' value='$" + parseFloat(total_cost, 2) + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + formattedPrice + "' readonly></td>" +
                    "<td align='center'><input type='text' value='" + partNumber + "' readonly></td>" +
                    "</tr>";

                $("#partsTable tbody").append(tr_str);
            }
        }
    });
}





function resetDropdown(dropdownValue) {
    if (dropdownValue.value !== "") {
        dropdownValue.value = "";
    }
}




function updateTableValues() {
    var dropdownValue = $("#pMarkup").val();

    // Iterate through each row with .valueRow class
    $(".valueRow").each(function () {
        var totalCostCell = $(this);
        var priceCell = totalCostCell.next();

        var cost = parseFloat(totalCostCell.prev().find("input").val());
        var quantity = parseFloat(totalCostCell.prev().prev().prev().prev().find("input").val());

        var total_cost = cost * quantity;
        var price;

        switch (dropdownValue) {
            case "1":
                // Calculation for dropdown value 1
                price = (total_cost * 0.05) + total_cost;
                break;
            case "2":
                // Calculation for dropdown value 2
                price = (total_cost * 0.10) + total_cost;
                break;
            case "3":
                // Calculation for dropdown value 3
                price = (total_cost * 0.15) + total_cost;
                break;
            case "4":
                // Calculation for dropdown value 4
                price = (total_cost * 0.20) + total_cost;
                break;
            case "5":
                // Calculation for dropdown value 5
                price = (total_cost * 0.25) + total_cost;
                break;
            case "6":
                // Calculation for dropdown value 6
                price = (total_cost * 0.30) + total_cost;
                break;
            case "7":
                // Calculation for dropdown value 7
                price = (total_cost * 0.35) + total_cost;
                break;
            case "8":
                // Calculation for dropdown value 8
                price = (total_cost * 0.40) + total_cost;
                break;
            default:
                // Calculation for dropdown value 0
                price = total_cost * 0.00;
                break;
        }

        // Check if total_cost is NaN
        if (isNaN(total_cost)) {
            total_cost = 0.00;
        }

        totalCostCell.find("input").val(total_cost.toFixed(2).toLocaleString('en-US', { style: 'currency', currency: 'USD' })); // Update total_cost input with formatted currency value
        priceCell.find("input").val(price.toFixed(2).toLocaleString('en-US', { style: 'currency', currency: 'USD' })); // Update price input with formatted currency value
    });
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


