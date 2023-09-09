$(document).ready(function () {
    // Function to populate the table based on the dropdown selection
    function populateTable(selectionValue) {
        $.ajax({
            url: 'romServer.php',
            type: 'GET',
            data: { selection: selectionValue },
            dataType: 'JSON',
            success: function (response) {
                // Clear the table content
                $('#romBdaSelectionTable').empty();

                // Append the table header
                var tableHeader = '<thead><tr><th>Column 1</th><th>Column 2</th></tr></thead>';
                $('#romBdaSelectionTable').append(tableHeader);

                // Append the table body content
                var tableBody = '<tbody>';
                for (var i = 0; i < response.length; i++) {
                    var rowData = '<tr>';
                    rowData += '<td>' + response[i].column1 + '</td>';
                    rowData += '<td>' + response[i].column2 + '</td>';
                    rowData += '</tr>';
                    tableBody += rowData;
                }
                tableBody += '</tbody>';
                $('#romBdaSelectionTable').append(tableBody);
            }
        });
    }

    // Event handler for dropdown selection change
    $('#dropdownSelection').change(function () {
        var selectedValue = $(this).val();
        populateTable(selectedValue);
    });

    // Initial population of the table
    var initialSelectedValue = $('#dropdownSelection').val();
    populateTable(initialSelectedValue);
});
